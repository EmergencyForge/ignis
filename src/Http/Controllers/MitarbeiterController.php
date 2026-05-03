<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ValidationException;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Http\Requests\Mitarbeiter\CreateDocumentRequest;
use App\Http\Requests\Mitarbeiter\CreateMitarbeiterRequest;
use App\Http\Requests\Mitarbeiter\UpdateMitarbeiterRequest;
use App\Models\Dienstgrad;
use App\Models\FwQuali;
use App\Models\Mitarbeiter;
use App\Models\MitarbeiterDokument;
use App\Models\RdQuali;
use App\Notifications\NotificationManager;
use App\Personnel\PersonalLogManager;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * MitarbeiterController — Personalverwaltung (Mitarbeiter-Modul).
 */
class MitarbeiterController extends Controller
{
    /**
     * GET /mitarbeiter/list.php — Übersicht aktiver oder archivierter Mitarbeiter.
     *
     * Filter-Logik:
     *   - Es gibt einen "Archiv-Dienstgrad" (intra_mitarbeiter_dienstgrade.archive=1)
     *   - Mitarbeiter mit diesem Dienstgrad gelten als entlassen
     *   - ?archiv → zeige nur Archivierte
     *   - ohne ?archiv → zeige alle aktiven (nicht im Archiv-Dienstgrad)
     */
    public function index(): void
    {

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
     * GET /mitarbeiter/profile.php?id=X — Mitarbeiter-Detail mit Inline-Editor,
     * Kommentaren, Logs, Dokumenten und Fachdienste-Modal.
     *
     * Die View bindet eine Reihe alter Partials ein (assets/components/profiles/*),
     * die als lokale Variablen im Scope $row, $dginfo, $rdginfo, $fwginfo,
     * $geburtstag, $einstellungsdatum, $bfqualtext, $dienstgradText, $rdqualtext,
     * $accountStatus, $panelakte, $pendingInvite und $pdo erwarten. Wir bauen
     * diesen Scope-Vertrag explizit auf, damit die Partials weiter funktionieren
     * ohne sie selbst migrieren zu müssen.
     */
    /**
     * Liefert das Hover-Card-Fragment (HTML) für einen Mitarbeiter.
     * Wird vom JS-Modul user-hover-card.js per fetch geholt und in eine
     * ignis-popover-Instanz geschoben. Auth: gleiche Sicht-Permission
     * wie die Mitarbeiter-Liste.
     */
    public function card(\App\Http\Request $request, string $id): \App\Http\Response
    {
        $this->requireAuth();
        \App\Auth\Gate::authorize('mitarbeiter.viewList');

        $idInt = (int) $id;
        if ($idInt <= 0) {
            return \App\Http\Response::html('Ungültige ID.', 400);
        }

        /** @var Mitarbeiter|null $mitarbeiter */
        $mitarbeiter = Mitarbeiter::query()
            ->with(['dienstgradModel', 'rdQualiModel', 'fwQualiModel'])
            ->find($idInt);

        if ($mitarbeiter === null) {
            return \App\Http\Response::html('Mitarbeiter nicht gefunden.', 404);
        }

        return $this->renderMitarbeiterCard($mitarbeiter);
    }

    /**
     * GET /api/mitarbeiter/by-dienstnr/{nr}/card — Hover-Card-Fragment
     * gelookupt per Dienstnummer statt per Datenbank-ID.
     *
     * Verwendung: Templates rendern Dienstnr-Strings (z.B. Tabellen,
     * Einsatz-Listen) oft ohne ID — die Card wird dann ueber das Attribut
     * `data-dienstnr-card="042"` getriggert (siehe user-hover-card.js).
     * Selber Output wie `card()`, einfach ein zweiter Lookup-Pfad.
     */
    public function cardByDienstnr(\App\Http\Request $request, string $nr): \App\Http\Response
    {
        $this->requireAuth();
        \App\Auth\Gate::authorize('mitarbeiter.viewList');

        $dienstnr = trim($nr);
        if ($dienstnr === '') {
            return \App\Http\Response::html('Ungültige Dienstnummer.', 400);
        }

        /** @var Mitarbeiter|null $mitarbeiter */
        $mitarbeiter = Mitarbeiter::query()
            ->with(['dienstgradModel', 'rdQualiModel', 'fwQualiModel'])
            ->where('dienstnr', $dienstnr)
            ->first();

        if ($mitarbeiter === null) {
            return \App\Http\Response::html('Dienstnummer nicht gefunden.', 404);
        }

        return $this->renderMitarbeiterCard($mitarbeiter);
    }

    private function renderMitarbeiterCard(Mitarbeiter $mitarbeiter): \App\Http\Response
    {
        $profileUrl = (defined('BASE_PATH') ? BASE_PATH : '/') . 'mitarbeiter/profile?id=' . (int) $mitarbeiter->id;

        ob_start();
        include __DIR__ . '/../../../assets/components/profiles/_hover-card.php';
        return \App\Http\Response::html((string) ob_get_clean());
    }

    public function show(): void
    {

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'invalid-id');
            $this->redirect('index');
        }

        /** @var Mitarbeiter|null $mitarbeiter */
        $mitarbeiter = Mitarbeiter::query()
            ->with(['dienstgradModel', 'rdQualiModel', 'fwQualiModel'])
            ->find($id);

        if ($mitarbeiter === null) {
            Flash::set('error', 'not-found');
            $this->redirect('mitarbeiter/list');
        }

        // Account-Status für die Status-Card oben in der View ermitteln
        // (verlinkter User, Pending-Invite, oder kein Konto)
        $accountStatus = 'none';
        $panelakte     = null;
        $pendingInvite = null;

        if (!empty($mitarbeiter->discordtag)) {
            $userRow = Capsule::table('intra_users as u')
                ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
                ->where('u.discord_id', $mitarbeiter->discordtag)
                ->select(
                    'u.id',
                    'u.username',
                    Capsule::raw('COALESCE(m.fullname, u.fullname) as fullname'),
                    'u.aktenid',
                    'u.is_active'
                )
                ->first();

            if ($userRow) {
                $panelakte     = (array) $userRow;
                $accountStatus = $userRow->is_active ? 'active' : 'inactive';
            } else {
                // Pending Registration-Code mit Label = Mitarbeiter-Name?
                $pending = Capsule::table('intra_registration_codes')
                    ->where('is_used', 0)
                    ->where('label', 'like', '%' . $mitarbeiter->fullname . '%')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', Capsule::raw('NOW()'));
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(1)
                    ->first();

                if ($pending) {
                    $pendingInvite = (array) $pending;
                    $accountStatus = 'pending';
                }
            }
        }

        // Scope-Variablen für die Partials — die greifen via $row['fullname']
        // etc. zu, statt aufs Eloquent-Model.
        $row = $mitarbeiter->getAttributes();

        $dginfo  = $mitarbeiter->dienstgradModel?->getAttributes() ?? [];
        $rdginfo = $mitarbeiter->rdQualiModel?->getAttributes() ?? ['none' => 1];
        $fwginfo = $mitarbeiter->fwQualiModel?->getAttributes() ?? ['none' => 1, 'shortname' => '-'];

        $bfqualtext = $fwginfo['shortname'] ?? '-';
        $dienstgradText = $mitarbeiter->dienstgradLabel();
        $rdqualtext     = $mitarbeiter->rdQualiLabel();

        $geburtstag        = $mitarbeiter->gebdatum?->format('d.m.Y') ?? '';
        $einstellungsdatum = $mitarbeiter->einstdatum?->format('d.m.Y') ?? '';

        // Legacy-Scope-Variablen für die Partials in assets/components/profiles/:
        //   $openedID    — die ID des angezeigten Profils (auch für hidden inputs in modals)
        //   $editdg      — Dienstgrad-ID des aktuell EINGELOGGTEN Users (sein eigenes Profil)
        //   $edituseric  — fullname des aktuell eingeloggten Users (für Audit-Anzeigen)
        // Wenn der eingeloggte User selbst kein Mitarbeiter-Profil hat, bleiben
        // editdg = null und edituseric = 'Unbekannt Unbekannt' — die Partials
        // zeigen dann eine Warnung, dass Profildaten fehlen.
        $openedID    = $id;
        $editdg      = null;
        $edituseric  = 'Unbekannt Unbekannt';

        $sessionDiscordTag = $_SESSION['discordtag'] ?? null;
        if (!empty($sessionDiscordTag)) {
            /** @var Mitarbeiter|null $ownProfile */
            $ownProfile = Mitarbeiter::query()
                ->where('discordtag', $sessionDiscordTag)
                ->first();
            if ($ownProfile !== null) {
                $editdg     = $ownProfile->dienstgrad;
                $edituseric = $ownProfile->fullname;
            }
        }

        $this->renderView('mitarbeiter/profile', [
            'mitarbeiter'       => $mitarbeiter,
            'row'               => $row,
            'dginfo'            => $dginfo,
            'rdginfo'           => $rdginfo,
            'fwginfo'           => $fwginfo,
            'bfqualtext'        => $bfqualtext,
            'dienstgradText'    => $dienstgradText,
            'rdqualtext'        => $rdqualtext,
            'geburtstag'        => $geburtstag,
            'einstellungsdatum' => $einstellungsdatum,
            'accountStatus'     => $accountStatus,
            'panelakte'         => $panelakte,
            'pendingInvite'     => $pendingInvite,
            'openedID'          => $openedID,
            'editdg'            => $editdg,
            'edituseric'        => $edituseric,
        ]);
    }

