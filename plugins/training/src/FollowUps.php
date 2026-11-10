<?php

declare(strict_types=1);

namespace Plugin\Training;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;

/**
 * Folgeaufgaben aus dem Lehrgangsabschluss und Bekanntgaben fürs
 * Dashboard. ignis hat kein Aufgabenmodul: offene Folgeaufgaben stehen
 * unter „Für dich offen“ und auf der Profilkarte, wo sie erledigt werden.
 * Jede Aufgabe gehört genau einem Konto; an eine Rolle gerichtet, hat
 * jedes Mitglied seine eigene.
 */
final class FollowUps
{
    private const TABLE = 'intra_training_follow_ups';

    /**
     * Offene Aufgaben eines Kontos.
     *
     * @return list<object{id: int, personnel_id: ?int, title: string, due_on: ?string, name: ?string}>
     */
    public static function openFor(int $userId): array
    {
        /** @var list<object{id: int, personnel_id: ?int, title: string, due_on: ?string, name: ?string}> */
        return Capsule::table(self::TABLE . ' as f')
            ->leftJoin('intra_mitarbeiter as m', 'm.id', '=', 'f.personnel_id')
            ->whereNull('f.done_at')
            ->where('f.user_id', $userId)
            ->orderByRaw('f.due_on IS NULL')->orderBy('f.due_on')->orderBy('f.id')
            ->limit(20)
            ->get(['f.id', 'f.personnel_id', 'f.title', 'f.due_on', 'm.fullname as name'])->all();
    }

    /**
     * Offene Aufgaben zu einem Mitarbeiter, für die Profilkarte.
     *
     * @return list<object{id: int, title: string, due_on: ?string, user_id: ?int, assignee: ?string}>
     */
    public static function openAbout(int $personnelId): array
    {
        /** @var list<object{id: int, title: string, due_on: ?string, user_id: ?int, assignee: ?string}> */
        return Capsule::table(self::TABLE . ' as f')
            ->leftJoin('intra_users as u', 'u.id', '=', 'f.user_id')
            ->where('f.personnel_id', $personnelId)->whereNull('f.done_at')->orderBy('f.id')
            ->get(['f.id', 'f.title', 'f.due_on', 'f.user_id', Capsule::connection()->raw("COALESCE(NULLIF(u.fullname, ''), u.username) as assignee")])->all();
    }

    /** Erledigen darf, wem die Aufgabe gehört, und wer Ausbildung verwaltet. */
    public static function close(int $id, int $userId, bool $manage): bool
    {
        $row = Capsule::table(self::TABLE)->where('id', $id)->whereNull('done_at')->first(['user_id']);
        if ($row === null || (!$manage && !self::owns($row, $userId))) {
            return false;
        }

        return Capsule::table(self::TABLE)->where('id', $id)->whereNull('done_at')->update(['done_at' => Carbon::now(), 'done_by' => $userId, 'updated_at' => Carbon::now()]) === 1;
    }

    /** @param object{user_id: ?int} $row */
    public static function owns(object $row, int $userId): bool
    {
        return $row->user_id !== null && (int) $row->user_id === $userId;
    }

    /** @return list<string> Texte der Bekanntgaben, die noch laufen, neueste zuerst */
    public static function announcements(): array
    {
        return Capsule::table('intra_training_announcements')->where('expires_at', '>', Carbon::now())->orderByDesc('id')->limit(5)
            ->pluck('text')->map(static fn ($t): string => (string) $t)->all();
    }
}
