<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\ValidationException;
use App\Session\SessionManager;
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
        // Alten Bag immer zuerst wegräumen (siehe Klassenkommentar), erst
        // danach gegebenenfalls mit dem Ergebnis dieses Aufrufs neu befüllen.
        SessionManager::forget(self::OLD_INPUT_SESSION_KEY);

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
            self::rememberInput($input);
            throw new ValidationException($flat, previous: $e);
        }

        try {
            return static::cast($input);
        } catch (ValidationException $e) {
            // cast() darf Prüfungen über mehrere Felder werfen (Kalender:
            // Ende vor Start); auch dann soll das Formular die Eingabe
            // zurückbekommen.
            self::rememberInput($input);
            throw $e;
        }
    }

    // ── Old-Input ─────────────────────────────────────────────────────
    //
    // Nach einer gescheiterten Prüfung leitet der Controller zurück aufs
    // Formular; damit die Eingabe dort wieder steht, liegt sie bis zum
    // nächsten Request in der Session (OLD_INPUT_SESSION_KEY) und das
    // Template liest sie über old('feld', 'standard') aus src/helpers.php.
    // Der Bag ist One-Shot: der erste old()-Aufruf eines Requests holt ihn
    // komplett aus der Session und löscht ihn dort, alle weiteren lesen aus
    // dem Zwischenspeicher. Der Zwischenspeicher wird bei jedem Dispatch
    // zurückgesetzt (RouterFactory, beforeDispatch), weil in
    // Tests\FeatureTestCase viele Requests im selben Prozess laufen.

    public const OLD_INPUT_SESSION_KEY = 'old_input';

    /** @var array<string,mixed> */
    private static array $oldInputCache = [];

    private static bool $oldInputLoaded = false;

    /**
     * Legt die Eingabe für den nächsten Request ab. validate() ruft das bei
     * Fehlern selbst; Controller ohne FormRequest (Fahrzeug anlegen) oder
     * mit Prüfungen nach validate() (Dienstnummer schon vergeben) rufen es
     * vor ihrem Redirect. Der CSRF-Token und Passwortfelder bleiben draußen.
     *
     * @param array<string,mixed> $input
     */
    public static function rememberInput(array $input): void
    {
        unset($input['csrf_token']);
        foreach (array_keys($input) as $key) {
            if (is_string($key) && str_contains(strtolower($key), 'password')) {
                unset($input[$key]);
            }
        }
        SessionManager::set(self::OLD_INPUT_SESSION_KEY, $input);
    }

    /**
     * Liest ein Feld aus dem Bag, Implementierung hinter old().
     */
    public static function pullOldInput(string $field, mixed $default): mixed
    {
        if (!self::$oldInputLoaded) {
            self::$oldInputLoaded = true;
            $stored = SessionManager::get(self::OLD_INPUT_SESSION_KEY);
            SessionManager::forget(self::OLD_INPUT_SESSION_KEY);
            self::$oldInputCache = is_array($stored) ? $stored : [];
        }

        return array_key_exists($field, self::$oldInputCache) ? self::$oldInputCache[$field] : $default;
    }

    /**
     * Setzt den Pro-Request-Zwischenspeicher zurück; RouterFactory hängt
     * das an jeden Dispatch.
     */
    public static function resetOldInputCache(): void
    {
        self::$oldInputLoaded = false;
        self::$oldInputCache  = [];
    }
}
