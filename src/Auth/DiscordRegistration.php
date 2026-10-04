<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\RegistrationCode;
use App\Models\User;
use App\Personnel\AccountLink;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Neues Konto aus der Discord-Anmeldung (auth/callback.php).
 *
 * Mit Einladungscode wird der Code atomar eingelöst und das Konto mit dem
 * Mitarbeiter der Einladung verknüpft, in derselben Transaktion wie das
 * Anlegen, so wie FabricaIdentity::resolve. Danach greift der Discord-
 * Rückfall (ADR-0002, Punkt 3).
 */
final class DiscordRegistration
{
    /**
     * @throws DomainException wenn der Einladungscode nicht (mehr) gilt
     */
    public static function create(string $discordId, string $username, int $roleId, bool $fullAdmin = false, ?string $code = null): User
    {
        $user = Capsule::connection()->transaction(static function () use ($discordId, $username, $roleId, $fullAdmin, $code): User {
            $invitation = null;
            if ($code !== null) {
                $invitation = RegistrationCode::reserve($code);
                if ($invitation === null) {
                    throw new DomainException('Ungültiger oder bereits verwendeter Einladungslink.');
                }
            }

            $user = User::query()->create([
                'discord_id' => $discordId,
                'username'   => $username,
                'fullname'   => null,
                'role'       => $roleId,
                'full_admin' => $fullAdmin ? 1 : 0,
            ]);
            $invitation?->redeemFor($user);

            return $user;
        });

        AccountLink::autoLinkByDiscord($discordId);

        return $user;
    }
}