    /**
     * POST /mitarbeiter/profile.php (new=1) — Legacy Update-Form.
     *
     * Wird in der aktuellen UI praktisch nicht mehr aufgerufen (Inline-Edit
     * läuft über api/personnel/update-profile.php), aber der Endpoint bleibt
     * für Bookmarks/externe Tools erhalten. Validierung via FormRequest,
     * Diff-Audits an den PersonalLogManager.
     */
    public function update(): void
    {

        try {
            $data = UpdateMitarbeiterRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('mitarbeiter/profile?id=' . (int) ($_POST['id'] ?? 0));
        }

        /** @var Mitarbeiter|null $mitarbeiter */
        $mitarbeiter = Mitarbeiter::find($data['id']);
        if ($mitarbeiter === null) {
            Flash::error('Mitarbeiter nicht gefunden.');
            $this->redirect('mitarbeiter/list');
        }

        $userHelper = new UserHelper($this->pdo);
        $edituser   = $userHelper->getCurrentUserFullnameForAction();
        $logManager = new PersonalLogManager($this->pdo);

        // Rang-Wechsel logging
        if ($mitarbeiter->dienstgrad !== $data['dienstgrad']) {
            $oldName = Dienstgrad::find($mitarbeiter->dienstgrad)->name ?? '?';
            $newName = Dienstgrad::find($data['dienstgrad'])->name ?? '?';
            $logManager->logRankChange($mitarbeiter->id, $oldName, $newName, $edituser);
            $mitarbeiter->dienstgrad = $data['dienstgrad'];
        }

        // RD-Quali-Wechsel logging
        if ($mitarbeiter->qualird !== $data['qualird']) {
            $oldName = RdQuali::find($mitarbeiter->qualird)->name ?? '?';
            $newName = RdQuali::find($data['qualird'])->name ?? '?';
            $logManager->logQualificationChange($mitarbeiter->id, 'RD', $oldName, $newName, $edituser);
            $mitarbeiter->qualird = $data['qualird'];
        }

        // FW-Quali-Wechsel logging
        if ($mitarbeiter->qualifw2 !== $data['qualifw2']) {
            $oldName = FwQuali::find($mitarbeiter->qualifw2)->name ?? '?';
            $newName = FwQuali::find($data['qualifw2'])->name ?? '?';
            $logManager->logQualificationChange($mitarbeiter->id, 'FW', $oldName, $newName, $edituser);
            $mitarbeiter->qualifw2 = $data['qualifw2'];
        }

        // Generische Datenänderung erkennen — wenn irgendetwas anderes anders
        // ist, wird ein "Profil bearbeitet"-Eintrag geschrieben.
        $dataChanged = (
            $mitarbeiter->fullname   !== $data['fullname']   ||
            (string) $mitarbeiter->gebdatum?->format('Y-m-d') !== $data['gebdatum'] ||
            (string) $mitarbeiter->discordtag !== $data['discordtag'] ||
            (string) $mitarbeiter->telefonnr  !== $data['telefonnr']  ||
            $mitarbeiter->dienstnr   !== $data['dienstnr']   ||
            $mitarbeiter->geschlecht !== $data['geschlecht'] ||
            (string) $mitarbeiter->zusatz     !== $data['zusatzqual'] ||
            (string) ($mitarbeiter->pfp ?? '') !== $data['pfp']       ||
            (defined('CHAR_ID') && CHAR_ID && $mitarbeiter->charakterid !== $data['charakterid'])
        );

        if ($dataChanged) {
            $mitarbeiter->fullname   = $data['fullname'];
            $mitarbeiter->gebdatum   = $data['gebdatum'];
            $mitarbeiter->discordtag = $data['discordtag'];
            $mitarbeiter->telefonnr  = $data['telefonnr'];
            $mitarbeiter->dienstnr   = $data['dienstnr'];
            $mitarbeiter->geschlecht = $data['geschlecht'];
            $mitarbeiter->zusatz     = $data['zusatzqual'];
            $mitarbeiter->pfp        = $data['pfp'] !== '' ? $data['pfp'] : '/assets/img/empty_user.png';
            if (defined('CHAR_ID') && CHAR_ID) {
                $mitarbeiter->charakterid = $data['charakterid'];
            }
            $mitarbeiter->save();
            $logManager->logProfileModification($mitarbeiter->id, $edituser);
        } else {
            $mitarbeiter->save();
        }

        $this->redirect('mitarbeiter/profile?id=' . $mitarbeiter->id);
    }

