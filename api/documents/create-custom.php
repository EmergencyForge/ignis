<?php
ob_start();

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Documents\DocumentTemplateManager;
use App\Documents\DocumentRenderer;
use App\Documents\DocumentPDFGenerator;
use App\Documents\DocumentIdGenerator;
use App\Auth\Permissions;
use App\Helpers\UserHelper;
use App\Notifications\NotificationManager;
use App\Personnel\PersonalLogManager;
use App\Utils\AuditLogger;

ob_clean();

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    ob_end_flush();
    exit;
}

$userHelper = new UserHelper($pdo);

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Keine Daten empfangen');
    }

    // Validiere erforderliche Felder
    $required = ['profileid', 'template_id', 'ausstellerid', 'erhalter'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Feld '$field' ist erforderlich");
        }
    }

    // Generiere neue 12-stellige Dokument-ID
    $documentId = DocumentIdGenerator::generate($pdo);

    // Stelle sicher dass ausstellungsdatum vorhanden ist
    $ausstellungsdatum = $input['fields']['ausstellungsdatum'] ?? $input['ausstellungsdatum'] ?? date('Y-m-d');

    $manager = new DocumentTemplateManager($pdo);

    // Erstelle Dokument mit der generierten ID
    $dbId = $manager->createDocument(
        $input['template_id'],
        $input['profileid'],
        array_merge(
            [
                'erhalter' => $input['erhalter'],
                'erhalter_gebdat' => $input['erhalter_gebdat'] ?? null,
                'anrede' => $input['anrede'] ?? null,
                'ausstellungsdatum' => $ausstellungsdatum,
                'document_id' => $documentId
            ],
            $input['fields'] ?? []
        ),
        $documentId
    );

    // PDF generieren
    try {
        $renderer = new DocumentRenderer($pdo);
        $pdfGenerator = new DocumentPDFGenerator($pdo, $renderer);
        $pdfPath = $pdfGenerator->generateAndStore($dbId);
        //error_log("PDF erfolgreich generiert: $pdfPath");
    } catch (Exception $e) {
        error_log("PDF-Generierung fehlgeschlagen für Dokument-DB-ID {$dbId}: " . $e->getMessage());
    }

    // Lade Template-Infos für Log-Eintrag
    $template = $manager->getTemplate($input['template_id']);

    // Create log entry using PersonalLogManager
    $logManager = new PersonalLogManager($pdo);
    $pdfFilename = $documentId . '.pdf';
    $pdfLink = BASE_PATH . 'storage/documents/' . $pdfFilename;
    
    $logContent = "Dokument erstellt: <a href='{$pdfLink}' target='_blank'>" .
        htmlspecialchars($template['name']) .
        " (ID: {$documentId})</a>";
    $panelUser = $userHelper->getCurrentUserFullnameForAction();
    
    $logManager->addEntry(
        $input['profileid'],
        PersonalLogManager::TYPE_DOCUMENT,
        $logContent,
        $panelUser,
        [
            'change_type' => 'document_created',
            'document_id' => $documentId,
            'template_id' => $input['template_id'],
            'template_name' => $template['name']
        ]
    );

    // Logge die Aktion im Audit-Log
    try {
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            (int)($_SESSION['userid']),
            'Dokument erstellt [' . $documentId . ']',
            'Für Profil: ' . $input['profileid'],
            'Dokumente',
            0
        );
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }

    // Create notification for employee if they have a user account
    try {
        // Get employee's discord tag from profile
        $profileStmt = $pdo->prepare("SELECT discordtag FROM intra_mitarbeiter WHERE id = ?");
        $profileStmt->execute([$input['profileid']]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($profile && !empty($profile['discordtag'])) {
            $notificationManager = new NotificationManager($pdo);
            $recipientUserId = $notificationManager->getUserIdByDiscordTag($profile['discordtag']);
            
            if ($recipientUserId) {
                $notificationManager->create(
                    $recipientUserId,
                    'dokument',
                    "Neues Dokument erstellt",
                    "Ein neues Dokument ({$template['name']} #{$documentId}) wurde für Sie erstellt.",
                    BASE_PATH . "mitarbeiter/dokument-view.php?docid={$documentId}"
                );
            }
        }
    } catch (Exception $e) {
        // Log error but don't fail the document creation
        error_log("Failed to create notification for document: " . $e->getMessage());
    }

    // Clear buffer before sending JSON
    ob_clean();

    echo json_encode([
        'success' => true,
        'db_id' => $dbId,
        'document_id' => $documentId,
        'pdf_url' => $pdfLink,
        'message' => 'Dokument erfolgreich erstellt'
    ]);

    ob_end_flush();
    exit;
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    ob_end_flush();
    exit;
}
