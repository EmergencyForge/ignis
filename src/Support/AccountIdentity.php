<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Personnel;
use App\Personnel\AccountLink;

/**
 * Wer oben rechts steht: Kopfleiste der klassischen Oberfläche, Menüleiste
 * und Statusleiste von efOS (DeskShell). Mit verknüpftem Mitarbeiter (ADR-0002)
 * dessen Name, Profilbild und Dienstgrad, sonst Benutzername und Rolle.
 */
final class AccountIdentity
{
    /** Platzhalter, den die Personalakte ohne eigenes Bild einträgt. */
    private const EMPTY_PHOTO = 'assets/img/empty_user.png';

    /**
     * @return array{name: string, initials: string, photo: ?string, rank: ?string}
     *         rank ist null ohne Mitarbeiter oder ohne Dienstgrad, dann gilt die Rolle
     */
    public static function current(): array
    {
        return self::resolve(AccountLink::current(), (string) ($_SESSION['cirs_username'] ?? 'Unbekannt'));
    }

    /**
     * @return array{name: string, initials: string, photo: ?string, rank: ?string}
     */
    public static function resolve(?Personnel $person, string $username): array
    {
        $name = $person !== null ? trim((string) $person->fullname) : '';
        if ($name === '') {
            return ['name' => $username, 'initials' => mb_strtoupper(mb_substr($username !== '' ? $username : '?', 0, 2)), 'photo' => null, 'rank' => null];
        }

        $photo = trim((string) $person->pfp);
        $rank = $person->dienstgradModel?->displayName($person->geschlecht);

        return [
            'name' => $name,
            'initials' => ignis_initials($name),
            'photo' => $photo === '' || str_ends_with($photo, self::EMPTY_PHOTO) ? null : $photo,
            'rank' => $rank !== null && $rank !== '' ? $rank : null,
        ];
    }
}
