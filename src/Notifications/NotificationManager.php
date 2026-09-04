<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Logging\Logger;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\Types\GenericType;
use App\Plugins\PluginLoader;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;

/**
 * In-App-Benachrichtigungen (`intra_notifications`) mit einer Registry
 * der Typen.
 *
 * Ein Typ ist ein NotificationTypeInterface: Beschriftung, Symbol,
 * Rechteprüfung und Link-Auflösung für alle Einträge mit diesem Schlüssel.
 * Der Kern bringt seine Typen in coreTypes() mit, die aktiven Plugins
 * ihre über das Manifest-Feld `notifications` (PluginLoader::
 * notificationTypes()); register() nimmt weitere zur Laufzeit an. Ein
 * Eintrag, dessen Typ keinen Handler hat, wird trotzdem gezeigt (Rohtext,
 * gespeicherter Link) — Datensätze eines abgeschalteten Plugins gehen
 * nicht verloren. Einträge eines Typs, dessen Handler allowed() verneint,
 * fehlen in Seite, Popover und Zähler.
 *
 * Erzeugen: notify() legt je Empfänger eine Zeile an; create() ist die
 * ältere Ein-Empfänger-Form, die die bestehenden Erzeuger (Anträge,
 * Dokumente, Kalender, Mängel, fireTab, eNOTF) weiter benutzen. Lesen:
 * forUser() liefert die Einträge mit label, icon und href aus dem Handler,
 * count() die ungelesenen; markRead() setzt einen oder alle auf gelesen.
 *
 * Fehler an der Datenbank werden protokolliert und schlucken den Aufruf:
 * eine Benachrichtigung darf die eigentliche Aktion nie scheitern lassen.
 */
class NotificationManager
{
    /** @var array<string, NotificationTypeInterface>|null */
    private ?array $types = null;

    public function __construct(private readonly ?PluginLoader $plugins = null)
    {
    }

    // ── Typ-Registry ───────────────────────────────────────────

    /**
     * Die Typen des Kerns. `protokoll` gehört fachlich zu eNOTF, bleibt
     * aber hier, weil plugins/enotf eingefroren ist, bis v2 v1 ersetzt,
     * und seine Erzeuger den Typ weiter mit create() schreiben.
     *
     * @return list<NotificationTypeInterface>
     */
    public static function coreTypes(): array
    {
        return [
            new GenericType('antrag',         'Anträge',        'fa-solid fa-file'),
            new GenericType('protokoll',      'Protokolle',     'fa-solid fa-truck-medical'),
            new GenericType('dokument',       'Dokumente',      'fa-solid fa-folder-open'),
            new GenericType('calendar',       'Termine',        'fa-solid fa-calendar-days'),
            new GenericType('vehicle_defect', 'Fahrzeugmängel', 'fa-solid fa-wrench', ['admin', 'vehicles.view', 'vehicles.manage']),
            new GenericType('system',         'System',         'fa-solid fa-gears'),
        ];
    }

    public function register(string $type, NotificationTypeInterface $handler): void
    {
        $this->types();
        $this->types[$type] = $handler;
    }

    /**
     * Alle registrierten Typen, Schlüssel => Handler: erst der Kern, dann
     * die aktiven Plugins, dann was register() dazugegeben hat.
     *
     * @return array<string, NotificationTypeInterface>
     */
    public function types(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $this->types = [];
        foreach (self::coreTypes() as $type) {
            $this->types[$type->key()] = $type;
        }

        try {
            $loader = $this->plugins ?? app(PluginLoader::class);
            foreach ($loader->notificationTypes() as $type) {
                $this->types[$type->key()] = $type;
            }
        } catch (\Throwable $e) {
            Logger::warning('Benachrichtigungstypen der Plugins nicht geladen: ' . $e->getMessage());
        }

        return $this->types;
    }

    public function type(string $key): ?NotificationTypeInterface
    {
        return $this->types()[$key] ?? null;
    }

