<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Models\Role;
use App\Models\RegistrationCode;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Capsule\Manager as Capsule;
use RuntimeException;

final class FabricaIdentity
{
    private const TABLE = 'intra_fabrica_identities';

    public static function user(string $issuer, string $subject): ?User
    {
        $id = Capsule::table(self::TABLE)->where('issuer', $issuer)->where('subject', $subject)->value('user_id');
        return $id === null ? null : User::query()->where('is_active', 1)->whereKey((int) $id)->first();
    }

    /** Register a confirmed Fabrica identity under the product's local admission rules. */
    public static function resolve(string $issuer, string $subject, ?string $username, string $mode, ?string $code): User
    {
        if (!FabricaClient::uuid($subject)) throw new DomainException('Ungültige Konto-ID.');
        try {
            return Capsule::connection()->transaction(function () use ($issuer, $subject, $username, $mode, $code): User {
                $existing = self::existingAccount($issuer, $subject);
                if ($existing !== null) return $existing;

                if (!in_array($mode, ['open', 'code'], true)) {
                    throw new DomainException('Registrierung ist derzeit geschlossen. Bitte wende dich an die Instanzverwaltung.');
                }
                if ($username === null || trim($username) === '') {
                    throw new DomainException('Fabrica muss für neue Sync-Konten aktualisiert werden. Bitte wende dich an die Instanzverwaltung.');
                }

                $role = Role::query()->where('default', 1)->first();
                if ($role === null) throw new DomainException('Es ist keine Standardrolle eingerichtet. Bitte wende dich an die Instanzverwaltung.');
                if ($mode === 'code') {
                    $now = date('Y-m-d H:i:s');
                    $reserved = RegistrationCode::query()->where('code', $code ?? '')
                        ->where('is_used', 0)->whereNull('used_at')
                        ->where(function ($query) use ($now): void {
                            $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                        })
                        ->update(['used_at' => $now, 'is_used' => 1]);
                    if ($reserved !== 1) {
                        throw new DomainException('Als neuer Benutzer benötigst du einen gültigen, noch nicht verwendeten Einladungscode.');
                    }
                }

                // The identity and invitation commit with the account; failed callbacks leave no orphan.
                $user = User::query()->create([
                    'discord_id' => null,
                    'username' => mb_substr(trim($username), 0, 255),
                    'fullname' => null,
                    'role' => $role->id,
                    'full_admin' => false,
                ]);
                Capsule::table(self::TABLE)->insert(['issuer' => $issuer, 'subject' => $subject, 'user_id' => $user->id]);
                if ($mode === 'code') RegistrationCode::query()->where('code', $code)->update(['used_by' => $user->id]);
                return $user;
            }, 3);
        } catch (QueryException $e) {
            // A parallel callback may have committed the same identity first.
            if (($e->errorInfo[1] ?? null) === 1062) {
                $existing = self::existingAccount($issuer, $subject);
                if ($existing !== null) return $existing;
            }
            throw $e;
        }
    }

    private static function existingAccount(string $issuer, string $subject): ?User
    {
        $id = Capsule::table(self::TABLE)->where('issuer', $issuer)->where('subject', $subject)->value('user_id');
        if ($id === null) return null;
        $user = User::query()->where('is_active', 1)->whereKey((int) $id)->first();
        if ($user === null) throw new DomainException('Dieses Benutzerkonto wurde deaktiviert. Bitte wende dich an die Instanzverwaltung.');
        return $user;
    }

    public static function link(string $issuer, string $subject, int $userId): void
    {
        if (!FabricaClient::uuid($subject) || $userId < 1) throw new RuntimeException('Ungültige Konto-ID.');
        Capsule::connection()->transaction(function () use ($issuer, $subject, $userId): void {
            if (User::query()->where('is_active', 1)->whereKey($userId)->lockForUpdate()->first() === null) throw new RuntimeException('Das lokale Konto fehlt oder ist deaktiviert.');
            $existing = Capsule::table(self::TABLE)->where('issuer', $issuer)->where(function ($query) use ($subject, $userId): void {
                $query->where('subject', $subject)->orWhere('user_id', $userId);
            })->lockForUpdate()->get();
            foreach ($existing as $row) {
                if ($row->subject !== $subject || (int) $row->user_id !== $userId) throw new RuntimeException('Es besteht bereits eine andere Zuordnung.');
            }
            if ($existing->isEmpty()) Capsule::table(self::TABLE)->insert(['issuer' => $issuer, 'subject' => $subject, 'user_id' => $userId]);
        });
    }

    public static function unlink(string $issuer, int $userId): void
    {
        Capsule::table(self::TABLE)->where('issuer', $issuer)->where('user_id', $userId)->delete();
    }
}
