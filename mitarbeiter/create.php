<?php
/**
 * Employee creation API endpoint.
 * The creation UI is now a modal on list.php.
 * GET requests redirect to the employee list.
 */
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\UserHelper;
use App\Utils\AuditLogger;
use App\Personnel\PersonalLogManager;

if (!Permissions::check(['admin', 'personnel.edit'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'Keine Berechtigung']));
    }
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// GET requests: redirect to list page
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_PATH . "mitarbeiter/list.php");
    exit();
}

// POST: handle employee creation (called via AJAX from list.php modal)
header('Content-Type: application/json');

$userHelper = new UserHelper($pdo);

$stmtr = $pdo->prepare("SELECT * FROM intra_mitarbeiter_rdquali WHERE none = 1 LIMIT 1");
$stmtr->execute();
$resultr = $stmtr->fetch();

$stmtf = $pdo->prepare("SELECT * FROM intra_mitarbeiter_fwquali WHERE none = 1 LIMIT 1");
$stmtf->execute();
$resultf = $stmtf->fetch();

$response = ['success' => false, 'message' => ''];

try {
    $fullname = $_POST['fullname'] ?? '';
    $gebdatum = $_POST['gebdatum'] ?? '';
    $dienstgrad = $_POST['dienstgrad'] ?? '';
    $geschlecht = $_POST['geschlecht'] ?? '';
    $discordtag = $_POST['discordtag'] ?? '';
    $telefonnr = $_POST['telefonnr'] ?? '';
    $dienstnr = trim($_POST['dienstnr'] ?? '');
    $einstdatum = $_POST['einstdatum'] ?? '';
    $qualird = $resultr['id'];
    $qualifw = $resultf['id'];

    if (!empty($dienstnr) && !preg_match('/^(?=.*[0-9])[A-Za-z0-9\-]+$/', $dienstnr)) {
        $response['message'] = "Ungültiges Format für Dienstnummer. Muss mindestens eine Zahl enthalten (z.B. RD-001, BF01).";
        echo json_encode($response);
        exit;
    }

    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM intra_mitarbeiter WHERE dienstnr = :dienstnr");
    $checkStmt->execute(['dienstnr' => $dienstnr]);
    if ($checkStmt->fetchColumn() > 0) {
        $response['message'] = "Diese Dienstnummer ist bereits vergeben.";
        echo json_encode($response);
        exit;
    }

    /** @phpstan-ignore ternary.alwaysTrue (CHAR_ID is runtime-configured) */
    $charakterid = CHAR_ID ? ($_POST['charakterid'] ?? '') : '';

    /** @phpstan-ignore booleanAnd.leftAlwaysTrue (CHAR_ID is runtime-configured) */
    if (empty($fullname) || empty($gebdatum) || empty($dienstgrad) || (CHAR_ID && empty($charakterid))) {
        $response['message'] = "Bitte alle erforderlichen Felder ausfüllen.";
        echo json_encode($response);
        exit;
    }

    $columns = ['fullname', 'gebdatum', 'dienstgrad', 'geschlecht', 'discordtag', 'telefonnr', 'dienstnr', 'einstdatum', 'qualifw2', 'qualird'];
    $params = [
        'fullname' => $fullname, 'gebdatum' => $gebdatum, 'dienstgrad' => $dienstgrad,
        'geschlecht' => $geschlecht, 'discordtag' => $discordtag, 'telefonnr' => $telefonnr,
        'dienstnr' => $dienstnr, 'einstdatum' => $einstdatum, 'qualifw2' => $qualifw, 'qualird' => $qualird
    ];
    if (CHAR_ID) {
        $columns[] = 'charakterid';
        $params['charakterid'] = $charakterid;
    }

    $colList = implode(', ', $columns);
    $placeholders = implode(', ', array_map(fn($c) => ':' . $c, $columns));
    $stmt = $pdo->prepare("INSERT INTO intra_mitarbeiter ({$colList}) VALUES ({$placeholders})");
    $stmt->execute($params);

    $savedId = $pdo->lastInsertId();

    $edituser = $userHelper->getCurrentUserFullnameForAction();
    $logManager = new PersonalLogManager($pdo);
    $logManager->logProfileCreation($savedId, $edituser);

    $response['success'] = true;
    $response['message'] = "Mitarbeiter erfolgreich erstellt!";
    $response['redirect'] = BASE_PATH . "mitarbeiter/profile.php?id=" . $savedId . "&new_created=1";
} catch (Exception $e) {
    $response['message'] = "Fehler: " . $e->getMessage();
}

$auditlogger = new AuditLogger($pdo);
$auditlogger->log($_SESSION['userid'], 'Mitarbeiter erstellt', 'Name: ' . $fullname . ', Dienstnummer: ' . $dienstnr, 'Mitarbeiter', 1);

echo json_encode($response);
exit;
