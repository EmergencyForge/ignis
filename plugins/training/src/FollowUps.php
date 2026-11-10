<?php

declare(strict_types=1);

namespace Plugin\Training;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;

/**
 * Folgeaufgaben aus dem Lehrgangsabschluss und Bekanntgaben fürs
 * Dashboard. ignis hat kein Aufgabenmodul: offene Folgeaufgaben stehen
 * unter „Für dich offen“ und auf der Profilkarte, wo sie erledigt werden.
 */
final class FollowUps
{
    private const TABLE = 'intra_training_follow_ups';

    /**
     * Offene Aufgaben für ein Konto, direkt oder über seine Rolle.
     *
     * @return list<object{id: int, personnel_id: ?int, title: string, due_on: ?string, name: ?string}>
     */
    public static function openFor(int $userId): array
    {
        $role = (int) Capsule::table('intra_users')->where('id', $userId)->value('role');

        /** @var list<object{id: int, personnel_id: ?int, title: string, due_on: ?string, name: ?string}> */
        return Capsule::table(self::TABLE . ' as f')
            ->leftJoin('intra_mitarbeiter as m', 'm.id', '=', 'f.personnel_id')
            ->whereNull('f.done_at')
            ->where(static fn ($q) => $q->where('f.user_id', $userId)->orWhere('f.role_id', $role))
            ->orderByRaw('f.due_on IS NULL')->orderBy('f.due_on')->orderBy('f.id')
            ->limit(20)
            ->get(['f.id', 'f.personnel_id', 'f.title', 'f.due_on', 'm.fullname as name'])->all();
    }

    /**
     * Offene Aufgaben zu einem Mitarbeiter, für die Profilkarte.
     *
     * @return list<object{id: int, title: string, due_on: ?string, user_id: ?int, role_id: ?int}>
     */
    public static function openAbout(int $personnelId): array
    {
        /** @var list<object{id: int, title: string, due_on: ?string, user_id: ?int, role_id: ?int}> */
        return Capsule::table(self::TABLE)->where('personnel_id', $personnelId)->whereNull('done_at')->orderBy('id')
            ->get(['id', 'title', 'due_on', 'user_id', 'role_id'])->all();
    }

    /** Erledigen darf, wem die Aufgabe gehört, und wer Ausbildung verwaltet. */
    public static function close(int $id, int $userId, bool $manage): bool
    {
        $row = Capsule::table(self::TABLE)->where('id', $id)->whereNull('done_at')->first(['user_id', 'role_id']);
        if ($row === null || (!$manage && !self::owns($row, $userId))) {
            return false;
        }

        return Capsule::table(self::TABLE)->where('id', $id)->whereNull('done_at')->update(['done_at' => Carbon::now(), 'done_by' => $userId, 'updated_at' => Carbon::now()]) === 1;
    }

    /** @param object{user_id: ?int, role_id: ?int} $row */
    public static function owns(object $row, int $userId): bool
    {
        if ($row->user_id !== null && (int) $row->user_id === $userId) {
            return true;
        }

        return $row->role_id !== null && (int) $row->role_id === (int) Capsule::table('intra_users')->where('id', $userId)->value('role');
    }

    /** @return list<string> Texte der Bekanntgaben, die noch laufen, neueste zuerst */
    public static function announcements(): array
    {
        return Capsule::table('intra_training_announcements')->where('expires_at', '>', Carbon::now())->orderByDesc('id')->limit(5)
            ->pluck('text')->map(static fn ($t): string => (string) $t)->all();
    }
}
