<?php

namespace App\Notifications;

use PDO;

class NotificationManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

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
            error_log("Invalid notification type: {$type}");
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO intra_notifications (user_id, type, title, message, link)
                VALUES (?, ?, ?, ?, ?)
            ");

            return $stmt->execute([$userId, $type, $title, $message, $link]);
        } catch (\PDOException $e) {
            error_log("Failed to create notification: " . $e->getMessage());
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
            $stmt = $this->pdo->prepare("SELECT id FROM intra_users WHERE discord_id = ?");
            $stmt->execute([$discordTag]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['id'] : null;
        } catch (\PDOException $e) {
            error_log("Failed to get user ID: " . $e->getMessage());
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
            $stmt = $this->pdo->prepare("
                SELECT u.id 
                FROM intra_mitarbeiter m 
                JOIN intra_users u ON m.discordtag = u.discord_id 
                WHERE m.fullname = ?
            ");
            $stmt->execute([$fullname]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return (int)$result['id'];
            }

            // Fallback to intra_users fullname
            $stmt = $this->pdo->prepare("SELECT id FROM intra_users WHERE fullname = ?");
            $stmt->execute([$fullname]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['id'] : null;
        } catch (\PDOException $e) {
            error_log("Failed to get user ID by fullname: " . $e->getMessage());
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
    public function getUnread(int $userId, int $limit = 50): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM intra_notifications
                WHERE user_id = ? AND is_read = 0
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Failed to get unread notifications: " . $e->getMessage());
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
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count FROM intra_notifications
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (\PDOException $e) {
            error_log("Failed to get unread count: " . $e->getMessage());
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
    public function getAll(int $userId, int $limit = 50, int $offset = 0): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM intra_notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Failed to get notifications: " . $e->getMessage());
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
            $stmt = $this->pdo->prepare("
                UPDATE intra_notifications
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notificationId, $userId]);
        } catch (\PDOException $e) {
            error_log("Failed to mark notification as read: " . $e->getMessage());
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
            $stmt = $this->pdo->prepare("
                UPDATE intra_notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND is_read = 0
            ");
            return $stmt->execute([$userId]);
        } catch (\PDOException $e) {
            error_log("Failed to mark all as read: " . $e->getMessage());
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
            $stmt = $this->pdo->prepare("
                DELETE FROM intra_notifications
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notificationId, $userId]);
        } catch (\PDOException $e) {
            error_log("Failed to delete notification: " . $e->getMessage());
            return false;
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
            $stmt = $this->pdo->prepare("
                DELETE FROM intra_notifications
                WHERE is_read = 1 AND read_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log("Failed to delete old notifications: " . $e->getMessage());
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
            $stmt = $this->pdo->prepare("
                SELECT u.id 
                FROM intra_mitarbeiter m 
                JOIN intra_users u ON m.discordtag = u.discord_id 
                WHERE m.id = ?
            ");
            $stmt->execute([$leaderId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return false;
            }

            $userId = (int)$result['id'];
            $incidentNumber = $incidentData['incident_number'] ?? 'Unbekannt';
            $location = $incidentData['location'] ?? 'Unbekannt';
            $incidentId = $incidentData['id'] ?? null;

            $title = "Feuerwehr-Protokoll abgeschlossen";
            $message = "Einsatzprotokoll {$incidentNumber} ({$location}) wurde zur QM-Sichtung freigegeben.";
            $link = $incidentId ? BASE_PATH . "einsatz/view.php?id={$incidentId}" : null;

            return $this->create($userId, 'fire_protocol', $title, $message, $link);
        } catch (\PDOException $e) {
            error_log("Failed to create fire protocol finalized notification: " . $e->getMessage());
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
            $stmt = $this->pdo->prepare("
                SELECT u.id 
                FROM intra_mitarbeiter m 
                JOIN intra_users u ON m.discordtag = u.discord_id 
                WHERE m.id = ?
            ");
            $stmt->execute([$leaderId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return false;
            }

            $userId = (int)$result['id'];
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
            $link = $incidentId ? BASE_PATH . "einsatz/view.php?id={$incidentId}" : null;

            return $this->create($userId, 'fire_protocol', $title, $message, $link);
        } catch (\PDOException $e) {
            error_log("Failed to create fire protocol status change notification: " . $e->getMessage());
            return false;
        }
    }
}
