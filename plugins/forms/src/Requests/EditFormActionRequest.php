<?php

declare(strict_types=1);

namespace Plugin\Forms\Requests;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Welches Formular der Bearbeitungsseite eines Antragstyps abgeschickt
 * wurde (POST /settings/forms/edit).
 *
 * Die Seite hat drei Formulare an derselben Adresse, erkennbar nur an
 * ihrem Knopf bzw. Merkfeld: `update_typ`, `add_feld`,
 * `update_felder_sortierung`. Deshalb gibt es hier kein `keySet()`, die
 * übrigen Felder prüfen SaveFormTypeRequest, AddFormFieldRequest und
 * SortRequest.
 */
final class EditFormActionRequest extends FormRequest
{
    public const TYP        = 'update_typ';
    public const FELD       = 'add_feld';
    public const SORTIERUNG = 'update_felder_sortierung';

    protected static function rules(): Validatable
    {
        return v::alwaysValid();
    }

    /**
     * Reihenfolge wie die Formulare auf der Seite: kommen mehrere Merkfelder
     * mit, gewinnt der Antragstyp.
     *
     * @param  array<string,mixed> $input
     * @return array{aktion:string|null}
     */
    protected static function cast(array $input): array
    {
        foreach ([self::TYP, self::FELD, self::SORTIERUNG] as $aktion) {
            if (isset($input[$aktion])) {
                return ['aktion' => $aktion];
            }
        }

        return ['aktion' => null];
    }
}
