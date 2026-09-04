<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Aktivität eines Datensatzes für die Seitenspalte einer Detailseite
 * (templates/partials/activity.php), nach dem Vorbild von Lex: die letzten
 * Einträge aus `intra_audit_log`, die den Datensatz nennen, mit einem
 * lesbaren Satz je Aktion.
 *
 * ignis schreibt keinen JSON-Kontext ins Audit-Log; die Kennung steht als
 * Text in der Aktion (`Fahrzeug aktualisiert [ID: 12]`) oder in den Details
 * (`Fahrzeug-ID: 12 | Titel`, `Name: … | ID: 12` beim EMD-Import). Gesucht
 * wird deshalb im Modul nach `ID: 12`, das nicht von einer weiteren Ziffer
 * gefolgt wird, damit 12 nicht auch 123 trifft. Das reicht für eine
 * Seitenspalte; wer Audit-Auswertungen braucht, nimmt das Audit-Log unter
 * Benutzer › Audit-Log.
 */
final class Activity
{
    /**
     * Sätze je Aktion (Anfang der Aktion ohne die Kennung). Ein Eintrag ohne
     * passenden Satz zeigt die Aktion selbst, ohne `[ID: …]`.
     */
    private const LABELS = [
        'Fahrzeug erstellt'                        => 'Fahrzeug angelegt',
        'Fahrzeug aktualisiert'                    => 'Stammdaten geändert',
        'Fahrzeug gelöscht'                        => 'Fahrzeug gelöscht',
        'Defekt gemeldet'                          => 'Mangel gemeldet: {title}',
        'Fahrzeug per EMD-Import erstellt'         => 'Per EMD-Import angelegt',
        'Fahrzeug per EMD-Import überschrieben'    => 'Per EMD-Import überschrieben',
        'Fahrzeug per EMD-Import zusammengeführt'  => 'Per EMD-Import zusammengeführt',
    ];

    /**
     * Die letzten Einträge zu einem Fahrzeug, neueste zuerst.
     *
     * @return list<array{label:string, actor:?string, at:string}>
     */
    public static function vehicle(int $vehicleId, int $limit = 8): array
    {
        return self::about('Fahrzeuge', $vehicleId, $limit);
    }

    /**
     * @return list<array{label:string, actor:?string, at:string}>
     */
    public static function about(string $module, int $id, int $limit): array
    {
        $needle  = 'ID: ' . $id;
        $pattern = '~(?<![0-9])' . preg_quote($needle, '~') . '(?![0-9])~';

        try {
            // LIKE grenzt grob ein (trifft auch 12 in 123), das Muster
            // entscheidet danach in PHP; deshalb etwas mehr Zeilen holen.
            $rows = Capsule::table('intra_audit_log as a')
                ->leftJoin('intra_users as u', 'a.user', '=', 'u.id')
                ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
                ->where('a.module', $module)
                ->where(static function ($q) use ($needle): void {
                    $like = '%' . ignis_like_prefix($needle) . '%';
                    $q->where('a.action', 'LIKE', $like)->orWhere('a.details', 'LIKE', $like);
                })
                ->orderByDesc('a.timestamp')
                ->orderByDesc('a.id')
                ->limit($limit * 4)
                ->get(['a.action', 'a.details', 'a.timestamp', Capsule::connection()->raw('COALESCE(m.fullname, u.username) AS actor')]);
        } catch (PDOException) {
            return [];
        }

        $entries = [];
        foreach ($rows as $row) {
            $action  = (string) $row->action;
            $details = $row->details === null ? null : (string) $row->details;
            if (preg_match($pattern, $action . ' ' . ($details ?? '')) !== 1) {
                continue;
            }
            $entries[] = [
                'label' => self::label($action, $details),
                'actor' => $row->actor === null || $row->actor === '' ? null : (string) $row->actor,
                'at'    => (string) $row->timestamp,
            ];
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /** Lesbarer Satz zu Aktion und Details eines Eintrags. */
    public static function label(string $action, ?string $details): string
    {
        $base = trim((string) preg_replace('~\s*\[ID: \d+\]\s*~', ' ', $action));

        // Sammelaktion der Fahrzeugliste: „Status: aktiv (Sammelaktion)".
        if ($base === 'Fahrzeug aktualisiert' && $details !== null && preg_match('~^Status: (\S+)~', $details, $m) === 1) {
            return 'Status auf ' . $m[1] . ' gesetzt';
        }

        $template = self::LABELS[$base] ?? $base;

        return (string) preg_replace_callback('~\{([a-z_]+)\}~', static function (array $m) use ($details): string {
            return match ($m[1]) {
                'title'  => self::afterPipe($details),
                default  => '—',
            };
        }, $template);
    }

    /** „Fahrzeug-ID: 12 | Bremsen quietschen" → „Bremsen quietschen". */
    private static function afterPipe(?string $details): string
    {
        if ($details === null || !str_contains($details, '|')) {
            return '—';
        }
        $title = trim(substr($details, strpos($details, '|') + 1));

        return $title !== '' ? $title : '—';
    }
}
