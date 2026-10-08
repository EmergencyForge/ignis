<?php

declare(strict_types=1);

namespace Plugin\ManvBoard\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Eine MANV-Lage, angelegt (POST /mci/create) oder geändert (POST /mci/edit).
 *
 * Pflichtfelder und erlaubte Status prüft weiter der Controller, damit die
 * Meldung und das Ziel der Umleitung dieselben bleiben. `status` schickt nur
 * das Bearbeiten, `id` steht hier, weil der Controller sie hilfsweise aus
 * dem Post nimmt, wenn sie in der Adresse fehlt.
 */
final class SaveLageRequest extends FormRequest
{
    private const FELDER = [
        'id', 'einsatznummer', 'einsatzort', 'einsatzanlass', 'einsatzbeginn',
        'lna_name', 'lna_mitarbeiter_id', 'orgl_name', 'orgl_mitarbeiter_id',
        'status', 'notizen',
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
     * @return array{id:int|null, einsatznummer:string, einsatzort:string, einsatzanlass:mixed, einsatzbeginn:mixed, lna_name:mixed, lna_mitarbeiter_id:int|null, orgl_name:mixed, orgl_mitarbeiter_id:int|null, status:string, notizen:mixed}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'                  => isset($input['id']) ? (int) $input['id'] : null,
            'einsatznummer'       => trim((string) ($input['einsatznummer'] ?? '')),
            'einsatzort'          => trim((string) ($input['einsatzort'] ?? '')),
            'einsatzanlass'       => $input['einsatzanlass'] ?? null,
            'einsatzbeginn'       => $input['einsatzbeginn'] ?? null,
            'lna_name'            => $input['lna_name'] ?? null,
            'lna_mitarbeiter_id'  => !empty($input['lna_mitarbeiter_id']) ? (int) $input['lna_mitarbeiter_id'] : null,
            'orgl_name'           => $input['orgl_name'] ?? null,
            'orgl_mitarbeiter_id' => !empty($input['orgl_mitarbeiter_id']) ? (int) $input['orgl_mitarbeiter_id'] : null,
            'status'              => (string) ($input['status'] ?? ''),
            'notizen'             => $input['notizen'] ?? null,
        ];
    }
}
