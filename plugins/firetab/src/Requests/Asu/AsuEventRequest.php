<?php

declare(strict_types=1);

namespace Plugin\Firetab\Requests\Asu;

use App\Http\Requests\FormRequest;
use Respect\Validation\Validatable;
use Respect\Validation\Validator as v;

/**
 * POST /api/fire/asu/event: ein Ereignis eines Trupps. Die client_uuid
 * erzeugt das Tablet; kommt dieselbe zweimal, bleibt es ein Eintrag.
 */
class AsuEventRequest extends FormRequest
{
    public const TYPES = [
        'angeschlossen', 'druck', 'ziel', 'hinweis_13', 'hinweis_23',
        'rueckzug', 'draussen', 'mayday', 'mayday_ende',
    ];

    protected static function rules(): Validatable
    {
        return v::keySet(
            v::key('incident_id', v::intVal()->positive()),
            v::key('trupp_id', v::intVal()->positive()),
            v::key('type', v::in(self::TYPES)),
            v::key('member_id', v::optional(v::intVal()->positive()), false),
            v::key('bar', v::optional(v::intVal()->between(0, 400)), false),
            v::key('note', v::optional(v::stringType()->length(0, 255)), false),
            v::key('client_uuid', v::stringType()->regex('/^[0-9a-f-]{36}$/i')),
        );
    }

    protected static function messages(): array
    {
        return [
            'type'        => 'Unbekannte Meldung.',
            'bar'         => 'Der Druck muss zwischen 0 und 400 bar liegen.',
            'note'        => 'Die Notiz ist zu lang (höchstens 255 Zeichen).',
            'client_uuid' => 'Kennung der Meldung fehlt.',
        ];
    }

    protected static function cast(array $input): array
    {
        return [
            'incident_id' => (int) $input['incident_id'],
            'trupp_id'    => (int) $input['trupp_id'],
            'type'        => (string) $input['type'],
            'member_id'   => isset($input['member_id']) ? (int) $input['member_id'] : null,
            'bar'         => isset($input['bar']) ? (int) $input['bar'] : null,
            'note'        => isset($input['note']) && trim((string) $input['note']) !== '' ? trim((string) $input['note']) : null,
            'client_uuid' => strtolower((string) $input['client_uuid']),
        ];
    }
}
