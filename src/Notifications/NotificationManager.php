<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;

class NotificationManager
{
    /**
     * Create a new notification for a user
     *
     * @param int $userId User ID to notify
     * @param string $type Type of notification (antrag, protokoll, dokument, system, fire_protocol)
     * @param string $title Notification title
     * @param string|null $message Optional notification message
     * @param string|null $link Optional link to related item
     * @return bool Success status
     */
    public function create(int $userId, string $type, string $title, ?string $message = null, ?string $link = null): bool
    {
        // Validate notification type
        $validTypes = ['antrag', 'protokoll', 'dokument', 'system', 'fire_protocol'];
        if (!in_array($type, $validTypes)) {
            \App\Logging\Logger::warning("Invalid notification type: {$type}");
            return false;
        }

        try {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
            ]);

            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to create notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user ID by discord tag
     *
     * @param string $discordTag Discord tag
     * @return int|null User ID or null if not found
     */
    public function getUserIdByDiscordTag(string $discordTag): ?int
    {
        try {
            $id = User::where('discord_id', $discordTag)->value('id');
            return $id !== null ? (int) $id : null;
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to get user ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user ID by full name
     * Searches in Mitarbeiter profile first, then falls back to intra_users
     *
     * @param string $fullname Full name of user
     * @return int|null User ID or null if not found
     */
    public function getUserIdByFullname(string $fullname): ?int
    {
        try {
            // First try to find by Mitarbeiter fullname
            $id = Capsule::table('intra_mitarbeiter as m')
                ->join('intra_users as u', 'm.discordtag', '=', 'u.discord_id')
                ->where('m.fullname', $fullname)
                ->value('u.id');

            if ($id !== null) {
                return (int) $id;
            }

            // Fallback to intra_users fullname
            $id = User::where('fullname', $fullname)->value('id');
            return $id !== null ? (int) $id : null;
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to get user ID by fullname: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get unread notifications for a user
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications to retrieve
     * @return array Array of notifications
     */
    public function getUnread(int $userId, int $limit = 50, ?string $type = null, int $offset = 0): array
    {
        try {
            $query = Notification::where('user_id', $userId)->where('is_read', 0);
            if ($type) {
                $query->where('type', $type);
            }

            return $query
                ->orderByDesc('created_at')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(fn (Notification $n) => $n->getAttributes())
                ->all();
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to get unread notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get unread notification count for a user
     *
     * @param int $userId User ID
     * @return int Count of unread notifications
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            return Notification::where('user_id', $userId)->where('is_read', 0)->count();
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to get unread count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all notifications for a user (read and unread)
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of notifications
     */
    public function getAll(int $userId, int $limit = 50, int $offset = 0, ?string $type = null): array
    {
        try {
            $query = Notification::where('user_id', $userId);
            if ($type) {
                $query->where('type', $type);
            }

            return $query
                ->orderByDesc('created_at')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(fn (Notification $n) => $n->getAttributes())
                ->all();
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to get notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark a notification as read
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for security check)
     * @return bool Success status
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->update([
                    'is_read' => 1,
                    'read_at' => Capsule::raw('NOW()'),
                ]);
            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to mark notification as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for a user
     *
     * @param int $userId User ID
     * @return bool Success status
     */
    public function markAllAsRead(int $userId): bool
    {
        try {
            Notification::where('user_id', $userId)
                ->where('is_read', 0)
                ->update([
                    'is_read' => 1,
                    'read_at' => Capsule::raw('NOW()'),
                ]);
            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to mark all as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a notification
     *
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for security check)
     * @return bool Success status
     */
    public function delete(int $notificationId, int $userId): bool
    {
        try {
            Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->delete();
            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to delete notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get new notifications since a given timestamp
     * Used for polling/real-time updates
     *
     * @param int $userId User ID
     * @param string $since ISO 8601 timestamp
     * @return array Array with unreadCount and new notifications
     */
    public function getNewSince(int $userId, string $since): array
    {
        try {
            $unreadCount = Notification::where('user_id', $userId)->where('is_read', 0)->count();

            $newNotifications = Notification::where('user_id', $userId)
                ->where('created_at', '>', $since)
                ->where('is_read', 0)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'type', 'title', 'message', 'link', 'created_at'])
                ->map(fn (Notification $n) => $n->getAttributes())
                ->all();

            return [
                'unreadCount' => $unreadCount,
                'new' => $newNotifications
            ];
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to poll notifications: " . $e->getMessage());
            return ['unreadCount' => 0, 'new' => []];
        }
    }

    /**
     * Delete old read notifications (older than specified days)
     *
     * @param int $days Number of days to keep notifications
     * @return int Number of deleted notifications
     */
    public function deleteOldRead(int $days = 30): int
    {
        try {
            return Notification::where('is_read', 1)
                ->whereRaw('read_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days])
                ->delete();
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to delete old notifications: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create notification for fireTab protocol finalization
     * Notifies the incident leader that their protocol has been finalized
     *
     * @param array $incidentData Incident data (id, incident_number, location, leader_id, leader_name)
     * @return bool Success status
     */
    public function notifyFireProtocolFinalized(array $incidentData): bool
    {
        $leaderId = $incidentData['leader_id'] ?? null;
        if (!$leaderId) {
            return false;
        }

        // Get user_id from leader_id (mitarbeiter id)
        try {
            $id = Capsule::table('intra_mitarbeiter as m')
                ->join('intra_users as u', 'm.discordtag', '=', 'u.discord_id')
                ->where('m.id', $leaderId)
                ->value('u.id');

            if ($id === null) {
                return false;
            }

            $userId = (int) $id;
            $incidentNumber = $incidentData['incident_number'] ?? 'Unbekannt';
            $location = $incidentData['location'] ?? 'Unbekannt';
            $incidentId = $incidentData['id'] ?? null;

            $title = "Feuerwehr-Protokoll abgeschlossen";
            $message = "Einsatzprotokoll {$incidentNumber} ({$location}) wurde zur QM-Sichtung freigegeben.";
            $link = $incidentId ? BASE_PATH . "firetab/view?id={$incidentId}" : null;

            return $this->create($userId, 'fire_protocol', $title, $message, $link);
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to create fire protocol finalized notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create notification for fireTab protocol status change
     * Notifies the incident leader when QM changes the protocol status
     *
     * @param array $incidentData Incident data (id, incident_number, location, leader_id, status)
     * @param string $qmUsername Name of QM user who changed the status
     * @return bool Success status
     */
    public function notifyFireProtocolStatusChanged(array $incidentData, string $qmUsername): bool
    {
        $leaderId = $incidentData['leader_id'] ?? null;
        if (!$leaderId) {
            return false;
        }

        // Get user_id from leader_id (mitarbeiter id)
        try {
            $id = Capsule::table('intra_mitarbeiter as m')
                ->join('intra_users as u', 'm.discordtag', '=', 'u.discord_id')
                ->where('m.id', $leaderId)
                ->value('u.id');

            if ($id === null) {
                return false;
            }

            $userId = (int) $id;
            $incidentNumber = $incidentData['incident_number'] ?? 'Unbekannt';
            $location = $incidentData['location'] ?? 'Unbekannt';
            $status = $incidentData['status'] ?? 'unbekannt';
            $incidentId = $incidentData['id'] ?? null;

            $statusLabels = [
                0 => 'Ungesehen',
                1 => 'In Prüfung',
                2 => 'Freigegeben',
                3 => 'Ungenügend',
                4 => 'Ausgeblendet'
            ];
            $statusLabel = $statusLabels[(int)$status] ?? $status;

            $title = "Ihr Protokoll #{$incidentNumber} wurde bearbeitet";
            $message = "Status: {$statusLabel}. Bearbeiter: {$qmUsername}";
            $link = $incidentId ? BASE_PATH . "firetab/view?id={$incidentId}" : null;

            return $this->create($userId, 'fire_protocol', $title, $message, $link);
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to create fire protocol status change notification: " . $e->getMessage());
            return false;
        }
    }
}
