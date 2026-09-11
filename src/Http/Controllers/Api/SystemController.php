<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Search\SearchRegistry;
use App\Utils\AuditLogger;
use App\Utils\SystemUpdater;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * System-Admin-API: Composer-Status, Performance-Metrics, API-Key-Regeneration,
 * User-Theme-Config, globale Suche über alle Module (App\Search).
 *
 * Alle Endpoints erfordern Session-Auth plus je nach Aktion spezifische
 * Permissions. Die Methoden-Kommentare dokumentieren die erforderlichen
 * Permissions.
 */
final class SystemController
{
    public function __construct(private readonly SearchRegistry $search)
    {
    }

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
     * Antwort für die Palette (assets/js/ui/palette.js): eine Gruppe je
     * Quelle, die der Nutzer sehen darf und die etwas gefunden hat, mit
     * höchstens fünf Treffern. Welche Quellen es gibt, weiß die
     * App\Search\SearchRegistry: die des Kerns plus die der aktiven Plugins
     * aus deren Manifest. Unter zwei Zeichen kommt nichts.
     *
     *     { "q": "must", "results": [
     *         { "key": "personnel", "label": "Mitarbeiter",
     *           "items": [ { "label": "Max Mustermann", "sub": "Dienstnr. RD-01", "href": "/personnel/profile?id=3" } ] }
     *     ] }
     */
    public function globalSearch(Request $request): Response
    {
        $query = trim((string) ($request->query['q'] ?? ''));

        try {
            return Response::json([
                'q'       => $query,
                'results' => $this->search->run($query),
            ]);
        } catch (\Throwable $e) {
            Logger::error('System: global-search Fehler', ['error' => $e->getMessage(), 'query' => $query]);
            return Response::json(['error' => 'Datenbankfehler'], 500);
        }
    }
}
