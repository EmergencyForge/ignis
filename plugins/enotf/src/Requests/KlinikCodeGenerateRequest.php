<?php

declare(strict_types=1);

namespace Plugin\Enotf\Requests;

use App\Http\Request;
use App\Http\Validation\FormRequest;
use Respect\Validation\Validator as v;

/**
 * Validation für POST /api/klinik/generate-code.
 *
 * Erwartet eine `enr` (Einsatznummer) — entweder als Form-POST oder als
 * JSON-Body. Der Klinik-Code-Endpoint wird historisch per `application/
 * x-www-form-urlencoded` aus dem eNOTF-Frontend gerufen, wir unterstützen
 * aber auch JSON für neue Aufrufer.
 */
final class KlinikCodeGenerateRequest extends FormRequest
{
    protected function source(Request $request): array
    {
        // Form-POST hat Vorrang; JSON als Fallback.
        if (!empty($request->post)) {
            return $request->post;
        }
        $json = $request->json();
        return is_array($json) ? $json : [];
    }

    protected function rules(): array
    {
        return [
            'enr' => v::stringType()->notEmpty()->length(1, 64),
        ];
    }

    protected function messages(): array
    {
        return [
            'enr' => 'Einsatznummer (enr) ist Pflicht',
        ];
    }
}
