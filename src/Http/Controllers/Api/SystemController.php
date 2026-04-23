<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Permissions;
use App\Http\Request;
use App\Http\Response;
use App\KnowledgeBase\KBHelper;
use App\Logging\Logger;
use App\Policies\MitarbeiterPolicy;
use App\Policies\FireIncidentPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\DocumentPolicy;
use App\Utils\AuditLogger;
use App\Utils\SystemUpdater;
use PDO;
use PDOException;

/**
 * System-Admin-API: Composer-Status, Performance-Metrics, API-Key-Regeneration,
 * User-Theme-Config, globale Suche über alle Module.
 *
 * Alle Endpoints erfordern Session-Auth plus je nach Aktion spezifische
 * Permissions. Die Methoden-Kommentare dokumentieren die erforderlichen
 * Permissions.
 */
final class SystemController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Composer-Status ───────────────────────────────────────────────

    /**
     * GET /api/system/composer-status?action=check   → prüft ob composer install pending ist
     * POST /api/system/composer-status?action=execute → führt composer install aus
     */
    public function composerStatus(Request $request): Response
    {
        $method = strtoupper($request->method);
        $action = $request->query['action'] ?? 'check';
        if ($method === 'POST') {
            $action = $request->post['action'] ?? 'execute';
        }

        $updater = new SystemUpdater();

        try {
            switch ($action) {
                case 'check':
                    if ($method !== 'GET') {
                        return Response::json([
                            'success' => false,
                            'error'   => true,
                            'message' => 'Methode nicht erlaubt. Verwenden Sie GET für "check".',
                        ], 405);
                    }
                    return Response::json($updater->getComposerStatus());

                case 'execute':
                    if ($method !== 'POST') {
                        return Response::json([
                            'success' => false,
                            'error'   => true,
                            'message' => 'Methode nicht erlaubt. Verwenden Sie POST für "execute".',
                        ], 405);
                    }
                    return Response::json($updater->executePendingComposerInstall());

                default:
                    return Response::json([
                        'success' => false,
                        'error'   => true,
                        'message' => 'Ungültige Aktion. Verwenden Sie "check" oder "execute".',
                    ], 400);
            }
        } catch (\Throwable $e) {
            Logger::error('System: composer-status Fehler', ['error' => $e->getMessage()]);
            return Response::json([
                'success' => false,
                'error'   => true,
                'message' => 'Fehler: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Performance-Metrics ───────────────────────────────────────────

    /**
     * GET /api/system/performance
     */
    public function performance(Request $request): Response
    {
        try {
            $data = [];

            // Datenbank-Größe
            $stmt = $this->pdo->query("
                SELECT table_schema AS db_name,
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    SUM(table_rows) AS total_rows,
                    COUNT(*) AS table_count
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                GROUP BY table_schema
            ");
            $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['database'] = [
                'name'        => $dbInfo['db_name'] ?? '',
                'size_mb'     => (float) ($dbInfo['size_mb'] ?? 0),
                'total_rows'  => (int) ($dbInfo['total_rows'] ?? 0),
                'table_count' => (int) ($dbInfo['table_count'] ?? 0),
            ];

            // Tabellen (Top 10)
            $stmt = $this->pdo->query("
                SELECT table_name, table_rows AS row_count,
                    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    ROUND(index_length / 1024 / 1024, 2) AS index_size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ");
            $data['tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Aktive Benutzer
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 24 HOUR THEN a.user END) AS active_24h,
                    COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 7 DAY THEN a.user END) AS active_7d,
                    COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 30 DAY THEN a.user END) AS active_30d,
                    (SELECT COUNT(*) FROM intra_users WHERE is_active = 1) AS total
                FROM intra_audit_log a
            ");
            $users = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach ($users as &$val) {
                $val = (int) $val;
            }
            $data['users'] = $users;

            // Content-Statistiken
            $contentStats = [
                'mitarbeiter'      => (int) $this->pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter")->fetchColumn(),
                'enotf_protokolle' => (int) $this->pdo->query("SELECT COUNT(*) FROM intra_edivi")->fetchColumn(),
                'dokumente'        => (int) $this->pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter_dokumente")->fetchColumn(),
                'kb_eintraege'     => (int) $this->pdo->query("SELECT COUNT(*) FROM intra_kb_entries WHERE is_archived = 0")->fetchColumn(),
            ];
            try {
                $contentStats['brandeinsaetze'] = (int) $this->pdo->query("SELECT COUNT(*) FROM intra_fire_incidents")->fetchColumn();
            } catch (PDOException) {
                $contentStats['brandeinsaetze'] = 0;
            }
            $data['content'] = $contentStats;

            // Server / MySQL
            $data['server'] = [
                'db_version' => $this->pdo->query("SELECT VERSION()")->fetchColumn(),
            ];
            $row = $this->pdo->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'")->fetch(PDO::FETCH_ASSOC);
            $data['server']['buffer_pool_mb'] = $row ? round((int) $row['Value'] / 1024 / 1024) : null;
            $row = $this->pdo->query("SHOW VARIABLES LIKE 'max_connections'")->fetch(PDO::FETCH_ASSOC);
            $data['server']['max_connections'] = $row ? (int) $row['Value'] : null;
            $row = $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch(PDO::FETCH_ASSOC);
            $data['server']['threads_connected'] = $row ? (int) $row['Value'] : null;
            $row = $this->pdo->query("SHOW STATUS LIKE 'Uptime'")->fetch(PDO::FETCH_ASSOC);
            $data['server']['uptime_seconds'] = $row ? (int) $row['Value'] : null;

            // PHP-Info
            $data['php'] = [
                'version'             => PHP_VERSION,
                'memory_limit'        => ini_get('memory_limit'),
                'max_execution_time'  => (int) ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'       => ini_get('post_max_size'),
            ];

            try {
                $row = $this->pdo->query("SHOW STATUS LIKE 'Slow_queries'")->fetch(PDO::FETCH_ASSOC);
                $data['server']['slow_queries'] = $row ? (int) $row['Value'] : null;
            } catch (PDOException) {
                $data['server']['slow_queries'] = null;
            }

            // Templates
            $templatePath = realpath(dirname(__DIR__, 4) . '/dokumente/templates/');
            $data['templates'] = [
                'count' => $templatePath ? count(glob($templatePath . '/*.html.twig') ?: []) : 0,
            ];

            // Migrations
            try {
                $data['migrations'] = [
                    'executed' => (int) $this->pdo->query("SELECT COUNT(*) FROM intra_migrations")->fetchColumn(),
                ];
            } catch (PDOException) {
                $data['migrations'] = ['executed' => 0];
            }

            return Response::json($data);
        } catch (\Throwable $e) {
            Logger::error('System: performance Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    // ── API-Key-Regeneration ──────────────────────────────────────────

    /**
     * POST /api/system/regenerate-api-key
     */
    public function regenerateApiKey(Request $request): Response
    {
        try {
            $newApiKey = bin2hex(random_bytes(32));

            $stmt = $this->pdo->prepare("
                UPDATE intra_config
                SET config_value = ?, updated_by = ?, updated_at = NOW()
                WHERE config_key = 'API_KEY'
            ");
            $success = $stmt->execute([$newApiKey, $_SESSION['userid'] ?? null]);

            if (!$success || $stmt->rowCount() === 0) {
                return Response::json([
                    'success' => false,
                    'message' => 'API_KEY wurde nicht in der Datenbank gefunden oder konnte nicht aktualisiert werden',
                ], 500);
            }

            $auditLogger = new AuditLogger($this->pdo);
            $auditLogger->log(
                $_SESSION['userid'] ?? 0,
                'API-Schlüssel neu generiert',
                'Neuer API-Schlüssel wurde erstellt',
                'System',
                1
            );

            return Response::json([
                'success' => true,
                'api_key' => $newApiKey,
                'message' => 'API-Schlüssel erfolgreich generiert',
            ]);
        } catch (\Throwable $e) {
            Logger::error('System: regenerate-api-key Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Interner Serverfehler'], 500);
        }
    }

    // ── User-Theme ────────────────────────────────────────────────────

    private const ALLOWED_THEME_PRESETS = ['red', 'blue', 'green', 'purple', 'orange', 'teal', 'pink', 'amber'];

    /**
     * GET /api/system/theme — aktuelle Theme-Config des eingeloggten Users
     */
    public function getTheme(Request $request): Response
    {
        $userId = (int) ($_SESSION['userid'] ?? 0);

        try {
            $stmt = $this->pdo->prepare("SELECT theme_config FROM intra_users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $config = null;
            if ($row && $row['theme_config']) {
                $config = json_decode($row['theme_config'], true);
            }
            return Response::json(['config' => $config]);
        } catch (PDOException $e) {
            Logger::error('System: theme GET Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Datenbankfehler'], 500);
        }
    }

    /**
     * POST /api/system/theme — Theme-Config speichern (Accent-Color-Preset oder Hex)
     */
    public function setTheme(Request $request): Response
    {
        $userId = (int) ($_SESSION['userid'] ?? 0);
        $input  = $request->json();
        if (!is_array($input) || !isset($input['accent'])) {
            return Response::json(['error' => 'Ungültige Daten'], 400);
        }

        $accent      = (string) $input['accent'];
        $isPreset    = in_array($accent, self::ALLOWED_THEME_PRESETS, true);
        $isCustomHex = (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $accent);

        if (!$isPreset && !$isCustomHex) {
            return Response::json(['error' => 'Ungültige Farbe'], 400);
        }

        $config = json_encode([
            'accent' => $accent,
            'type'   => $isPreset ? 'preset' : 'custom',
        ]);

        try {
            $this->pdo->prepare("UPDATE intra_users SET theme_config = :config WHERE id = :id")
                ->execute(['config' => $config, 'id' => $userId]);

            return Response::json(['success' => true, 'config' => json_decode($config, true)]);
        } catch (PDOException $e) {
            Logger::error('System: theme SET Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Datenbankfehler'], 500);
        }
    }

    // ── Globale Suche ─────────────────────────────────────────────────

    /**
     * GET /api/system/global-search?q=...
     *
     * Durchsucht Wissensdatenbank, Mitarbeiter, Brandeinsätze, eNOTF,
     * Dokumente, Templates, Fahrzeuge und Defekte — je nach User-Permission.
     */
    public function globalSearch(Request $request): Response
    {
        $query = trim((string) ($request->query['q'] ?? ''));
        if (mb_strlen($query) < 2) {
            return Response::json(['results' => []]);
        }

        $searchParam = '%' . $query . '%';
        $results     = [];

        try {
            // Wissensdatenbank
            $kbResults = $this->searchKnowledgeBase($query, $searchParam);
            if (!empty($kbResults)) {
                $results[] = ['module' => 'Wissensdatenbank', 'icon' => 'fa-book-medical', 'items' => $kbResults];
            }

            if (MitarbeiterPolicy::viewList()) {
                $items = $this->searchMitarbeiter($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'Mitarbeiter', 'icon' => 'fa-users', 'items' => $items];
                }
            }

            if (FireIncidentPolicy::manageQm()) {
                $items = $this->searchFireIncidents($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'Brandeinsätze', 'icon' => 'fa-fire', 'items' => $items];
                }
            }

            if (Permissions::check(['admin', 'edivi.view'])) {
                $items = $this->searchEnotf($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'eNOTF Protokolle', 'icon' => 'fa-file-medical', 'items' => $items];
                }
            }

            if (MitarbeiterPolicy::viewList()) {
                $items = $this->searchDocuments($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'Dokumente', 'icon' => 'fa-file-lines', 'items' => $items];
                }
            }

            if (DocumentPolicy::resetTemplate()) {
                $items = $this->searchTemplates($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'Dokumentvorlagen', 'icon' => 'fa-file-contract', 'items' => $items];
                }
            }

            if (VehiclePolicy::view()) {
                $items = $this->searchVehicles($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'Fahrzeuge', 'icon' => 'fa-truck', 'items' => $items];
                }
                $items = $this->searchDefects($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'Defekt-Meldungen', 'icon' => 'fa-triangle-exclamation', 'items' => $items];
                }
            }

            return Response::json(['results' => $results]);
        } catch (\Throwable $e) {
            Logger::error('System: global-search Fehler', ['error' => $e->getMessage(), 'query' => $query]);
            return Response::json(['error' => 'Datenbankfehler'], 500);
        }
    }

    // ── Private Search-Helper (aus dem alten global-search.php übernommen) ──

    /**
     * @return list<array{title: string, subtitle: string, url: string}>
     */
    private function searchKnowledgeBase(string $query, string $searchParam): array
    {
        $ftQuery = '';
        $words = preg_split('/\s+/', trim($query)) ?: [];
        foreach ($words as $w) {
            $w = trim($w);
            if (mb_strlen($w) >= 2) {
                $w = preg_replace('/[+\-><()~*"@]+/', '', $w);
                if ($w !== '') {
                    $ftQuery .= '+' . $w . '* ';
                }
            }
        }
        $ftQuery = trim($ftQuery);

        if ($ftQuery !== '') {
            $sql = "SELECT kb.id, kb.title, kb.subtitle, kb.content
                    FROM intra_kb_entries kb
                    WHERE kb.is_archived = 0
                    AND (
                        MATCH(kb.title, kb.subtitle, kb.content) AGAINST(:ft_main IN BOOLEAN MODE)
                        OR kb.title LIKE :search
                    )
                    ORDER BY MATCH(kb.title, kb.subtitle, kb.content) AGAINST(:ft_rel IN BOOLEAN MODE) DESC, kb.title ASC
                    LIMIT 5";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['ft_main' => $ftQuery, 'ft_rel' => $ftQuery, 'search' => $searchParam]);
        } else {
            $sql = "SELECT kb.id, kb.title, kb.subtitle, kb.content
                    FROM intra_kb_entries kb
                    WHERE kb.is_archived = 0 AND kb.title LIKE :search
                    ORDER BY kb.title ASC LIMIT 5";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['search' => $searchParam]);
        }

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $snippet = KBHelper::createSearchSnippet($row['content'], $query, 100);
            $items[] = [
                'title'    => $row['title'],
                'subtitle' => $snippet ?? ($row['subtitle'] ?: ''),
                'url'      => 'wissensdb/entry.php?id=' . $row['id'],
            ];
        }
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function searchMitarbeiter(string $searchParam): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, fullname, dienstnr FROM intra_mitarbeiter
             WHERE fullname LIKE :s1 OR dienstnr LIKE :s2 ORDER BY fullname ASC LIMIT 5"
        );
        $stmt->execute(['s1' => $searchParam, 's2' => $searchParam]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $items[] = [
                'title'    => $row['fullname'],
                'subtitle' => $row['dienstnr'] ? 'DNr: ' . $row['dienstnr'] : '',
                'url'      => 'mitarbeiter/view.php?id=' . $row['id'],
            ];
        }
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function searchFireIncidents(string $searchParam): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, incident_number, location, keyword, started_at
                FROM intra_fire_incidents
                WHERE incident_number LIKE :s1 OR location LIKE :s2 OR keyword LIKE :s3
                ORDER BY started_at DESC LIMIT 5
            ");
            $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $subtitle = $row['keyword'] ?: $row['location'] ?: '';
            if ($row['started_at']) {
                $subtitle .= ($subtitle ? ' — ' : '') . date('d.m.Y', strtotime($row['started_at']));
            }
            $items[] = [
                'title'    => $row['incident_number'],
                'subtitle' => $subtitle,
                'url'      => 'einsatz/admin/view.php?id=' . $row['id'],
            ];
        }
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function searchEnotf(string $searchParam): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, enr, patname, diagnose, edatum FROM intra_edivi
            WHERE enr LIKE :s1 OR patname LIKE :s2 OR diagnose LIKE :s3
            ORDER BY edatum DESC LIMIT 5
        ");
        $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam]);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $subtitle = $row['patname'] ?: '';
            if ($row['edatum']) {
                $subtitle .= ($subtitle ? ' — ' : '') . date('d.m.Y', strtotime($row['edatum']));
            }
            $items[] = [
                'title'    => 'Protokoll ' . $row['enr'],
                'subtitle' => $subtitle,
                'url'      => 'enotf/admin/view.php?id=' . $row['id'],
            ];
        }
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function searchDocuments(string $searchParam): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT d.id, d.docid, d.erhalter, d.ausstellungsdatum, d.profileid,
                    d.aussteller_name, t.name AS template_name
                FROM intra_mitarbeiter_dokumente d
                LEFT JOIN intra_dokument_templates t ON d.template_id = t.id
                WHERE (d.erhalter LIKE :s1 OR d.docid LIKE :s2 OR t.name LIKE :s3 OR d.aussteller_name LIKE :s4)
                    AND IFNULL(d.is_archived, 0) = 0
                ORDER BY d.timestamp DESC LIMIT 8
            ");
            $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam, 's4' => $searchParam]);
        } catch (PDOException) {
            $stmt = $this->pdo->prepare("
                SELECT d.id, d.docid, d.erhalter, d.ausstellungsdatum, d.profileid,
                    d.aussteller_name, t.name AS template_name
                FROM intra_mitarbeiter_dokumente d
                LEFT JOIN intra_dokument_templates t ON d.template_id = t.id
                WHERE (d.erhalter LIKE :s1 OR d.docid LIKE :s2 OR t.name LIKE :s3 OR d.aussteller_name LIKE :s4)
                ORDER BY d.timestamp DESC LIMIT 8
            ");
            $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam, 's4' => $searchParam]);
        }

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $title    = $row['erhalter'] ?: 'Dokument #' . $row['docid'];
            $subtitle = $row['template_name'] ?: '';
            if ($row['ausstellungsdatum']) {
                $subtitle .= ($subtitle ? ' — ' : '') . date('d.m.Y', strtotime($row['ausstellungsdatum']));
            }
            $items[] = [
                'title'    => $title,
                'subtitle' => $subtitle,
                'url'      => 'mitarbeiter/dokument-view.php?docid=' . $row['docid'],
            ];
        }
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function searchTemplates(string $searchParam): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, category, description FROM intra_dokument_templates
                WHERE name LIKE :s1 OR description LIKE :s2 ORDER BY name ASC LIMIT 5
            ");
            $stmt->execute(['s1' => $searchParam, 's2' => $searchParam]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        $categoryLabels = [
            'urkunde'    => 'Urkunde',
            'zertifikat' => 'Zertifikat',
            'schreiben'  => 'Schreiben',
            'sonstiges'  => 'Sonstiges',
        ];

        $items = [];
        foreach ($rows as $row) {
            $subtitle = $categoryLabels[$row['category']] ?? $row['category'] ?? '';
            if ($row['description']) {
                $subtitle .= ($subtitle ? ' — ' : '') . mb_substr($row['description'], 0, 60);
            }
            $items[] = [
                'title'    => $row['name'],
                'subtitle' => $subtitle,
                'url'      => 'settings/documents/templates.php',
            ];
        }
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function searchVehicles(string $searchParam): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, identifier, name, kennzeichen FROM intra_fahrzeuge
                WHERE identifier LIKE :s1 OR name LIKE :s2 OR kennzeichen LIKE :s3
                ORDER BY identifier ASC LIMIT 5
            ");
            $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $subtitle = $row['name'] ?: '';
            if ($row['kennzeichen']) {
                $subtitle .= ($subtitle ? ' — ' : '') . $row['kennzeichen'];
            }
            $items[] = [
                'title'    => $row['identifier'],
                'subtitle' => $subtitle,
                'url'      => 'settings/fahrzeuge/fahrzeuge/index.php',
            ];
        }
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function searchDefects(string $searchParam): array
    {
        $statusLabels = [
            'open'        => 'Offen',
            'in_progress' => 'In Bearbeitung',
            'deferred'    => 'Aufgeschoben',
            'resolved'    => 'Gelöst',
        ];

        try {
            $stmt = $this->pdo->prepare("
                SELECT d.id, d.title, d.description, d.status, d.created_at,
                    f.name AS vehicle_name, f.identifier AS vehicle_identifier
                FROM intra_fahrzeuge_defects d
                JOIN intra_fahrzeuge f ON d.vehicle_id = f.id
                WHERE d.title LIKE :s1 OR d.description LIKE :s2
                    OR f.name LIKE :s3 OR f.identifier LIKE :s4
                ORDER BY d.created_at DESC LIMIT 5
            ");
            $stmt->execute(['s1' => $searchParam, 's2' => $searchParam, 's3' => $searchParam, 's4' => $searchParam]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $status   = $statusLabels[$row['status']] ?? $row['status'];
            $subtitle = $row['vehicle_name'] . ' (' . $row['vehicle_identifier'] . ') — ' . $status;
            if ($row['created_at']) {
                $subtitle .= ' — ' . date('d.m.Y', strtotime($row['created_at']));
            }
            $items[] = [
                'title'    => $row['title'],
                'subtitle' => $subtitle,
                'url'      => 'settings/fahrzeuge/defekte/index.php',
            ];
        }
        return $items;
    }
}
