<?php

declare(strict_types=1);

namespace Plugin\ManvBoard\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein Patient der Lage, angelegt (POST /mci/patient-create) oder geändert
 * (POST /mci/patient-view).
 *
 * `massnahmen` hat kein Feld im Formular, der Controller übernimmt es beim
 * Anlegen aber, wenn es mitkommt. `sichtungskategorie` bleibt null, wenn sie
 * fehlt: beim Ändern heißt das „nicht angefasst", nicht „ungesichtet".
 */
final class SavePatientRequest extends FormRequest
{
    private const FELDER = [
        'name', 'vorname', 'geburtsdatum', 'geschlecht', 'sichtungskategorie',
        'transportmittel_id', 'transportziel', 'verletzungen', 'massnahmen', 'notizen',
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
     * @return array{name:mixed, vorname:mixed, geburtsdatum:mixed, geschlecht:mixed, sichtungskategorie:mixed, transportmittel_id:int|null, transportziel:mixed, verletzungen:mixed, massnahmen:mixed, notizen:mixed}
     */
    protected static function cast(array $input): array
    {
        return [
            'name'               => $input['name'] ?? null,
            'vorname'            => $input['vorname'] ?? null,
            'geburtsdatum'       => !empty($input['geburtsdatum']) ? $input['geburtsdatum'] : null,
            'geschlecht'         => $input['geschlecht'] ?? 'unbekannt',
            'sichtungskategorie' => $input['sichtungskategorie'] ?? null,
            'transportmittel_id' => !empty($input['transportmittel_id']) ? (int) $input['transportmittel_id'] : null,
            'transportziel'      => $input['transportziel'] ?? null,
            'verletzungen'       => $input['verletzungen'] ?? null,
            'massnahmen'         => $input['massnahmen'] ?? null,
            'notizen'            => $input['notizen'] ?? null,
        ];
    }
}
