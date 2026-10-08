<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Discord\DiscordBot;
use App\Discord\DiscordBotException;
use App\Logging\Logger;

/**
 * Job: eine Direktnachricht des Discord-Bots an eine Person.
 *
 * Eingereiht von App\Discord\DiscordNotifier. Netzfehler, Rate-Limits und
 * Störungen bei Discord versucht die Warteschlange erneut; lässt Discord
 * die DM grundsätzlich nicht zu (kein gemeinsamer Server, DMs gesperrt),
 * bleibt es bei einem Eintrag im Log.
 *
 *     app(JobDispatcher::class)->dispatch(
 *         new SendDiscordDmJob('123456789012345678', ['content' => 'Hallo'])
 *     );
 */
final class SendDiscordDmJob extends Job
{
    public int $tries = 3;

    /** @param array<string, mixed> $message Discord-Nachricht (content, embeds) */
    public function __construct(
        private readonly string $discordId,
        private readonly array $message,
    ) {
    }

    public function handle(): void
    {
        // Inzwischen abgeschaltet: nichts mehr zustellen.
        if (!DiscordBot::active()) {
            return;
        }

        try {
            DiscordBot::sendDirectMessage($this->discordId, $this->message);
        } catch (DiscordBotException $e) {
            if ($e->retryable()) {
                throw $e;
            }
            Logger::info('Discord-DM nicht zugestellt', ['discord_id' => $this->discordId, 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Logger::error('Discord-DM endgültig gescheitert', ['discord_id' => $this->discordId, 'error' => $e->getMessage()]);
    }
}
