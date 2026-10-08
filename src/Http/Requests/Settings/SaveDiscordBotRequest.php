<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /settings/system/discord.
 *
 * Felder:
 *   - action   (optional): `disconnect` trennt den Bot, sonst wird gespeichert
 *   - token    (optional): leer heißt, das gespeicherte bleibt
 *   - name     (optional): fehlt, solange kein Bot verbunden ist
 *   - enabled  (optional, Checkbox)
 *   - dm_types (optional, Liste): unbekannte Typen sortiert der Controller
 *              aus, weil nur er die Registry kennt
 *
 * Das Profilbild kommt über `$_FILES` und gehört nicht hierher.
 */
class SaveDiscordBotRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('action',   v::stringType(), false),
            v::key('token',    v::stringType(), false),
            v::key('name',     v::stringType(), false),
            v::key('enabled',  v::stringType(), false),
            v::key('dm_types', v::arrayType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'action'   => 'Unbekannte Aktion.',
            'token'    => 'Das Token muss Text sein.',
            'name'     => 'Der Name muss Text sein.',
            'enabled'  => 'Ungültiger Wert für Einschalten.',
            'dm_types' => 'Die Direktnachrichten müssen als Liste kommen.',
            'keySet'   => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'action'   => (string) ($input['action'] ?? ''),
            'token'    => trim((string) ($input['token'] ?? '')),
            'name'     => trim((string) ($input['name'] ?? '')),
            'enabled'  => isset($input['enabled']),
            'dm_types' => array_map('strval', array_filter((array) ($input['dm_types'] ?? []), 'is_scalar')),
        ];
    }
}
