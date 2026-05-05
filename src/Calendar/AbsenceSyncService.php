<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Models\Antrag;
use App\Models\CalendarAttendee;
use App\Models\CalendarEvent;
use App\Models\Mitarbeiter;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Bridge zwischen Antragssystem und Kalender. Bei Genehmigung eines
 * Urlaubsantrags wird ein CalendarEvent angelegt (oder geupdated), das per
 * source='antrag' + source_ref_id auf den Antrag zeigt — keine Doppel-
 * Wahrheit, nur eine Bridge-Row, die der Cascade-Logik (DELETE/UPDATE)
 * folgt.
 *
 * Pattern: aufrufen aus FormsController::decide() nach $antrag->save():
 *
 *   if ($isUrlaub && $newStatus === Antrag::STATUS_ACCEPTED) {
 *       AbsenceSyncService::syncFromAntrag($antrag, $vonDatum, $bisDatum);
 *   } elseif ($isUrlaub) {
 *       AbsenceSyncService::removeForAntrag($antrag->id);
 *   }
 */
final class AbsenceSyncService
{
    /**
     * Antragstyp-Namen, die als "Urlaub/Abwesenheit" erkannt werden sollen.
     * Match ist case-insensitive. Bei Bedarf erweitern oder durch eine
     * DB-Spalte auf intra_antrag_typen ersetzen.
     */
    private const URLAUB_TYP_NAMES = [
        'urlaubsantrag',
        'urlaub',
        'freistellungsantrag',
        'freistellung',
    ];

    /**
     * Prueft, ob der gegebene Antrag ein Urlaubs-/Abwesenheits-Antrag ist
     * (anhand des Typ-Namens). Zentrale Stelle, damit FormsController
     * und Console-Backfill dieselbe Regel benutzen.
     */
    public static function isAbsenceAntrag(\App\Models\Antrag $antrag): bool
    {
        $name = strtolower(trim((string) ($antrag->typ?->name ?? '')));
        return in_array($name, self::URLAUB_TYP_NAMES, true);
    }

    /**
     * Legt ein Absence-Event aus einem genehmigten Urlaubsantrag an oder
     * aktualisiert ein bestehendes (Bridge ueber source + source_ref_id).
     *
     * @param string $vonDatum  ISO YYYY-MM-DD oder leerer String
     * @param string $bisDatum  ISO YYYY-MM-DD oder leerer String
     */
    public static function syncFromAntrag(Antrag $antrag, string $vonDatum, string $bisDatum): ?CalendarEvent
    {
        if ($vonDatum === '' || $bisDatum === '') {
            return null;
        }

        // Validate ISO-Format (defensive: legacy-Daten koennten DD.MM.YYYY sein)
        $vonDatum = self::normalizeDate($vonDatum);
        $bisDatum = self::normalizeDate($bisDatum);
        if ($vonDatum === null || $bisDatum === null) {
            return null;
        }

        /** @var CalendarEvent $event */
        $event = CalendarEvent::firstOrNew([
            'source'        => CalendarEvent::SOURCE_ANTRAG,
            'source_ref_id' => $antrag->id,
        ]);

        $event->title       = 'Urlaub: ' . self::displayName($antrag);
        $event->description = self::buildDescription($antrag);
        $event->starts_at   = $vonDatum . ' 00:00:00';
        $event->ends_at     = $bisDatum . ' 23:59:59';
        $event->all_day     = true;
        $event->category    = 'absence';
        $event->color       = 'gray';
        $event->visibility  = CalendarEvent::VISIBILITY_ALL;

        // created_by: bevorzugt der Bearbeiter, sonst der Antragsteller, sonst
        // ein Fallback-User. Wir wollen NIE NULL (FK-Constraint).
        $event->created_by = self::resolveCreatorUserId($antrag);
        $event->source     = CalendarEvent::SOURCE_ANTRAG;
        $event->source_ref_id = $antrag->id;
        $event->save();

        // Mitarbeiter (Antragsteller) als Attendee+Organizer setzen — fuers
        // "Wer ist heute da"-Widget zaehlt das. Bei multi-day-Updates wird
        // firstOrCreate verwendet, sodass keine Duplikate entstehen.
        if (!empty($antrag->discordid)) {
            $mitarbeiter = Mitarbeiter::query()
                ->where('discordtag', $antrag->discordid)
                ->first(['id']);
            if ($mitarbeiter !== null) {
                CalendarAttendee::firstOrCreate(
                    [
                        'event_id'       => $event->id,
                        'mitarbeiter_id' => $mitarbeiter->id,
                    ],
                    [
                        'response'     => CalendarAttendee::RESPONSE_ACCEPTED,
                        'is_organizer' => true,
                    ]
                );
            }
        }

        return $event;
    }

    /**
     * Entfernt Absence-Event(s) eines Antrags (z.B. wenn Status zurueckgesetzt
     * oder abgelehnt wurde). Cascade-DELETE auf intra_calendar_attendees
     * laeuft per FK-Constraint.
     */
    public static function removeForAntrag(int $antragId): int
    {
        return CalendarEvent::query()
            ->where('source', CalendarEvent::SOURCE_ANTRAG)
            ->where('source_ref_id', $antragId)
            ->delete();
    }

    /**
     * Normalisiert ISO-, DD.MM.YYYY- oder DateTime-Eingaben auf ISO YYYY-MM-DD.
     * Liefert null bei nicht-parsebarem Input.
     */
    private static function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;

        // ISO mit optionaler Uhrzeit
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        // Deutsches Format DD.MM.YYYY
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }

    /**
     * Anzeigename des Antragstellers — name_dn aus dem Antrag oder Fallback
     * auf den Discord-Tag.
     */
    private static function displayName(Antrag $antrag): string
    {
        $name = trim((string) ($antrag->name_dn ?? ''));
        return $name !== '' ? $name : (string) $antrag->discordid;
    }

    /**
     * Beschreibung mit Antragsnummer + Begruendung (sofern angegeben).
     */
    private static function buildDescription(Antrag $antrag): ?string
    {
        $parts = ['Antrag #' . $antrag->uniqueid];
        $grund = $antrag->getFieldValue('grund');
        if ($grund !== null && trim($grund) !== '') {
            $parts[] = trim($grund);
        }
        return implode("\n\n", $parts);
    }

    /**
     * Liefert eine User-ID, die als created_by gesetzt werden kann.
     * Vorrang: Bearbeiter (cirs_manager_id, falls vorhanden) → Antragsteller
     * (via discordid → intra_users.id) → erster Admin-User.
     */
    private static function resolveCreatorUserId(Antrag $antrag): int
    {
        // 1) Bearbeiter, falls die Spalte existiert
        if (isset($antrag->cirs_manager_userid) && (int) $antrag->cirs_manager_userid > 0) {
            return (int) $antrag->cirs_manager_userid;
        }

        // 2) Antragsteller via Discord-ID
        if (!empty($antrag->discordid)) {
            $uid = Capsule::table('intra_users')
                ->where('discord_id', $antrag->discordid)
                ->value('id');
            if ($uid !== null) {
                return (int) $uid;
            }
        }

        // 3) Fallback: erster User mit full_admin (oder erster ueberhaupt)
        $admin = Capsule::table('intra_users')
            ->where('full_admin', 1)
            ->orderBy('id')
            ->value('id');
        if ($admin !== null) {
            return (int) $admin;
        }

        $any = Capsule::table('intra_users')->orderBy('id')->value('id');
        return (int) ($any ?? 1);
    }
}
