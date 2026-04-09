<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ValidationException;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Http\Requests\Mitarbeiter\CreateMitarbeiterRequest;
use App\Models\Dienstgrad;
use App\Models\FwQuali;
use App\Models\Mitarbeiter;
use App\Models\RdQuali;
use App\Personnel\PersonalLogManager;
use App\Utils\AuditLogger;

/**
 * MitarbeiterController — Migration des `mitarbeiter/`-Moduls (Phase 2 Welle 3).
 *
 * Wird inkrementell aufgebaut über mehrere Turns:
 *
 *   Turn 1 (jetzt):
 *     index()         — list.php (Übersicht mit Filtern + Create-Modal)
 *     store()         — create.php (AJAX-Endpoint, JSON-Response)
 *     destroy()       — delete.php
 *     deleteComment() — comment-delete.php
 *
 *   Turn 2 (folgt):
 *     show()   — profile.php (Detail-Seite)
 *     update() — POST-Handler für profile.php
 *
 *   Turn 3 (folgt):
 *     showDocument()   — dokument-view.php
 *     deleteDocument() — dokument-delete.php
 */
class MitarbeiterController extends Controller
{
    /**
     * GET /mitarbeiter/list.php — Übersicht aktiver oder archivierter Mitarbeiter.
     *
     * Filter-Logik (aus dem Legacy-Code):
     *   - Es gibt einen "Archiv-Dienstgrad" (intra_mitarbeiter_dienstgrade.archive=1)
     *   - Mitarbeiter mit diesem Dienstgrad gelten als entlassen
     *   - ?archiv → zeige nur Archivierte
     *   - ohne ?archiv → zeige alle aktiven (nicht im Archiv-Dienstgrad)
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('mitarbeiter.viewList', redirectTo: 'index.php');

        $showArchive = isset($_GET['archiv']);

        $archiveDienstgradIds = Dienstgrad::query()
            ->where('archive', 1)
            ->pluck('id')
            ->all();

        $query = Mitarbeiter::query()->with(['dienstgradModel', 'rdQualiModel', 'fwQualiModel']);
        if ($showArchive) {
            $query->archived($archiveDienstgradIds);
        } else {
            $query->active($archiveDienstgradIds);
        }
        $mitarbeiter = $query->orderBy('einstdatum')->get();

        $dienstgrade = Dienstgrad::active()->get();
        $rdQualis    = RdQuali::query()->orderBy('priority')->get();
        $fwQualis    = FwQuali::query()->orderBy('priority')->get();

        $this->renderView('mitarbeiter/list', [
            'mitarbeiter' => $mitarbeiter,
            'dienstgrade' => $dienstgrade,
            'rdQualis'    => $rdQualis,
            'fwQualis'    => $fwQualis,
            'showArchive' => $showArchive,
        ]);
    }

    /**
     * POST /mitarbeiter/create.php — AJAX-Endpoint zum Anlegen eines Mitarbeiters.
     * Antwortet IMMER mit JSON.
     *
     * Reaktion zu Legacy-Verhalten 1:1:
     *   - GET-Request → Redirect zur Liste
     *   - POST ohne Permission → 403 JSON
     *   - POST mit invaliden Daten → success=false JSON
     *   - POST erfolgreich → success=true + redirect-URL
     */
    public function store(): void
    {
        $this->requireAuth();

        if (\App\Auth\Gate::denies('mitarbeiter.create')) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Keine Berechtigung'], 403);
            }
            $this->redirect('index.php');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('mitarbeiter/list.php');
        }

        try {
            $data = CreateMitarbeiterRequest::validate($_POST);
        } catch (ValidationException $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->firstError() ?? 'Ungültige Eingabe.',
            ]);
        }

        // Conditional Charakter-ID-Pflicht: nur wenn CHAR_ID-Konstante aktiv ist
        if (defined('CHAR_ID') && CHAR_ID && $data['charakterid'] === '') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Bitte alle erforderlichen Felder ausfüllen.',
            ]);
        }

        // Dienstnummer-Eindeutigkeit prüfen
        if (Mitarbeiter::query()->where('dienstnr', $data['dienstnr'])->exists()) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Diese Dienstnummer ist bereits vergeben.',
            ]);
        }

        // Default-Quali-IDs ("Keine"-Einträge)
        $defaultRdQualiId = RdQuali::query()->where('none', 1)->value('id') ?? 0;
        $defaultFwQualiId = FwQuali::query()->where('none', 1)->value('id') ?? 0;

        $mitarbeiter = new Mitarbeiter();
        $mitarbeiter->fullname    = $data['fullname'];
        $mitarbeiter->gebdatum    = $data['gebdatum'];
        $mitarbeiter->dienstgrad  = $data['dienstgrad'];
        $mitarbeiter->geschlecht  = $data['geschlecht'];
        $mitarbeiter->discordtag  = $data['discordtag'];
        $mitarbeiter->telefonnr   = $data['telefonnr'];
        $mitarbeiter->dienstnr    = $data['dienstnr'];
        $mitarbeiter->einstdatum  = $data['einstdatum'];
        $mitarbeiter->qualifw2    = $defaultFwQualiId;
        $mitarbeiter->qualird     = $defaultRdQualiId;
        if (defined('CHAR_ID') && CHAR_ID) {
            $mitarbeiter->charakterid = $data['charakterid'];
        }

        try {
            $mitarbeiter->save();
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage(),
            ]);
        }

        // Personal-Log + Audit-Log
        $userHelper = new UserHelper($this->pdo);
        $edituser   = $userHelper->getCurrentUserFullnameForAction();

        (new PersonalLogManager($this->pdo))->logProfileCreation((int) $mitarbeiter->id, $edituser);
        (new AuditLogger($this->pdo))->log(
            (int) $_SESSION['userid'],
            'Mitarbeiter erstellt',
            'Name: ' . $data['fullname'] . ', Dienstnummer: ' . $data['dienstnr'],
            'Mitarbeiter',
            1
        );

        $this->jsonResponse([
            'success'  => true,
            'message'  => 'Mitarbeiter erfolgreich erstellt!',
            'redirect' => BASE_PATH . 'mitarbeiter/profile.php?id=' . (int) $mitarbeiter->id . '&new_created=1',
        ]);
    }

    /**
     * GET /mitarbeiter/delete.php?id=X — Mitarbeiter komplett löschen.
     */
    public function destroy(): void
    {
        $this->requireAuth();
        $this->ensure('mitarbeiter.delete', redirectTo: 'mitarbeiter/list.php');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'invalid-id');
            $this->redirect('mitarbeiter/list.php');
        }

        $deleted = Mitarbeiter::query()->where('id', $id)->delete();

        if ($deleted > 0) {
            Flash::set('personal', 'deleted');
            (new AuditLogger($this->pdo))->log(
                (int) $_SESSION['userid'],
                'Mitarbeiter gelöscht [ID: ' . $id . ']',
                null,
                'Mitarbeiter',
                1
            );
        }

        $this->redirect('mitarbeiter/list.php');
    }

    /**
     * GET /mitarbeiter/comment-delete.php?id=X&pid=Y — Personal-Log-Eintrag löschen.
     */
    public function deleteComment(): void
    {
        $this->requireAuth();
        $this->ensure('mitarbeiter.deleteComments', redirectTo: 'benutzer/list.php');

        $logId = (int) ($_GET['id'] ?? 0);
        if ($logId <= 0) {
            Flash::set('error', 'invalid-id');
            $this->redirectBackOrIndex();
        }

        (new PersonalLogManager($this->pdo))->deleteEntry($logId);
        (new AuditLogger($this->pdo))->log(
            (int) $_SESSION['userid'],
            'Profil-Kommentar gelöscht [ID: ' . $logId . ']',
            null,
            'Mitarbeiter',
            1
        );

        $this->redirectBackOrIndex();
    }

    // -----------------------------------------------------------------------
    //  Mitarbeiter-spezifische Helpers
    // -----------------------------------------------------------------------

    /**
     * Antwortet mit einer JSON-Response und exit(). Wird vom AJAX-Endpoint
     * store() benutzt — der Legacy-Code hat hier ebenfalls IMMER JSON
     * zurückgegeben, daher ist das kein Render-View-Fall.
     *
     * @param array<string,mixed> $payload
     */
    private function jsonResponse(array $payload, int $httpCode = 200): never
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect zum HTTP-Referer (wo der Klick herkam) oder Fallback zur
     * Index-Page. Wird von deleteComment() benutzt, weil der Comment auf
     * verschiedenen Pages stehen kann.
     */
    private function redirectBackOrIndex(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '') {
            header('Location: ' . $referer);
            exit;
        }
        $this->redirect('index.php');
    }
}
