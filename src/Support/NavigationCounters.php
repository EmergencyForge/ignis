<?php

declare(strict_types=1);

namespace App\Support;

use App\Notifications\NotificationManager;
use App\Session\SessionManager;
use Throwable;

/**
 * Zähler an den Einträgen der Sidebar (config/navigation.php, Schlüssel
 * `counter`) und an der Glocke in der Topbar. Ein Zähler erscheint nur,
 * wo er eine Handlung bedeutet; `inbox` sind die ungelesenen
 * Benachrichtigungen des Betrachters (NotificationManager::count(), also
 * ohne die Typen, die er nicht sehen darf). Die Werte bleiben je Request
 * gecacht, weil Topbar und Sidebar dieselben Schlüssel fragen; Tests
 * setzen den Cache mit reset() zurück.
 *
 * `null` heißt: nichts anzeigen (nicht angemeldet, nichts offen, oder die
 * Abfrage ist gescheitert — ein Zähler darf nie eine Seite zerreißen).
 */
final class NavigationCounters
{
    /** @var array<string, int|null> */
    private static array $cache = [];

    public static function for(string $key): ?int
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $value = match ($key) {
                'inbox'  => self::inbox(),
                default  => null,
            };
        } catch (Throwable) {
            $value = null;
        }

        return self::$cache[$key] = ($value !== null && $value > 0) ? $value : null;
    }

    public static function reset(): void
    {
        self::$cache = [];
    }

    private static function inbox(): ?int
    {
        $userId = SessionManager::userId();
        if ($userId === null) {
            return null;
        }

        return app(NotificationManager::class)->count($userId);
    }
}
