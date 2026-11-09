<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Notifications\NotificationManager;
use Illuminate\Database\Capsule\Manager as Capsule;

/** Benachrichtigungen vom Typ `training` an Mitarbeiter oder Konten. */
final class Notify
{
    /** @param list<int> $personnelIds */
    public static function personnel(array $personnelIds, string $title, string $link, ?string $message = null): void
    {
        if ($personnelIds === []) {
            return;
        }
        $userIds = Capsule::table('intra_users')->whereIn('aktenid', $personnelIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        self::users(array_values($userIds), $title, $link, $message);
    }

    /** @param list<int> $userIds */
    public static function users(array $userIds, string $title, string $link, ?string $message = null): void
    {
        if ($userIds === []) {
            return;
        }
        app(NotificationManager::class)->notify('training', $userIds, ['title' => $title, 'message' => $message, 'link' => (defined('BASE_PATH') ? BASE_PATH : '/') . ltrim($link, '/')]);
    }
}
