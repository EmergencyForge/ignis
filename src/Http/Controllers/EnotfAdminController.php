<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Flash;
use App\Notifications\NotificationManager;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * EnotfAdminController — eNOTF Admin/QM-Bereich.
 *
 * Verwaltet:
 *   - list.php — Protokollübersicht für QM
 *   - delete.php — Protokoll ausblenden (hidden=1, status=4)
 *   - qm-actions-modal.php — AJAX-Endpoint für QM-Status-Updates
 *   - qm-log-modal.php — AJAX-Endpoint für Log-Anzeige
 *   - bulk-delete-empty.php — leitet weiter an api/enotf/bulk-delete-empty.php
 */
class EnotfAdminController extends Controller
{
    public function listAction(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'edivi.view'])) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index.php');
        }

        $this->renderView('enotf/admin/list', []);
    }

    public function destroy(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'edivi.edit'])) {
            Flash::set('error', 'no-permissions');
            header('Location: ' . EnotfUrl::admin('list'));
            exit;
        }

        $userid = $_SESSION['userid'];
        $id     = (int) ($_GET['id'] ?? 0);

        // Protocol-Info VOR Delete für Notification
        $protocol = Capsule::table('intra_edivi')
            ->where('id', $id)
            ->select('enr', 'pfname')
            ->first();

        Capsule::table('intra_edivi')
            ->where('id', $id)
            ->update(['hidden' => 1, 'protokoll_status' => 4]);

        Flash::set('edivi', 'deleted');
        $auditLogger = new AuditLogger($this->pdo);
        $auditLogger->log($userid, 'Protokoll gelöscht [ID: ' . $id . ']', null, 'eNOTF', 1);

        // Notification für Protokoll-Autor
        if ($protocol && !empty($protocol->pfname)) {
            try {
                $notificationManager = new NotificationManager($this->pdo);
                $authorUserId = $notificationManager->getUserIdByFullname($protocol->pfname);
                if ($authorUserId) {
                    $notificationManager->create(
                        $authorUserId,
                        'protokoll',
                        "Ihr Protokoll #{$protocol->enr} wurde ausgeblendet",
                        'Das Protokoll wurde vom QM-Team ausgeblendet.',
                        EnotfUrl::page('overview')
                    );
                }
            } catch (\Exception $e) {
                error_log('Failed to create notification for deleted protocol: ' . $e->getMessage());
            }
        }

        header('Location: ' . EnotfUrl::admin('list'));
        exit;
    }

    public function qmActionsModal(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'edivi.view'])) {
            http_response_code(403);
            exit(json_encode(['success' => false, 'message' => 'Keine Berechtigung']));
        }
        $this->renderView('enotf/admin/qm-actions-modal', []);
    }

    public function qmLogModal(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'edivi.view'])) {
            http_response_code(403);
            exit('Keine Berechtigung');
        }
        $this->renderView('enotf/admin/qm-log-modal', []);
    }

    /**
     * Stub-Wrapper für bulk-delete-empty — Aufruf läuft seit dem Legacy-
     * Cleanup direkt über den Router an `/api/enotf/bulk-delete-empty`.
     * Diese Methode bleibt erhalten, damit bestehende Direktaufrufe
     * per HTTP 308 auf die kanonische URL redirectet werden, statt
     * einen Fatal Error zu produzieren.
     */
    public function bulkDeleteEmpty(): void
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        header('Location: ' . $base . 'api/enotf/bulk-delete-empty', true, 308);
        exit;
    }
}