    /**
     * Die Typen, die der angemeldete Nutzer sehen darf, für Filter und
     * Beschriftungen.
     *
     * @return array<string, NotificationTypeInterface>
     */
    public function visibleTypes(): array
    {
        return array_filter($this->types(), static fn (NotificationTypeInterface $t): bool => $t->allowed());
    }

    /**
     * Schlüssel registrierter Typen, deren Handler den Nutzer ausschließt.
     * Unbekannte Typen stehen nicht hier: sie werden roh gezeigt.
     *
     * @return list<string>
     */
    private function hiddenTypes(): array
    {
        $hidden = [];
        foreach ($this->types() as $key => $type) {
            if (!$type->allowed()) {
                $hidden[] = $key;
            }
        }

        return $hidden;
    }

    // ── Erzeugen ───────────────────────────────────────────────

    /**
     * Legt für jeden Empfänger einen Eintrag an. `payload` trägt `title`
     * (Pflicht), `message` und `link` (optional). Ein Typ ohne Handler
     * wird abgewiesen und protokolliert, wie früher die feste Liste.
     *
     * @param list<int>            $userIds
     * @param array<string, mixed> $payload
     * @return int Zahl der angelegten Einträge
     */
    public function notify(string $type, array $userIds, array $payload): int
    {
        if ($this->type($type) === null) {
            Logger::warning("Invalid notification type: {$type}");
            return 0;
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            Logger::warning("Notification of type {$type} without title");
            return 0;
        }
        $message = isset($payload['message']) && $payload['message'] !== '' ? (string) $payload['message'] : null;
        $link    = isset($payload['link']) && $payload['link'] !== '' ? (string) $payload['link'] : null;

        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $rows = [];
        foreach ($ids as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'type'    => $type,
                'title'   => mb_substr($title, 0, 255),
                'message' => $message,
                'link'    => $link !== null ? mb_substr($link, 0, 512) : null,
            ];
        }

