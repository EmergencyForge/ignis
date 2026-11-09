<?php

declare(strict_types=1);

namespace Plugin\Training\Notifications;

use App\Notifications\NotificationTypeInterface;

/**
 * Benachrichtigungen der Ausbildung: Anmeldung, Zulassung, Warteliste,
 * Lehrgangsbeginn. Sehen darf sie jeder Empfänger.
 */
final class TrainingNotificationType implements NotificationTypeInterface
{
    public function key(): string
    {
        return 'training';
    }

    public function label(): string
    {
        return 'Ausbildung';
    }

    public function icon(): string
    {
        return 'fa-solid fa-graduation-cap';
    }

    public function allowed(): bool
    {
        return true;
    }

    public function link(array $row): ?string
    {
        $link = $row['link'] ?? null;

        return is_string($link) && $link !== '' ? $link : null;
    }
}
