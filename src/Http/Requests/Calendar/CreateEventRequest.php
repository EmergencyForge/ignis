<?php

declare(strict_types=1);

namespace App\Http\Requests\Calendar;

use App\Http\Requests\FormRequest;
use App\Models\CalendarEvent;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validation fuer POST /kalender/create.
 *
 * starts_at / ends_at akzeptieren ISO-Datetime (YYYY-MM-DDTHH:MM[:SS]) und
 * deutsches Format (DD.MM.YYYY HH:MM); cast() normalisiert beide auf
 * MySQL-DATETIME.
 *
 * recurrence_rule ist optional; wenn gesetzt, muss recurrence_until ebenfalls
 * gesetzt sein. Erlaubtes Subset: FREQ + INTERVAL + BYDAY + COUNT|UNTIL.
 */
class CreateEventRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        $datetimeRegex = '/^(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?|\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}(:\d{2})?)$/';
        $rruleRegex    = '/^(FREQ=(DAILY|WEEKLY|MONTHLY))(;INTERVAL=\d+)?(;BYDAY=(MO|TU|WE|TH|FR|SA|SU)(,(MO|TU|WE|TH|FR|SA|SU))*)?(;COUNT=\d+|;UNTIL=\d{4}-\d{2}-\d{2})?$/';

        $allowedColors      = CalendarEvent::COLORS;
        $allowedCategories  = array_keys(CalendarEvent::CATEGORIES);
        $allowedVisibility  = [
            CalendarEvent::VISIBILITY_PRIVATE,
            CalendarEvent::VISIBILITY_ATTENDEES,
            CalendarEvent::VISIBILITY_ROLE,
            CalendarEvent::VISIBILITY_ALL,
        ];

        return v::keySet(
            v::key('title',              v::stringType()->notBlank()->length(1, 160)),
            v::key('description',        v::optional(v::stringType()->length(0, 2000)), false),
            v::key('location',           v::optional(v::stringType()->length(0, 255)), false),
            v::key('starts_at',          v::stringType()->regex($datetimeRegex)),
            v::key('ends_at',            v::stringType()->regex($datetimeRegex)),
            v::key('all_day',            v::optional(v::in(['0', '1', 0, 1, true, false], true)), false),
            v::key('color',              v::optional(v::in($allowedColors, true)), false),
            v::key('category',           v::optional(v::in($allowedCategories, true)), false),
            v::key('visibility',         v::in($allowedVisibility, true)),
            v::key('visibility_role_id', v::optional(v::stringVal()->intVal()), false),
            v::key('attendees',          v::optional(v::arrayType()), false),
            v::key('recurrence_rule',    v::optional(v::stringType()->regex($rruleRegex)), false),
            v::key('recurrence_until',   v::optional(v::stringType()->regex('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/')), false),
            // Routing-Felder, nicht validierte Daten
            v::key('action',             v::optional(v::stringType()), false),
            v::key('return_to',          v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Pflichtfeld darf nicht leer sein.',
            'regex'    => 'Ungültiges Format.',
            'in'       => 'Ungültiger Wert.',
            'length'   => 'Maximal {{maxValue}} Zeichen.',
            'intVal'   => 'Muss eine Zahl sein.',
        ];
    }

    protected static function cast(array $input): array
    {
        $startsAt = self::normalizeDateTime((string) $input['starts_at']);
        $endsAt   = self::normalizeDateTime((string) $input['ends_at']);

        // Cross-field-Check: ends_at >= starts_at. Wir loesen das nicht via
        // Respect/Validation (kein eingebauter "field-comparison"-Validator),
        // sondern werfen via Exception aus dem cast().
        if (strtotime($endsAt) < strtotime($startsAt)) {
            throw new \App\Exceptions\ValidationException(
                ['ends_at' => 'Ende muss nach dem Start liegen.'],
                'Ungültiger Zeitraum.'
            );
        }

        $allDay     = !empty($input['all_day']) && $input['all_day'] !== '0';
        $visibility = (string) $input['visibility'];
        $roleId     = ($visibility === CalendarEvent::VISIBILITY_ROLE && !empty($input['visibility_role_id']))
            ? (int) $input['visibility_role_id']
            : null;

        // Wenn Recurrence-Rule gesetzt, muss UNTIL/COUNT entweder im Pattern
        // selbst oder als separates Feld stehen. Wir akzeptieren beide.
        $rrule = !empty($input['recurrence_rule']) ? trim((string) $input['recurrence_rule']) : null;
        $until = !empty($input['recurrence_until']) ? self::normalizeDateTime((string) $input['recurrence_until']) : null;

        // Attendees: nur fuer visibility=attendees (sonst ignorieren).
        $attendees = [];
        if ($visibility === CalendarEvent::VISIBILITY_ATTENDEES && !empty($input['attendees']) && is_array($input['attendees'])) {
            foreach ($input['attendees'] as $aid) {
                $aid = (int) $aid;
                if ($aid > 0) {
                    $attendees[] = $aid;
                }
            }
            $attendees = array_values(array_unique($attendees));
        }

        return [
            'title'              => trim((string) $input['title']),
            'description'        => !empty($input['description']) ? trim((string) $input['description']) : null,
            'location'           => !empty($input['location']) ? trim((string) $input['location']) : null,
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
            'all_day'            => $allDay,
            'color'              => !empty($input['color']) && in_array($input['color'], CalendarEvent::COLORS, true)
                ? (string) $input['color'] : 'orange',
            'category'           => !empty($input['category']) && array_key_exists($input['category'], CalendarEvent::CATEGORIES)
                ? (string) $input['category'] : 'general',
            'visibility'         => $visibility,
            'visibility_role_id' => $roleId,
            'attendees'          => $attendees,
            'recurrence_rule'    => $rrule,
            'recurrence_until'   => $until,
        ];
    }

    /**
     * Normalisiert deutsches und ISO-Datetime auf MySQL-DATETIME (YYYY-MM-DD HH:MM:SS).
     */
    private static function normalizeDateTime(string $value): string
    {
        $value = trim($value);

        // ISO mit T-Trennung → Leerzeichen
        $value = str_replace('T', ' ', $value);

        // Deutsches Format: DD.MM.YYYY HH:MM[:SS]
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}:\d{2}(:\d{2})?)$/', $value, $m)) {
            $time = $m[4];
            if (strlen($time) === 5) {
                $time .= ':00';
            }
            return "$m[3]-$m[2]-$m[1] $time";
        }

        // Reines Datum (von recurrence_until) → 00:00:00 anhaengen
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ' 00:00:00';
        }

        // ISO ohne Sekunden ergaenzen
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }

        return $value;
    }
}
