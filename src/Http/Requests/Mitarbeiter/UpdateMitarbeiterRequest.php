<?php

declare(strict_types=1);

namespace App\Http\Requests\Mitarbeiter;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /mitarbeiter/profile.php?new=1 — Legacy Update-Form.
 *
 * Wird in der aktuellen UI gar nicht mehr direkt aufgerufen (Inline-Edit
 * läuft über api/personnel/update-profile.php), aber das Endpoint existiert
 * weiterhin als Fallback. Wir validieren die Felder defensive.
 *
 * Optionale Felder werden zu null/'' gemappt, damit der Caller einheitlich
 * arbeiten kann.
 */
class UpdateMitarbeiterRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        $dienstnrPattern = '/^(?=.*[0-9])[A-Za-z0-9\-]+$/';
        $dateRegex       = '/^\d{4}-\d{2}-\d{2}$/';

        return v::keySet(
            v::key('id',          v::stringVal()->intVal()->positive()),
            v::key('fullname',    v::stringType()->notBlank()->length(1, 255)),
            v::key('gebdatum',    v::stringType()->regex($dateRegex)),
            v::key('dienstgrad',  v::stringVal()->intVal()->positive()),
            v::key('discordtag',  v::optional(v::stringType()->regex('/^[0-9]{17,20}$/')), false),
            v::key('telefonnr',   v::optional(v::stringType()->length(0, 50)), false),
            v::key('dienstnr',    v::stringType()->notBlank()->regex($dienstnrPattern)),
            v::key('qualird',     v::stringVal()->intVal()),
            v::key('qualifw2',    v::stringVal()->intVal()),
            v::key('geschlecht',  v::stringVal()->intVal()->in(['0', '1', '2'])),
            v::key('zusatzqual',  v::optional(v::stringType()->length(0, 255)), false),
            v::key('pfp',         v::optional(v::stringType()->length(0, 500)), false),
            v::key('charakterid', v::optional(v::stringType()), false),
            v::key('new',         v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Pflichtfeld darf nicht leer sein.',
            'regex'    => 'Ungültiges Format.',
            'intVal'   => 'Muss eine Zahl sein.',
            'positive' => 'Muss positiv sein.',
            'in'       => 'Ungültiger Wert.',
            'length'   => 'Maximal {{maxValue}} Zeichen.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'          => (int) $input['id'],
            'fullname'    => trim((string) $input['fullname']),
            'gebdatum'    => (string) $input['gebdatum'],
            'dienstgrad'  => (int) $input['dienstgrad'],
            'discordtag'  => isset($input['discordtag']) ? trim((string) $input['discordtag']) : '',
            'telefonnr'   => isset($input['telefonnr']) ? trim((string) $input['telefonnr']) : '',
            'dienstnr'    => trim((string) $input['dienstnr']),
            'qualird'     => (int) $input['qualird'],
            'qualifw2'    => (int) $input['qualifw2'],
            'geschlecht'  => (int) $input['geschlecht'],
            'zusatzqual'  => isset($input['zusatzqual']) ? trim((string) $input['zusatzqual']) : '',
            'pfp'         => isset($input['pfp']) ? trim((string) $input['pfp']) : '',
            'charakterid' => isset($input['charakterid']) ? trim((string) $input['charakterid']) : '',
        ];
    }
}
