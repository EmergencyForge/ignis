<?php

declare(strict_types=1);

namespace App\Discord;

use App\Models\Personnel;
use App\Models\RegistrationCode;
use App\Utils\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Stellt eine Einladung als Direktnachricht des Discord-Bots zu. Empfänger
 * ist die Discord-ID am Mitarbeiter der Einladung, freie Einladungen haben
 * keinen.
 *
 * Gesendet wird im Request und nicht über die Warteschlange: Wer einlädt,
 * soll sofort sehen, ob die Nachricht ankam, und sonst den Link von Hand
 * weitergeben. Das Abschalten von Direktnachrichten im Kontomenü greift
 * hier nicht, der Eingeladene hat noch kein Konto.
 */
final class InvitationDelivery
{
    /** Hat der Mitarbeiter eine Discord-ID, an die der Bot schreiben kann? */
    public static function reachable(?Personnel $person): bool
    {
        return DiscordBot::snowflake($person?->discordtag);
    }

    /**
     * Schickt die Einladung und merkt den Zeitpunkt. Die Einladung bleibt
     * gültig, auch wenn nichts ankommt.
     *
     * @return string|null Grund, warum die Nachricht nicht ankam
     */
    public static function send(RegistrationCode $code): ?string
    {
        $person = $code->mitarbeiter_id !== null ? Personnel::query()->find($code->mitarbeiter_id) : null;

        $blocked = match (true) {
            !$code->isRedeemable()                        => 'Die Einladung ist nicht mehr gültig.',
            !DiscordBot::active()                         => 'Der Discord-Bot ist nicht eingeschaltet.',
            $person === null || !self::reachable($person) => 'Der Mitarbeiter hat keine Discord-ID.',
            default                                       => null,
        };
        if ($blocked !== null) {
            return $blocked;
        }

        $actor   = (int) ($_SESSION['userid'] ?? 0);
        $context = ['mitarbeiter_id' => $person->id, 'code_id' => $code->id];
        try {
            DiscordBot::sendDirectMessage((string) $person->discordtag, self::message((string) $person->fullname, $code));
        } catch (DiscordBotException $e) {
            (new AuditLogger())->log($actor, 'Einladung per Discord nicht zugestellt', $e->getMessage(), 'Benutzer', 1, $context);

            return $e->getMessage();
        }

        $code->discord_sent_at = Carbon::now();
        $code->save();
        (new AuditLogger())->log($actor, 'Einladung per Discord gesendet', $code->label, 'Benutzer', 1, $context);

        return null;
    }

    /**
     * Embed mit Link und ein Knopf, der direkt zur Registrierung führt.
     *
     * @return array<string, mixed>
     */
    private static function message(string $name, RegistrationCode $code): array
    {
        $url    = RegistrationCode::inviteUrl((string) $code->code);
        $system = defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : 'ıgnıs';
        $hello  = trim($name) !== '' ? 'Hallo ' . trim($name) . ', d' : 'D';
        $label  = 'Einladung' . ($code->expires_at !== null ? ' · gültig bis ' . $code->expires_at->format('d.m.Y H:i') : '');

        return [
            'embeds' => [DiscordNotifier::embed(
                'Einladung zu ' . $system,
                $hello . 'u bist eingeladen, dir ein Konto anzulegen. Über den Link meldest du dich an, das Konto ist dann gleich mit deinem Personaleintrag verknüpft.',
                $url,
                $label,
            )],
            'components' => [[
                'type'       => 1,
                'components' => [['type' => 2, 'style' => 5, 'label' => 'Konto anlegen', 'url' => $url]],
            ]],
        ];
    }
}
