<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Logging\LogReader;
use App\Logging\Logger;

/**
 * LogsController — Admin-Lookup für Error-IDs.
 *
 * Bietet eine Suchoberfläche, mit der ein Admin per Error-ID (8-stelliger
 * Hex-Code aus der Production-Fehlerseite) den vollständigen Stack-Trace
 * inkl. File/Line/Context aufrufen kann — ohne Server-Filesystem-Zugriff.
 *
 * Endpoints:
 *   GET  /settings/system/logs.php          → Such-UI (HTML)
 *   GET  /settings/system/logs.php?id=...   → JSON: ein konkreter Eintrag
 *   GET  /settings/system/logs.php?q=...    → JSON: Volltext-Suche
 *   GET  /settings/system/logs.php?file=... → JSON: Tail eines Files
 */
class LogsController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        // JSON-API erkennen
        if (
            isset($_GET['id']) ||
            isset($_GET['q']) ||
            isset($_GET['file_tail'])
        ) {
            $this->serveJson();
            return;
        }

        $reader = $this->reader();

        $this->renderView('settings/system/logs', [
            'files' => $reader->listFiles(),
        ]);
    }

    private function serveJson(): void
    {
        // Output-Buffer säubern, damit kein Render-Junk vor dem JSON landet
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        $reader = $this->reader();

        try {
            // 1) Detail-Lookup nach Error-ID
            if (isset($_GET['id'])) {
                $entry = $reader->findByErrorId((string) $_GET['id']);
                if ($entry === null) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Keine Einträge zu dieser Error-ID gefunden.',
                    ], JSON_UNESCAPED_UNICODE);
                    return;
                }
                echo json_encode([
                    'success' => true,
                    'entry'   => $entry,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 2) Volltext-Suche
            if (isset($_GET['q'])) {
                $query   = (string) $_GET['q'];
                $limit   = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
                $filters = [
                    'file'  => $_GET['file'] ?? null,
                    'level' => $_GET['level'] ?? null,
                    'since' => $_GET['since'] ?? null,
                ];
                $results = $reader->search($query, $limit, $filters);
                echo json_encode([
                    'success' => true,
                    'count'   => count($results),
                    'results' => $results,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 3) Tail eines Files
            if (isset($_GET['file_tail'])) {
                $file  = (string) $_GET['file_tail'];
                $lines = max(10, min(500, (int) ($_GET['lines'] ?? 100)));
                $entries = $reader->tail($file, $lines);
                echo json_encode([
                    'success' => true,
                    'count'   => count($entries),
                    'entries' => $entries,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Fehler beim Lesen der Logs: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function reader(): LogReader
    {
        return new LogReader(Logger::getLogPath());
    }

    private function ensureAdmin(): void
    {
        if (!Permissions::check('admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index.php');
        }
    }
}
