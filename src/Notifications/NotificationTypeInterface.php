<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Ein Benachrichtigungstyp der Registry in App\Notifications\NotificationManager.
 *
 * Der Typ ist der Wert in `intra_notifications.type`. Sein Handler sagt,
 * wie Einträge dieses Typs im Posteingang heißen und aussehen, ob der
 * angemeldete Nutzer sie sehen darf, und wohin ein Eintrag führt. Der Kern
 * registriert seine Typen im Manager (NotificationManager::coreTypes()),
 * Plugins tragen ihre über das Manifest ein:
 *
 *     'notifications' => ['Plugin\\Firetab\\Notifications\\FireProtocolType'],
 *
 * Ein Eintrag, dessen Typ keinen Handler hat (etwa aus einem inzwischen
 * abgeschalteten Plugin), erscheint trotzdem: Titel und Text roh, der
 * gespeicherte Link, der Typschlüssel als Beschriftung.
 */
interface NotificationTypeInterface
{
    /** Schlüssel in `intra_notifications.type`, z.B. `antrag`. */
    public function key(): string;

    /** Beschriftung der Gruppe und des Filters, z.B. „Anträge". */
    public function label(): string;

    /** Font-Awesome-Klasse, z.B. `fa-solid fa-file`. */
    public function icon(): string;

    /**
     * Darf der angemeldete Nutzer Einträge dieses Typs sehen? Wer nein
     * sagt, versteckt die Einträge in Popover, Seite und Zähler; die
     * Datensätze bleiben.
     */
    public function allowed(): bool;

    /**
     * Das Ziel eines Eintrags. Bekommt die Zeile aus der Tabelle (mit
     * `link`, `title`, `message`, `id`, `user_id`) und liefert den Pfad
     * mit BASE_PATH, oder null, wenn der Eintrag nirgendwohin führt.
     *
     * @param array<string, mixed> $row
     */
    public function link(array $row): ?string;
}
