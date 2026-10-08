<?php

declare(strict_types=1);

namespace App\Integrations;

use RuntimeException;

/**
 * Ein Discord-Webhook ging nicht raus. `status` ist der HTTP-Status der
 * Antwort, 0 bei einem Netzfehler.
 */
final class DiscordWebhookException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    /**
     * Lohnt ein neuer Versuch? Netzfehler, Rate-Limit und Störungen bei
     * Discord ja; eine abgelehnte Nachricht oder ein gelöschter Webhook
     * (4xx) wird beim nächsten Mal genauso abgelehnt.
     */
    public function retryable(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}
