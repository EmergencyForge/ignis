<?php

declare(strict_types=1);

namespace App\Personnel;

use App\Models\Personnel;
use App\Models\User;
use App\Utils\AuditLogger;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;

/**
 * Verknüpfung zwischen Benutzerkonto und Mitarbeiter (ADR-0002).
 *
 * `intra_users.aktenid` ist die einzige Quelle: höchstens ein Konto je
 * Mitarbeiter (eindeutiger Index). Wer den Mitarbeiter eines Kontos oder
 * das Konto eines Mitarbeiters braucht, fragt hier oder joint
 * `u.aktenid = m.id`. Die Discord-ID ist nur noch ein Weg, die
 * Verknüpfung automatisch zu setzen.
 */
final class AccountLink
{
    /** @var array<int, Personnel|null> Mitarbeiter des angemeldeten Kontos, für die laufende Anfrage */
    private static array $cache = [];

    public static function personnelFor(int $userId): ?Personnel
    {
        $id = Capsule::table('intra_users')->where('id', $userId)->value('aktenid');

        return $id === null ? null : Personnel::query()->find((int) $id);
    }

    public static function userFor(int $mitarbeiterId): ?User
    {
        return User::query()->where('aktenid', $mitarbeiterId)->first();
    }

    /** Mitarbeiter des angemeldeten Kontos, aus der Datenbank, nicht aus der Sitzung. */
    public static function current(): ?Personnel
    {
        $userId = (int) ($_SESSION['userid'] ?? 0);
        if ($userId <= 0) {
            return null;
        }
        if (!array_key_exists($userId, self::$cache)) {
            self::$cache[$userId] = self::personnelFor($userId);
        }

        return self::$cache[$userId];
    }

    public static function currentId(): ?int
    {
        $person = self::current();

        return $person === null ? null : (int) $person->id;
    }

    /** Vergisst die gelesenen Verknüpfungen (nach Änderungen und in Tests). */
    public static function forget(): void
    {
        self::$cache = [];
    }

    /**
     * Verknüpft ein freies Konto mit einem freien Mitarbeiter. Hängt nichts
     * um: ist eine Seite schon vergeben, gibt es eine DomainException.
     *
     * @param string $weg steht im Audit-Log, z. B. "von Hand"
     */
    public static function link(int $userId, int $mitarbeiterId, int $actorId, string $weg = 'von Hand'): void
    {
        $person = Personnel::query()->find($mitarbeiterId, ['id', 'fullname']);
        if ($person === null) {
            throw new DomainException('Mitarbeiter nicht gefunden.');
        }

        try {
            $changed = Capsule::connection()->transaction(static function () use ($userId, $mitarbeiterId): bool {
                $current = Capsule::table('intra_users')->where('id', $userId)->lockForUpdate()->first(['id', 'aktenid']);
                if ($current === null) {
                    throw new DomainException('Benutzer nicht gefunden.');
                }
                if ($current->aktenid !== null && (int) $current->aktenid === $mitarbeiterId) {
                    return false;
                }
                if ($current->aktenid !== null) {
                    throw new DomainException('Dieses Konto ist schon mit einem anderen Mitarbeiter verknüpft.');
                }
                if (Capsule::table('intra_users')->where('aktenid', $mitarbeiterId)->lockForUpdate()->exists()) {
                    throw new DomainException('Dieser Mitarbeiter ist schon mit einem anderen Konto verknüpft.');
                }
                Capsule::table('intra_users')->where('id', $userId)->update(['aktenid' => $mitarbeiterId]);

                return true;
            });
        } catch (QueryException $e) {
            // Eine parallele Verknüpfung war schneller; der eindeutige Index hält.
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('Dieser Mitarbeiter ist schon mit einem anderen Konto verknüpft.');
            }
            throw $e;
        } finally {
            self::forget();
        }

        if ($changed) {
            (new AuditLogger())->log(
                $actorId,
                'Mitarbeiter verknüpft [Benutzer-ID: ' . $userId . ']',
                $person->fullname . ' (Akten-ID ' . $mitarbeiterId . '), ' . $weg,
                'Benutzer',
                1,
                ['user_id' => $userId, 'mitarbeiter_id' => $mitarbeiterId],
            );
        }
    }

    public static function unlink(int $userId, int $actorId): void
    {
        $mitarbeiterId = Capsule::table('intra_users')->where('id', $userId)->value('aktenid');
        if ($mitarbeiterId === null) {
            return;
        }

        Capsule::table('intra_users')->where('id', $userId)->update(['aktenid' => null]);
        self::forget();

        (new AuditLogger())->log(
            $actorId,
            'Mitarbeiterverknüpfung gelöst [Benutzer-ID: ' . $userId . ']',
            'Akten-ID ' . (int) $mitarbeiterId,
            'Benutzer',
            1,
            ['user_id' => $userId, 'mitarbeiter_id' => (int) $mitarbeiterId],
        );
    }

    /**
     * Einladung mit Mitarbeiter eingelöst: das neue Konto wird verknüpft.
     * Ist der Mitarbeiter schon vergeben, bleibt das Konto frei und das
     * Audit-Log hält es fest.
     */
    public static function linkInvited(int $userId, ?int $mitarbeiterId): void
    {
        if ($mitarbeiterId === null) {
            return;
        }

        try {
            self::link($userId, $mitarbeiterId, $userId, 'über die Einladung');
        } catch (DomainException $e) {
            (new AuditLogger())->log(
                $userId,
                'Einladung ohne Mitarbeiterverknüpfung eingelöst [Benutzer-ID: ' . $userId . ']',
                $e->getMessage() . ' (Akten-ID ' . $mitarbeiterId . ')',
                'Benutzer',
                1,
                ['user_id' => $userId, 'mitarbeiter_id' => $mitarbeiterId],
            );
        }
    }

    /**
     * Discord als automatischer Rückfall: verknüpft, wenn zu dieser
     * Discord-ID genau ein aktives Konto und genau ein Mitarbeiter gehören
     * und beide frei sind. Liefert die ID des verknüpften Kontos.
     */
    public static function autoLinkByDiscord(?string $discordId): ?int
    {
        $discordId = trim((string) $discordId);
        if ($discordId === '') {
            return null;
        }

        $users = Capsule::table('intra_users')->where('discord_id', $discordId)->where('is_active', 1)->limit(2)->get(['id', 'aktenid']);
        $people = Capsule::table('intra_mitarbeiter')->where('discordtag', $discordId)->limit(2)->pluck('id');
        if ($users->count() !== 1 || $people->count() !== 1 || $users[0]->aktenid !== null) {
            return null;
        }

        $userId = (int) $users[0]->id;
        try {
            self::link($userId, (int) $people[0], $userId, 'automatisch über die Discord-ID');
        } catch (DomainException) {
            return null;
        }

        return $userId;
    }
}
