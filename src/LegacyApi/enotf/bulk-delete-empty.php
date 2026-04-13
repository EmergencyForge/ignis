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

if (!Permissions::check(['admin', 'edivi.edit'])) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get selected fields from POST request
        $selectedFields = $_POST['fields'] ?? ['patname'];
        $isPreview = isset($_POST['preview']);
        $timePeriod = $_POST['timePeriod'] ?? '30';

        // Available fields for checking
        $availableFields = [
            'patname' => 'Patientenname',
            'patgebdat' => 'Geburtsdatum',
            'fahrzeuge' => 'Transportfahrzeug ODER Notarztfahrzeug',
            'ziel_adresse' => 'Zieladresse',
            'transp_adresse' => 'Einsatzort (Von-Adresse)'
        ];

        // Validate selected fields
        $fieldsToCheck = array_intersect($selectedFields, array_keys($availableFields));

        if (empty($fieldsToCheck)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Keine gültigen Felder ausgewählt'
            ]);
            exit();
        }

        // Build SQL query to find empty protocols
        $conditions = [];
        foreach ($fieldsToCheck as $field) {
            // Different conditions for different field types
            switch ($field) {
                case 'patgebdat':
                    // Birth date: NULL or '0000-00-00' (no empty string check for date fields)
                    $conditions[] = "({$field} IS NULL OR {$field} = '0000-00-00')";
                    break;
                case 'fahrzeuge':
                    // Vehicle fields with OR: either transport or emergency vehicle must be empty
                    $conditions[] = "((fzg_transp IS NULL OR fzg_transp = '') OR (fzg_na IS NULL OR fzg_na = ''))";
                    break;
                case 'ziel_adresse':
                case 'transp_adresse':
                    // Address fields stored as JSON: NULL, empty or '{}' or '[]'
                    $conditions[] = "({$field} IS NULL OR {$field} = '' OR {$field} = '{}' OR {$field} = '[]')";
                    break;
                default:
                    // Text fields: NULL, empty or 'Unbekannt'
                    $conditions[] = "({$field} IS NULL OR {$field} = '' OR {$field} = 'Unbekannt')";
                    break;
            }
        }

        $whereClause = implode(' AND ', $conditions);

        // Create label for selected fields
        $selectedFieldsLabel = implode(', ', array_map(function ($field) use ($availableFields) {
            return $availableFields[$field] ?? $field;
        }, $fieldsToCheck));

        // If this is a preview request, return the protocols
        if ($isPreview) {
            // Build time condition
            $timeCondition = '';
            if ($timePeriod !== 'all') {
                $days = intval($timePeriod);
                $timeCondition = "AND sendezeit > DATE_SUB(NOW(), INTERVAL {$days} DAY)";
            }

            // Matching criteria in selected time period
            $query = "
                SELECT id, enr, patname, sendezeit, pfname
                FROM intra_edivi 
                WHERE hidden <> 1 
                AND ({$whereClause})
                {$timeCondition}
                ORDER BY sendezeit DESC
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

        // Build time condition for delete operation
        $timeCondition = '';
        if ($timePeriod !== 'all') {
            $days = intval($timePeriod);
            $timeCondition = "AND sendezeit > DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        }

        // First, get the count of protocols that will be deleted
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM intra_edivi 
            WHERE hidden <> 1 
            AND ({$whereClause})
            {$timeCondition}
        ");
        $countStmt->execute();
        $result = $countStmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'];

        if ($count == 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Keine leeren Protokolle gefunden',
                'deleted' => 0
            ]);
            exit();
        }

        // Delete the empty protocols by setting hidden = 1
        $deleteStmt = $pdo->prepare("
            UPDATE intra_edivi 
            SET hidden = 1,
                protokoll_status = 4,
                bearbeiter = :bearbeiter
            WHERE hidden <> 1 
            AND ({$whereClause})
            {$timeCondition}
        ");

        $bearbeiter = $_SESSION['username'] ?? 'System';
        $deleteStmt->execute(['bearbeiter' => $bearbeiter]);

        $affectedRows = $deleteStmt->rowCount();

        // Build time label for audit log
        $timeLabel = $timePeriod === 'all' ? 'alle' : "letzte {$timePeriod} Tage";

        // Log the action in audit log
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            $_SESSION['userid'],
            "Bulk-Delete: {$affectedRows} leere Protokolle gelöscht",
            "Gelöschte Protokolle mit leeren Feldern ({$selectedFieldsLabel}), Zeitraum: {$timeLabel}",
            'eNOTF',
            0
        );

        Flash::set('success', "Es wurden {$affectedRows} leere Protokolle erfolgreich gelöscht.");

        echo json_encode([
            'success' => true,
            'message' => "{$affectedRows} Protokolle wurden gelöscht",
            'deleted' => $affectedRows
        ]);
    } catch (Exception $e) {
        error_log("Bulk delete error: " . $e->getMessage());
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
        // Available fields for checking
        $availableFields = [
            'patname' => 'Patientenname',
            'patgebdat' => 'Geburtsdatum',
            'fahrzeuge' => 'Transportfahrzeug ODER Notarztfahrzeug',
            'ziel_adresse' => 'Zieladresse',
            'transp_adresse' => 'Einsatzort (Von-Adresse)'
        ];

        echo json_encode([
            'success' => true,
            'fields' => $availableFields
        ]);
    } finally {
        exit();
    }
}
