<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /settings/vehicles/vehload/beladung_handler.
 *
 * Die Beladelisten-Seite und beladung-edit.js schicken alle Aktionen an
 * diese eine Adresse, `action` wählt sie aus. Deshalb steht hier die
 * Vereinigung ihrer Felder, jedes optional; was eine Aktion braucht,
 * prüft der Controller, unbekannte Aktionen ebenso.
 *
 * Felder:
 *   - action
 *   - id                        (Kategorie oder Gegenstand)
 *   - title, type, priority, veh_type (Kategorie)
 *   - category, title, amount   (Gegenstand)
 *   - category, order           (Reihenfolge, `order` als Ids mit Komma)
 */
class LoadoutActionRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('action',   v::stringType(), false),
            v::key('id',       v::optional(v::intVal()), false),
            v::key('title',    v::stringType(), false),
            v::key('type',     v::optional(v::intVal()), false),
            v::key('priority', v::optional(v::intVal()), false),
            v::key('veh_type', v::stringType(), false),
            v::key('category', v::optional(v::intVal()), false),
            v::key('amount',   v::optional(v::intVal()), false),
            v::key('order',    v::stringType(), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'action'   => 'Unbekannte Aktion',
            'id'       => 'Ungültige ID',
            'title'    => 'Der Titel muss Text sein',
            'type'     => 'Ungültiger Typ',
            'priority' => 'Die Priorität muss eine Zahl sein',
            'veh_type' => 'Der Fahrzeugtyp muss Text sein',
            'category' => 'Ungültige Kategorie',
            'amount'   => 'Die Anzahl muss eine Zahl sein',
            'order'    => 'Ungültige Reihenfolge',
            'keySet'   => 'Die Anfrage enthält unbekannte Felder',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'action'   => (string) ($input['action'] ?? ''),
            'id'       => (int) ($input['id'] ?? 0),
            'title'    => (string) ($input['title'] ?? ''),
            'type'     => (int) ($input['type'] ?? 0),
            'priority' => (int) ($input['priority'] ?? 0),
            'veh_type' => ($input['veh_type'] ?? '') ?: null,
            'category' => (int) ($input['category'] ?? 0),
            'amount'   => (int) ($input['amount'] ?? 0),
            'order'    => (string) ($input['order'] ?? ''),
        ];
    }
}
