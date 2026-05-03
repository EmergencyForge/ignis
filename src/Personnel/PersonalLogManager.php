<?php

namespace App\Personnel;

use PDO;

/**
 * PersonalLogManager
 * 
 * Centralized service for managing employee profile log entries.
 * Replaces hardcoded log creation with a structured, maintainable approach.
 */
class PersonalLogManager
{
    // Log entry type constants
    public const TYPE_NOTE = 0;           // General note
    public const TYPE_POSITIVE = 1;       // Positive comment
    public const TYPE_NEGATIVE = 2;       // Negative comment
    public const TYPE_RANK_CHANGE = 4;    // Rank/Dienstgrad change
    public const TYPE_MODIFICATION = 5;   // Profile modification
    public const TYPE_CREATED = 6;        // Profile created
    public const TYPE_DOCUMENT = 7;       // Document created

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Add a new log entry
     * 
     * @param int $profileId Employee profile ID
     * @param int $type Log entry type (use TYPE_* constants)
     * @param string $content Log entry content
     * @param string $panelUser User who created the entry
     * @param array|null $metadata Additional structured data (optional)
     * @return int The ID of the created log entry
     */
    public function addEntry(
        int $profileId,
        int $type,
        string $content,
        string $panelUser,
        ?array $metadata = null
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO intra_mitarbeiter_log (profilid, type, content, paneluser, metadata) 
             VALUES (:profileId, :type, :content, :panelUser, :metadata)"
        );
        
