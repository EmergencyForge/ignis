<?php

declare(strict_types=1);

namespace App\Http\Validation;

use RuntimeException;

/**
 * Wird geworfen, wenn ein FormRequest seine Validation-Rules gegen den
 * eingehenden Request nicht bestätigen kann.
 *
 * Die Exception trägt eine Liste von Feld → Fehlermeldung-Paaren, damit
 * sie sauber als JSON-Response serialisiert werden kann. Sie wird im
 * Front-Controller gefangen und in eine 422-JSON-Response umgewandelt.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors   Feld → Fehler-Message
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Validierung fehlgeschlagen',
    ) {
        parent::__construct($message, 422);
    }
}
