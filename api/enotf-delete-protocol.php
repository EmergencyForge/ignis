<?php

/**
 * API: Einzelnes eNOTF Protokoll löschen (soft-delete)
 *
 * POST /api/enotf-delete-protocol.php
 * Body: { "enr": "..." }
 *
 * Protokolle, die von der Leitstelle (EMD-Sync) erstellt wurden (createdby = 1),
 * können nicht gelöscht werden.
 */

require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../assets/config/database.php';

header('Content-Type: application/json');

// Nur eingeloggte eNOTF-Benutzer
if (!isset($_SESSION['protfzg']) || !isset($_SESSION['fahrername'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

// Nur POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// JSON Body lesen
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['enr'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'enr fehlt']);
    exit;
}

$enr = $input['enr'];
$vehicle = $_SESSION['protfzg'];

try {
    // Prüfen ob das Protokoll existiert und zum aktuellen Fahrzeug gehört
    $stmt = $pdo->prepare("
        SELECT enr, createdby, hidden_user
        FROM intra_edivi
        WHERE enr = :enr
        AND (fzg_transp = :fzg_transp OR fzg_na = :fzg_na)
        AND hidden = 0
        AND hidden_user = 0
        AND freigegeben = 0
    ");
    $stmt->execute([
        ':enr' => $enr,
        ':fzg_transp' => $vehicle,
        ':fzg_na' => $vehicle
    ]);
    $protocol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$protocol) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Protokoll nicht gefunden oder nicht zugänglich']);
        exit;
    }

    // Prüfen ob das Protokoll von der Leitstelle erstellt wurde
    // createdby = 1 bedeutet EMD-Sync (Leitstelle), createdby = 2 bedeutet User
    // NULL oder 1 = Leitstelle (für alte Protokolle ohne createdby-Feld)
    if ($protocol['createdby'] === null || $protocol['createdby'] == 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Protokolle der Leitstelle können nicht gelöscht werden'
        ]);
        exit;
    }

    // Freigeber-Name zusammenstellen
    $freigeber_name = $_SESSION['fahrername'];
    if (!empty($_SESSION['beifahrername'])) {
        $freigeber_name .= ', ' . $_SESSION['beifahrername'];
    }

    // Soft-Delete durchführen (wie bei "alle löschen")
    $updateStmt = $pdo->prepare("
        UPDATE intra_edivi
        SET hidden_user = 1,
            freigeber_name = :freigeber_name,
            last_edit = NOW(),
            freigegeben = 1
        WHERE enr = :enr
    ");
    $updateStmt->execute([
        ':freigeber_name' => $freigeber_name,
        ':enr' => $enr
    ]);

    echo json_encode(['success' => true, 'message' => 'Protokoll erfolgreich gelöscht']);
} catch (PDOException $e) {
    error_log("Fehler beim Löschen des Protokolls: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Interner Fehler']);
}
