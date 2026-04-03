<?php
/**
 * Dokument archivieren / wiederherstellen.
 * POST: { docid: string, archived: bool, csrf_token: string }
 */
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Security\CsrfProtection;
use App\Utils\AuditLogger;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    CsrfProtection::requireValid($input);

    $docid = $input['docid'] ?? '';
    $archived = (bool) ($input['archived'] ?? true);

    if (empty($docid)) {
        throw new \Exception('docid ist erforderlich');
    }

    $stmt = $pdo->prepare("
        UPDATE intra_mitarbeiter_dokumente
        SET is_archived = :archived
        WHERE docid = :docid
    ");
    $stmt->execute([
        'archived' => $archived ? 1 : 0,
        'docid' => $docid,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new \Exception('Dokument nicht gefunden');
    }

    $auditlogger = new AuditLogger($pdo);
    $action = $archived ? 'archiviert' : 'wiederhergestellt';
    $auditlogger->log(
        $_SESSION['userid'],
        "Dokument {$action} [ID: {$docid}]",
        null,
        'Mitarbeiter',
        1
    );

    echo json_encode([
        'success' => true,
        'archived' => $archived,
        'csrf_token' => CsrfProtection::getResponseToken(),
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
