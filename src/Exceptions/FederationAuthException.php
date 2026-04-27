<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Wird von FederationMiddleware geworfen, wenn die Authentifizierung
 * eines eingehenden Federation-Requests fehlschlägt — sei es weil
 * Federation deaktiviert ist (404), kein/ungültiger X-Federation-Key
 * (401/403) oder die anfragende Instanz keine Capability für die
 * angeforderte Datenart hat (403).
 *
 * Das HTTP-Status-Mapping ist in `$statusCode` mitgeführt, damit der
 * Caller (FederationController bzw. eine zukünftige Router-Middleware)
 * direkt den passenden Code zurückgeben kann.
 */
class FederationAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
