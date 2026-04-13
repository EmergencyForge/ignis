<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Logging\Logger;
use App\Notifications\NotificationManager;

/**
 * Job: Erzeugt eine In-App-Notification asynchron.
 *
 * Wrappt `NotificationManager::create()`. Nützlich, wenn ein einzelner
 * Controller-Request viele Notifications erzeugen soll (z.B. "eNOTF-
 * Protokoll freigegeben" → benachrichtige alle Admins) — sonst würde
 * der Request am Schreiben in die DB kleben.
 *
 * Der `failed()`-Handler loggt final gescheiterte Notifications als
 * Fehler — Notifications sind fire-and-forget, aber komplett verschluckte
 * Fehler wären auch unschön.
 */
final class SendNotificationJob extends Job
{
    public int $tries = 2;
    public string $queue = 'notifications';

    public function __construct(
        private readonly int $userId,
        private readonly string $type,
        private readonly string $title,
        private readonly ?string $message = null,
        private readonly ?string $link = null,
    ) {}

    public function handle(): void
    {
        $manager = app(NotificationManager::class);
        $success = $manager->create(
            $this->userId,
            $this->type,
            $this->title,
            $this->message,
            $this->link,
        );

        if (!$success) {
            throw new \RuntimeException(
                "NotificationManager::create lieferte false für User {$this->userId}"
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Logger::error('SendNotificationJob: Final failure', [
            'user_id' => $this->userId,
            'type'    => $this->type,
            'title'   => $this->title,
            'error'   => $exception->getMessage(),
        ]);
    }
}
