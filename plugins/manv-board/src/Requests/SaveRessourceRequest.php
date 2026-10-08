<?php

declare(strict_types=1);

namespace Plugin\ManvBoard\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eine Ressource der Lage, angelegt (`action=create`) oder geändert
 * (`action=edit`), beides POST /mci/resources.
 *
 * `fahrzeug_id` schickt die Fahrzeugsuche mit, gespeichert wird sie nicht.
 * `rufname`, `status` und `besatzung` haben kein Feld im Formular, der
 * Controller übernimmt sie beim Anlegen aber, wenn sie mitkommen.
 * `bezeichnung` bleibt ungekürzt, nur das Anlegen kürzt sie.
 */
final class SaveRessourceRequest extends FormRequest
{
    private const FELDER = [
        'action', 'ressource_id', 'fahrzeug_id', 'typ', 'bezeichnung', 'rufname',
        'fahrzeugtyp', 'lokalisation', 'status', 'besatzung', 'notizen',
    ];

    protected static function rules(): Validatable
    {
        return v::keySet(...array_map(
            static fn (string $feld): Validatable => v::key($feld, v::optional(v::stringVal()), false),
            self::FELDER,
        ));
    }

    protected static function messages(): array
    {
        return array_fill_keys([...self::FELDER, 'keySet'], 'Ungültige Eingabe.');
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{ressource_id:int, typ:mixed, bezeichnung:mixed, rufname:mixed, fahrzeugtyp:mixed, lokalisation:mixed, status:mixed, besatzung:mixed, notizen:mixed}
     */
    protected static function cast(array $input): array
    {
        return [
            'ressource_id' => (int) ($input['ressource_id'] ?? 0),
            'typ'          => $input['typ'] ?? 'fahrzeug',
            'bezeichnung'  => $input['bezeichnung'] ?? '',
            'rufname'      => $input['rufname'] ?? null,
            'fahrzeugtyp'  => $input['fahrzeugtyp'] ?? null,
            'lokalisation' => $input['lokalisation'] ?? null,
            'status'       => $input['status'] ?? 'verfuegbar',
            'besatzung'    => $input['besatzung'] ?? null,
            'notizen'      => $input['notizen'] ?? null,
        ];
    }
}
