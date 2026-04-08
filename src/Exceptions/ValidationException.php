<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Wird von FormRequest::validate() geworfen, wenn die Eingaben nicht den
 * deklarativen Regeln entsprechen.
 *
 * Hält strukturierte Field-Errors für API-Responses (Phase 4+) sowie eine
 * lesbare Zusammenfassung für Flash-Messages.
 */
class ValidationException extends RuntimeException
{
    /**
     * @param array<string,string> $errors Field-Name => Fehlertext (erste Verletzung pro Feld)
     */
    public function __construct(
        private array $errors,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? $this->buildSummaryMessage(),
            422,
            $previous,
        );
    }

    /**
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        if ($this->errors === []) {
            return null;
        }
        $first = reset($this->errors);
        return $first === false ? null : (string) $first;
    }

    private function buildSummaryMessage(): string
    {
        if ($this->errors === []) {
            return 'Validierung fehlgeschlagen.';
        }
        return 'Validierung fehlgeschlagen: ' . implode(' · ', $this->errors);
    }
}
