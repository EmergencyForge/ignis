<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\UserHelper;
use App\Personnel\PersonalLogManager;

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Nicht autorisiert']));
}

if (!Permissions::check(['admin', 'personnel.edit'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Keine Berechtigung']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

$id = (int)$data['id'];
$fullname = trim($data['fullname'] ?? '');
$gebdatum = $data['gebdatum'] ?? '';
$dienstgrad = (int)($data['dienstgrad'] ?? 0);
$discordtag = trim($data['discordtag'] ?? '');
$telefonnr = trim($data['telefonnr'] ?? '');
$dienstnr = trim($data['dienstnr'] ?? '');
$qualird = (int)($data['qualird'] ?? 0);
$qualifw2 = (int)($data['qualifw2'] ?? 0);
$geschlecht = (int)($data['geschlecht'] ?? 0);
$zusatzqual = trim($data['zusatzqual'] ?? '');
$pfp = trim($data['pfp'] ?? '');
$charakterid = CHAR_ID ? trim($data['charakterid'] ?? '') : '';

// Validate required fields
if (empty($fullname) || empty($gebdatum)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Name und Geburtsdatum sind Pflichtfelder']));
}

// Validate dienstnr format
if (!empty($dienstnr) && !preg_match('/^(?=.*[0-9])[A-Za-z0-9\-]+$/', $dienstnr)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Ungültiges Format für Dienstnummer']));
}

// Validate dienstnr uniqueness
if (!empty($dienstnr)) {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM intra_mitarbeiter WHERE dienstnr = :dienstnr AND id != :id");
    $checkStmt->execute(['dienstnr' => $dienstnr, 'id' => $id]);
    if ($checkStmt->fetchColumn() > 0) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Diese Dienstnummer ist bereits vergeben']));
    }
}

try {
    // Fetch current data
    $stmt = $pdo->prepare("SELECT * FROM intra_mitarbeiter WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Mitarbeiter nicht gefunden']));
    }

    $userHelper = new UserHelper($pdo);
    $edituser = $userHelper->getCurrentUserFullnameForAction();
    $logManager = new PersonalLogManager($pdo);
    $changes = [];

    // Handle rank change with logging
    if ((int)$current['dienstgrad'] !== $dienstgrad) {
        $pdo->prepare("UPDATE intra_mitarbeiter SET dienstgrad = :dg WHERE id = :id")
            ->execute(['dg' => $dienstgrad, 'id' => $id]);

        $oldDg = $pdo->prepare("SELECT name FROM intra_mitarbeiter_dienstgrade WHERE id = ?");
        $oldDg->execute([(int)$current['dienstgrad']]);
        $newDg = $pdo->prepare("SELECT name FROM intra_mitarbeiter_dienstgrade WHERE id = ?");
        $newDg->execute([$dienstgrad]);
        $logManager->logRankChange($id, $oldDg->fetchColumn(), $newDg->fetchColumn(), $edituser);
        $changes[] = 'dienstgrad';
    }

    // Handle RD qualification change with logging
    if ((int)$current['qualird'] !== $qualird) {
        $pdo->prepare("UPDATE intra_mitarbeiter SET qualird = :q WHERE id = :id")
            ->execute(['q' => $qualird, 'id' => $id]);

        $oldQ = $pdo->prepare("SELECT name FROM intra_mitarbeiter_rdquali WHERE id = ?");
        $oldQ->execute([(int)$current['qualird']]);
        $newQ = $pdo->prepare("SELECT name FROM intra_mitarbeiter_rdquali WHERE id = ?");
        $newQ->execute([$qualird]);
        $logManager->logQualificationChange($id, 'RD', $oldQ->fetchColumn(), $newQ->fetchColumn(), $edituser);
        $changes[] = 'qualird';
    }

    // Handle FW qualification change with logging
    if ((int)$current['qualifw2'] !== $qualifw2) {
        $pdo->prepare("UPDATE intra_mitarbeiter SET qualifw2 = :q WHERE id = :id")
            ->execute(['q' => $qualifw2, 'id' => $id]);

        $oldQ = $pdo->prepare("SELECT name FROM intra_mitarbeiter_fwquali WHERE id = ?");
        $oldQ->execute([(int)$current['qualifw2']]);
        $newQ = $pdo->prepare("SELECT name FROM intra_mitarbeiter_fwquali WHERE id = ?");
        $newQ->execute([$qualifw2]);
        $logManager->logQualificationChange($id, 'FW', $oldQ->fetchColumn(), $newQ->fetchColumn(), $edituser);
        $changes[] = 'qualifw2';
    }

    // Handle base data changes
    if (empty($pfp)) {
        $pfp = '/assets/img/empty_user.png';
    }

    $baseDataChanged = (
        $current['fullname'] !== $fullname ||
        $current['gebdatum'] !== $gebdatum ||
        $current['discordtag'] !== $discordtag ||
        $current['telefonnr'] !== $telefonnr ||
        $current['dienstnr'] !== $dienstnr ||
        (int)$current['geschlecht'] !== $geschlecht ||
        ($current['zusatz'] ?? '') !== $zusatzqual ||
        ($current['pfp'] ?? '') !== $pfp ||
        (CHAR_ID && ($current['charakterid'] ?? '') !== $charakterid)
    );

    if ($baseDataChanged) {
        $setClauses = ['fullname = :fullname', 'gebdatum = :gebdatum', 'discordtag = :discordtag',
            'telefonnr = :telefonnr', 'dienstnr = :dienstnr', 'geschlecht = :geschlecht',
            'zusatz = :zusatzqual', 'pfp = :pfp'];
        $params = [
            'fullname' => $fullname, 'gebdatum' => $gebdatum, 'discordtag' => $discordtag,
            'telefonnr' => $telefonnr, 'dienstnr' => $dienstnr, 'geschlecht' => $geschlecht,
            'zusatzqual' => $zusatzqual, 'pfp' => $pfp, 'id' => $id
        ];
        if (CHAR_ID) {
            $setClauses[] = 'charakterid = :charakterid';
            $params['charakterid'] = $charakterid;
        }

        $setStr = implode(', ', $setClauses);
        $pdo->prepare("UPDATE intra_mitarbeiter SET {$setStr} WHERE id = :id")->execute($params);
        $logManager->logProfileModification($id, $edituser);
        $changes[] = 'basedata';
    }

    // Fetch updated data to return
    $stmt = $pdo->prepare("SELECT m.*,
        dg.name as dg_name, dg.name_m as dg_name_m, dg.name_w as dg_name_w, dg.badge as dg_badge,
        rd.name as rd_name, rd.name_m as rd_name_m, rd.name_w as rd_name_w, rd.none as rd_none,
        fw.shortname as fw_shortname, fw.none as fw_none
        FROM intra_mitarbeiter m
        LEFT JOIN intra_mitarbeiter_dienstgrade dg ON m.dienstgrad = dg.id
        LEFT JOIN intra_mitarbeiter_rdquali rd ON m.qualird = rd.id
        LEFT JOIN intra_mitarbeiter_fwquali fw ON m.qualifw2 = fw.id
        WHERE m.id = :id");
    $stmt->execute(['id' => $id]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    // Build display values based on gender
    $g = (int)$updated['geschlecht'];
    $dgText = $g === 0 ? $updated['dg_name_m'] : ($g === 1 ? $updated['dg_name_w'] : $updated['dg_name']);
    $rdText = $g === 0 ? $updated['rd_name_m'] : ($g === 1 ? $updated['rd_name_w'] : $updated['rd_name']);
    $geschlechtText = $g === 0 ? 'Herr' : ($g === 1 ? 'Frau' : 'Divers');

    echo json_encode([
        'success' => true,
        'message' => empty($changes) ? 'Keine Änderungen' : 'Profil gespeichert',
        'changes' => $changes,
        'display' => [
            'fullname' => $updated['fullname'],
            'gebdatum' => (new DateTime($updated['gebdatum']))->format('d.m.Y'),
            'discordtag' => $updated['discordtag'] ?? 'N. hinterlegt',
            'telefonnr' => $updated['telefonnr'],
            'dienstnr' => $updated['dienstnr'],
            'geschlechtText' => $geschlechtText,
            'zusatz' => $updated['zusatz'] ?? 'Keine',
            'einstdatum' => (new DateTime($updated['einstdatum']))->format('d.m.Y'),
            'charakterid' => $updated['charakterid'] ?? '',
            'dgText' => $dgText,
            'dgBadge' => $updated['dg_badge'] ?? '',
            'rdText' => $rdText,
            'rdNone' => (bool)$updated['rd_none'],
            'fwShortname' => $updated['fw_shortname'] ?? '',
            'fwNone' => (bool)$updated['fw_none'],
            'pfp' => !empty($updated['pfp']) ? $updated['pfp'] : BASE_PATH . 'assets/img/empty_user.png',
            'profileName' => $geschlechtText . ' ' . $updated['fullname'],
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Serverfehler: ' . $e->getMessage()]);
}
