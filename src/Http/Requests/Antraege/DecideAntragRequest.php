<?php

declare(strict_types=1);

namespace App\Http\Requests\Antraege;

use App\Http\Requests\FormRequest;
use App\Models\Antrag;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /antrag/admin/view (Status-Update durch Bearbeiter).
 *
 * Felder:
 *   - cirs_status (int 0-3, einer der Antrag::STATUS_* Werte)
 *   - cirs_text   (optional, max 5000 Zeichen)
 */
class DecideAntragRequest extends FormRequest
{
    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('cirs_status', v::stringVal()->intVal()->in([
                (string) Antrag::STATUS_IN_PROGRESS,
                (string) Antrag::STATUS_REJECTED,
                (string) Antrag::STATUS_DEFERRED,
                (string) Antrag::STATUS_ACCEPTED,
            ])),
            v::key('cirs_text', v::optional(v::stringType()->length(0, 5000)), false),
            v::key('save',      v::optional(v::stringType()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'in'     => 'Ungültiger Status.',
            'intVal' => 'Status muss eine Zahl sein.',
            'length' => 'Bemerkung darf maximal {{maxValue}} Zeichen lang sein.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'cirs_status' => (int) $input['cirs_status'],
            'cirs_text'   => isset($input['cirs_text']) ? trim((string) $input['cirs_text']) : '',
        ];
    }
}
