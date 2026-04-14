<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Jobs\FailedJobsReader;
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

        // POST-Actions für Failed Jobs (retry/delete) — vor JSON-Routing
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handleFailedJobAction();
            return;
        }

        // JSON-API erkennen
        if (
            isset($_GET['id']) ||
            isset($_GET['q']) ||
            isset($_GET['file_tail']) ||
            isset($_GET['recent']) ||
            isset($_GET['stats']) ||
            isset($_GET['failed_jobs'])
        ) {
            $this->serveJson();
            return;
        }

        $reader        = $this->reader();
        $failedReader  = $this->failedJobsReader();

        // Default-View: letzte 200 Errors gruppiert nach Fingerprint + Stats
        $recent = $reader->getRecentErrors(200);
        $groups = $reader->groupByFingerprint($recent);
        $stats  = $reader->getStats();

        // Failed Jobs — zweite Sektion im Fehlerprotokoll
        $failedJobs      = $failedReader->getRecent(100);
        $failedJobsStats = $failedReader->getStats();

        $this->renderView('settings/system/logs', [
            'files'           => $reader->listFiles(),
            'recent'          => $recent,
            'groups'          => $groups,
            'stats'           => $stats,
            'failedJobs'      => $failedJobs,
            'failedJobsStats' => $failedJobsStats,
        ]);
    }

    /**
     * Verarbeitet POST-Actions für Failed Jobs:
     *   - action=retry   &id=<failed_job_id>
     *   - action=delete  &id=<failed_job_id>
     *   - action=retry_all
     *   - action=delete_all
     *
     * Antwortet mit JSON, damit das Frontend-JS nach der Aktion den Inhalt
     * neu laden kann, ohne einen Vollreload der Seite zu triggern.
     */
    private function handleFailedJobAction(): void
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');

        // CSRF-Schutz für state-ändernde Admin-Aktionen. Der Token kommt
        // via Header (X-CSRF-Token) oder POST-Feld (csrf_token).
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!is_string($csrfToken) || $csrfToken === '' || !\App\Security\CsrfProtection::validateToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF-Token ungültig oder abgelaufen']);
            return;
        }

        $reader = $this->failedJobsReader();
        $action = (string) ($_POST['action'] ?? '');
        $id     = (int)    ($_POST['id']     ?? 0);

        try {
            switch ($action) {
                case 'retry':
                    if ($id <= 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
                        return;
                    }
                    $ok = $reader->retry($id);
                    echo json_encode([
                        'success' => $ok,
                        'message' => $ok ? 'Job erneut in die Queue gelegt' : 'Job konnte nicht re-queued werden',
                    ]);
                    return;

                case 'delete':
                    if ($id <= 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
                        return;
                    }
                    $ok = $reader->delete($id);
                    echo json_encode([
                        'success' => $ok,
                        'message' => $ok ? 'Job gelöscht' : 'Job nicht gefunden',
                    ]);
                    return;

                case 'retry_all':
                    $n = $reader->retryAll();
                    echo json_encode([
                        'success' => true,
                        'message' => "$n Jobs erneut in die Queue gelegt",
                        'count'   => $n,
                    ]);
                    return;

                case 'delete_all':
                    $n = $reader->deleteAll();
                    echo json_encode([
                        'success' => true,
                        'message' => "$n Jobs gelöscht",
                        'count'   => $n,
                    ]);
                    return;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
                    return;
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage(),
            ]);
        }
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

            // 4) Recent Errors (mit optionalem Grouping)
            if (isset($_GET['recent'])) {
                $limit    = max(1, min(500, (int) ($_GET['limit'] ?? 100)));
                $grouped  = !empty($_GET['grouped']);
                $minLevel = $_GET['min_level'] ?? null;
                $entries  = $reader->getRecentErrors($limit, false, $minLevel);

                if ($grouped) {
                    echo json_encode([
                        'success' => true,
                        'count'   => count($entries),
                        'groups'  => $reader->groupByFingerprint($entries),
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'success' => true,
                        'count'   => count($entries),
                        'entries' => $entries,
                    ], JSON_UNESCAPED_UNICODE);
                }
                return;
            }

            // 5) Stats
            if (isset($_GET['stats'])) {
                echo json_encode([
                    'success' => true,
                    'stats'   => $reader->getStats(),
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 6) Failed Jobs — Liste + Stats in einem Rutsch
            if (isset($_GET['failed_jobs'])) {
                $failedReader = $this->failedJobsReader();
                $limit = max(1, min(500, (int) ($_GET['limit'] ?? 100)));
                echo json_encode([
                    'success' => true,
                    'jobs'    => $failedReader->getRecent($limit),
                    'stats'   => $failedReader->getStats(),
                    'table_exists' => $failedReader->tableExists(),
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

    private function failedJobsReader(): FailedJobsReader
    {
        return new FailedJobsReader($this->pdo);
    }

    private function ensureAdmin(): void
    {
        if (!Permissions::check('admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index.php');
        }
    }
}
