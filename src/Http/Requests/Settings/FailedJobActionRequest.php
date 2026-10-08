<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * Validierung für POST /settings/system/logs (fehlgeschlagene Jobs).
 *
 * Felder:
 *   - action (retry, delete, retry_all, delete_all)
 *   - id     (optional, Zahl): braucht nur retry und delete, das prüft
 *            der Controller
 */
class FailedJobActionRequest extends FormRequest
{
    public const ACTIONS = ['retry', 'delete', 'retry_all', 'delete_all'];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('action', v::in(self::ACTIONS, true), false),
            v::key('id',     v::optional(v::intVal()), false),
        );
    }

    protected static function messages(): array
    {
        return [
            'action' => 'Unbekannte Aktion',
            'id'     => 'Ungültige ID',
            'keySet' => 'Die Anfrage enthält unbekannte Felder',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'action' => (string) ($input['action'] ?? ''),
            'id'     => (int) ($input['id'] ?? 0),
        ];
    }
}
