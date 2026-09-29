<?php

declare(strict_types=1);

namespace Plugin\Mail\Notifications;

use App\Auth\Permissions;
use App\Notifications\NotificationTypeInterface;

/**
 * Benachrichtigung „Neue Mail“, angelegt bei der Zustellung
 * (MailController::notifyRecipients()). Der Eintrag führt in den
 * Posteingang zur Mail; beim Lesen der Mail wird er mit auf gelesen
 * gesetzt. Sehen darf ihn, wer Mail nutzen darf.
 */
final class MailType implements NotificationTypeInterface
{
    public function key(): string
    {
        return 'mail';
    }

    public function label(): string
    {
        return 'Mail';
    }

    public function icon(): string
    {
        return 'fa-solid fa-envelope';
    }

    public function allowed(): bool
    {
        return Permissions::check(['admin', 'mail.use']);
    }

    public function link(array $row): ?string
    {
        $link = $row['link'] ?? null;

        return is_string($link) && $link !== '' ? $link : null;
    }
}
