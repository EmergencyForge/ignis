<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Utils\AuditLogger;

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht authentifiziert']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// GET: Alle Vorlagen laden
if ($action === 'list') {
    if (!Permissions::check(['admin', 'vehicles.view'])) {
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
        exit();
    }

    try {
        $stmt = $pdo->query("SELECT t.*, u.username AS created_by_name FROM intra_fahrzeuge_tz_templates t LEFT JOIN intra_users u ON t.created_by = u.id ORDER BY t.name ASC");
        echo json_encode(['success' => true, 'templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Schreibaktionen brauchen vehicles.manage
if (!Permissions::check(['admin', 'vehicles.manage'])) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit();
}

// POST: Vorlage speichern (neu oder update)
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name ist erforderlich']);
        exit();
    }

    $fields = [
        'grundzeichen' => trim($_POST['grundzeichen'] ?? '') ?: null,
        'organisation' => trim($_POST['organisation'] ?? '') ?: null,
        'fachaufgabe' => trim($_POST['fachaufgabe'] ?? '') ?: null,
        'einheit' => trim($_POST['einheit'] ?? '') ?: null,
        'symbol' => trim($_POST['symbol'] ?? '') ?: null,
        'typ' => trim($_POST['typ'] ?? '') ?: null,
        'text' => trim($_POST['text'] ?? '') ?: null,
    ];

    try {
        // Prüfen ob Name schon existiert
        $existStmt = $pdo->prepare("SELECT id FROM intra_fahrzeuge_tz_templates WHERE name = ?");
        $existStmt->execute([$name]);
        $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE intra_fahrzeuge_tz_templates
                SET grundzeichen = :grundzeichen, organisation = :organisation, fachaufgabe = :fachaufgabe,
                    einheit = :einheit, symbol = :symbol, typ = :typ, text = :text
                WHERE id = :id
            ");
            $stmt->execute(array_merge($fields, [':id' => $existing['id']]));
            echo json_encode(['success' => true, 'message' => "Vorlage '{$name}' aktualisiert", 'id' => $existing['id']]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO intra_fahrzeuge_tz_templates (name, grundzeichen, organisation, fachaufgabe, einheit, symbol, typ, text, created_by)
                VALUES (:name, :grundzeichen, :organisation, :fachaufgabe, :einheit, :symbol, :typ, :text, :created_by)
            ");
            $stmt->execute(array_merge([':name' => $name], $fields, [':created_by' => $_SESSION['userid']]));
            $newId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => "Vorlage '{$name}' gespeichert", 'id' => $newId]);
        }

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], "TZ-Vorlage gespeichert: {$name}", null, 'Fahrzeuge', 1);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit();
}

// POST: Vorlage löschen
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
        exit();
    }

    try {
        $nameStmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge_tz_templates WHERE id = ?");
        $nameStmt->execute([$id]);
        $tpl = $nameStmt->fetch(PDO::FETCH_ASSOC);

        $pdo->prepare("DELETE FROM intra_fahrzeuge_tz_templates WHERE id = ?")->execute([$id]);

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], "TZ-Vorlage gelöscht: " . ($tpl['name'] ?? $id), null, 'Fahrzeuge', 1);

        echo json_encode(['success' => true, 'message' => 'Vorlage gelöscht']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// POST: Vorlage auf alle Fahrzeuge eines Typs anwenden
if ($action === 'apply_to_type' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateId = (int)($_POST['template_id'] ?? 0);
    $vehType = trim($_POST['veh_type'] ?? '');

    if ($templateId <= 0 || empty($vehType)) {
        echo json_encode(['success' => false, 'message' => 'Template-ID und Fahrzeugtyp erforderlich']);
        exit();
    }

    try {
        $tplStmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge_tz_templates WHERE id = ?");
        $tplStmt->execute([$templateId]);
        $tpl = $tplStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tpl) {
            echo json_encode(['success' => false, 'message' => 'Vorlage nicht gefunden']);
            exit();
        }

        // tz_name wird NICHT überschrieben (bleibt individuell pro Fahrzeug)
        $stmt = $pdo->prepare("
            UPDATE intra_fahrzeuge
            SET grundzeichen = :grundzeichen, organisation = :organisation, fachaufgabe = :fachaufgabe,
                einheit = :einheit, symbol = :symbol, typ = :typ, text = :text
            WHERE veh_type = :veh_type
        ");
        $stmt->execute([
            ':grundzeichen' => $tpl['grundzeichen'],
            ':organisation' => $tpl['organisation'],
            ':fachaufgabe' => $tpl['fachaufgabe'],
            ':einheit' => $tpl['einheit'],
            ':symbol' => $tpl['symbol'],
            ':typ' => $tpl['typ'],
            ':text' => $tpl['text'],
            ':veh_type' => $vehType
        ]);

        $affected = $stmt->rowCount();

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], "TZ-Vorlage '{$tpl['name']}' auf {$affected} Fahrzeuge vom Typ '{$vehType}' angewendet", null, 'Fahrzeuge', 1);

        echo json_encode(['success' => true, 'message' => "Vorlage auf {$affected} Fahrzeug(e) angewendet", 'affected' => $affected]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