        try {
            Notification::query()->insert($rows);
            return count($rows);
        } catch (\PDOException $e) {
            Logger::error('Failed to create notification: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Ein Eintrag für einen Empfänger; die ältere Form von notify(), die
     * die bestehenden Erzeuger benutzen.
     */
    public function create(int $userId, string $type, string $title, ?string $message = null, ?string $link = null): bool
    {
        return $this->notify($type, [$userId], ['title' => $title, 'message' => $message, 'link' => $link]) === 1;
    }

    // ── Lesen ──────────────────────────────────────────────────

    /**
     * Die Einträge eines Nutzers, neueste zuerst, ohne die Typen, die
     * er nicht sehen darf. Jede Zeile trägt zusätzlich `label`, `icon`
     * und `href` aus dem Handler (oder dem Fallback) und `known`, ob es
     * einen Handler gibt.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId, bool $unreadOnly = false, int $limit = 50, int $offset = 0, ?string $type = null): array
    {
        try {
            return $this->builder($userId, $unreadOnly, $type)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(fn (Notification $n): array => $this->decorate($n->getAttributes()))
                ->all();
        } catch (\PDOException $e) {
            Logger::error('Failed to get notifications: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Zahl aller sichtbaren Einträge eines Nutzers (für die Seitenzählung).
     */
    public function total(int $userId, bool $unreadOnly = false, ?string $type = null): int
    {
        try {
            return $this->builder($userId, $unreadOnly, $type)->count();
        } catch (\PDOException $e) {
            Logger::error('Failed to count notifications: ' . $e->getMessage());
            return 0;
        }
    }

    /** Ungelesene, sichtbare Einträge: der Zähler an Glocke und Sidebar. */
    public function count(int $userId): int
    {
        try {
            return $this->builder($userId, true)->count();
        } catch (\PDOException $e) {
            Logger::error('Failed to get unread count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Ein einzelner Eintrag des Nutzers, dekoriert; null, wenn es ihn
     * nicht gibt oder er einem anderen gehört.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $userId, int $id): ?array
    {
        try {
            $row = Notification::query()->where('user_id', $userId)->where('id', $id)->first();
        } catch (\PDOException $e) {
            Logger::error('Failed to load notification: ' . $e->getMessage());
            return null;
        }

        return $row instanceof Notification ? $this->decorate($row->getAttributes()) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function decorate(array $row): array
    {
        $handler = $this->type((string) ($row['type'] ?? ''));
        if ($handler === null) {
            $link = $row['link'] ?? null;
            $row['known'] = false;
            $row['label'] = (string) ($row['type'] ?? 'Sonstiges');
            $row['icon']  = 'fa-solid fa-bell';
            $row['href']  = is_string($link) && $link !== '' ? $link : null;
        } else {
            $row['known'] = true;
            $row['label'] = $handler->label();
            $row['icon']  = $handler->icon();
            $row['href']  = $handler->link($row);
        }
        $row['is_read'] = (int) ($row['is_read'] ?? 0);

        return $row;
    }

    /**
     * Die sichtbaren Einträge eines Nutzers als Abfrage, ohne Sortierung;
     * die Seite /inbox blättert damit über App\Support\ListQuery.
     *
     * @return Builder<Notification>
     */
    public function builder(int $userId, bool $unreadOnly = false, ?string $type = null): Builder
    {
        $query = Notification::query()->where('user_id', $userId);
        $hidden = $this->hiddenTypes();
        if ($hidden !== []) {
            $query->whereNotIn('type', $hidden);
        }
        if ($unreadOnly) {
            $query->where('is_read', 0);
        }
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        return $query;
    }

    // ── Gelesen ────────────────────────────────────────────────

    /**
     * Setzt einen Eintrag (`id`) oder alle ungelesenen des Nutzers auf
     * gelesen; liefert die Zahl der geänderten Zeilen.
     */
    public function markRead(int $userId, ?int $id = null): int
    {
        try {
            $query = Notification::query()->where('user_id', $userId)->where('is_read', 0);
            if ($id !== null) {
                $query->where('id', $id);
            }

            return $query->update([
                'is_read' => 1,
                'read_at' => Capsule::raw('NOW()'),
            ]);
        } catch (\PDOException $e) {
            Logger::error('Failed to mark notification as read: ' . $e->getMessage());
            return 0;
        }
    }

    // ── Ältere Aufrufe (Erzeuger, API, Jobs) ───────────────────

    /**
     * Get user ID by discord tag
     */
    public function getUserIdByDiscordTag(string $discordTag): ?int
    {
        try {
            $id = User::where('discord_id', $discordTag)->value('id');
            return $id !== null ? (int) $id : null;
        } catch (\PDOException $e) {
            Logger::warning("Failed to get user ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user ID by full name: Mitarbeiter profile first, then intra_users.
     */
    public function getUserIdByFullname(string $fullname): ?int
    {
        try {
            $id = Capsule::table('intra_mitarbeiter as m')
                ->join('intra_users as u', 'm.discordtag', '=', 'u.discord_id')
                ->where('m.fullname', $fullname)
                ->value('u.id');

            if ($id !== null) {
                return (int) $id;
            }

            $id = User::where('fullname', $fullname)->value('id');
            return $id !== null ? (int) $id : null;
        } catch (\PDOException $e) {
            Logger::warning("Failed to get user ID by fullname: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUnread(int $userId, int $limit = 50, ?string $type = null, int $offset = 0): array
    {
        return $this->forUser($userId, true, $limit, $offset, $type);
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->count($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAll(int $userId, int $limit = 50, int $offset = 0, ?string $type = null): array
    {
        return $this->forUser($userId, false, $limit, $offset, $type);
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->markRead($userId, $notificationId) > 0;
    }

    public function markAllAsRead(int $userId): bool
    {
        $this->markRead($userId);
        return true;
    }

    public function delete(int $notificationId, int $userId): bool
    {
        try {
            Notification::query()
                ->where('id', $notificationId)
                ->where('user_id', $userId)
                ->delete();
            return true;
        } catch (\PDOException $e) {
            Logger::error("Failed to delete notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Neue Einträge seit einem Zeitstempel plus der aktuelle Zähler; das
     * Polling der Glocke (assets/js/navbar/notifications.js) fragt so.
     *
     * @return array{unreadCount: int, new: list<array<string, mixed>>}
     */
    public function getNewSince(int $userId, string $since): array
    {
        try {
            $unreadCount = $this->count($userId);

            $newNotifications = $this->builder($userId, true)
                ->where('created_at', '>', $since)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'type', 'title', 'message', 'link', 'created_at'])
                ->map(fn (Notification $n): array => $this->decorate($n->getAttributes()))
                ->all();

            return [
                'unreadCount' => $unreadCount,
                'new' => $newNotifications,
            ];
        } catch (\PDOException $e) {
            Logger::error("Failed to poll notifications: " . $e->getMessage());
            return ['unreadCount' => 0, 'new' => []];
        }
    }

    /**
     * Delete old read notifications (older than specified days)
     */
    public function deleteOldRead(int $days = 30): int
    {
        try {
            return Notification::query()->where('is_read', 1)
                ->whereRaw('read_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days])
                ->delete();
        } catch (\PDOException $e) {
            Logger::warning("Failed to delete old notifications: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * fireTab: Protokoll zur QM-Sichtung freigegeben, Einsatzleiter
     * benachrichtigen. Der Typ `fire_protocol` kommt aus dem Manifest von
     * plugins/firetab; ohne das Plugin legt notify() nichts an.
     *
     * @param array<string, mixed> $incidentData (id, incident_number, location, leader_id, leader_name)
     */
    public function notifyFireProtocolFinalized(array $incidentData): bool
    {
        $userId = $this->userIdForLeader($incidentData['leader_id'] ?? null);
        if ($userId === null) {
            return false;
        }

        $incidentNumber = $incidentData['incident_number'] ?? 'Unbekannt';
        $location = $incidentData['location'] ?? 'Unbekannt';
        $incidentId = $incidentData['id'] ?? null;

        return $this->create(
            $userId,
            'fire_protocol',
            'Feuerwehr-Protokoll abgeschlossen',
            "Einsatzprotokoll {$incidentNumber} ({$location}) wurde zur QM-Sichtung freigegeben.",
            $incidentId ? BASE_PATH . "firetab/view?id={$incidentId}" : null,
        );
    }

    /**
     * fireTab: QM hat den Status eines Protokolls geändert.
     *
     * @param array<string, mixed> $incidentData (id, incident_number, location, leader_id, status)
     */
    public function notifyFireProtocolStatusChanged(array $incidentData, string $qmUsername): bool
    {
        $userId = $this->userIdForLeader($incidentData['leader_id'] ?? null);
        if ($userId === null) {
            return false;
        }

        $incidentNumber = $incidentData['incident_number'] ?? 'Unbekannt';
        $status = $incidentData['status'] ?? 'unbekannt';
        $incidentId = $incidentData['id'] ?? null;

        $statusLabels = [
            0 => 'Ungesehen',
            1 => 'In Prüfung',
            2 => 'Freigegeben',
            3 => 'Ungenügend',
            4 => 'Ausgeblendet',
        ];
        $statusLabel = $statusLabels[(int) $status] ?? $status;

        return $this->create(
            $userId,
            'fire_protocol',
            "Ihr Protokoll #{$incidentNumber} wurde bearbeitet",
            "Status: {$statusLabel}. Bearbeiter: {$qmUsername}",
            $incidentId ? BASE_PATH . "firetab/view?id={$incidentId}" : null,
        );
    }

    /** Konto des Einsatzleiters (Mitarbeiter-ID) über den Discord-Tag. */
    private function userIdForLeader(mixed $leaderId): ?int
    {
        if (!$leaderId) {
            return null;
        }

        try {
            $id = Capsule::table('intra_mitarbeiter as m')
                ->join('intra_users as u', 'm.discordtag', '=', 'u.discord_id')
                ->where('m.id', $leaderId)
                ->value('u.id');

            return $id === null ? null : (int) $id;
        } catch (\PDOException $e) {
            Logger::error('Failed to resolve incident leader: ' . $e->getMessage());
            return null;
        }
    }
}
