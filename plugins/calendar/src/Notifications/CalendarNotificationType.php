<?php

declare(strict_types=1);

namespace Plugin\Calendar\Notifications;

use App\Notifications\NotificationTypeInterface;

/**
 * Benachrichtigungen zu Terminen (Einladungen, Änderungen). Eingetragen über
 * das Manifest (`notifications`), erzeugt im CalendarController. Der
 * Schlüssel bleibt `calendar`, damit ältere Einträge zugeordnet bleiben.
 * Sehen darf sie jeder Empfänger.
 */
final class CalendarNotificationType implements NotificationTypeInterface
{
    public function key(): string
    {
        return 'calendar';
    }

    public function label(): string
    {
        return 'Termine';
    }

    public function icon(): string
    {
        return 'fa-solid fa-calendar-days';
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
