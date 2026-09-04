<?php

declare(strict_types=1);

namespace App\Notifications\Types;

use App\Auth\Permissions;
use App\Notifications\NotificationTypeInterface;

/**
 * Ein Benachrichtigungstyp ohne eigene Logik: Schlüssel, Beschriftung,
 * Symbol und optional die Rechte, die ein Nutzer braucht, um die Einträge
 * zu sehen (ANY-Match über Permissions::check; leer heißt jeder
 * Angemeldete). Der Link ist der gespeicherte.
 *
 * Die Kern-Typen (NotificationManager::coreTypes()) sind alle von dieser
 * Form; ein Plugin nimmt sie, wenn es keine eigene Link-Auflösung
 * braucht, sonst schreibt es seine eigene Klasse gegen das Interface.
 */
final class GenericType implements NotificationTypeInterface
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $icon,
        private readonly array $permissions = [],
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function allowed(): bool
    {
        return $this->permissions === [] || Permissions::check($this->permissions);
    }

    public function link(array $row): ?string
    {
        $link = $row['link'] ?? null;

        return is_string($link) && $link !== '' ? $link : null;
    }
}
