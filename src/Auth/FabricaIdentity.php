<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
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
