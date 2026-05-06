<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Validation\FormRequest;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /api/notifications/mark-read.
 *
 * Erwartet ein JSON-Body mit `id` als positive Ganzzahl (die
 * Notification-ID, die als gelesen markiert werden soll).
 */
final class MarkNotificationReadRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'id' => v::intVal()->positive(),
        ];
    }

    protected function messages(): array
    {
        return [
            'id' => 'Ungültige Notification-ID',
        ];
    }
}
