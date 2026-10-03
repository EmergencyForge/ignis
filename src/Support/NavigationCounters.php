<?php

declare(strict_types=1);

namespace App\Support;

use App\Notifications\NotificationManager;
use App\Plugins\PluginLoader;
use App\Session\SessionManager;
use Throwable;

/**
 * Zähler an den Einträgen der Sidebar (config/navigation.php, Schlüssel
 * `counter`) und an der Glocke in der Topbar. Ein Zähler erscheint nur,
 * wo er eine Handlung bedeutet; `inbox` sind die ungelesenen
 * Benachrichtigungen des Betrachters (NotificationManager::count(), also
 * ohne die Typen, die er nicht sehen darf). Schlüssel, die der Kern nicht
 * kennt, kommen aus den Plugins (counters.php, z.B. `mail`). Die Werte bleiben je Request
 * gecacht, weil Topbar und Sidebar dieselben Schlüssel fragen; Tests
 * setzen den Cache mit reset() zurück.
 *
 * `null` heißt: nichts anzeigen (nicht angemeldet, nichts offen, oder die
 * Abfrage ist gescheitert; ein Zähler darf nie eine Seite zerreißen).
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
                default  => self::plugin($key),
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

    /** Zähler aus einem Plugin (counters.php, PluginLoader::navigationCounters()). */
    private static function plugin(string $key): ?int
    {
        $counter = app(PluginLoader::class)->navigationCounters()[$key] ?? null;

        return $counter !== null ? $counter() : null;
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
