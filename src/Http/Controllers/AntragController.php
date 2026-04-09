<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Exceptions\ValidationException;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Http\Requests\Antraege\DecideAntragRequest;
use App\Models\Antrag;
use App\Models\AntragData;
use App\Models\AntragField;
use App\Models\AntragTyp;
use App\Notifications\NotificationManager;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;

/**
 * AntragController — Migration des `antrag/`-Moduls (Phase 2 Welle 2).
 *
 * URL-Mapping:
 *   GET  antrag/select.php             → selectType()
 *   GET  antrag/create.php?typ=X       → create()
 *   POST antrag/create.php             → store()
 *   GET  antrag/view.php?antrag=X      → view()
 *   GET  antrag/admin/list.php         → adminList()
 *   GET  antrag/admin/view.php?antrag=X → adminView()
 *   POST antrag/admin/view.php         → decide()
 *
 * Mitarbeiter-Daten (für Auto-Fill in create() und name_dn-Bildung in store())
 * werden via Capsule Query Builder geladen — kein eigenes Mitarbeiter-Model
 * in dieser Phase, das kommt erst in einer späteren Welle.
 */
class AntragController
{
    /** @var array<int,array{class:string,text:string,icon:string}> */
    private const STATUS_DISPLAY = [
        Antrag::STATUS_IN_PROGRESS => ['class' => 'info',    'text' => 'In Bearbeitung', 'icon' => 'fa-regular fa-clock'],
        Antrag::STATUS_REJECTED    => ['class' => 'danger',  'text' => 'Abgelehnt',      'icon' => 'fa-solid fa-circle-xmark'],
        Antrag::STATUS_DEFERRED    => ['class' => 'warning', 'text' => 'Aufgeschoben',   'icon' => 'fa-solid fa-circle-pause'],
        Antrag::STATUS_ACCEPTED    => ['class' => 'success', 'text' => 'Angenommen',     'icon' => 'fa-solid fa-circle-check'],
    ];

    public function __construct(
        private PDO $pdo,
    ) {}

    // -----------------------------------------------------------------------
    //  Public Routes
    // -----------------------------------------------------------------------

    /**
     * GET /antrag/select.php — Liste der aktiven Antragstypen als Karten.
     */
    public function selectType(): void
    {
        $this->requireAuth();

        $typen = AntragTyp::query()
            ->active()
            ->withCount('felder')
            ->get();

        $this->renderView('antraege/select', [
            'typen' => $typen,
        ]);
    }

    /**
     * GET /antrag/create.php?typ=X — Form-Renderer für einen Antragstyp.
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->ensure('antrag.create');

        $mitarbeiter = $this->loadCurrentMitarbeiter();
        if ($mitarbeiter === null) {
            Flash::set('error', 'Kein Mitarbeiterprofil für Ihre Discord-ID gefunden.');
            $this->redirect('index.php');
        }

        $typId = (int) ($_GET['typ'] ?? 0);
        if ($typId <= 0) {
            Flash::set('error', 'Kein Antragstyp ausgewählt.');
            $this->redirect('index.php');
        }

        /** @var AntragTyp|null $typ */
        $typ = AntragTyp::query()->where('id', $typId)->where('aktiv', 1)->first();
        if ($typ === null) {
            Flash::set('error', 'Antragstyp nicht gefunden oder nicht aktiv.');
            $this->redirect('index.php');
        }

        $felder = AntragField::query()
            ->where('antragstyp_id', $typId)
            ->orderBy('sortierung')
            ->get();

