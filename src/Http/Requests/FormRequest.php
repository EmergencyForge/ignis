<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\ValidationException;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validatable;

/**
 * Base-Klasse für deklarative Form-Validation.
 *
 * Konkrete FormRequests definieren `rules()` (Respect/Validation Validator-
 * Instance), `messages()` (Field-Name → Custom-Message) und optional `cast()`
 * (Mapping von rohem Input → typisierten Werten).
 *
 * Aufruf in Controllern:
 *
 *     $data = CreateRoleRequest::validate($_POST);
 *     // bei Fehlern wirft validate() eine ValidationException — der Caller
 *     // catched die und macht Flash::error() + redirect.
 */
abstract class FormRequest
{
    /**
     * Validator-Pipeline aufbauen. Üblicherweise via v::keySet(...).
     */
    abstract protected static function rules(): Validatable;

    /**
     * Optionale Custom-Messages pro Feld. Default: Respect/Validation-Default-
     * Texte (englisch). Override in der konkreten Request-Klasse für deutsche
     * UX-Texte.
     *
     * @return array<string,string>
     */
    protected static function messages(): array
    {
        return [];
    }

    /**
     * Optional: roher Input → typisierter, normalisierter Output.
     * Default: rohen Input zurückgeben.
     *
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     */
    protected static function cast(array $input): array
    {
        return $input;
    }

    /**
     * Validiert den Input gegen `rules()`. Wirft ValidationException bei Fehlern,
     * gibt sonst die typisierten Werte aus `cast()` zurück.
     *
     * @param  array<string,mixed> $input
     * @return array<string,mixed>
     * @throws ValidationException
     */
    public static function validate(array $input): array
    {
        try {
            static::rules()->assert($input);
        } catch (NestedValidationException $e) {
            $messages = static::messages();
            $errors   = $messages !== []
                ? $e->getMessages($messages)
                : $e->getMessages();

            // Field-Errors auf "erste Verletzung pro Feld" reduzieren
            $flat = [];
            foreach ($errors as $field => $msg) {
                if (is_array($msg)) {
                    $flat[$field] = (string) reset($msg);
                } else {
                    $flat[$field] = (string) $msg;
                }
            }
            throw new ValidationException($flat, previous: $e);
        }

        return static::cast($input);
    }
}
