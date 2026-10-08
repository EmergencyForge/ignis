<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Titel, angelegt oder geändert (`intra_mitarbeiter_titel`). `id`
 * fehlt beim Anlegen, ob es beim Ändern größer null ist, prüft der
 * Controller.
 */
class SaveTitleRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name',     v::stringType()->notBlank()->length(1, 50)),
            v::key('priority', v::optional(v::stringVal()->intVal()->between(0, 9999)), false),
            v::key('id',       v::optional(v::stringVal()->intVal()), false),
        );
    }

    protected static function messages(): array
    {
        // Unter v::keySet() greifen nur Schlüssel nach Feldname (und
        // `keySet`), nicht nach Regelname wie `notBlank`.
        return [
            'name'     => 'Der Titel darf nicht leer sein und höchstens 50 Zeichen haben.',
            'priority' => 'Die Priorität muss eine Zahl zwischen 0 und 9999 sein.',
            'id'       => 'Ungültige ID.',
            'keySet'   => 'Das Formular ist unvollständig oder enthält unbekannte Felder.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'id'       => (int) ($input['id'] ?? 0),
            'name'     => trim((string) $input['name']),
            'priority' => (int) ($input['priority'] ?? 0),
        ];
    }
}