        $this->renderView('antraege/create', [
            'typ'         => $typ,
            'felder'      => $felder,
            'mitarbeiter' => $mitarbeiter,
        ]);
    }

    /**
     * POST /antrag/create.php — Antrag einreichen, Daten in Transaction speichern.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->ensure('antrag.create');

        $mitarbeiter = $this->loadCurrentMitarbeiter();
        if ($mitarbeiter === null) {
            Flash::set('error', 'Kein Mitarbeiterprofil für Ihre Discord-ID gefunden.');
            $this->redirect('index.php');
        }

        $typId = (int) ($_GET['typ'] ?? 0);
        if ($typId <= 0) {
            Flash::set('error', 'Kein Antragstyp ausgewählt.');
            $this->redirect('index.php');
        }

        /** @var AntragTyp|null $typ */
        $typ = AntragTyp::query()->where('id', $typId)->where('aktiv', 1)->first();
        if ($typ === null) {
            Flash::set('error', 'Antragstyp nicht gefunden oder nicht aktiv.');
            $this->redirect('index.php');
        }

        $felder = AntragField::query()
            ->where('antragstyp_id', $typId)
            ->orderBy('sortierung')
            ->get();

        // Eindeutige Public-ID generieren (6 Stellen)
        do {
            $uniqueId = (string) random_int(100000, 999999);
        } while (Antrag::query()->where('uniqueid', $uniqueId)->exists());

        try {
            Capsule::connection()->transaction(function () use ($typ, $felder, $mitarbeiter, $uniqueId): void {
                $antrag = new Antrag();
                $antrag->uniqueid      = $uniqueId;
                $antrag->antragstyp_id = $typ->id;
                $antrag->name_dn       = $mitarbeiter->fullname . ' (' . $mitarbeiter->dienstnr . ')';
                $antrag->dienstgrad    = $mitarbeiter->dienstgrad_name ?? null;
                $antrag->discordid     = $_SESSION['discordtag'] ?? null;
                $antrag->cirs_status   = Antrag::STATUS_IN_PROGRESS;
                $antrag->save();

                foreach ($felder as $feld) {
                    $data = new AntragData();
                    $data->antrag_id = $antrag->id;
                    $data->feldname  = $feld->feldname;
                    $data->wert      = (string) ($_POST[$feld->feldname] ?? '');
                    $data->save();
                }
            });
        } catch (\Throwable $e) {
            Flash::set('error', 'Fehler beim Speichern: ' . $e->getMessage());
            $this->redirect('antrag/create.php?typ=' . $typId);
        }

        Flash::set('success', 'Antrag erfolgreich eingereicht!');
        $this->redirect('antrag/view.php?antrag=' . $uniqueId);
    }

    /**
     * GET /antrag/view.php?antrag=X — Detailansicht eines Antrags.
     */
    public function view(): void
    {
        $this->requireAuth();

        $caseId = (string) ($_GET['antrag'] ?? '');
        if ($caseId === '') {
            Flash::set('error', 'Keine Antragsnummer angegeben.');
            $this->redirect('index.php');
        }

        /** @var Antrag|null $antrag */
        $antrag = Antrag::query()
            ->with(['typ', 'daten'])
            ->where('uniqueid', $caseId)
            ->first();

        if ($antrag === null) {
            Flash::set('error', 'Antrag nicht gefunden.');
            $this->redirect('index.php');
        }

        if (Gate::denies('antrag.view', $antrag)) {
            Flash::set('error', 'Sie haben keine Berechtigung, diesen Antrag anzusehen.');
            $this->redirect('index.php');
        }

        $felderMitWerten = $this->loadFieldsWithValues($antrag);

        $this->renderView('antraege/view', [
            'antrag'           => $antrag,
            'felderMitWerten'  => $felderMitWerten,
            'currentStatus'    => self::STATUS_DISPLAY[$antrag->cirs_status] ?? ['class' => 'dark', 'text' => 'Unbekannt', 'icon' => 'fa-solid fa-circle-question'],
        ]);
    }

    /**
     * GET /antrag/admin/list.php — Admin-Übersicht aller Anträge.
     */
    public function adminList(): void
    {
        $this->requireAuth();
        $this->ensure('antrag.viewAny', redirectTo: 'index.php');

        $antraege = Antrag::query()
            ->with('typ')
            ->orderBy('time_added', 'desc')
            ->get();

        $this->renderView('antraege/admin/list', [
            'antraege'      => $antraege,
            'statusDisplay' => self::STATUS_DISPLAY,
        ]);
    }

    /**
     * GET /antrag/admin/view.php?antrag=X — Admin-Detailansicht mit Status-Form.
     */
    public function adminView(): void
    {
        $this->requireAuth();
        $this->ensure('antrag.decide', redirectTo: 'index.php');

        $caseId = (string) ($_GET['antrag'] ?? '');
        if ($caseId === '') {
            Flash::set('error', 'Keine Antragsnummer angegeben.');
            $this->redirect('antrag/admin/list.php');
        }

        /** @var Antrag|null $antrag */
        $antrag = Antrag::query()
            ->with(['typ', 'daten'])
            ->where('uniqueid', $caseId)
            ->first();

        if ($antrag === null) {
            Flash::set('error', 'Antrag nicht gefunden.');
            $this->redirect('antrag/admin/list.php');
        }

        $felderMitWerten = $this->loadFieldsWithValues($antrag);
        $userHelper      = new UserHelper($this->pdo);

        $this->renderView('antraege/admin/view', [
            'antrag'             => $antrag,
            'felderMitWerten'    => $felderMitWerten,
            'currentStatus'      => self::STATUS_DISPLAY[$antrag->cirs_status] ?? ['class' => 'dark', 'text' => 'Unbekannt', 'icon' => 'fa-solid fa-circle-question'],
            'currentUserFullname' => $userHelper->getCurrentUserFullnameForAction(),
        ]);
    }

    /**
     * POST /antrag/admin/view.php — Status-Änderung durch Bearbeiter.
     * Schreibt Audit-Log-Einträge für jede einzelne Änderung und sendet eine
     * Notification an den Antragsteller.
     */
    public function decide(): void
    {
        $this->requireAuth();
        $this->ensure('antrag.decide', redirectTo: 'index.php');

        $caseId = (string) ($_GET['antrag'] ?? '');
        if ($caseId === '') {
            Flash::set('error', 'Keine Antragsnummer angegeben.');
            $this->redirect('antrag/admin/list.php');
        }

        /** @var Antrag|null $antrag */
        $antrag = Antrag::query()->where('uniqueid', $caseId)->first();
        if ($antrag === null) {
            Flash::set('error', 'Antrag nicht gefunden.');
            $this->redirect('antrag/admin/list.php');
        }

        try {
            $data = DecideAntragRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('antrag/admin/view.php?antrag=' . $caseId);
        }

        $userHelper       = new UserHelper($this->pdo);
        $newCirsManager   = $userHelper->getCurrentUserFullnameForAction();
        $currentUserId    = (int) $_SESSION['userid'];
        $auditLogger      = new AuditLogger($this->pdo);

        // Diff-Audit: nur tatsächliche Änderungen loggen
        if ($antrag->cirs_manager !== $newCirsManager) {
            $auditLogger->log($currentUserId, 'Bearbeiter geändert [ID: ' . $caseId . ']', $newCirsManager, 'Anträge', 1);
        }
        if ($antrag->cirs_status !== $data['cirs_status']) {
            $auditLogger->log($currentUserId, 'Status geändert [ID: ' . $caseId . ']', 'Neuer Status: ' . $data['cirs_status'], 'Anträge', 1);
        }
        if (($antrag->cirs_text ?? '') !== $data['cirs_text']) {
            $auditLogger->log($currentUserId, 'Bemerkung geändert [ID: ' . $caseId . ']', '"' . $data['cirs_text'] . '"', 'Anträge', 1);
        }

        $antrag->cirs_manager = $newCirsManager;
        $antrag->cirs_status  = $data['cirs_status'];
        $antrag->cirs_text    = $data['cirs_text'];
        $antrag->cirs_time    = new \DateTime();
        $antrag->save();

        // Notification an den Antragsteller
        $notificationManager = new NotificationManager($this->pdo);
        $statusName          = Antrag::STATUS_LABELS[$data['cirs_status']] ?? 'Unbekannt';
        if ($antrag->discordid !== null && $antrag->discordid !== '') {
            $userId = $notificationManager->getUserIdByDiscordTag($antrag->discordid);
            if ($userId) {
                $notificationManager->create(
                    $userId,
                    'antrag',
                    "Ihr Antrag #{$caseId} wurde bearbeitet",
                    "Status: {$statusName}. Bearbeiter: {$newCirsManager}",
                    BASE_PATH . "antrag/view.php?antrag={$caseId}"
                );
            }
        }

        Flash::set('success', 'Antrag erfolgreich aktualisiert');
        $this->redirect('antrag/view.php?antrag=' . $caseId);
    }

    // -----------------------------------------------------------------------
    //  Private Helpers
    // -----------------------------------------------------------------------

    /**
     * Lädt das Mitarbeiter-Profil zum aktuellen Discord-Tag aus der Session.
     * Returns null wenn keine Discord-Session, kein Profil oder archivierter Dienstgrad.
     *
     * Bewusst via Capsule (kein Mitarbeiter-Model in dieser Phase) — der
     * geschlechts-bedingte Dienstgrad-Name ist sehr Mitarbeiter-spezifisch
     * und gehört eigentlich in das Mitarbeiter-Modul, wenn das migriert wird.
     */
    private function loadCurrentMitarbeiter(): ?\stdClass
    {
        $discordTag = $_SESSION['discordtag'] ?? null;
        if ($discordTag === null || $discordTag === '') {
            return null;
        }

        $row = Capsule::table('intra_mitarbeiter as m')
            ->leftJoin('intra_mitarbeiter_dienstgrade as dg', 'm.dienstgrad', '=', 'dg.id')
            ->where('m.discordtag', $discordTag)
            ->where('dg.archive', 0)
            ->select(
                'm.fullname',
                'm.dienstnr',
                'm.geschlecht',
                'm.discordtag',
                Capsule::raw("CASE WHEN m.geschlecht = 1 THEN dg.name_m ELSE dg.name_w END AS dienstgrad_name")
            )
            ->first();

        return $row ?: null;
    }

    /**
     * Joint die Field-Definitionen mit den eingegebenen Werten für die View.
     * Returns Array von stdClass-Rows mit allen Field-Spalten + 'wert'.
     *
     * @return array<int,\stdClass>
     */
    private function loadFieldsWithValues(Antrag $antrag): array
    {
        return Capsule::table('intra_antrag_felder as af')
            ->leftJoin('intra_antraege_daten as ad', function ($join) use ($antrag) {
                $join->on('af.feldname', '=', 'ad.feldname')
                     ->where('ad.antrag_id', '=', $antrag->id);
            })
            ->where('af.antragstyp_id', $antrag->antragstyp_id)
            ->orderBy('af.sortierung')
            ->select('af.*', 'ad.wert')
            ->get()
            ->all();
    }

    // -----------------------------------------------------------------------
    //  Auth/Render Helpers — duplizieren aktuell zu User/Role/Antrag, wird in
    //  Phase 4+ in eine Controller-Base extrahiert.
    // -----------------------------------------------------------------------

    private function requireAuth(): void
    {
        if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }
    }

    private function ensure(string $ability, mixed $resource = null, string $redirectTo = 'index.php'): void
    {
        if (Gate::denies($ability, $resource)) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirectTo);
        }
    }

    private function redirect(string $relativePath): never
    {
        header('Location: ' . BASE_PATH . $relativePath);
        exit;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderView(string $view, array $data = []): void
    {
        $templatePath = dirname(__DIR__, 3) . '/templates/' . $view . '.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException("View not found: $view ($templatePath)");
        }
        $pdo = $this->pdo;
        extract($data, EXTR_SKIP);
        require $templatePath;
    }
}