    /**
     * POST /mitarbeiter/profile.php (new=4) — Fachdienste-JSON-Update.
     */
    public function updateFachdienste(): void
    {

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('mitarbeiter/list');
        }

        /** @var Mitarbeiter|null $mitarbeiter */
        $mitarbeiter = Mitarbeiter::find($id);
        if ($mitarbeiter === null) {
            $this->redirect('mitarbeiter/list');
        }

        $fachdienste     = isset($_POST['fachdienste']) && is_array($_POST['fachdienste']) ? $_POST['fachdienste'] : [];
        $fachdienste     = array_values(array_filter($fachdienste, 'is_string'));
        $fachdiensteJson = json_encode($fachdienste);

        if ($mitarbeiter->fachdienste !== $fachdiensteJson) {
            $mitarbeiter->fachdienste = $fachdiensteJson;
            $mitarbeiter->save();

            $userHelper = new UserHelper($this->pdo);
            (new PersonalLogManager($this->pdo))->logDepartmentModification(
                $id,
                $userHelper->getCurrentUserFullnameForAction()
            );
        }

        $this->redirect('mitarbeiter/profile?id=' . $id);
    }

    /**
     * POST /mitarbeiter/profile.php (new=5) — Notiz/Comment hinzufügen.
     */
    public function addNote(): void
    {

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('mitarbeiter/list');
        }

        $content = trim((string) ($_POST['content'] ?? ''));
        // noteType ist eine PersonalLogManager::TYPE_*-Konstante:
        //   0 = TYPE_NOTE (allgemeine Notiz), 1 = TYPE_POSITIVE, 2 = TYPE_NEGATIVE
        // Wir akzeptieren alle drei Werte explizit — `> 0` würde die allgemeine
        // Notiz (=0) fälschlich rausfiltern.
        $type = isset($_POST['noteType']) ? (int) $_POST['noteType'] : -1;
        $allowedTypes = [
            PersonalLogManager::TYPE_NOTE,
            PersonalLogManager::TYPE_POSITIVE,
            PersonalLogManager::TYPE_NEGATIVE,
        ];

        if ($content !== '' && in_array($type, $allowedTypes, true)) {
            $userHelper = new UserHelper($this->pdo);
            (new PersonalLogManager($this->pdo))->addNote(
                $id,
                $type,
                $content,
                $userHelper->getCurrentUserFullnameForAction()
            );
        }

        $this->redirect('mitarbeiter/profile?id=' . $id);
    }

    /**
     * POST /mitarbeiter/profile.php (new=6) — Dokument für Mitarbeiter erstellen.
     *
     * Schreibt einen Eintrag in `intra_mitarbeiter_dokumente` und sendet eine
     * Notification an den Empfänger, sofern dessen Discord-ID einem System-User
     * zugeordnet ist. Die PDF-Generierung passiert auf der Folge-Seite
     * (`assets/functions/docredir.php?docid=...`).
     */
    public function createDocument(): void
    {
        try {
            $data = CreateDocumentRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('mitarbeiter/list');
        }

        $profileId = $data['profileid'];
        $docType   = $data['docType'];

        /** @var Mitarbeiter|null $mitarbeiter */
        $mitarbeiter = Mitarbeiter::find($profileId);
        if ($mitarbeiter === null) {
            Flash::error('Mitarbeiter nicht gefunden.');
            $this->redirect('mitarbeiter/list');
        }

        // Eindeutige docid generieren (7-stellig, wie Legacy)
        do {
            $docId  = (string) random_int(1000000, 9999999);
            $exists = Capsule::table('intra_mitarbeiter_dokumente')->where('docid', $docId)->exists();
        } while ($exists);

        Capsule::table('intra_mitarbeiter_dokumente')->insert([
            'docid'             => $docId,
            'type'              => $docType,
            'anrede'            => $data['anrede'],
            'erhalter'          => $data['erhalter'],
            'inhalt'            => $data['inhalt'],
            'suspendtime'       => $data['suspendtime'],
            'erhalter_gebdat'   => $data['erhalter_gebdat'],
            'erhalter_rang'     => $data['erhalter_rang'],
            'erhalter_rang_rd'  => $data['erhalter_rang_rd'],
            'erhalter_quali'    => $data['erhalter_quali'],
            'ausstellungsdatum' => $data['ausstellungsdatum'],
            'ausstellerid'      => $data['ausstellerid'],
            'profileid'         => $profileId,
            'aussteller_name'   => $data['aussteller_name'],
            'aussteller_rang'   => $data['aussteller_rang'],
            'discordid'         => $mitarbeiter->discordtag,
        ]);

        $userHelper = new UserHelper($this->pdo);
        (new PersonalLogManager($this->pdo))->logDocumentCreation(
            $profileId,
            (int) $docId,
            $userHelper->getCurrentUserFullnameForAction()
        );

        // Notification an den Empfänger (sofern verlinkter User existiert)
        if (!empty($mitarbeiter->discordtag)) {
            $notificationManager = new NotificationManager($this->pdo);
            $recipientUserId     = $notificationManager->getUserIdByDiscordTag($mitarbeiter->discordtag);

            if ($recipientUserId) {
                $docTypeNames = [
                    1  => 'Beförderungsurkunde',
                    2  => 'Ernennungsurkunde',
                    3  => 'Entlassungsurkunde',
                    4  => 'Zertifikat',
                    5  => 'Fachlehrgangszertifikat',
                    6  => 'Ausbildungszertifikat',
                    7  => 'Abmahnung',
                    8  => 'Kündigung',
                    9  => 'Dienstenthebung',
                    10 => 'Dienstentfernung',
                ];
                $docTypeName = $docTypeNames[(int) $docType] ?? 'Dokument';

                $notificationManager->create(
                    $recipientUserId,
                    'dokument',
                    'Neues Dokument erstellt',
                    "Ein neues Dokument ({$docTypeName} #{$docId}) wurde für Sie erstellt.",
                    BASE_PATH . "mitarbeiter/dokument-view?docid={$docId}"
                );
            }
        }

        // Redirect zum Dokument-Viewer (PDF-Renderer + Toolbar).
        header('Location: ' . BASE_PATH . 'mitarbeiter/dokument-view?docid=' . $docId, true, 302);
        exit;
    }

    /**
     * POST /mitarbeiter/create.php — AJAX-Endpoint zum Anlegen eines Mitarbeiters.
     * Antwortet IMMER mit JSON.
     *
     * Response-Shape:
     *   - GET-Request → Redirect zur Liste
     *   - POST ohne Permission → 403 JSON
     *   - POST mit invaliden Daten → success=false JSON
     *   - POST erfolgreich → success=true + redirect-URL
     */
    public function store(): void
    {

        if (\App\Auth\Gate::denies('mitarbeiter.create')) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->jsonResponse(['success' => false, 'message' => 'Keine Berechtigung'], 403);
            }
            $this->redirect('index');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('mitarbeiter/list');
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
            'redirect' => BASE_PATH . 'mitarbeiter/profile?id=' . (int) $mitarbeiter->id . '&new_created=1',
        ]);
    }

    /**
     * GET /mitarbeiter/delete.php?id=X — Mitarbeiter komplett löschen.
     */
    public function destroy(): void
    {

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'invalid-id');
            $this->redirect('mitarbeiter/list');
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

        $this->redirect('mitarbeiter/list');
    }

    /**
     * GET /mitarbeiter/comment-delete.php?id=X&pid=Y — Personal-Log-Eintrag löschen.
     */
    public function deleteComment(): void
    {

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

    /**
     * GET /mitarbeiter/dokument-view.php?docid=X — PDF-Viewer mit Toolbar.
     *
     * Joint das Dokument mit Template + Kategorie + Empfänger via Capsule.
     * `intra_dokument_templates`/`intra_dokument_kategorien` haben (noch)
     * kein eigenes Eloquent-Model.
     */
    public function showDocument(): void
    {

        $docid = (string) ($_GET['docid'] ?? '');
        if ($docid === '') {
            Flash::set('error', 'Dokument-ID fehlt');
            $this->redirect('index');
        }

        $doc = Capsule::table('intra_mitarbeiter_dokumente as pd')
            ->leftJoin('intra_dokument_templates as t', 'pd.template_id', '=', 't.id')
            ->leftJoin('intra_dokument_kategorien as dk', 't.category_id', '=', 'dk.id')
            ->leftJoin('intra_users as u', 'pd.ausstellerid', '=', 'u.discord_id')
            ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
            ->leftJoin('intra_mitarbeiter as emp', 'pd.profileid', '=', 'emp.id')
            ->where('pd.docid', $docid)
            ->select(
                'pd.*',
                Capsule::raw('IFNULL(pd.is_archived, 0) as is_archived'),
                't.name as template_name',
                't.category as template_category',
                't.editor_type',
                'dk.name as category_name',
                'dk.color as category_color',
                Capsule::raw("COALESCE(pd.aussteller_name, m.fullname, u.fullname, 'Unbekannt') as ersteller_name"),
                'emp.fullname as empfaenger_fullname',
                'emp.id as empfaenger_id'
            )
            ->first();

        if ($doc === null) {
            Flash::set('error', 'Dokument nicht gefunden');
            $this->redirect('index');
        }

        // Berechtigung: Eigenes Dokument oder personnel.documents.* Permission
        $isOwnDoc = ((string) $doc->ausstellerid === ($_SESSION['discord_id'] ?? ''));
        if (!$isOwnDoc && !\App\Auth\Gate::allows('mitarbeiter.viewDoc')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $canManage = \App\Auth\Gate::allows('mitarbeiter.manageDocs');
        $typLabel  = \App\Documents\DocumentTemplateManager::getDocumentTypeLabel(
            (int) $doc->type,
            $doc->template_name ?? null
        );

        $pdfRelativePath = BASE_PATH . 'storage/documents/' . $doc->docid . '.pdf';
        $pdfAbsolutePath = dirname(__DIR__, 3) . '/storage/documents/' . basename((string) $doc->docid) . '.pdf';
        $pdfExists       = is_file($pdfAbsolutePath);
        $isArchived      = !empty($doc->is_archived);
        $austdatum       = $doc->ausstellungsdatum ? date('d.m.Y', strtotime($doc->ausstellungsdatum)) : '-';

        $backUrl = $doc->empfaenger_id
            ? BASE_PATH . 'mitarbeiter/profile?id=' . (int) $doc->empfaenger_id . '#documents'
            : BASE_PATH . 'index.php';

        $this->renderView('mitarbeiter/dokument-view', [
            'doc'        => $doc,
            'typLabel'   => $typLabel,
            'pdfUrl'     => $pdfRelativePath,
            'pdfExists'  => $pdfExists,
            'isArchived' => $isArchived,
            'austdatum'  => $austdatum,
            'backUrl'    => $backUrl,
            'canManage'  => $canManage,
        ]);
    }

    /**
     * POST /mitarbeiter/dokument-delete.php — Dokument endgültig löschen.
     *
     * Erfordert CSRF-Token + personnel.documents.manage. Löscht die PDF-Datei
     * im Storage UND den DB-Eintrag.
     */
    public function deleteDocument(): void
    {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Flash::set('error', 'Ungueltige Anfrage');
            $this->redirectBackOrIndex();
        }

        $token = (string) ($_POST['csrf_token'] ?? '');
        if (!\App\Security\CsrfProtection::validateToken($token)) {
            Flash::set('error', 'Ungültiger Sicherheitstoken. Bitte versuche es erneut.');
            $this->redirectBackOrIndex();
        }

        $docid = (string) ($_POST['docid'] ?? '');
        $pid   = (string) ($_POST['pid'] ?? '');

        if ($docid === '') {
            Flash::set('error', 'Dokument-ID fehlt');
            $this->redirectBackOrIndex();
        }

        // PDF-Datei löschen (basename als Schutz vor Path-Traversal)
        $pdfPath = dirname(__DIR__, 3) . '/storage/documents/' . basename($docid) . '.pdf';
        if (is_file($pdfPath)) {
            @unlink($pdfPath);
        }

        // DB-Eintrag löschen
        MitarbeiterDokument::query()->where('docid', $docid)->delete();

        (new AuditLogger($this->pdo))->log(
            (int) $_SESSION['userid'],
            'Dokument gelöscht [ID: ' . $docid . ']',
            $pid !== '' ? $pid : null,
            'Mitarbeiter',
            1
        );

        Flash::set('success', 'Dokument wurde gelöscht');
        $this->redirectBackOrIndex();
    }

    // -----------------------------------------------------------------------
    //  Mitarbeiter-spezifische Helpers
    // -----------------------------------------------------------------------

    /**
     * Antwortet mit einer JSON-Response und exit(). Wird vom AJAX-Endpoint
     * store() benutzt, der immer JSON liefert.
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
        $this->redirect('index');
    }
}
