<?php

declare(strict_types=1);

namespace Plugin\Firetab\Notifications;

use App\Notifications\NotificationTypeInterface;

/**
 * Benachrichtigungen an den Einsatzleiter eines Brandeinsatzes: Protokoll
 * zur QM-Sichtung freigegeben, QM-Status geändert. Eingetragen über das
 * Manifest (`notifications`); die Einträge erzeugt weiterhin
 * App\Notifications\NotificationManager::notifyFireProtocol*(), aufgerufen
 * aus FiretabController. Sehen darf sie jeder Empfänger — er wurde als
 * Einsatzleiter ausgewählt, ein Recht ist dafür nicht nötig.
 */
final class FireProtocolType implements NotificationTypeInterface
{
    public function key(): string
    {
        return 'fire_protocol';
    }

    public function label(): string
    {
        return 'Einsätze';
    }

    public function icon(): string
    {
        return 'fa-solid fa-fire';
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
