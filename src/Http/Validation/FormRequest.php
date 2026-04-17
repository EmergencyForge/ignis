<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Exceptions\ValidationException;
use App\Http\Request;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException as RespectValidationException;
use Respect\Validation\Validator;

/**
 * Basis-Klasse für deklarative Request-Validation (Form-Request-Pattern,
 * angelehnt an Laravel — aber bewusst minimalistisch).
 *
 * Konkrete FormRequests erben davon und überschreiben `rules()`, um
 * pro Feld einen Respect-Validator zurückzugeben. Zusätzlich kann
 * `messages()` überschrieben werden, um deutsche Fehlermeldungen zu
 * liefern — sonst kommen die (englischen) Default-Messages von Respect.
 *
 * Nutzung im Controller:
 *
 *     public function identify(Request $request): Response
 *     {
 *         $data = IdentifyRequest::validate($request);
 *         // $data ist garantiert nicht-null, enthält nur die deklarierten Felder
 *         // und hat bereits passende Typen
 *         ...
 *     }
 *
 * Nicht-deklarierte Felder werden NICHT durchgereicht — das schützt
 * Controller vor Mass-Assignment und macht die erwartete Input-Form
 * direkt aus dem FormRequest ablesbar.
 */
abstract class FormRequest
{
    /**
     * @return array<string, Validator>
     */
    abstract protected function rules(): array;

    /**
     * Optional überschreiben, um deutsche oder kontextspezifische
     * Fehlermeldungen zu liefern.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Liest die Input-Daten aus dem Request. Default: JSON-Body bei
     * POST/PUT/PATCH/DELETE, Query-Params bei GET. Subklassen können
     * das überschreiben, z.B. für reine Form-POSTs.
     *
     * @return array<string, mixed>
     */
    protected function source(Request $request): array
    {
        if (in_array(strtoupper($request->method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $json = $request->json();
            if (is_array($json)) {
                return $json;
            }
            return $request->post;
        }
        return $request->query;
    }

    /**
     * Hauptzugang: Baut eine Instanz, validiert und gibt die gesäuberten
     * Daten zurück. Wirft `ValidationException`, wenn Regeln verletzt sind.
     *
     * @return array<string, mixed>
     */
    public static function validate(Request $request): array
    {
        /** @var static $form */
        $form = new static();

        $data  = $form->source($request);
        $rules = $form->rules();
        $msgs  = $form->messages();

        $errors    = [];
        $validated = [];

        foreach ($rules as $field => $validator) {
            $value = $data[$field] ?? null;
            try {
                $validator->setName($field)->assert($value);
                $validated[$field] = $value;
            } catch (NestedValidationException $e) {
                // NestedValidationException hat mehrere Messages —
                // wir nehmen die erste, die der User sehen soll.
                if (isset($msgs[$field])) {
                    $errors[$field] = $msgs[$field];
                } else {
                    $messages = $e->getMessages();
                    $errors[$field] = $messages[0] ?? 'Ungültiger Wert';
                }
            } catch (RespectValidationException $e) {
                $errors[$field] = $msgs[$field] ?? $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $validated;
    }
}
