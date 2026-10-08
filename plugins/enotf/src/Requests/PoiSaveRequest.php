<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Ein POI, angelegt oder geändert (`intra_edivi_pois`).
 *
 * `id` fehlt beim Anlegen, beim Ändern prüft der Controller, dass sie
 * größer null ist. Anders als beim Medikament wird nichts gekürzt und ein
 * leeres Feld nicht zu NULL; die Tabelle hält beides schon so.
 */
final class PoiSaveRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('name',     v::stringType()->notBlank()->length(1, 255)),
            v::key('strasse',  v::optional(v::stringType()->length(0, 255)), false),
            v::key('hnr',      v::optional(v::stringType()->length(0, 50)), false),
            v::key('ort',      v::stringType()->notBlank()->length(1, 255)),
            v::key('ortsteil', v::optional(v::stringType()->length(0, 255)), false),
            v::key('typ',      v::optional(v::stringType()->length(0, 50)), false),
            v::key('active',   v::optional(v::stringType()), false),
            v::key('id',       v::optional(v::stringVal()->intVal()), false),
        );
    }

    /** Je Feld eine Meldung, siehe MedikamentSaveRequest::messages(). */
    protected static function messages(): array
    {
        return [
            'name'     => 'Der Name ist Pflicht und darf höchstens 255 Zeichen lang sein.',
            'ort'      => 'Der Ort ist Pflicht und darf höchstens 255 Zeichen lang sein.',
            'strasse'  => 'Die Straße darf höchstens 255 Zeichen lang sein.',
            'hnr'      => 'Die Hausnummer darf höchstens 50 Zeichen lang sein.',
            'ortsteil' => 'Der Ortsteil darf höchstens 255 Zeichen lang sein.',
            'typ'      => 'Der Typ darf höchstens 50 Zeichen lang sein.',
            'active'   => 'Ungültige Angabe bei Aktiv.',
            'id'       => 'Ungültige ID.',
            'keySet'   => 'Das Formular enthält unbekannte Felder.',
        ];
    }

    /**
     * @param  array<string,mixed> $input
     * @return array{id:int, name:string, strasse:string|null, hnr:string|null, ort:string, ortsteil:string|null, typ:string|null, active:int}
     */
    protected static function cast(array $input): array
    {
        return [
            'id'       => (int) ($input['id'] ?? 0),
            'name'     => (string) $input['name'],
            'strasse'  => $input['strasse'] ?? null,
            'hnr'      => $input['hnr'] ?? null,
            'ort'      => (string) $input['ort'],
            'ortsteil' => $input['ortsteil'] ?? null,
            'typ'      => $input['typ'] ?? null,
            'active'   => isset($input['active']) ? 1 : 0,
        ];
    }
}
