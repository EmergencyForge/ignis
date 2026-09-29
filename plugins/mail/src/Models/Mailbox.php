<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;
use App\Models\Personnel;
use App\Models\User;
use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_mail_mailboxes` — das Postfach eines
 * Mitarbeiters. Adresse und Anzeigename liegen hier selbst, weil
 * Mitarbeiter hart gelöscht werden (`mitarbeiter_id` wird dann NULL).
 *
 * Zustellbar ist ein Postfach nur, wenn es aktiv UND nicht gesperrt ist.
 *
 * @property int         $id
 * @property int|null    $mitarbeiter_id
 * @property string      $address
 * @property string      $display_name
 * @property string      $domain
 * @property bool        $active
 * @property bool        $locked
 * @property-read Personnel|null $mitarbeiter
 */
class Mailbox extends Model
{
    protected $table = 'intra_mail_mailboxes';

    /** @var array<string,string> */
    protected $casts = [
        'id'             => 'integer',
        'mitarbeiter_id' => 'integer',
        'active'         => 'boolean',
        'locked'         => 'boolean',
    ];

    /** @var array<int, self|null> */
    private static array $current = [];

    /** @return BelongsTo<Personnel, $this> */
    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'mitarbeiter_id', 'id');
    }

    /**
     * Das Postfach des angemeldeten Nutzers, nur wenn es zustellbar ist.
     * Ein gesperrtes oder stillgelegtes Postfach öffnet niemand.
     *
     * Der Mitarbeiter hinter dem Konto kommt wie überall in ignis über
     * `discordtag = discord_id`, ersatzweise über `intra_users.aktenid`.
     * Je Request gecacht; Tests leeren den Cache mit forget().
     */
    public static function current(): ?self
    {
        $userId = SessionManager::userId();
        if ($userId === null) {
            return null;
        }
        if (array_key_exists($userId, self::$current)) {
            return self::$current[$userId];
        }

        $mitarbeiterId = self::mitarbeiterIdForUser($userId);
        $mailbox = $mitarbeiterId === null
            ? null
            : self::query()->where('mitarbeiter_id', $mitarbeiterId)->where('active', true)->where('locked', false)->first();

        return self::$current[$userId] = $mailbox;
    }

    public static function forget(): void
    {
        self::$current = [];
    }

    /** Mitarbeiter-ID eines Kontos: erst über die Discord-ID, dann über aktenid. */
    public static function mitarbeiterIdForUser(int $userId): ?int
    {
        $user = User::query()->find($userId, ['id', 'discord_id', 'aktenid']);
        if ($user === null) {
            return null;
        }
        if (!empty($user->discord_id)) {
            $id = Personnel::query()->where('discordtag', $user->discord_id)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }
        if (!empty($user->aktenid) && Personnel::query()->whereKey($user->aktenid)->exists()) {
            return (int) $user->aktenid;
        }

        return null;
    }

    /**
     * Die Konten hinter Postfächern, dieselbe Zuordnung rückwärts: für die
     * Benachrichtigung bei Zustellung.
     *
     * @param list<int> $mailboxIds
     * @return list<int>
     */
    public static function userIdsFor(array $mailboxIds): array
    {
        if ($mailboxIds === []) {
            return [];
        }

        $rows = Capsule::table('intra_mail_mailboxes as mb')
            ->join('intra_mitarbeiter as m', 'm.id', '=', 'mb.mitarbeiter_id')
            ->join('intra_users as u', static function ($join): void {
                $join->on('u.discord_id', '=', 'm.discordtag')->orOn('u.aktenid', '=', 'm.id');
            })
            ->whereIn('mb.id', $mailboxIds)
            ->where('u.is_active', 1)
            ->distinct()
            ->pluck('u.id')
            ->all();

        return array_values(array_map('intval', $rows));
    }
}
