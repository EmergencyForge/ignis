<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers;

use App\Http\Controllers\Controller;

use App\Auth\Gate;
use App\Config\ConfigManager;
use App\Federation\FederationMiddleware;
use Plugin\Enotf\Helpers\EnotfUrl;
use App\Helpers\Flash;
use App\Notifications\NotificationManager;
use App\Support\ListQuery;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Response;
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
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    public function listAction(): void
    {
        $this->requireAuth();
        if (!Gate::allows('enotf.viewAdminList')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $list = ListQuery::fromQuery($_GET, [
            'nr'      => 'enr',
            'patient' => 'patname',
            'created' => 'sendezeit',
            'author'  => 'pfname',
            'status'  => 'protokoll_status',
        ], 'created', 'desc', 20, ['view'], ['id']);
        $segment = in_array($list->filter('view'), ['1', '2'], true) ? (int) $list->filter('view') : 0;

        // Eigene Protokolle ohne ausgeblendete, dazu mit Verbund die gecachten
        // der aktiven Partner (nur lesend). Fehlt einem Verbund-Protokoll ein
        // Feld, gilt es wie bisher als geprüft und freigegeben.
        $db   = Capsule::connection();
        $rows = Capsule::table('intra_edivi')
            ->where('hidden', '<>', 1)
            ->select([
                'id', 'enr', 'patname', 'sendezeit', 'pfname',
                $db->raw('COALESCE(protokoll_status, 0) as protokoll_status'),
                'freigegeben', 'hidden_user', 'bearbeiter', 'freigeber_name',
                $db->raw('NULL as federation_source'),
            ]);

        if ((bool) (new ConfigManager())->get('FEDERATION_ENABLED', FederationMiddleware::isEnabled())) {
            // JSON null kommt als Text 'null' aus JSON_UNQUOTE, NULLIF macht wieder NULL daraus.
            $json = static function (string $key, ?int $default = null) use ($db): \Illuminate\Contracts\Database\Query\Expression {
                $value = "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fce.cached_data, '$.$key')), 'null')";

                return $db->raw(($default === null ? $value : "COALESCE($value, $default)") . " as $key");
            };
            $rows->unionAll(
                Capsule::table('intra_federation_cache_enotf as fce')
                    ->join('intra_federation_links as fl', function ($join) {
                        $join->on('fl.instance_id', '=', 'fce.source_instance_id')
                            ->where('fl.is_active', 1);
                    })
                    ->select([
                        'fce.remote_id as id', $json('enr'), $json('patname'),
                        'fce.protocol_date as sendezeit', $json('pfname'),
                        $json('protokoll_status', 2), $json('freigegeben', 1), $json('hidden_user', 0),
                        $json('bearbeiter'), $json('freigeber_name'),
                        'fl.instance_name as federation_source',
                    ])
            );
        }

        $query = $db->query()->fromSub($rows, 'u');
        if ($list->q !== '') {
            $query->where(function ($q) use ($list) {
                $q->where('enr', 'LIKE', $list->like())
                    ->orWhere('patname', 'LIKE', $list->like())
                    ->orWhere('pfname', 'LIKE', $list->like());
            });
        }

        // Zähler aller drei Segmente in einer Abfrage, mit der Suche. Nicht
        // freigegeben zählt wie die Alarmkachel des Dashboards
        // (App\Support\Overview::openProtocols).
        $counts = (array) (clone $query)->selectRaw(
            'COUNT(*) AS `all`, COALESCE(SUM(protokoll_status IN (0, 1)), 0) AS unprocessed, COALESCE(SUM(freigegeben = 0 AND hidden_user <> 1), 0) AS unreleased'
        )->first();

        if ($segment === 1) {
            $query->whereIn('protokoll_status', [0, 1]);
        } elseif ($segment === 2) {
            $query->where('freigegeben', 0)->where('hidden_user', '<>', 1);
        }

        $this->renderView('enotf/admin/list', [
            'protocols' => $list->paginate($query)->map(fn ($r) => (array) $r)->all(),
            'list'      => $list,
            'segment'   => $segment, // nicht 'view', so heißt schon der Parameter von renderView()
            'counts'    => array_map('intval', $counts),
            'canEdit'   => Gate::allows('enotf.editProtocol'),
        ]);
    }

    public function destroy(): void
    {
        $this->requireAuth();
        if (!Gate::allows('enotf.editProtocol')) {
            Flash::set('error', 'no-permissions');
            throw new \EmergencyForge\Http\Exceptions\RedirectException(EnotfUrl::admin('list'));
        }

        $userid = $_SESSION['userid'];
        $id     = (int) ($_POST['id'] ?? 0);

        // Protocol-Info VOR Delete für Notification
        $protocol = Capsule::table('intra_edivi')
            ->where('id', $id)
            ->select('enr', 'pfname')
            ->first();

        Capsule::table('intra_edivi')
            ->where('id', $id)
            ->update(['hidden' => 1, 'protokoll_status' => 4]);

        Flash::set('edivi', 'deleted');
        $auditLogger = new AuditLogger();
        $auditLogger->log($userid, 'Protokoll gelöscht [ID: ' . $id . ']', null, 'eNOTF', 1);

        // Notification für Protokoll-Autor
        if ($protocol && !empty($protocol->pfname)) {
            try {
                $notificationManager = new NotificationManager();
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

        // Zurück zur Liste mit derselben Suche, Sortierung und Seite. Wie im
        // Posteingang nur auf Adressen der Liste selbst.
        $back = (string) ($_POST['return'] ?? '');
        if (str_starts_with($back, 'enotf/admin/list') && preg_match('~^[a-z][a-z0-9/_-]*(\?[^\s]*)?$~i', $back) === 1) {
            $this->redirect($back);
        }

        // Exception statt header()+exit: der Router schickt den Redirect.
        throw new \EmergencyForge\Http\Exceptions\RedirectException(EnotfUrl::admin('list'));
    }

    public function qmActionsModal(): ?Response
    {
        $this->requireAuth();
        // Ansehen darf, wer die Liste sieht; speichern nur, wer Protokolle bearbeitet.
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        if (!Gate::allows('enotf.viewAdminList') || ($isPost && !Gate::allows('enotf.editProtocol'))) {
            return Response::json(['success' => false, 'message' => 'Keine Berechtigung'], 403);
        }
        return $this->renderView('enotf/admin/qm-actions-modal', []);
    }

    public function qmLogModal(): void
    {
        $this->requireAuth();
        if (!Gate::allows('enotf.viewAdminList')) {
            http_response_code(403);
            exit('Keine Berechtigung');
        }
        $this->renderView('enotf/admin/qm-log-modal', []);
    }

    /**
     * 308-Redirect auf den kanonischen Endpoint `/api/enotf/bulk-delete-empty`.
     * Hält Direktaufrufe an die alte URL am Leben (bewahrt Method + Body).
     */
    public function bulkDeleteEmpty(): void
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        header('Location: ' . $base . 'api/enotf/bulk-delete-empty', true, 308);
        exit;
    }
}
