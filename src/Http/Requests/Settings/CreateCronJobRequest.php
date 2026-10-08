<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /settings/system/cron/create.
 *
 * Hier stehen nur Form und Standardwerte der Felder. Pflichtfelder,
 * Handler-Typ, Cron-Ausdruck und JSON prüft der Controller, weil jede
 * dieser Prüfungen ihren eigenen Hinweis in der Meldungstabelle hat.
 *
 * Felder:
 *   - identifier, name, handler, schedule
 *   - description (optional)
 *   - handler_type (fehlt er, gilt `webhook`)
 *   - config (optional, JSON)
 */
class CreateCronJobRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('identifier',   v::stringType(), false),
            v::key('name',         v::stringType(), false),
            v::key('description',  v::stringType(), false),
            v::key('handler_type', v::stringType(), false),
            v::key('handler',      v::stringType(), false),
            v::key('schedule',     v::stringType(), false),
            v::key('config',       v::stringType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'identifier'   => 'Der Identifier muss Text sein.',
            'name'         => 'Der Anzeigename muss Text sein.',
            'description'  => 'Die Beschreibung muss Text sein.',
            'handler_type' => 'Ungültiger Handler-Typ.',
            'handler'      => 'Der Handler muss Text sein.',
            'schedule'     => 'Der Schedule muss Text sein.',
            'config'       => 'Die Config muss Text sein.',
            'keySet'       => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'identifier'   => trim((string) ($input['identifier'] ?? '')),
            'name'         => trim((string) ($input['name'] ?? '')),
            'description'  => trim((string) ($input['description'] ?? '')),
            'handler_type' => (string) ($input['handler_type'] ?? 'webhook'),
            'handler'      => trim((string) ($input['handler'] ?? '')),
            'schedule'     => trim((string) ($input['schedule'] ?? '')),
            'config'       => trim((string) ($input['config'] ?? '')),
        ];
    }
}
