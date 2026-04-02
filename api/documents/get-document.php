<?php
/**
 * Gibt Metadaten eines erstellten Dokuments zurueck.
 * GET: ?docid=XXXX-XXXX-XXXX
 */
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

try {
    $docid = $_GET['docid'] ?? '';
    if (empty($docid)) {
        throw new \Exception('docid ist erforderlich');
    }

    $stmt = $pdo->prepare("
        SELECT
            pd.id,
            pd.docid,
            pd.type,
            pd.anrede,
            pd.erhalter,
            pd.ausstellungsdatum,
            pd.ausstellerid,
            pd.aussteller_name,
            pd.profileid,
            pd.pdf_path,
            pd.template_id,
            pd.custom_data,
            pd.timestamp,
            IFNULL(pd.is_archived, 0) as is_archived,
            t.name as template_name,
            t.category as template_category,
            t.editor_type,
            dk.name as category_name,
            dk.color as category_color,
            dk.icon as category_icon,
            COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name,
            emp.fullname as empfaenger_fullname
        FROM intra_mitarbeiter_dokumente pd
        LEFT JOIN intra_dokument_templates t ON pd.template_id = t.id
        LEFT JOIN intra_dokument_kategorien dk ON t.category_id = dk.id
        LEFT JOIN intra_users u ON pd.ausstellerid = u.discord_id
        LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag
        LEFT JOIN intra_mitarbeiter emp ON pd.profileid = emp.id
        WHERE pd.docid = :docid
    ");
    $stmt->execute(['docid' => $docid]);
    $doc = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$doc) {
        throw new \Exception('Dokument nicht gefunden');
    }

    // Berechtigungspruefung: Admin, Personalverwaltung, oder eigenes Dokument
    $isOwnDoc = ($doc['ausstellerid'] == ($_SESSION['discord_id'] ?? ''));
    if (!$isOwnDoc && !Permissions::check(['admin', 'personnel.documents.manage', 'personnel.documents.view'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }

    // Dokumenttyp-Label
    $typLabel = \App\Documents\DocumentTemplateManager::getDocumentTypeLabel(
        (int) $doc['type'],
        $doc['template_name'] ?? null
    );

    // PDF-URL
    $pdfUrl = BASE_PATH . 'storage/documents/' . $doc['docid'] . '.pdf';
    $pdfExists = file_exists(__DIR__ . '/../../storage/documents/' . basename($doc['docid']) . '.pdf');

    echo json_encode([
        'success' => true,
        'document' => [
            'id' => (int) $doc['id'],
            'docid' => $doc['docid'],
            'type' => (int) $doc['type'],
            'type_label' => $typLabel,
            'erhalter' => $doc['erhalter'],
            'empfaenger_fullname' => $doc['empfaenger_fullname'],
            'ersteller_name' => $doc['ersteller_name'],
            'ausstellungsdatum' => $doc['ausstellungsdatum'],
            'ausstellungsdatum_formatted' => $doc['ausstellungsdatum']
                ? date('d.m.Y', strtotime($doc['ausstellungsdatum']))
                : '',
            'timestamp' => $doc['timestamp'],
            'template_name' => $doc['template_name'],
            'category_name' => $doc['category_name'],
            'category_color' => $doc['category_color'],
            'is_archived' => (bool) $doc['is_archived'],
            'pdf_url' => $pdfUrl,
            'pdf_exists' => $pdfExists,
            'profileid' => (int) $doc['profileid'],
        ],
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