        $stmt->execute([
            'profileId' => $profileId,
            'type' => $type,
            'content' => $content,
            'panelUser' => $panelUser,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Log a rank change
     * 
     * @param int $profileId Employee profile ID
     * @param string $oldRankName Old rank name
     * @param string $newRankName New rank name
     * @param string $panelUser User who made the change
     * @return int Log entry ID
     */
    public function logRankChange(
        int $profileId,
        string $oldRankName,
        string $newRankName,
        string $panelUser
    ): int {
        $content = sprintf(
            'Dienstgrad wurde von <strong>%s</strong> auf <strong>%s</strong> geändert.',
            htmlspecialchars($oldRankName),
            htmlspecialchars($newRankName)
        );

        $metadata = [
            'change_type' => 'rank',
            'old_value' => $oldRankName,
            'new_value' => $newRankName
        ];

        return $this->addEntry($profileId, self::TYPE_RANK_CHANGE, $content, $panelUser, $metadata);
    }

    /**
     * Log a qualification change (RD or FW)
     * 
     * @param int $profileId Employee profile ID
     * @param string $qualificationType Type of qualification ('RD' or 'FW')
     * @param string $oldQualification Old qualification name
     * @param string $newQualification New qualification name
     * @param string $panelUser User who made the change
     * @return int Log entry ID
     */
    public function logQualificationChange(
        int $profileId,
        string $qualificationType,
        string $oldQualification,
        string $newQualification,
        string $panelUser
    ): int {
        $content = sprintf(
            'Qualifikation (%s) wurde von <strong>%s</strong> auf <strong>%s</strong> geändert.',
            htmlspecialchars($qualificationType),
            htmlspecialchars($oldQualification),
            htmlspecialchars($newQualification)
        );

        $metadata = [
            'change_type' => 'qualification',
            'qualification_type' => $qualificationType,
            'old_value' => $oldQualification,
            'new_value' => $newQualification
        ];

        return $this->addEntry($profileId, self::TYPE_MODIFICATION, $content, $panelUser, $metadata);
    }

    /**
     * Log a profile data modification
     * 
     * @param int $profileId Employee profile ID
     * @param string $panelUser User who made the change
     * @param array|null $changedFields Optional array of changed field names
     * @return int Log entry ID
     */
    public function logProfileModification(
        int $profileId,
        string $panelUser,
        ?array $changedFields = null
    ): int {
        $content = 'Profildaten wurden bearbeitet.';

        $metadata = [
            'change_type' => 'profile_data',
            'changed_fields' => $changedFields
        ];

        return $this->addEntry($profileId, self::TYPE_MODIFICATION, $content, $panelUser, $metadata);
    }

    /**
     * Log department/Fachdienste modification
     * 
     * @param int $profileId Employee profile ID
     * @param string $panelUser User who made the change
     * @return int Log entry ID
     */
    public function logDepartmentModification(int $profileId, string $panelUser): int
    {
        $content = 'Fachdienste wurden bearbeitet.';

        $metadata = [
            'change_type' => 'departments'
        ];

        return $this->addEntry($profileId, self::TYPE_MODIFICATION, $content, $panelUser, $metadata);
    }

    /**
     * Log document creation
     * 
     * @param int $profileId Employee profile ID
     * @param int $documentId Document ID
     * @param string $panelUser User who created the document
     * @param string|null $basePath Base path for URLs (defaults to BASE_PATH constant)
     * @return int Log entry ID
     */
    public function logDocumentCreation(
        int $profileId,
        int $documentId,
        string $panelUser,
        ?string $basePath = null
    ): int {
        $basePath = $basePath ?? (defined('BASE_PATH') ? BASE_PATH : '');
        
        $content = sprintf(
            'Ein neues Dokument (<a href="%smitarbeiter/dokument-view?docid=%d" target="_blank">#%d</a>) wurde erstellt.',
            $basePath,
            $documentId,
            $documentId
        );

        $metadata = [
            'change_type' => 'document_created',
            'document_id' => $documentId
        ];

        return $this->addEntry($profileId, self::TYPE_DOCUMENT, $content, $panelUser, $metadata);
    }

    /**
     * Log profile creation
     * 
     * @param int $profileId Employee profile ID
     * @param string $panelUser User who created the profile
     * @return int Log entry ID
     */
    public function logProfileCreation(int $profileId, string $panelUser): int
    {
        $content = 'Mitarbeiterprofil wurde angelegt.';

        $metadata = [
            'change_type' => 'profile_created'
        ];

        return $this->addEntry($profileId, self::TYPE_CREATED, $content, $panelUser, $metadata);
    }

    /**
     * Add a manual note/comment
     * 
     * @param int $profileId Employee profile ID
     * @param int $noteType Note type (TYPE_NOTE, TYPE_POSITIVE, or TYPE_NEGATIVE)
     * @param string $content Note content
     * @param string $panelUser User who created the note
     * @return int Log entry ID
     */
    public function addNote(
        int $profileId,
        int $noteType,
        string $content,
        string $panelUser
    ): int {
        if (!in_array($noteType, [self::TYPE_NOTE, self::TYPE_POSITIVE, self::TYPE_NEGATIVE])) {
            throw new \InvalidArgumentException('Invalid note type');
        }

        return $this->addEntry($profileId, $noteType, $content, $panelUser);
    }

    /**
     * Get log entries for a profile with pagination
     * 
     * @param int $profileId Employee profile ID
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @param array|null $typeFilter Optional array of types to filter by
     * @return array Array with 'entries' and 'total' keys
     */
    public function getEntries(int $profileId, int $page = 1, int $perPage = 6, ?array $typeFilter = null): array
    {
        $offset = ($page - 1) * $perPage;

        // Build WHERE clause for type filter
        $whereClause = "profilid = ?";
        $params = [$profileId];
        
        if ($typeFilter !== null && !empty($typeFilter)) {
            $placeholders = str_repeat('?,', count($typeFilter) - 1) . '?';
            $whereClause .= " AND type IN ($placeholders)";
            $params = array_merge($params, $typeFilter);
        }

        // Get total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_mitarbeiter_log WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Get entries for current page
        $stmt = $this->pdo->prepare(
            "SELECT * FROM intra_mitarbeiter_log 
             WHERE $whereClause
             ORDER BY datetime DESC 
             LIMIT ?, ?"
        );
        $stmt->execute(array_merge($params, [$offset, $perPage]));
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse metadata JSON
        foreach ($entries as &$entry) {
            if (!empty($entry['metadata'])) {
                $entry['metadata'] = json_decode($entry['metadata'], true);
            }
        }

        return [
            'entries' => $entries,
            'total' => $total
        ];
    }

    /**
     * Get comments (manual notes) for a profile with pagination
     * Comments are types 0, 1, 2 (NOTE, POSITIVE, NEGATIVE)
     * 
     * @param int $profileId Employee profile ID
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array Array with 'entries' and 'total' keys
     */
    public function getComments(int $profileId, int $page = 1, int $perPage = 6): array
    {
        return $this->getEntries($profileId, $page, $perPage, [
            self::TYPE_NOTE,
            self::TYPE_POSITIVE,
            self::TYPE_NEGATIVE
        ]);
    }

    /**
     * Get system logs (auto-generated entries) for a profile with pagination
     * System logs are types 4, 5, 6, 7 (RANK_CHANGE, MODIFICATION, CREATED, DOCUMENT)
     * 
     * @param int $profileId Employee profile ID
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array Array with 'entries' and 'total' keys
     */
    public function getSystemLogs(int $profileId, int $page = 1, int $perPage = 6): array
    {
        return $this->getEntries($profileId, $page, $perPage, [
            self::TYPE_RANK_CHANGE,
            self::TYPE_MODIFICATION,
            self::TYPE_CREATED,
            self::TYPE_DOCUMENT
        ]);
    }

    /**
     * Delete a log entry
     * 
     * @param int $logId Log entry ID
     * @return bool Success status
     */
    public function deleteEntry(int $logId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM intra_mitarbeiter_log WHERE logid = ?");
        return $stmt->execute([$logId]);
    }

    /**
     * Get the type name for a log entry type
     * 
     * @param int $type Log entry type
     * @return string Type name
     */
    public static function getTypeName(int $type): string
    {
        $types = [
            self::TYPE_NOTE => 'note',
            self::TYPE_POSITIVE => 'positive',
            self::TYPE_NEGATIVE => 'negative',
            self::TYPE_RANK_CHANGE => 'rank',
            self::TYPE_MODIFICATION => 'modify',
            self::TYPE_CREATED => 'created',
            self::TYPE_DOCUMENT => 'document'
        ];

        return $types[$type] ?? 'note';
    }
}
