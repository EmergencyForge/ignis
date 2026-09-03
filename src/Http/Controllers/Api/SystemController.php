<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Policies\PersonnelPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\DocumentPolicy;
use App\Utils\AuditLogger;
use App\Utils\SystemUpdater;
use Illuminate\Database\Capsule\Manager as Capsule;
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
            $dbInfo = Capsule::table('information_schema.tables')
                ->selectRaw('table_schema AS db_name')
                ->selectRaw('ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb')
                ->selectRaw('SUM(table_rows) AS total_rows')
                ->selectRaw('COUNT(*) AS table_count')
                ->whereRaw('table_schema = DATABASE()')
                ->groupBy('table_schema')
                ->first();
            $dbInfo = $dbInfo !== null ? (array) $dbInfo : [];
            $data['database'] = [
                'name'        => $dbInfo['db_name'] ?? '',
                'size_mb'     => (float) ($dbInfo['size_mb'] ?? 0),
                'total_rows'  => (int) ($dbInfo['total_rows'] ?? 0),
                'table_count' => (int) ($dbInfo['table_count'] ?? 0),
            ];

            // Tabellen (Top 10)
            $data['tables'] = Capsule::table('information_schema.tables')
                ->selectRaw('table_name, table_rows AS row_count')
                ->selectRaw('ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb')
                ->selectRaw('ROUND(index_length / 1024 / 1024, 2) AS index_size_mb')
                ->whereRaw('table_schema = DATABASE()')
                ->orderByRaw('(data_length + index_length) DESC')
                ->limit(10)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            // Aktive Benutzer
            $users = (array) Capsule::table('intra_audit_log as a')
                ->selectRaw('COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 24 HOUR THEN a.user END) AS active_24h')
                ->selectRaw('COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 7 DAY THEN a.user END) AS active_7d')
                ->selectRaw('COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 30 DAY THEN a.user END) AS active_30d')
                ->selectRaw('(SELECT COUNT(*) FROM intra_users WHERE is_active = 1) AS total')
                ->first();
            foreach ($users as &$val) {
                $val = (int) $val;
            }
            $data['users'] = $users;

            // Content-Statistiken — Modul-Tabellen (eNOTF, KB, fireTab)
            // existieren nur bei installiertem Plugin, deshalb einzeln
            // abgesichert.
            $contentStats = [
                'mitarbeiter' => Capsule::table('intra_mitarbeiter')->count(),
                'dokumente'   => Capsule::table('intra_mitarbeiter_dokumente')->count(),
            ];
            try {
                $contentStats['enotf_protokolle'] = Capsule::table('intra_edivi')->count();
            } catch (PDOException) {
                $contentStats['enotf_protokolle'] = 0;
            }
            try {
                $contentStats['kb_eintraege'] = Capsule::table('intra_kb_entries')->where('is_archived', 0)->count();
            } catch (PDOException) {
                $contentStats['kb_eintraege'] = 0;
            }
            try {
                $contentStats['brandeinsaetze'] = Capsule::table('intra_fire_incidents')->count();
            } catch (PDOException) {
                $contentStats['brandeinsaetze'] = 0;
            }
            $data['content'] = $contentStats;

            // Server / MySQL
            $connection = Capsule::connection();
            $data['server'] = [
                'db_version' => $connection->selectOne('SELECT VERSION() AS version')->version,
            ];
            $row = $connection->selectOne("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
            $data['server']['buffer_pool_mb'] = $row ? round((int) $row->Value / 1024 / 1024) : null;
            $row = $connection->selectOne("SHOW VARIABLES LIKE 'max_connections'");
            $data['server']['max_connections'] = $row ? (int) $row->Value : null;
            $row = $connection->selectOne("SHOW STATUS LIKE 'Threads_connected'");
            $data['server']['threads_connected'] = $row ? (int) $row->Value : null;
            $row = $connection->selectOne("SHOW STATUS LIKE 'Uptime'");
            $data['server']['uptime_seconds'] = $row ? (int) $row->Value : null;

            // PHP-Info
            $data['php'] = [
                'version'             => PHP_VERSION,
                'memory_limit'        => ini_get('memory_limit'),
                'max_execution_time'  => (int) ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'       => ini_get('post_max_size'),
            ];

            try {
                $row = $connection->selectOne("SHOW STATUS LIKE 'Slow_queries'");
                $data['server']['slow_queries'] = $row ? (int) $row->Value : null;
            } catch (PDOException) {
                $data['server']['slow_queries'] = null;
            }

            // Templates
            $templatePath = realpath(dirname(__DIR__, 4) . '/documents/templates/');
            $data['templates'] = [
                'count' => $templatePath ? count(glob($templatePath . '/*.html.twig') ?: []) : 0,
            ];

            // Migrations
            try {
                $data['migrations'] = [
                    'executed' => Capsule::table('intra_migrations')->count(),
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

            $affected = Capsule::table('intra_config')
                ->where('config_key', 'API_KEY')
                ->update([
                    'config_value' => $newApiKey,
                    'updated_by'   => $_SESSION['userid'] ?? null,
                    'updated_at'   => Capsule::raw('NOW()'),
                ]);

            if ($affected === 0) {
                return Response::json([
                    'success' => false,
                    'message' => 'API_KEY wurde nicht in der Datenbank gefunden oder konnte nicht aktualisiert werden',
                ], 500);
            }

            $auditLogger = new AuditLogger();
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
            $themeConfig = Capsule::table('intra_users')->where('id', $userId)->value('theme_config');

            $config = null;
            if ($themeConfig) {
                $config = json_decode($themeConfig, true);
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
            Capsule::table('intra_users')->where('id', $userId)->update(['theme_config' => $config]);

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

        $searchParam = '%' . ignis_like_prefix($query) . '%';
        $results     = [];

        try {
            // Wissensdatenbank
            $kbResults = $this->searchKnowledgeBase($query, $searchParam);
            if (!empty($kbResults)) {
                $results[] = ['module' => 'Wissensdatenbank', 'icon' => 'fa-book-medical', 'items' => $kbResults];
            }

            if (PersonnelPolicy::viewList()) {
                $items = $this->searchMitarbeiter($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'Mitarbeiter', 'icon' => 'fa-users', 'items' => $items];
                }
            }

            if (app(\App\Plugins\PluginLoader::class)->isActive('firetab') && \Plugin\Firetab\Policies\FireIncidentPolicy::manageQm()) {
                $items = $this->searchFireIncidents($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'Brandeinsätze', 'icon' => 'fa-fire', 'items' => $items];
                }
            }

            if (app(\App\Plugins\PluginLoader::class)->isActive('enotf') && Gate::allows('enotf.viewAdminList')) {
                $items = $this->searchEnotf($searchParam);
                if (!empty($items)) {
                    $results[] = ['module' => 'eNOTF Protokolle', 'icon' => 'fa-file-medical', 'items' => $items];
                }
            }

            if (PersonnelPolicy::viewList()) {
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
        // Kein Wissensdatenbank-Plugin, keine Treffer — sonst würde die
        // globale Suche auf Seiten verlinken, deren Routen nicht existieren.
        if (!app(\App\Plugins\PluginLoader::class)->isActive('knowledge-base')) {
            return [];
        }

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
            $rows = Capsule::table('intra_kb_entries as kb')
                ->select('kb.id', 'kb.title', 'kb.subtitle', 'kb.content')
                ->where('kb.is_archived', 0)
                ->where(function ($q) use ($ftQuery, $searchParam) {
                    $q->whereRaw('MATCH(kb.title, kb.subtitle, kb.content) AGAINST(? IN BOOLEAN MODE)', [$ftQuery])
                        ->orWhere('kb.title', 'LIKE', $searchParam);
                })
                ->orderByRaw('MATCH(kb.title, kb.subtitle, kb.content) AGAINST(? IN BOOLEAN MODE) DESC, kb.title ASC', [$ftQuery])
                ->limit(5)
                ->get();
        } else {
            $rows = Capsule::table('intra_kb_entries as kb')
                ->select('kb.id', 'kb.title', 'kb.subtitle', 'kb.content')
                ->where('kb.is_archived', 0)
                ->where('kb.title', 'LIKE', $searchParam)
                ->orderBy('kb.title')
                ->limit(5)
                ->get();
        }

        $items = [];
        foreach ($rows->map(fn ($row) => (array) $row)->all() as $row) {
            $snippet = \Plugin\KnowledgeBase\KBHelper::createSearchSnippet($row['content'], $query, 100);
            $items[] = [
                'title'    => $row['title'],
                'subtitle' => $snippet ?? ($row['subtitle'] ?: ''),
                'url'      => 'lexicon/entry.php?id=' . $row['id'],
            ];
        }
        return $items;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function searchMitarbeiter(string $searchParam): array
    {
        $rows = Capsule::table('intra_mitarbeiter')
            ->select(['id', 'fullname', 'dienstnr'])
            ->where('fullname', 'LIKE', $searchParam)
            ->orWhere('dienstnr', 'LIKE', $searchParam)
            ->orderBy('fullname')
            ->limit(5)
            ->get();
        $items = [];
        foreach ($rows->map(fn ($row) => (array) $row)->all() as $row) {
            $items[] = [
                'title'    => $row['fullname'],
                'subtitle' => $row['dienstnr'] ? 'DNr: ' . $row['dienstnr'] : '',
                'url'      => 'mitarbeiter/profile?id=' . $row['id'],
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
            $rows = Capsule::table('intra_fire_incidents')
                ->select(['id', 'incident_number', 'location', 'keyword', 'started_at'])
                ->where('incident_number', 'LIKE', $searchParam)
                ->orWhere('location', 'LIKE', $searchParam)
                ->orWhere('keyword', 'LIKE', $searchParam)
                ->orderByDesc('started_at')
                ->limit(5)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
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
        $rows = Capsule::table('intra_edivi')
            ->select(['id', 'enr', 'patname', 'diagnose', 'edatum'])
            ->where('enr', 'LIKE', $searchParam)
            ->orWhere('patname', 'LIKE', $searchParam)
            ->orWhere('diagnose', 'LIKE', $searchParam)
            ->orderByDesc('edatum')
            ->limit(5)
            ->get();

        $items = [];
        foreach ($rows->map(fn ($row) => (array) $row)->all() as $row) {
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
        $documentQuery = fn (bool $withArchiveFilter) => Capsule::table('intra_mitarbeiter_dokumente as d')
            ->leftJoin('intra_dokument_templates as t', 'd.template_id', '=', 't.id')
            ->select(
                'd.id', 'd.docid', 'd.erhalter', 'd.ausstellungsdatum', 'd.profileid',
                'd.aussteller_name', 't.name as template_name'
            )
            ->where(function ($q) use ($searchParam) {
                $q->where('d.erhalter', 'LIKE', $searchParam)
                    ->orWhere('d.docid', 'LIKE', $searchParam)
                    ->orWhere('t.name', 'LIKE', $searchParam)
                    ->orWhere('d.aussteller_name', 'LIKE', $searchParam);
            })
            ->when($withArchiveFilter, fn ($q) => $q->whereRaw('IFNULL(d.is_archived, 0) = 0'))
            ->orderByDesc('d.timestamp')
            ->limit(8);

        try {
            $rows = $documentQuery(true)->get();
        } catch (PDOException) {
            $rows = $documentQuery(false)->get();
        }

        $items = [];
        foreach ($rows->map(fn ($row) => (array) $row)->all() as $row) {
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
            $rows = Capsule::table('intra_dokument_templates')
                ->select(['id', 'name', 'category', 'description'])
                ->where('name', 'LIKE', $searchParam)
                ->orWhere('description', 'LIKE', $searchParam)
                ->orderBy('name')
                ->limit(5)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
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
            $rows = Capsule::table('intra_fahrzeuge')
                ->select(['id', 'identifier', 'name', 'kennzeichen'])
                ->where('identifier', 'LIKE', $searchParam)
                ->orWhere('name', 'LIKE', $searchParam)
                ->orWhere('kennzeichen', 'LIKE', $searchParam)
                ->orderBy('identifier')
                ->limit(5)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
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
                'url'      => 'settings/vehicles/vehicles/index.php',
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
            $rows = Capsule::table('intra_fahrzeuge_defects as d')
                ->join('intra_fahrzeuge as f', 'd.vehicle_id', '=', 'f.id')
                ->select(
                    'd.id', 'd.title', 'd.description', 'd.status', 'd.created_at',
                    'f.name as vehicle_name', 'f.identifier as vehicle_identifier'
                )
                ->where('d.title', 'LIKE', $searchParam)
                ->orWhere('d.description', 'LIKE', $searchParam)
                ->orWhere('f.name', 'LIKE', $searchParam)
                ->orWhere('f.identifier', 'LIKE', $searchParam)
                ->orderByDesc('d.created_at')
                ->limit(5)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
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
                'url'      => 'settings/vehicles/defects/index.php',
            ];
        }
        return $items;
    }
}
