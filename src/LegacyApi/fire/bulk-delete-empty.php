<?php
// Set headers first to ensure clean JSON output
header('Content-Type: application/json');

require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

// Check authentication and permissions
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht authentifiziert']);
    exit();
}

if (!Permissions::check(['admin', 'fire.incident.qm'])) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get selected fields from POST request
        $selectedFields = $_POST['fields'] ?? ['location'];
        $isPreview = isset($_POST['preview']);
        $timePeriod = $_POST['timePeriod'] ?? '30';
        $statusFilter = $_POST['statusFilter'] ?? 'all';

        // Available fields for checking
        $availableFields = [
            'incident_number' => 'Einsatznummer',
            'location' => 'Einsatzort',
            'keyword' => 'Stichwort',
            'leader_id' => 'Einsatzleiter',
            'notes' => 'Einsatzgeschehen',
            'no_vehicles' => 'Keine Fahrzeuge zugewiesen',
        ];

        // Validate selected fields
        $fieldsToCheck = array_intersect($selectedFields, array_keys($availableFields));

        if (empty($fieldsToCheck)) {
            echo json_encode([
                'success' => false,
                'message' => 'Keine gültigen Felder ausgewählt'
            ]);
            exit();
        }

        // Build SQL query to find empty protocols
        $conditions = [];
        foreach ($fieldsToCheck as $field) {
            switch ($field) {
                case 'leader_id':
                    $conditions[] = "(i.leader_id IS NULL)";
                    break;
                case 'no_vehicles':
                    $conditions[] = "(SELECT COUNT(*) FROM intra_fire_incident_vehicles v WHERE v.incident_id = i.id) = 0";
                    break;
                case 'notes':
                    $conditions[] = "(i.notes IS NULL OR i.notes = '')";
                    break;
                default:
                    // Text fields: NULL or empty
                    $conditions[] = "(i.{$field} IS NULL OR i.{$field} = '')";
                    break;
            }
        }

        $whereClause = implode(' AND ', $conditions);

        // Create label for selected fields
        $selectedFieldsLabel = implode(', ', array_map(function ($field) use ($availableFields) {
            return $availableFields[$field] ?? $field;
        }, $fieldsToCheck));

        // Build time condition
        $timeCondition = '';
        if ($timePeriod !== 'all') {
            $days = intval($timePeriod);
            $timeCondition = "AND i.created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        }

        // Build status filter condition
        $statusCondition = '';
        if ($statusFilter === 'unfinalized') {
            $statusCondition = 'AND i.finalized = 0';
        } elseif ($statusFilter === 'finalized') {
            $statusCondition = 'AND i.finalized = 1';
        }

        // If this is a preview request, return the protocols
        if ($isPreview) {
            $query = "
                SELECT i.id, i.incident_number, i.location, i.keyword, i.created_at, i.finalized,
                       m.fullname AS leader_name
                FROM intra_fire_incidents i
                LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id
                WHERE i.archived = 0
                AND ({$whereClause})
                {$timeCondition}
                {$statusCondition}
                ORDER BY i.created_at DESC
            ";

            $previewStmt = $pdo->prepare($query);
            $previewStmt->execute();
            $protocols = $previewStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'protocols' => $protocols,
                'count' => count($protocols),
                'selectedFieldsLabel' => $selectedFieldsLabel
            ]);
            exit();
        }

        // Count protocols that will be deleted
        $countQuery = "
            SELECT COUNT(*) as count
            FROM intra_fire_incidents i
            WHERE i.archived = 0
            AND ({$whereClause})
            {$timeCondition}
            {$statusCondition}
        ";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute();
        $result = $countStmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'];

        if ($count == 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Keine passenden Protokolle gefunden',
                'deleted' => 0
            ]);
            exit();
        }

        // Soft-delete by archiving and setting status to "Ausgeblendet"
        $deleteQuery = "
            UPDATE intra_fire_incidents i
            SET i.archived = 1,
                i.archived_at = NOW(),
                i.archived_by = :userId,
                i.status = 4,
                i.updated_by = :userId2,
                i.updated_at = NOW()
            WHERE i.archived = 0
            AND ({$whereClause})
            {$timeCondition}
            {$statusCondition}
        ";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $userId = $_SESSION['userid'];
        $deleteStmt->execute(['userId' => $userId, 'userId2' => $userId]);

        $affectedRows = $deleteStmt->rowCount();

        // Build time label for audit log
        $timeLabel = $timePeriod === 'all' ? 'alle' : "letzte {$timePeriod} Tage";
        $statusLabel = $statusFilter === 'unfinalized' ? ', nur unfertige' : ($statusFilter === 'finalized' ? ', nur abgeschlossene' : '');

        // Log the action in audit log
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            $_SESSION['userid'],
            "Bulk-Delete: {$affectedRows} Einsatzprotokolle gelöscht",
            "Gelöschte Protokolle mit leeren Feldern ({$selectedFieldsLabel}), Zeitraum: {$timeLabel}{$statusLabel}",
            'Feuerwehr',
            0
        );

        Flash::set('success', "Es wurden {$affectedRows} Einsatzprotokolle erfolgreich gelöscht.");

        echo json_encode([
            'success' => true,
            'message' => "{$affectedRows} Protokolle wurden gelöscht",
            'deleted' => $affectedRows
        ]);
    } catch (Exception $e) {
        error_log("Fire bulk delete error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Fehler beim Löschen: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Handle GET request - Return available fields
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $availableFields = [
            'incident_number' => 'Einsatznummer',
            'location' => 'Einsatzort',
            'keyword' => 'Stichwort',
            'leader_id' => 'Einsatzleiter',
            'notes' => 'Einsatzgeschehen',
            'no_vehicles' => 'Keine Fahrzeuge zugewiesen',
        ];

        echo json_encode([
            'success' => true,
            'fields' => $availableFields
        ]);
    } finally {
        exit();
    }
}
