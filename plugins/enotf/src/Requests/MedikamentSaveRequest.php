<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Medikament, angelegt oder geändert (`intra_edivi_medikamente`).
 *
 * Anlegen und Ändern teilen sich das Formular: `id` fehlt beim Anlegen,
 * dass sie beim Ändern größer null ist, prüft der Controller. „Aktiv“ ist
 * ein Kästchen und fehlt im Post, wenn es nicht gesetzt ist.
 */
final class MedikamentSaveRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('wirkstoff',      v::stringType()->notBlank()->length(1, 255)),
            v::key('herstellername', v::optional(v::stringType()->length(0, 255)), false),
            v::key('dosierungen',    v::optional(v::stringType()), false),
            v::key('priority',       v::optional(v::stringVal()->intVal()), false),
            v::key('active',         v::optional(v::stringType()), false),
            v::key('id',             v::optional(v::stringVal()->intVal()), false),
        );
    }

    /**
     * Je Feld eine Meldung: unter keySet() greift Respect nur Vorlagen mit
     * dem Feldnamen, nicht die mit dem Namen der Regel.
     */
    protected static function messages(): array
    {
        return [
            'wirkstoff'      => 'Der Wirkstoff ist Pflicht und darf höchstens 255 Zeichen lang sein.',
            'herstellername' => 'Der Herstellername darf höchstens 255 Zeichen lang sein.',
            'dosierungen'    => 'Die Dosierungen müssen Text sein.',
            'priority'       => 'Die Priorität muss eine Zahl sein.',
            'active'         => 'Ungültige Angabe bei Aktiv.',
            'id'             => 'Ungültige ID.',
            'keySet'         => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int, wirkstoff:string, herstellername:string|null, dosierungen:string|null, priority:int, active:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'             => (int) ($input['id'] ?? 0),
            'wirkstoff'      => trim((string) $input['wirkstoff']),
            'herstellername' => trim((string) ($input['herstellername'] ?? '')) ?: null,
            'dosierungen'    => trim((string) ($input['dosierungen'] ?? '')) ?: null,
            'priority'       => (int) ($input['priority'] ?? 0),
            'active'         => isset($input['active']) ? 1 : 0,
        ];
    }
}
