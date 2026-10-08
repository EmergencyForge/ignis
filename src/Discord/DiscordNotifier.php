<?php

declare(strict_types=1);

namespace App\Discord;

use App\Jobs\JobDispatcher;
use App\Jobs\SendDiscordDmJob;
use App\Models\RegistrationCode;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Bringt Benachrichtigungen zusätzlich als Discord-Direktnachricht raus.
 * NotificationManager::notify() ruft hier an; welche Typen per DM gehen,
 * steht in den Bot-Einstellungen, abschalten kann es jeder für sich im
 * Kontomenü (intra_users.discord_dm). Die Nachrichten laufen über die
 * Warteschlange, damit Discord die Seite nicht aufhält.
 */
final class DiscordNotifier
{
    /** Discords Grenze für die Beschreibung eines Embeds. */
    private const DESCRIPTION_LIMIT = 4096;

    /**
     * @param list<int> $userIds
     * @return int Zahl der eingereihten Nachrichten
     */
    public static function notification(string $type, array $userIds, string $title, ?string $message, ?string $link, ?string $label = null): int
    {
        if (!DiscordBot::active() || !in_array($type, DiscordBot::settings()['dm_types'], true)) {
            return 0;
        }

        $recipients = self::recipients($userIds);
        if ($recipients === []) {
            return 0;
        }

        $payload = ['embeds' => [self::embed($title, $message, $link, $label)]];
        $jobs = app(JobDispatcher::class);
        foreach ($recipients as $discordId) {
            $jobs->dispatch(new SendDiscordDmJob($discordId, $payload));
        }

        return count($recipients);
    }

    /**
     * Die Discord-IDs der Konten, die DMs bekommen wollen: aktiv, nicht
     * abgemeldet, mit Discord-ID am Konto oder am verknüpften Mitarbeiter.
     *
     * @param list<int> $userIds
     * @return array<int, string> Konto-ID => Discord-ID
     */
    public static function recipients(array $userIds, bool $respectOptOut = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $query = Capsule::table('intra_users as u')
            ->leftJoin('intra_mitarbeiter as m', 'm.id', '=', 'u.aktenid')
            ->whereIn('u.id', $ids)
            ->where('u.is_active', 1)
            ->select(['u.id', 'u.discord_id', 'm.discordtag']);
        if ($respectOptOut) {
            $query->where('u.discord_dm', 1);
        }

        $recipients = [];
        foreach ($query->get() as $row) {
            $discordId = DiscordBot::snowflake($row->discord_id) ? (string) $row->discord_id : (DiscordBot::snowflake($row->discordtag) ? (string) $row->discordtag : null);
            if ($discordId !== null) {
                $recipients[(int) $row->id] = $discordId;
            }
        }

        return $recipients;
    }

    /**
     * Für das Kontomenü: will das Konto DMs (true/false), oder ist es per
     * Discord gar nicht erreichbar (null, ebenso bei abgeschaltetem Bot)?
     */
    public static function preference(int $userId): ?bool
    {
        if ($userId < 1 || !DiscordBot::active()) {
            return null;
        }
        $row = Capsule::table('intra_users as u')
            ->leftJoin('intra_mitarbeiter as m', 'm.id', '=', 'u.aktenid')
            ->where('u.id', $userId)
            ->first(['u.discord_dm', 'u.discord_id', 'm.discordtag']);
        if ($row === null || (!DiscordBot::snowflake($row->discord_id) && !DiscordBot::snowflake($row->discordtag))) {
            return null;
        }

        return (bool) $row->discord_dm;
    }

    /**
     * Ein Embed im Stil der Benachrichtigung: Titel mit Link zurück in die
     * Installation, Text, unten Systemname und Art.
     *
     * @return array<string, mixed>
     */
    public static function embed(string $title, ?string $message, ?string $link, ?string $label = null): array
    {
        $embed = [
            'title' => mb_substr($title, 0, 256),
            'color' => 0xD9480F,
            'timestamp' => gmdate('c'),
        ];
        if ($message !== null && trim($message) !== '') {
            $embed['description'] = mb_substr($message, 0, self::DESCRIPTION_LIMIT);
        }
        $url = self::absolute($link);
        if ($url !== null) {
            $embed['url'] = $url;
        }
        $footer = array_filter([defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : '', (string) $label], static fn (string $part): bool => $part !== '');
        if ($footer !== []) {
            $embed['footer'] = ['text' => mb_substr(implode(' · ', $footer), 0, 2048)];
        }

        return $embed;
    }

    /** Ein Pfad der Installation als volle URL, die Discord verlinken kann. */
    public static function absolute(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $link) === 1) {
            return $link;
        }
        $base = RegistrationCode::baseUrl();
        if (preg_match('#^https?://[^/]+#i', $base) !== 1) {
            return null;
        }

        return $base . '/' . ltrim($link, '/');
    }
}
