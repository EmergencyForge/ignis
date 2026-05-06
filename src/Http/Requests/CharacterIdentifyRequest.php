<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Validation\FormRequest;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /api/character/identify.
 *
 * Wird vom `CharacterController::identify()` genutzt. Der FiveM-Server
 * schickt eine fremde Session-ID plus Charakter-Daten, um sie in jene
 * Session zu injizieren.
 *
 * Feld-Regeln:
 *   - session_id: nicht-leerer String (FiveM schickt hex/base64-ähnliche
 *     Session-IDs, wir prüfen nur auf Form-Größe)
 *   - char_name:  nicht-leerer String, max 100 Zeichen
 *   - char_job:   nicht-leerer String, max 50 Zeichen
 *   - char_id:    optional, positive Ganzzahl
 */
final class CharacterIdentifyRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'session_id' => v::stringType()->notEmpty()->length(8, 128),
            'char_name'  => v::stringType()->notEmpty()->length(1, 100),
            'char_job'   => v::stringType()->notEmpty()->length(1, 50),
            'char_id'    => v::optional(v::intVal()->positive()),
        ];
    }

    protected function messages(): array
    {
        return [
            'session_id' => 'Ungültige oder fehlende Session-ID',
            'char_name'  => 'char_name ist Pflicht (1-100 Zeichen)',
            'char_job'   => 'char_job ist Pflicht (1-50 Zeichen)',
            'char_id'    => 'char_id muss eine positive Ganzzahl sein',
        ];
    }
}
