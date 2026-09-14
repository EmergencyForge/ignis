<?php

declare(strict_types=1);

namespace App\Http\Requests\Antraege;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Antragstyp, angelegt oder geändert (`intra_antrag_typen`).
 *
 * `submit` bzw. `update_typ` sind die Knöpfe der beiden Formulare; sie
 * stehen hier nur, damit `keySet()` sie nicht als unbekannte Schlüssel
 * abweist.
 */
class SaveFormTypeRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name',         v::stringType()->notBlank()->length(1, 255)),
            v::key('beschreibung', v::optional(v::stringType()->length(0, 500)), false),
            v::key('icon',         v::optional(v::stringType()->length(0, 50)->regex('~^[a-z0-9 -]*$~i')), false),
            v::key('aktiv',        v::optional(v::stringType()), false),
            v::key('sortierung',   v::optional(v::stringVal()->intVal()->between(0, 9999)), false),
            v::key('submit',       v::optional(v::stringType()), false),
            v::key('update_typ',   v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'notBlank' => 'Bitte gib einen Namen für den Antragstyp an.',
            'length'   => 'Ein Eintrag ist länger als {{maxValue}} Zeichen.',
            'intVal'   => 'Die Sortierung muss eine Zahl sein.',
            'between'  => 'Die Sortierung muss zwischen {{minValue}} und {{maxValue}} liegen.',
            'regex'    => 'Der Symbolname darf nur Buchstaben, Ziffern und Bindestriche enthalten.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'name'         => trim((string) $input['name']),
            'beschreibung' => trim((string) ($input['beschreibung'] ?? '')),
            'icon'         => trim((string) ($input['icon'] ?? '')),
            'aktiv'        => isset($input['aktiv']) ? 1 : 0,
            'sortierung'   => (int) ($input['sortierung'] ?? 0),
        ];
    }
}
