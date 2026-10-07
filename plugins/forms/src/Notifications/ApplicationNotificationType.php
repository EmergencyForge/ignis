<?php

declare(strict_types=1);

namespace Plugin\Forms\Notifications;

use App\Notifications\NotificationTypeInterface;

/**
 * Benachrichtigungen an den Antragsteller, wenn sein Antrag bearbeitet
 * wurde. Eingetragen über das Manifest (`notifications`), erzeugt in
 * FormsController::decide(). Der Schlüssel bleibt `antrag`, damit ältere
 * Einträge weiter zugeordnet werden. Sehen darf sie jeder Empfänger.
 */
final class ApplicationNotificationType implements NotificationTypeInterface
{
    public function key(): string
    {
        return 'antrag';
    }

    public function label(): string
    {
        return 'Anträge';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file';
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
