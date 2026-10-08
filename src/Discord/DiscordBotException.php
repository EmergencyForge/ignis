<?php

declare(strict_types=1);

namespace App\Discord;

use RuntimeException;

/**
 * Ein Aufruf der Discord-API ist gescheitert. Die Nachricht ist für
 * Menschen geschrieben und darf so auf der Einstellungsseite stehen.
 */
final class DiscordBotException extends RuntimeException
{
    /** Discord: „Cannot send messages to this user“. */
    public const CANNOT_DM = 50007;

    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly int $discordCode = 0,
    ) {
        parent::__construct($message);
    }

    /**
     * Lohnt ein neuer Versuch? Netzfehler, Rate-Limit und Störungen bei
     * Discord ja; ein abgelehntes Token oder eine gesperrte DM bleibt so.
     */
    public function retryable(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}
