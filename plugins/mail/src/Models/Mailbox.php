<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;
use App\Models\Personnel;
use App\Models\User;
use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Eloquent-Model für `intra_mail_mailboxes` — das Postfach eines
 * Mitarbeiters. Adresse und Anzeigename liegen hier selbst, weil
 * Mitarbeiter hart gelöscht werden (`mitarbeiter_id` wird dann NULL).
 *
 * Zustellbar ist ein Postfach nur, wenn es aktiv UND nicht gesperrt ist.
 *
 * Wem es gehört, sagt allein `user_id`. Die Discord-ID am Mitarbeiter
 * pflegt die Personalverwaltung; sie entscheidet nur, welches Konto ein
 * noch freies Postfach bekommt (autoBind()), danach nie wieder.
 * Umhängen geht nur in der Postfachverwaltung („Konto zuordnen“).
 *
 * @property int         $id
 * @property int|null    $mitarbeiter_id
 * @property int|null    $user_id
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
        'user_id'        => 'integer',
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

        $mailbox = self::ownedBy($userId);

        return self::$current[$userId] = $mailbox !== null && $mailbox->active && !$mailbox->locked ? $mailbox : null;
    }

    public static function forget(): void
    {
        self::$current = [];
    }

    /**
     * Das Postfach dieses Kontos, in jedem Zustand. Gebunden zählt nur
     * `user_id`. Ist das Postfach des eigenen Mitarbeiters noch frei, wird
     * es hier gebunden, sofern dieses Konto das einzige passende ist.
     */
    public static function ownedBy(int $userId): ?self
    {
        $bound = self::query()->where('user_id', $userId)->first();
        if ($bound !== null) {
            return $bound;
        }

        $mitarbeiterId = self::mitarbeiterIdForUser($userId);
        $free = $mitarbeiterId === null ? null : self::query()->where('mitarbeiter_id', $mitarbeiterId)->whereNull('user_id')->first();

        return $free !== null && $free->autoBind() === $userId ? $free : null;
    }

    /**
     * Bindet ein freies Postfach an das Konto seines Mitarbeiters, wenn
     * genau ein aktives Konto passt und dieses noch kein Postfach hat. Ein
     * gebundenes Postfach bleibt, wie es ist. Liefert das Konto, an dem
     * das Postfach danach hängt, oder null.
     */
    public function autoBind(): ?int
    {
        if ($this->user_id !== null) {
            return $this->user_id;
        }
        $accounts = $this->mitarbeiter_id !== null ? self::accountsFor($this->mitarbeiter_id) : [];
        if (count($accounts) !== 1 || self::query()->where('user_id', $accounts[0])->exists()) {
            return null;
        }

        try {
            // Nur solange frei: sonst hat ein paralleler Request gewonnen.
            $bound = self::query()->whereKey($this->id)->whereNull('user_id')->update(['user_id' => $accounts[0]]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
        if ($bound !== 1) {
            return null;
        }
        $this->user_id = $accounts[0];
        $this->syncOriginalAttribute('user_id');

        return $this->user_id;
    }

    /**
     * Aktive Konten, die zum Mitarbeiter passen: Discord-ID gleich
     * `discordtag`, oder `intra_users.aktenid` zeigt auf ihn.
     *
     * @return list<int>
     */
    public static function accountsFor(int $mitarbeiterId): array
    {
        $tag = trim((string) Personnel::query()->whereKey($mitarbeiterId)->value('discordtag'));

        $ids = Capsule::table('intra_users')->where('is_active', 1)
            ->where(static function ($q) use ($mitarbeiterId, $tag): void {
                $q->where('aktenid', $mitarbeiterId);
                if ($tag !== '') {
                    $q->orWhere('discord_id', $tag);
                }
            })
            ->pluck('id')
            ->all();

        return array_values(array_map('intval', $ids));
    }

    /**
     * Mitarbeiter-ID eines Kontos: erst über die Discord-ID, dann über
     * aktenid. Nur für noch freie Postfächer und Hinweise, nie für den
     * Zugriff auf ein gebundenes.
     */
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
     * Die aktiven Konten hinter Postfächern, für die Benachrichtigung bei
     * Zustellung. Ein noch freies Postfach hat keins.
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
            ->join('intra_users as u', 'u.id', '=', 'mb.user_id')
            ->whereIn('mb.id', $mailboxIds)
            ->where('u.is_active', 1)
            ->distinct()
            ->pluck('u.id')
            ->all();

        return array_values(array_map('intval', $rows));
    }
}
