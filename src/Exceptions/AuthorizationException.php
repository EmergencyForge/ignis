<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Wird von Gate::authorize() geworfen, wenn die geprüfte Ability vom Aktor
 * nicht ausgeführt werden darf.
 *
 * Im Web-Kontext wird die Exception aktuell vom Controller selbst gefangen
 * (Flash + Redirect). Mit wird sie zentral in
 * eine 403-Response übersetzt.
 */
class AuthorizationException extends RuntimeException
{
    public function __construct(
        private string $ability,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? "Aktion '{$ability}' ist nicht erlaubt.",
            403,
            $previous,
        );
    }

    public function ability(): string
    {
        return $this->ability;
    }
}
