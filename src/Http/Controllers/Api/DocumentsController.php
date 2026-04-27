<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Documents\DocumentIdGenerator;
use App\Documents\DocumentPDFGenerator;
use App\Documents\DocumentRenderer;
use App\Documents\DocumentTemplateManager;
use App\Documents\TemplateAssetManager;
use App\Documents\TemplateLayoutManager;
use App\Documents\TwigToCanvasConverter;
use App\Documents\VisualTemplateRenderer;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Notifications\NotificationManager;
use App\Personnel\PersonalLogManager;
use App\Security\CsrfProtection;
use App\Utils\AuditLogger;
use PDO;
use PDOException;

/**
 * Dokumenten-Template-API — Admin-UI für Template-Verwaltung, Assets,
 * Layouts, PDF-Generierung und Kategorien.
 *
 * Die meisten Methoden erfordern `admin` oder `personnel.documents.manage`.
 * Einzelne Read-Methoden (`listTemplates`, `listCategories`) sind für
 * eingeloggte User ohne Spezial-Permission erreichbar.
 */
final class DocumentsController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Templates ─────────────────────────────────────────────────────

    /**
     * GET /api/documents/list?category=...
     */
    public function listTemplates(Request $request): Response
    {
        try {
            $manager  = new DocumentTemplateManager($this->pdo);
            $category = $request->query['category'] ?? null;
            return Response::json($manager->listTemplates($category) ?: [])->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        } catch (\Throwable $e) {
            Logger::error('Documents: list Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Interner Fehler'], 500);
        }
    }

    /**
     * GET /api/documents/get?id=N  — Template-Details inkl. Felder
     */
    public function getTemplate(Request $request): Response
    {
        try {
            $id = (int) ($request->query['id'] ?? 0);
            if ($id <= 0) {
                throw new \Exception('Template-ID fehlt');
            }

            $manager  = new DocumentTemplateManager($this->pdo);
            $template = $manager->getTemplate($id);
            if (!$template) {
                throw new \Exception('Template nicht gefunden');
            }

            // DB-backed Feld-Optionen auflösen (Dienstgrade, RD-Qualis)
            foreach ($template['fields'] as &$field) {
                if ($field['field_type'] === 'db_dg') {
                    $stmt = $this->pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_dienstgrade WHERE archive = 0 ORDER BY priority ASC");
                    $field['field_options'] = array_map(
                        fn ($item) => [
                            'value'   => $item['id'],
                            'label'   => $item['name'],
                            'label_m' => $item['name_m'],
                            'label_w' => $item['name_w'],
                        ],
                        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                    );
                }
                if ($field['field_type'] === 'db_rdq') {
                    $stmt = $this->pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_rdquali WHERE trainable = 1 AND none = 0 ORDER BY priority ASC");
                    $field['field_options'] = array_map(
                        fn ($item) => [
                            'value'   => $item['id'],
                            'label'   => $item['name'],
                            'label_m' => $item['name_m'],
                            'label_w' => $item['name_w'],
                        ],
                        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                    );
                }
            }
            unset($field);

            return Response::json($template);
        } catch (\Throwable $e) {
            Logger::error('Documents: get Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Interner Fehler'], 404);
        }
    }

    /**
     * POST /api/documents/save — Template erstellen/aktualisieren inkl. Felder und Template-Datei
     */
    public function saveTemplate(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung']);
        }

        try {
            $input = $request->json();
            if (!is_array($input)) {
                throw new \Exception('Keine Daten empfangen');
            }

            $manager       = new DocumentTemplateManager($this->pdo);
            $isUpdate      = isset($input['id']) && $input['id'];
            $fieldsChanged = false;

            if ($isUpdate) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_dokument_template_fields WHERE template_id = ?");
                $stmt->execute([$input['id']]);
                $oldFieldCount = (int) $stmt->fetchColumn();
                $newFieldCount = count($input['fields'] ?? []);
                $fieldsChanged = ($newFieldCount !== $oldFieldCount);

                $manager->updateTemplate($input['id'], [
                    'name'          => $input['name'],
                    'category'      => $input['category']      ?? null,
                    'category_id'   => $input['category_id']   ?? null,
                    'description'   => $input['description']   ?? null,
                    'template_file' => $input['template_file'] ?? null,
                    'editor_type'   => $input['editor_type']   ?? 'visual',
                ]);

                $this->pdo->prepare("DELETE FROM intra_dokument_template_fields WHERE template_id = :id")
                    ->execute(['id' => $input['id']]);

                $templateId = $input['id'];
            } else {
                $templateFile = $input['template_file']
                    ?? strtolower(str_replace(' ', '_', (string) $input['name'])) . '.html.twig';

                $templateId = $manager->createTemplate([
                    'name'          => $input['name'],
                    'category'      => $input['category']    ?? null,
                    'category_id'   => $input['category_id'] ?? null,
                    'description'   => $input['description'] ?? null,
                    'template_file' => $templateFile,
                    'editor_type'   => $input['editor_type'] ?? 'visual',
                ]);
            }

            foreach (($input['fields'] ?? []) as $field) {
                $field['is_required'] = !empty($field['is_required']) ? 1 : 0;
                $manager->addField($templateId, $field);
            }

            // Config mit Gender-spezifischen Labels
            $config = $this->buildTemplateConfig($input['fields'] ?? []);
            $this->pdo->prepare("UPDATE intra_dokument_templates SET config = ? WHERE id = ?")
                ->execute([json_encode($config), $templateId]);

            // Template-Datei erstellen (nur wenn sie noch nicht existiert)
            $templateFile    = $input['template_file']
                ?? strtolower(str_replace(' ', '_', (string) $input['name'])) . '.html.twig';
            $templateCreated = $this->createTemplateFile($input);

            // Flash-Nachrichten setzen
            if ($templateCreated) {
                Flash::success('Template erfolgreich erstellt');
            } elseif ($fieldsChanged) {
                Flash::warning(
                    'Template wurde aktualisiert, aber die Felder wurden geändert.<br><br>'
                    . '<strong>Wichtig:</strong> Die Template-Datei <code>' . htmlspecialchars($templateFile) . '</code> wurde nicht automatisch aktualisiert.<br><br>'
                    . '<strong>Optionen:</strong><br>'
                    . '<ul style="margin: 8px 0; padding-left: 20px;">'
                    . '<li>Löschen Sie die Datei <code>dokumente/templates/' . htmlspecialchars($templateFile) . '</code> und speichern Sie erneut, um sie automatisch zu generieren</li>'
                    . '<li>Oder passen Sie die bestehende Datei manuell an die neuen Felder an</li>'
                    . '</ul>',
                    'Template aktualisiert - Aktion erforderlich!'
                );
            } else {
                Flash::success('Template erfolgreich aktualisiert');
            }

            return Response::json([
                'success'          => true,
                'id'               => $templateId,
                'template_created' => $templateCreated,
                'fields_changed'   => $fieldsChanged && !$templateCreated,
            ]);
        } catch (\Throwable $e) {
            if (class_exists(Flash::class)) {
                Flash::error($e->getMessage());
            }
            Logger::error('Documents: save Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler']);
        }
    }

    /**
     * POST|DELETE /api/documents/delete?id=N — Template inkl. Felder löschen
     */
    public function deleteTemplate(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung'], 403);
        }

        try {
            $id = $request->query['id'] ?? null;
            if (!$id) {
                throw new \Exception('Template-ID fehlt');
            }

            $this->pdo->prepare("DELETE FROM intra_dokument_template_fields WHERE template_id = :id")
                ->execute(['id' => $id]);

            $manager = new DocumentTemplateManager($this->pdo);
            $success = $manager->deleteTemplate($id);

            return Response::json(['success' => $success]);
        } catch (\Throwable $e) {
            Logger::error('Documents: delete Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler'], 400);
        }
    }

    /**
     * POST /api/documents/duplicate — Template kopieren
     */
    public function duplicateTemplate(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung'], 403);
        }

        try {
            $input = $request->json();
            CsrfProtection::requireValid($input);

            $sourceId = (int) ($input['template_id'] ?? 0);
            if (!$sourceId) {
                throw new \Exception('template_id ist erforderlich');
            }

            $manager = new DocumentTemplateManager($this->pdo);
            $newId   = $manager->duplicateTemplate($sourceId);

            return Response::json([
                'success'     => true,
                'template_id' => $newId,
                'csrf_token'  => CsrfProtection::getResponseToken(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: duplicate Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler'], 400);
        }
    }

    /**
     * POST /api/documents/regenerate — Template-Datei (.html.twig) neu generieren
     */
    public function regenerateTemplateFile(Request $request): Response
    {
        if (Gate::denies('document.resetTemplate')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung']);
        }

        try {
            $input = $request->json();
            CsrfProtection::requireValid($input);

            $templateId = (int) ($input['template_id'] ?? 0);
            if (!$templateId) {
                throw new \Exception('Template-ID fehlt');
            }

            $manager  = new DocumentTemplateManager($this->pdo);
            $template = $manager->getTemplate($templateId);
            if (!$template) {
                throw new \Exception('Template nicht gefunden');
            }

            $templatePath = dirname(__DIR__, 4) . '/dokumente/templates/';
            if (!is_dir($templatePath)) {
                mkdir($templatePath, 0755, true);
            }

            $filename = $template['template_file']
                ?? strtolower(str_replace(' ', '_', (string) $template['name'])) . '.html.twig';
            $filepath = $templatePath . $filename;

            $twig = $this->buildTwigTemplateHtml($template);
            file_put_contents($filepath, $twig);

            return Response::json([
                'success'    => true,
                'message'    => 'Template-Datei wurde neu generiert',
                'file'       => $filename,
                'csrf_token' => CsrfProtection::getResponseToken(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: regenerate Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler']);
        }
    }

    /**
     * POST /api/documents/create-custom — Erstelltes Dokument mit PDF + Audit + Notification
     */
    public function createCustom(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung'], 403);
        }

        try {
            $input = $request->json();
            if (!is_array($input)) {
                throw new \Exception('Keine Daten empfangen');
            }

            $required = ['profileid', 'template_id', 'ausstellerid', 'erhalter'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    throw new \Exception("Feld '$field' ist erforderlich");
                }
            }

            $documentId        = DocumentIdGenerator::generate($this->pdo);
            $ausstellungsdatum = $input['fields']['ausstellungsdatum']
                ?? $input['ausstellungsdatum']
                ?? date('Y-m-d');

            $manager = new DocumentTemplateManager($this->pdo);
            $dbId = $manager->createDocument(
                $input['template_id'],
                $input['profileid'],
                array_merge([
                    'erhalter'          => $input['erhalter'],
                    'erhalter_gebdat'   => $input['erhalter_gebdat'] ?? null,
                    'anrede'            => $input['anrede']          ?? null,
                    'ausstellungsdatum' => $ausstellungsdatum,
                    'document_id'       => $documentId,
                ], $input['fields'] ?? []),
                $documentId
            );

            // PDF generieren — nicht-kritisch, Fehler loggen aber Flow nicht abbrechen
            try {
                $renderer     = new DocumentRenderer($this->pdo);
                $pdfGenerator = new DocumentPDFGenerator($this->pdo, $renderer);
                $pdfGenerator->generateAndStore($dbId);
            } catch (\Throwable $e) {
                Logger::warning('Documents: create-custom PDF-Gen fehlgeschlagen', [
                    'error' => $e->getMessage(),
                    'db_id' => $dbId,
                ]);
            }

            $template   = $manager->getTemplate($input['template_id']);
            $userHelper = new UserHelper($this->pdo);

            // Personal-Log
            $logManager = new PersonalLogManager($this->pdo);
            $base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
            $pdfLink    = $base . 'storage/documents/' . $documentId . '.pdf';
            $logContent = "Dokument erstellt: <a href='{$pdfLink}' target='_blank'>"
                . htmlspecialchars($template['name'])
                . " (ID: {$documentId})</a>";

            $logManager->addEntry(
                $input['profileid'],
                PersonalLogManager::TYPE_DOCUMENT,
                $logContent,
                $userHelper->getCurrentUserFullnameForAction(),
                [
                    'change_type'   => 'document_created',
                    'document_id'   => $documentId,
                    'template_id'   => $input['template_id'],
                    'template_name' => $template['name'],
                ]
            );

            // Audit-Log
            try {
                (new AuditLogger($this->pdo))->log(
                    (int) ($_SESSION['userid'] ?? 0),
                    'Dokument erstellt [' . $documentId . ']',
                    'Für Profil: ' . $input['profileid'],
                    'Dokumente',
                    0
                );
            } catch (\Throwable $e) {
                Logger::warning('Documents: create-custom Audit-Log fehlgeschlagen', ['error' => $e->getMessage()]);
            }

            // Notification an Mitarbeiter (falls User-Account existiert)
            try {
                $profileStmt = $this->pdo->prepare("SELECT discordtag FROM intra_mitarbeiter WHERE id = ?");
                $profileStmt->execute([$input['profileid']]);
                $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

                if ($profile && !empty($profile['discordtag'])) {
                    $notificationManager = new NotificationManager($this->pdo);
                    $recipientUserId     = $notificationManager->getUserIdByDiscordTag($profile['discordtag']);
                    if ($recipientUserId) {
                        $notificationManager->create(
                            $recipientUserId,
                            'dokument',
                            'Neues Dokument erstellt',
                            "Ein neues Dokument ({$template['name']} #{$documentId}) wurde für Sie erstellt.",
                            $base . "mitarbeiter/dokument-view.php?docid={$documentId}"
                        );
                    }
                }
            } catch (\Throwable $e) {
                Logger::warning('Documents: create-custom Notification fehlgeschlagen', ['error' => $e->getMessage()]);
            }

            return Response::json([
                'success'     => true,
                'db_id'       => $dbId,
                'document_id' => $documentId,
                'pdf_url'     => $pdfLink,
                'message'     => 'Dokument erfolgreich erstellt',
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: create-custom Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/documents/get-document?docid=... — Metadaten eines erstellten Dokuments
     */
    public function getDocument(Request $request): Response
    {
        if (!isset($_SESSION['userid'])) {
            return Response::json(['success' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        try {
            $docid = (string) ($request->query['docid'] ?? '');
            if ($docid === '') {
                throw new \Exception('docid ist erforderlich');
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    pd.id, pd.docid, pd.type, pd.anrede, pd.erhalter, pd.ausstellungsdatum,
                    pd.ausstellerid, pd.aussteller_name, pd.profileid, pd.pdf_path, pd.template_id,
                    pd.custom_data, pd.timestamp, IFNULL(pd.is_archived, 0) as is_archived,
                    t.name as template_name, t.category as template_category, t.editor_type,
                    dk.name as category_name, dk.color as category_color, dk.icon as category_icon,
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
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                throw new \Exception('Dokument nicht gefunden');
            }

            // Zugriffsprüfung: Admin, Personal-Verwalter oder Eigenersteller
            $isOwnDoc = ($doc['ausstellerid'] == ($_SESSION['discord_id'] ?? ''));
            if (!$isOwnDoc && Gate::denies('document.view')) {
                return Response::json(['success' => false, 'error' => 'Keine Berechtigung'], 403);
            }

            $typLabel = DocumentTemplateManager::getDocumentTypeLabel(
                (int) $doc['type'],
                $doc['template_name'] ?? null
            );

            $base      = defined('BASE_PATH') ? (string) BASE_PATH : '/';
            $pdfUrl    = $base . 'storage/documents/' . $doc['docid'] . '.pdf';
            $pdfExists = file_exists(dirname(__DIR__, 4) . '/storage/documents/' . basename($doc['docid']) . '.pdf');

            return Response::json([
                'success'  => true,
                'document' => [
                    'id'                          => (int) $doc['id'],
                    'docid'                       => $doc['docid'],
                    'type'                        => (int) $doc['type'],
                    'type_label'                  => $typLabel,
                    'erhalter'                    => $doc['erhalter'],
                    'empfaenger_fullname'         => $doc['empfaenger_fullname'],
                    'ersteller_name'              => $doc['ersteller_name'],
                    'ausstellungsdatum'           => $doc['ausstellungsdatum'],
                    'ausstellungsdatum_formatted' => $doc['ausstellungsdatum'] ? date('d.m.Y', strtotime($doc['ausstellungsdatum'])) : '',
                    'timestamp'                   => $doc['timestamp'],
                    'template_name'               => $doc['template_name'],
                    'category_name'               => $doc['category_name'],
                    'category_color'              => $doc['category_color'],
                    'is_archived'                 => (bool) $doc['is_archived'],
                    'pdf_url'                     => $pdfUrl,
                    'pdf_exists'                  => $pdfExists,
                    'profileid'                   => (int) $doc['profileid'],
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: get-document Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler'], 400);
        }
    }

    /**
     * POST /api/documents/archive — Dokument archivieren/wiederherstellen
     */
    public function archiveDocument(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung'], 403);
        }

        try {
            $input = $request->json();
            CsrfProtection::requireValid($input);

            $docid    = (string) ($input['docid'] ?? '');
            $archived = (bool)   ($input['archived'] ?? true);

            if ($docid === '') {
                throw new \Exception('docid ist erforderlich');
            }

            $stmt = $this->pdo->prepare(
                "UPDATE intra_mitarbeiter_dokumente SET is_archived = :archived WHERE docid = :docid"
            );
            $stmt->execute(['archived' => $archived ? 1 : 0, 'docid' => $docid]);

            if ($stmt->rowCount() === 0) {
                throw new \Exception('Dokument nicht gefunden');
            }

            $action = $archived ? 'archiviert' : 'wiederhergestellt';
            (new AuditLogger($this->pdo))->log(
                (int) ($_SESSION['userid'] ?? 0),
                "Dokument {$action} [ID: {$docid}]",
                null,
                'Mitarbeiter',
                1
            );

            return Response::json([
                'success'    => true,
                'archived'   => $archived,
                'csrf_token' => CsrfProtection::getResponseToken(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: archive Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler'], 400);
        }
    }

    // ── Assets ────────────────────────────────────────────────────────

    /**
     * GET /api/documents/asset-list?template_id=N
     */
    public function assetList(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung']);
        }

        try {
            $templateId = isset($request->query['template_id']) ? (int) $request->query['template_id'] : null;
            $manager    = new TemplateAssetManager($this->pdo);
            return Response::json(['success' => true, 'assets' => $manager->listAssets($templateId)]);
        } catch (\Throwable $e) {
            Logger::error('Documents: asset-list Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler']);
        }
    }

    /**
     * POST /api/documents/asset-upload (multipart/form-data)
     */
    public function assetUpload(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung']);
        }

        try {
            CsrfProtection::requireValid(null);

            if (empty($request->files['file'])) {
                throw new \Exception('Keine Datei hochgeladen');
            }

            $templateId = isset($request->post['template_id']) ? (int) $request->post['template_id'] : null;
            $assetType  = (string) ($request->post['asset_type'] ?? 'image');

            $manager = new TemplateAssetManager($this->pdo);
            $result  = $manager->upload($request->files['file'], $templateId, $assetType);

            return Response::json([
                'success'    => true,
                'asset'      => $result,
                'csrf_token' => CsrfProtection::getResponseToken(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: asset-upload Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler']);
        }
    }

    /**
     * POST|DELETE /api/documents/asset-delete
     */
    public function assetDelete(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung']);
        }

        try {
            $input = $request->json();
            CsrfProtection::requireValid($input);

            $assetId = (int) ($input['id'] ?? 0);
            if (!$assetId) {
                throw new \Exception('Asset-ID ist erforderlich');
            }

            $manager = new TemplateAssetManager($this->pdo);
            if (!$manager->delete($assetId)) {
                throw new \Exception('Asset nicht gefunden');
            }

            return Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Logger::error('Documents: asset-delete Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler']);
        }
    }

    // ── Layouts ───────────────────────────────────────────────────────

    /**
     * GET /api/documents/layout-get?template_id=N
     */
    public function layoutGet(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung']);
        }

        try {
            $templateId = (int) ($request->query['template_id'] ?? 0);
            if (!$templateId) {
                throw new \Exception('template_id ist erforderlich');
            }

            $manager = new TemplateLayoutManager($this->pdo);
            $layout  = $manager->getLayout($templateId);

            if (!$layout) {
                return Response::json(['success' => true, 'layout' => null]);
            }

            return Response::json([
                'success' => true,
                'layout'  => [
                    'id'             => (int)   $layout['id'],
                    'version'        => (int)   $layout['version'],
                    'canvas_json'    =>         $layout['canvas_json'],
                    'page_width_mm'  => (float) $layout['page_width_mm'],
                    'page_height_mm' => (float) $layout['page_height_mm'],
                    'updated_at'     =>         $layout['updated_at'],
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: layout-get Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler']);
        }
    }

    /**
     * POST /api/documents/layout-save
     */
    public function layoutSave(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung']);
        }

        try {
            $input = $request->json();
            CsrfProtection::requireValid($input);

            if (empty($input['template_id']) || empty($input['canvas_json'])) {
                throw new \Exception('template_id und canvas_json sind erforderlich');
            }

            $manager = new TemplateLayoutManager($this->pdo);

            $canvasJson = is_string($input['canvas_json'])
                ? $input['canvas_json']
                : json_encode($input['canvas_json']);

            $layoutId = $manager->saveLayout(
                (int) $input['template_id'],
                $canvasJson,
                $input['page_width_mm']  ?? null,
                $input['page_height_mm'] ?? null
            );
            $layout = $manager->getLayoutById($layoutId);

            // is_draft-Flag im Template-Config setzen
            if (isset($input['set_draft'])) {
                $templateId = (int) $input['template_id'];
                $stmtCfg = $this->pdo->prepare("SELECT config FROM intra_dokument_templates WHERE id = ?");
                $stmtCfg->execute([$templateId]);
                $config = json_decode($stmtCfg->fetchColumn() ?: '{}', true) ?: [];
                $config['is_draft'] = (bool) $input['set_draft'];
                $this->pdo->prepare("UPDATE intra_dokument_templates SET config = :config WHERE id = :id")
                    ->execute(['config' => json_encode($config), 'id' => $templateId]);
            }

            return Response::json([
                'success'    => true,
                'layout_id'  => $layoutId,
                'version'    => $layout['version'] ?? 1,
                'csrf_token' => CsrfProtection::getResponseToken(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: layout-save Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler']);
        }
    }

    /**
     * GET|POST /api/documents/layout-versions
     *   GET:  template_id=N                    → Versionen auflisten
     *   POST: {template_id, layout_id, csrf}   → Version wiederherstellen
     */
    public function layoutVersions(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung'], 403);
        }

        $manager = new TemplateLayoutManager($this->pdo);

        try {
            if (strtoupper($request->method) === 'GET') {
                $templateId = (int) ($request->query['template_id'] ?? 0);
                if (!$templateId) {
                    throw new \InvalidArgumentException('template_id fehlt');
                }
                return Response::json([
                    'success'  => true,
                    'versions' => $manager->getLayoutVersions($templateId),
                ]);
            }

            $input = $request->json();
            CsrfProtection::requireValid($input);
            $templateId = (int) ($input['template_id'] ?? 0);
            $layoutId   = (int) ($input['layout_id']   ?? 0);
            if (!$templateId || !$layoutId) {
                throw new \InvalidArgumentException('template_id und layout_id benötigt');
            }

            return Response::json([
                'success'    => $manager->restoreVersion($templateId, $layoutId),
                'csrf_token' => CsrfProtection::getResponseToken(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: layout-versions Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler'], 400);
        }
    }

    /**
     * POST /api/documents/layout-preview — HTML oder PDF-Vorschau für ein Layout.
     *
     * Gibt bewusst direkt HTML oder PDF zurück (kein JSON), weil die UI
     * das als iframe src bzw. `application/pdf`-Download lädt.
     */
    public function layoutPreview(Request $request): Response
    {
        if (Gate::denies('document.manage')) {
            return new Response(
                status:  403,
                body:    '<html><body style="font-family:sans-serif;color:red;padding:2rem;">Keine Berechtigung</body></html>',
                headers: ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        try {
            $input = $request->json();
            CsrfProtection::requireValid($input);

            if (empty($input['template_id'])) {
                throw new \Exception('template_id ist erforderlich');
            }

            $renderer   = new VisualTemplateRenderer($this->pdo);
            $sampleData = $input['sample_data'] ?? [];

            $html = $renderer->renderPreview(
                (int) $input['template_id'],
                $sampleData,
                $input['canvas_json'] ?? null
            );

            $format = $input['format'] ?? 'html';
            if ($format === 'pdf') {
                $dompdf = new \Dompdf\Dompdf([
                    'defaultFont'            => 'DejaVu Sans',
                    'isRemoteEnabled'        => false,
                    'isHtml5ParserEnabled'   => true,
                    'defaultPaperSize'       => 'A4',
                    'defaultPaperOrientation' => 'portrait',
                    'dpi'                    => 150,
                    'fontDir'                => dirname(__DIR__, 4) . '/storage/fonts/',
                    'fontCache'              => dirname(__DIR__, 4) . '/storage/fonts/',
                ]);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                return new Response(
                    status:  200,
                    body:    (string) $dompdf->output(),
                    headers: [
                        'Content-Type'        => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="preview.pdf"',
                    ],
                );
            }

            return new Response(
                status:  200,
                body:    $html,
                headers: ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        } catch (\Throwable $e) {
            $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;">'
                . '<h3 style="color:#dc3545;">Vorschau-Fehler</h3>'
                . '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $body .= '<pre style="font-size:0.8rem;color:#666;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            }
            $body .= '</body></html>';

            return new Response(
                status:  200,
                body:    $body,
                headers: ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }
    }

    /**
     * GET /api/documents/twig-preview?id=N — gerendertes HTML des Twig-Templates
     * (Twig-Tags entfernt, nur Development-Modus)
     */
    public function twigPreview(Request $request): Response
    {
        if (Gate::denies('document.resetTemplate')) {
            return new Response(403, 'Keine Berechtigung');
        }

        $templateId = (int) ($request->query['id'] ?? 0);
        if (!$templateId) {
            return new Response(400, 'Template-ID fehlt');
        }

        $manager  = new DocumentTemplateManager($this->pdo);
        $template = $manager->getTemplate($templateId);
        if (!$template || empty($template['template_file'])) {
            return new Response(404, 'Template nicht gefunden');
        }

        $templatePath = dirname(__DIR__, 4) . '/dokumente/templates/' . $template['template_file'];
        if (!file_exists($templatePath)) {
            return new Response(
                status: 404,
                body:   'Template-Datei nicht gefunden: ' . htmlspecialchars($template['template_file'], ENT_QUOTES, 'UTF-8'),
            );
        }

        $html = (string) file_get_contents($templatePath);
        // Twig-Kontrollstrukturen entfernen, Inhalt behalten
        $html = preg_replace('/\{%\s*(?:if|elseif|else|endif|for|endfor|block|endblock|extends|set)[^%]*%\}/', '', $html);
        // Filter aus {{ var|filter }} entfernen
        $html = preg_replace('/\{\{\s*([a-zA-Z0-9_.]+)\s*\|[^}]*\}\}/', '{{ $1 }}', $html);

        return new Response(
            status:  200,
            body:    $html,
            headers: [
                'Content-Type'    => 'text/html; charset=UTF-8',
                'X-Frame-Options' => 'SAMEORIGIN',
            ],
        );
    }

    /**
     * POST /api/documents/convert-twig — Twig-Template → visuelles Layout (Canvas-JSON).
     * Nur im Development-Modus.
     */
    public function convertTwig(Request $request): Response
    {
        if (($_ENV['APP_ENV'] ?? 'production') !== 'development') {
            return Response::json([
                'success' => false,
                'error'   => 'Nur im Development-Modus verfügbar',
            ]);
        }

        if (Gate::denies('document.resetTemplate')) {
            return Response::json(['success' => false, 'error' => 'Keine Berechtigung']);
        }

        try {
            $input = $request->json();
            CsrfProtection::requireValid($input);

            $templateId = (int) ($input['template_id'] ?? 0);
            $convertAll = !empty($input['convert_all']);

            $manager       = new DocumentTemplateManager($this->pdo);
            $layoutManager = new TemplateLayoutManager($this->pdo);
            $converter     = new TwigToCanvasConverter();

            if ($convertAll) {
                $templates = $manager->listTemplates();
                $results   = ['converted' => 0, 'skipped' => 0, 'errors' => []];

                foreach ($templates as $t) {
                    try {
                        $template = $manager->getTemplate((int) $t['id']);
                        if (!$template || empty($template['template_file'])) {
                            $results['skipped']++;
                            continue;
                        }

                        $existingLayout = $layoutManager->getLayout((int) $t['id']);
                        if ($existingLayout && !empty($input['overwrite'])) {
                            // Überschreiben wenn gewünscht
                        } elseif ($existingLayout) {
                            $results['skipped']++;
                            continue;
                        }

                        $canvasJson = $converter->convert($template['template_file'], $template['fields'] ?? []);
                        $layoutManager->saveLayout((int) $t['id'], json_encode($canvasJson));
                        $results['converted']++;
                    } catch (\Throwable $e) {
                        $results['errors'][] = ($t['name'] ?? $t['id']) . ': ' . $e->getMessage();
                    }
                }

                return Response::json(['success' => true, 'results' => $results]);
            }

            if (!$templateId) {
                throw new \Exception('Template-ID fehlt');
            }

            $template = $manager->getTemplate($templateId);
            if (!$template) {
                throw new \Exception('Template nicht gefunden');
            }
            if (empty($template['template_file'])) {
                throw new \Exception('Keine Template-Datei vorhanden');
            }

            $canvasJson = $converter->convert($template['template_file'], $template['fields'] ?? []);
            $layoutId   = $layoutManager->saveLayout($templateId, json_encode($canvasJson));

            return Response::json([
                'success'       => true,
                'layout_id'     => $layoutId,
                'objects_count' => count($canvasJson['objects'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Documents: convert-twig Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Interner Fehler']);
        }
    }

    // ── Kategorien ────────────────────────────────────────────────────

    /**
     * GET /api/documents/categories — Auflistung (eingeloggte User)
     * POST: erstellen/aktualisieren (Admin)
     * DELETE: löschen (Admin, nur wenn nicht verwendet)
     */
    public function categories(Request $request): Response
    {
        if (!isset($_SESSION['userid'])) {
            return Response::json(['error' => 'Nicht angemeldet'], 401);
        }

        $method = strtoupper($request->method);

        if ($method === 'GET') {
            $stmt = $this->pdo->query(
                "SELECT * FROM intra_dokument_kategorien ORDER BY sort_order ASC, name ASC"
            );
            return Response::json($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        // Ab hier nur Admins
        if (Gate::denies('document.resetTemplate')) {
            return Response::json(['error' => 'Keine Berechtigung'], 403);
        }

        if ($method === 'POST') {
            return $this->saveCategoryPost($request);
        }

        if ($method === 'DELETE') {
            return $this->deleteCategory($request);
        }

        return Response::json(['error' => 'Methode nicht erlaubt'], 405);
    }

    private function saveCategoryPost(Request $request): Response
    {
        $input = $request->json();
        if (!is_array($input) || empty($input['name'])) {
            return Response::json(['error' => 'Name ist erforderlich'], 400);
        }

        $name      = trim((string) $input['name']);
        $color     = $input['color'] ?? 'text-bg-secondary';
        $icon      = !empty($input['icon']) ? trim((string) $input['icon']) : null;
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if (!empty($input['id'])) {
            $this->pdo->prepare(
                "UPDATE intra_dokument_kategorien SET name = :name, color = :color, icon = :icon, sort_order = :sort_order WHERE id = :id"
            )->execute([
                'id'         => (int) $input['id'],
                'name'       => $name,
                'color'      => $color,
                'icon'       => $icon,
                'sort_order' => $sortOrder,
            ]);
            return Response::json(['success' => true, 'id' => (int) $input['id']]);
        }

        $this->pdo->prepare(
            "INSERT INTO intra_dokument_kategorien (name, color, icon, sort_order) VALUES (:name, :color, :icon, :sort_order)"
        )->execute([
            'name'       => $name,
            'color'      => $color,
            'icon'       => $icon,
            'sort_order' => $sortOrder,
        ]);
        return Response::json(['success' => true, 'id' => (int) $this->pdo->lastInsertId()]);
    }

    private function deleteCategory(Request $request): Response
    {
        $id = (int) ($request->query['id'] ?? 0);
        if (!$id) {
            return Response::json(['error' => 'Keine ID angegeben'], 400);
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_dokument_templates WHERE category_id = :id");
        $stmt->execute(['id' => $id]);
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            return Response::json([
                'error' => "Kategorie wird von $count Template(s) verwendet und kann nicht gelöscht werden.",
            ], 409);
        }

        $this->pdo->prepare("DELETE FROM intra_dokument_kategorien WHERE id = :id")
            ->execute(['id' => $id]);

        return Response::json(['success' => true]);
    }

    // ── Private Helper ────────────────────────────────────────────────

    /**
     * Erzeugt das Config-Array mit geschlechtsspezifischen Labels für
     * Select-Felder — wird in `intra_dokument_templates.config` gespeichert.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function buildTemplateConfig(array $fields): array
    {
        $config = ['fields' => []];
        foreach ($fields as $field) {
            if ($field['field_type'] === 'select' && !empty($field['field_options'])) {
                $config['fields'][$field['field_name']] = [
                    'type'            => 'select',
                    'label'           => $field['field_label'],
                    'options'         => $field['field_options'],
                    'gender_specific' => $field['gender_specific'] ?? false,
                ];
            }
        }
        return $config;
    }

    /**
     * Erstellt die Twig-Template-Datei für ein neues Template, aber nur
     * wenn sie noch nicht existiert. Gibt true bei Neu-Erstellung zurück,
     * false wenn die Datei schon da war.
     *
     * @param  array<string, mixed>  $data
     */
    private function createTemplateFile(array $data): bool
    {
        $templatePath = dirname(__DIR__, 4) . '/dokumente/templates/';
        if (!file_exists($templatePath)) {
            mkdir($templatePath, 0755, true);
        }

        $filename = $data['template_file']
            ?? strtolower(str_replace(' ', '_', (string) $data['name'])) . '.html.twig';
        $filepath = $templatePath . $filename;

        if (file_exists($filepath)) {
            return false;
        }

        file_put_contents($filepath, $this->buildTwigTemplateHtml($data));
        return true;
    }

    /**
     * Generiert das vollständige HTML für ein Twig-Template mit allen
     * Feldern. Gemeinsamer Helper für `saveTemplate()` (Datei-Neuerstellung)
     * und `regenerateTemplateFile()` (Neu-Generierung).
     *
     * @param  array<string, mixed>  $data
     */
    private function buildTwigTemplateHtml(array $data): string
    {
        $template = <<<'TWIG'
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>{{ SYSTEM_NAME }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0; padding: 20mm 25mm;
            font-size: 11pt; line-height: 1.4;
        }
        .header { margin-bottom: 8mm; }
        .header-right { float: right; width: 35%; text-align: right; }
        .header-left { width: 60%; font-size: 10pt; line-height: 1.3; }
        .logo-placeholder {
            padding: 5mm 2.5mm; text-align: center;
            font-size: 9pt; color: #666; margin-bottom: 4mm;
        }
        .date-box { margin-top: 4mm; }
        .date-label { font-size: 10pt; margin-bottom: 2mm; }
        .date-value { font-size: 12pt; font-weight: bold; }
        .recipient { margin: 10mm 0; font-size: 11pt; line-height: 1.5; }
        .title { font-size: 15pt; font-weight: bold; margin: 12mm 0 8mm 0; }
        .letter-content { font-size: 11pt; line-height: 1.6; }
        .letter-content p { margin: 4mm 0; }
        .field-section { margin: 4mm 0; }
        .field-box { border: 1px solid #ccc; padding: 3mm; margin: 4mm 0; min-height: 20mm; }
        .date-location { margin-top: 12mm; font-size: 10pt; }
        .document-reference { margin-top: 4mm; font-size: 9pt; color: #333; }
        .issuer-info { margin-top: 6mm; font-size: 10pt; }
        .electronic-note { margin-top: 2mm; font-size: 8pt; font-style: italic; color: #666; }
        .clearfix::after { content: ""; display: table; clear: both; }
    </style>
</head>

<body>
    <div class="header clearfix">
        <div class="header-right">
            <div class="logo-placeholder">
                {% if logo_base64 %}
                <img src="{{ logo_base64 }}" alt="Logo" style="max-width: 100%;">
                {% endif %}
            </div>
            <div class="date-box">
                <div class="date-label">Datum</div>
                <div class="date-value">{{ ausstellungsdatum }}</div>
            </div>
        </div>
        <div class="header-left">
            {{ RP_ORGTYPE }} {{ SERVER_CITY }}<br>
            {{ RP_STREET }}<br>
            {{ RP_ZIP }} {{ SERVER_CITY }}
        </div>
    </div>

    <div style="clear: both;"></div>

    <div class="recipient">
        {{ anrede_text }}<br>
        {{ erhalter }}<br>
        {{ RP_ZIP }} {{ SERVER_CITY }}
    </div>

    <div class="title">Dokument</div>

    <div class="letter-content">

TWIG;

        foreach (($data['fields'] ?? []) as $field) {
            $fieldName  = $field['field_name'];
            $fieldLabel = $field['field_label'];

            if (in_array($field['field_type'], ['richtext', 'textarea'], true)) {
                $template .= "        <div class=\"field-section\">\n";
                $template .= "            <strong>{$fieldLabel}:</strong>\n";
                $template .= "            <div class=\"field-box\">{{ {$fieldName}|raw }}</div>\n";
                $template .= "        </div>\n";
            } else {
                $template .= "        <p><strong>{$fieldLabel}:</strong> {{ {$fieldName} }}</p>\n";
            }
        }

        $template .= <<<'TWIG'
    </div>

    <div class="date-location">
        {{ SERVER_CITY }}, den {{ ausstellungsdatum }}
    </div>

    <div class="document-reference">
        <strong>Ihr Zeichen:</strong> {{ document_id }}
    </div>

    <div class="issuer-info">
        <strong>{{ issuer.fullname }}</strong><br>
        {{ issuer.dienstgrad_text }}
        {% if issuer.zusatz %}<br>{{ issuer.zusatz }}{% endif %}
    </div>

    <div class="electronic-note">
        — Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —
    </div>
</body>

</html>
TWIG;

        return $template;
    }
}
