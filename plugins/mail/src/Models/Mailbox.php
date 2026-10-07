<?php

declare(strict_types=1);

namespace Plugin\Mail\Models;

use App\Models\Model;
use App\Models\Personnel;
use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Eloquent-Model für `intra_mail_mailboxes`: das Postfach eines
 * Mitarbeiters. Adresse und Anzeigename liegen hier selbst, weil
 * Mitarbeiter hart gelöscht werden (`mitarbeiter_id` wird dann NULL).
 *
 * Zustellbar ist ein Postfach nur, wenn es aktiv UND nicht gesperrt ist.
 *
 * Wem es gehört, sagt allein `user_id`. Die Verknüpfung Konto und
 * Mitarbeiter (`intra_users.aktenid`, App\Personnel\AccountLink)
 * entscheidet nur, welches Konto ein noch freies Postfach bekommt
 * (autoBind()), danach nie wieder.
 * Umhängen geht nur in der Postfachverwaltung („Konto zuordnen“).
 *
 * Ein Gruppenpostfach (`kind = group`) gehört keinem Mitarbeiter und keinem
 * Konto. Lesen und senden dürfen die Konten in `intra_mail_mailbox_members`,
 * gepflegt in der Postfachverwaltung. Für Empfänger ist es ein Postfach wie
 * jedes andere; anders als ein Verteiler hat es eigene Ordner, und alle
 * Mitglieder sehen dieselbe Kopie. Welches Postfach die Mail-Oberfläche
 * zeigt, entscheidet selected(); current() bleibt das persönliche.
 *
 * @property int         $id
 * @property string      $kind  personal|group
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

    public const KIND_PERSONAL = 'personal';
    public const KIND_GROUP    = 'group';

    /** Wie die Oberfläche die Arten nennt; der Verteiler steht daneben, ist aber kein Postfach. */
    public const KIND_LABELS = [
        self::KIND_PERSONAL => 'Persönliches Postfach',
        self::KIND_GROUP    => 'Gruppenpostfach',
    ];

    public const LIST_LABEL = 'Verteiler';

    /** Session-Schlüssel der Postfach-Auswahl in der Mail-Oberfläche, siehe selected(). */
    private const SESSION_KEY = 'mail_mailbox_id';

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

    /** @var array<int, list<self>> */
    private static array $accessible = [];

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
        self::$current    = [];
        self::$accessible = [];
    }

    public function isGroup(): bool
    {
        return $this->kind === self::KIND_GROUP;
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? self::KIND_LABELS[self::KIND_PERSONAL];
    }

    /**
     * Alle Postfächer, die das angemeldete Konto lesen darf: zuerst das
     * persönliche (current()), dann die aktiven, nicht gesperrten
     * Gruppenpostfächer, in denen es Mitglied ist, nach Namen. `mail.admin`
     * öffnet hier nichts zusätzlich. Je Request gecacht wie current().
     *
     * @return list<self>
     */
    public static function accessible(): array
    {
        $userId = SessionManager::userId();
        if ($userId === null) {
            return [];
        }
        if (array_key_exists($userId, self::$accessible)) {
            return self::$accessible[$userId];
        }

        $groups = self::query()->where('kind', self::KIND_GROUP)
            ->where('active', true)->where('locked', false)
            ->whereIn('id', MailboxMember::query()->where('user_id', $userId)->select('mailbox_id'))
            ->orderBy('display_name')
            ->get()
            ->all();
        $own = self::current();

        return self::$accessible[$userId] = $own !== null ? [$own, ...$groups] : array_values($groups);
    }

    /** @return list<int> */
    public static function accessibleIds(): array
    {
        return array_map(static fn (self $mailbox): int => $mailbox->id, self::accessible());
    }

    /**
     * Das Postfach, das die Mail-Oberfläche zeigt und aus dem sie sendet.
     * Mit `$requested` genau dieses, wenn das Konto es lesen darf, sonst
     * null. Ohne: die zuletzt gewählte Auswahl aus der Session, sonst das
     * persönliche bzw. das erste lesbare.
     */
    public static function selected(?int $requested = null): ?self
    {
        $accessible = self::accessible();
        $value      = SessionManager::get(self::SESSION_KEY);
        $wanted     = $requested ?? (is_int($value) ? $value : null);

        foreach ($accessible as $mailbox) {
            if ($mailbox->id === $wanted) {
                return $mailbox;
            }
        }

        return $requested !== null ? null : ($accessible[0] ?? null);
    }

    /** Merkt sich die Auswahl für die nächsten Seitenaufrufe (nur Anzeige, kein Recht). */
    public static function remember(int $mailboxId): void
    {
        SessionManager::set(self::SESSION_KEY, $mailboxId);
    }

    /** Ist das Konto Mitglied dieses Gruppenpostfachs? Bei persönlichen immer false. */
    public function hasMember(int $userId): bool
    {
        return $this->isGroup() && MailboxMember::query()->where('mailbox_id', $this->id)->where('user_id', $userId)->exists();
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
     * Aktive Konten, die mit dem Mitarbeiter verknüpft sind
     * (`intra_users.aktenid`, eindeutig, also höchstens eines).
     *
     * @return list<int>
     */
    public static function accountsFor(int $mitarbeiterId): array
    {
        $ids = Capsule::table('intra_users')->where('is_active', 1)
            ->where('aktenid', $mitarbeiterId)
            ->pluck('id')
            ->all();

        return array_values(array_map('intval', $ids));
    }

    /**
     * Mitarbeiter-ID eines Kontos über die Verknüpfung. Nur für noch freie
     * Postfächer und Hinweise, nie für den Zugriff auf ein gebundenes.
     */
    public static function mitarbeiterIdForUser(int $userId): ?int
    {
        $id = Capsule::table('intra_users')->where('id', $userId)->value('aktenid');

        return $id === null ? null : (int) $id;
    }

    /**
     * Die aktiven Konten hinter Postfächern, für die Benachrichtigung bei
     * Zustellung: das Konto eines persönlichen Postfachs, die Mitglieder
     * eines Gruppenpostfachs. Ein noch freies Postfach hat keins.
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
        $members = Capsule::table('intra_mail_mailbox_members as mm')
            ->join('intra_mail_mailboxes as mb', 'mb.id', '=', 'mm.mailbox_id')
            ->join('intra_users as u', 'u.id', '=', 'mm.user_id')
            ->whereIn('mm.mailbox_id', $mailboxIds)
            ->where('mb.kind', self::KIND_GROUP)
            ->where('u.is_active', 1)
            ->distinct()
            ->pluck('u.id')
            ->all();

        return array_values(array_unique(array_map('intval', [...$rows, ...$members])));
    }
}
