<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Requests\Users\GenerateRegistrationCodeRequest;
use App\Models\Personnel;
use App\Models\RegistrationCode;
use App\Models\Role;
use App\Models\User;
use App\Personnel\AccountLink;
use App\Support\ListQuery;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * UserController für das benutzer/-Modul.
 *
 * Nutzt Eloquent-Models für Daten-Zugriff. Die Methoden werden aktuell von
 * Stub-Files in benutzer/*.php aus aufgerufen und können später unter
 * zentrale Routes-Definitions in routes/web.php wandern.
 *
 * Verantwortlichkeiten:
 *   - Auth & Permission Checks (vor Router-Middleware: inline)
 *   - Datenfetching via Eloquent-Models (App\Models\User, App\Models\Role)
 *   - Side-Effects (Audit-Logs, Flash-Messages)
 *   - View-Rendering via templates/users/*.php oder Redirect
 *
 * Diese Klasse läuft unter PSR-4 Autoloading und wird via DI-Container
 * instanziiert.
 */
class UserController extends Controller
{
    /**
     * Liefert die Hover-Card für einen User. Wenn der User ein verknüpftes
     * Mitarbeiter-Profil hat (User.aktenid → Mitarbeiter.id), wird die
     * Mitarbeiter-Card gerendert; sonst ein Minimal-Fragment mit Username
     * und Discord-ID.
     */
    public function card(\EmergencyForge\Http\Request $request, string $id): \EmergencyForge\Http\Response
    {
        $this->requireAuth();
        Gate::authorize('user.viewList');

        $user = User::query()->with(['userRole', 'mitarbeiter'])->find((int) $id);
        if ($user === null) {
            return \EmergencyForge\Http\Response::html('Benutzer nicht gefunden.', 404);
        }

        $linkedMitarbeiter = $user->mitarbeiter;

        ob_start();
        // User-Hover-Card zeigt User-Stammdaten und ggf. den Link auf den
        // verbundenen Mitarbeiter. Nicht die Mitarbeiter-Card selbst rendern.
        // Das ist ein separates `data-mitarbeiter-card`-Trigger.
        include __DIR__ . '/../../../assets/components/profiles/_user-hover-card.php';
        return \EmergencyForge\Http\Response::html((string) ob_get_clean());
    }

    /**
     * GET /users/list: Benutzer-Liste, sortiert, gefiltert und geblättert
     * auf dem Server (ListQuery): `?q=` sucht in Benutzername und
     * Mitarbeiter-Namen, `?status=active|inactive` filtert, `?sort=` kennt
     * die Spalten der Whitelist unten.
     *
     * Schließt einen LEFT JOIN auf intra_mitarbeiter ein, um den Mitarbeiter-
     * Namen anzuzeigen falls verlinkt. Ohne Verlinkung wird "Kein Profil
     * verbunden" angezeigt. Der Rollenname kommt per JOIN mit, damit sich
     * danach sortieren lässt.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('user.viewList', redirectTo: 'index');

        $list = ListQuery::fromQuery($_GET, [
            'id'      => 'intra_users.id',
            'name'    => 'intra_users.username',
            'role'    => 'role_name',
            'status'  => 'intra_users.is_active',
            'created' => 'intra_users.created_at',
        ], 'name', 'asc', 25, ['status']);

        $query = User::query()
            ->leftJoin('intra_mitarbeiter', 'intra_users.aktenid', '=', 'intra_mitarbeiter.id')
            ->leftJoin('intra_users_roles', 'intra_users.role', '=', 'intra_users_roles.id')
            ->select(
                'intra_users.*',
                // Ohne Profil bleibt der Name leer, das Template zeigt dann
                // einen Hinweis und nimmt die Initialen aus dem Benutzernamen.
                'intra_mitarbeiter.fullname as mitarbeiter_fullname',
                'intra_users_roles.name as role_name'
            );

        if ($list->q !== '') {
            $query->where(function ($q) use ($list) {
                $q->where('intra_users.username', 'LIKE', $list->like())
                    ->orWhere('intra_mitarbeiter.fullname', 'LIKE', $list->like());
            });
        }
        $byStatus = ListQuery::countBy($query, 'intra_users.is_active');
        if ($list->filter('status') === 'active') {
            $query->where('intra_users.is_active', 1);
        } elseif ($list->filter('status') === 'inactive') {
            $query->where('intra_users.is_active', 0);
        }

        $users = $list->paginate($query);
        $roles = Role::all()->keyBy('id');

        $this->renderView('users/list', [
            'users'  => $users,
            'roles'  => $roles,
            'list'   => $list,
            'counts' => ['' => array_sum($byStatus), 'active' => $byStatus['1'] ?? 0, 'inactive' => $byStatus['0'] ?? 0],
        ]);
    }

    /**
     * GET /users/edit?id=X: Edit-Formular für einen User.
     *
     * Lädt den Ziel-User samt Rolle, prüft Self-Edit + Priority, rendert dann
     * das Edit-Template inkl. der für die Rollen-Auswahl filtrierten Rollen
     * und der user-spezifischen Audit-Log-Subsection.
     */
    public function edit(): void
    {
        $this->requireAuth();
        // Permission-Check ohne Target, vor dem Laden:
        $this->ensure('user.update', redirectTo: 'users/list');

        $target = $this->loadUserForEditing();

        $availableRoles = Role::query()
            ->where('priority', '>', (int) ($_SESSION['role_priority'] ?? 0))
            ->orderBy('priority')
            ->get();

        // Die letzten 100 Einträge des Kontos; die vollständige Historie
        // steht sortier- und durchsuchbar unter /users/auditlog.
        $auditEntries = [];
        if (Gate::allows('user.viewAuditLog')) {
            $auditEntries = Capsule::table('intra_audit_log')
                ->where('user', $target->id)
                ->orderBy('timestamp', 'desc')
                ->limit(100)
                ->get();
        }

        $this->renderView('users/edit', [
            'target'              => $target,
            'availableRoles'      => $availableRoles,
            'auditEntries'        => $auditEntries,
            'linkedPersonnel'     => $target->mitarbeiter,
            'personnelCandidates' => $target->aktenid === null ? self::unlinkedPersonnel() : [],
        ]);
    }

    /**
     * POST /users/personnel-link (id, action=link|unlink, mitarbeiter_id,
     * back=profile): verknüpft ein Konto mit einem Mitarbeiter oder löst
     * die Verknüpfung (ADR-0002). Dieselben Regeln wie die Benutzer-
     * bearbeitung; das eigene Konto lässt sich nicht umhängen.
     */
    public function linkPersonnel(): void
    {
        $this->requireAuth();
        $this->ensure('user.update', redirectTo: 'users/list');

        $targetId      = (int) ($_POST['id'] ?? 0);
        $mitarbeiterId = (int) ($_POST['mitarbeiter_id'] ?? 0);
        $action        = (string) ($_POST['action'] ?? '');
        $back          = ($_POST['back'] ?? '') === 'profile' && $mitarbeiterId > 0
            ? 'personnel/profile?id=' . $mitarbeiterId
            : 'users/edit?id=' . $targetId;

        /** @var User|null $target */
        $target = User::with('userRole')->find($targetId);
        if ($target === null || !in_array($action, ['link', 'unlink'], true)) {
            Flash::set('error', 'invalid-request');
            $this->redirect('users/list');
        }
        if ((int) $target->id === (int) $_SESSION['userid']) {
            Flash::error('Das eigene Konto lässt sich nicht umhängen.');
            $this->redirect($back);
        }
        if (Gate::denies('user.update', $target)) {
            Flash::set('user', 'low-permissions');
            $this->redirect($back);
        }

        try {
            if ($action === 'unlink') {
                AccountLink::unlink((int) $target->id, (int) $_SESSION['userid']);
                Flash::success('Die Verknüpfung ist gelöst.');
            } elseif ($mitarbeiterId <= 0) {
                Flash::error('Bitte einen Mitarbeiter wählen.');
            } else {
                AccountLink::link((int) $target->id, $mitarbeiterId, (int) $_SESSION['userid']);
                Flash::success('Konto und Mitarbeiter sind verknüpft.');
            }
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        $this->redirect($back);
    }

    /**
     * Mitarbeiter ohne Konto, für die Auswahl beim Verknüpfen und bei
     * Einladungen.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Personnel>
     */
    private static function unlinkedPersonnel(): \Illuminate\Database\Eloquent\Collection
    {
        return Personnel::query()
            ->whereNotIn('id', User::query()->whereNotNull('aktenid')->select('aktenid'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'dienstnr']);
    }

    /**
     * POST /users/edit (mit `new=1`): Update der Rolle eines Users.
     *
     * Aktualisiert bewusst nur das Feld `role`. Der Username bleibt
     * unveränderlich, obwohl das Form-Field ihn mitschickt.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->ensure('user.update', redirectTo: 'users/list');

        $target = $this->loadUserForEditing();

        $newRoleId = (int) ($_POST['role'] ?? 0);
        if ($newRoleId > 0) {
            $target->role = $newRoleId;
            $target->save();

            Flash::success('Benutzer wurde erfolgreich aktualisiert.');
            (new AuditLogger())->log(
                (int) $_SESSION['userid'],
                'Benutzer aktualisiert [ID: ' . $target->id . ']',
                null,
                'Benutzer',
                1
            );
        }

        $this->redirect('users/list');
    }

    /**
     * GET /users/audit-log: Globale Audit-Log-Tabelle.
     *
     * Zeigt alle Einträge mit `global = 1`. Joint sich die Usernamen via
     * Capsule (es gibt kein eigenes AuditLog-Model).
     */
    public function auditlog(): void
    {
        $this->requireAuth();
        $this->ensure('user.viewAuditLog', redirectTo: 'index');

        // Sortiert, gesucht und geblättert auf dem Server (ListQuery); das
        // Log wächst mit jeder Änderung, deshalb 50 je Seite, neueste zuerst.
        $list = ListQuery::fromQuery($_GET, [
            'zeit'   => 'intra_audit_log.timestamp',
            'modul'  => 'intra_audit_log.module',
            'aktion' => 'intra_audit_log.action',
            'user'   => 'username',
        ], 'zeit', 'desc', 50, ['modul']);

        $query = Capsule::table('intra_audit_log')
            ->leftJoin('intra_users', 'intra_audit_log.user', '=', 'intra_users.id')
            ->select('intra_audit_log.*', 'intra_users.username')
            ->where('intra_audit_log.global', 1);

        if ($list->q !== '') {
            $query->where(function ($q) use ($list) {
                $q->where('intra_audit_log.action', 'LIKE', $list->like())
                    ->orWhere('intra_audit_log.details', 'LIKE', $list->like())
                    ->orWhere('intra_users.username', 'LIKE', $list->like());
            });
        }
        if ($list->filter('modul') !== '') {
            $query->where('intra_audit_log.module', $list->filter('modul'));
        }

        $modules = Capsule::table('intra_audit_log')
            ->where('global', 1)
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->all();

        $this->renderView('users/auditlog', [
            'entries' => $list->paginate($query),
            'modules' => $modules,
            'list'    => $list,
        ]);
    }

    /**
     * GET /users/registration-codes: Einladungs-Codes verwalten.
     * POST mit `action=generate` → neuen Code erzeugen
     * POST mit `action=delete`   → Code löschen (nur ungenutzte)
     *
     * Internes Dispatching nach REQUEST_METHOD + action. Der Stub bleibt
     * dadurch ein 2-Zeiler. 
     */
    public function registrationCodes(): void
    {
        $this->requireAuth();
        $this->ensure('user.createRegistrationCode', redirectTo: 'index');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'generate') {
                $this->generateRegistrationCode();
                return;
            }
            if ($action === 'delete') {
                $this->deleteRegistrationCode();
                return;
            }
        }

        $codes = RegistrationCode::query()
            ->with(['creator', 'usedByUser', 'mitarbeiter'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->renderView('users/registration-codes', [
            'codes'            => $codes,
            'freePersonnel'    => self::unlinkedPersonnel(),
            'registrationMode' => defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open',
            'systemUrl'        => $this->resolveSystemUrl(),
        ]);
    }

    private function generateRegistrationCode(): void
    {
        try {
            $data = GenerateRegistrationCodeRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('users/registration-codes');
        }

        $person = null;
        if ($data['mitarbeiter_id'] !== null) {
            $person = Personnel::query()->find($data['mitarbeiter_id'], ['id', 'fullname']);
            if ($person === null || AccountLink::userFor((int) $person->id) !== null) {
                Flash::error('Dieser Mitarbeiter hat schon ein Konto oder existiert nicht.');
                $this->redirect('users/registration-codes');
            }
        }

        $code = bin2hex(random_bytes(8));

        $rc = new RegistrationCode();
        $rc->code       = $code;
        $rc->label      = $data['label'] ?? $person?->fullname;
        $rc->mitarbeiter_id = $person?->id;
        $rc->created_by = (int) $_SESSION['userid'];
        $rc->expires_at = $data['expires_at'];
        $rc->is_used    = false;
        $rc->save();

        $inviteUrl = $this->resolveSystemUrl() . BASE_PATH . 'invite?code=' . $code;
        Flash::set('success', 'Einladungslink erstellt: ' . $inviteUrl);

        $this->redirect('users/registration-codes');
    }

    private function deleteRegistrationCode(): void
    {
        $codeId = (int) ($_POST['code_id'] ?? 0);

        $deleted = RegistrationCode::query()
            ->where('id', $codeId)
            ->where('is_used', 0)
            ->delete();

        if ($deleted > 0) {
            Flash::success('Einladung erfolgreich gelöscht.');
        } else {
            Flash::error('Einladung konnte nicht gelöscht werden (bereits verwendet oder nicht gefunden).');
        }

        $this->redirect('users/registration-codes');
    }

    /**
     * POST /users/delete (id): Endgültiges Löschen eines Users.
     *
     * Schutzregeln:
     *   - Selbst-Löschung verboten
     *   - Ziel darf kein full_admin sein
     *   - Ziel-Rolle muss eine niedrigere Priorität haben als der Aufrufer
     */
    public function destroy(): void
    {
        $this->requireAuth();

        $currentUserId = (int) $_SESSION['userid'];
        $targetId      = (int) ($_POST['id'] ?? 0);

        if ($targetId <= 0) {
            Flash::set('error', 'invalid-request');
            $this->redirect('users/list');
        }

        if ($targetId === $currentUserId) {
            Flash::set('user', 'edit-self');
            $this->redirect('users/list');
        }

        /** @var User|null $target */
        $target = User::with('userRole')->find($targetId);

        if ($target === null) {
            Flash::set('error', 'user-not-found');
            $this->redirect('users/list');
        }

        if (Gate::denies('user.delete', $target)) {
            Flash::set('user', 'low-permissions');
            $this->redirect('users/list');
        }

        $target->delete();

        Flash::set('user', 'deleted');
        (new AuditLogger())->log(
            $currentUserId,
            'Benutzer endgültig gelöscht [ID: ' . $targetId . ']',
            null,
            'Benutzer',
            1
        );

        $this->redirect('users/list');
    }

    /**
     * POST /users/toggle-active (id, action=deactivate|reactivate)
     *
     * Soft-Delete: Benutzer wird deaktiviert statt gelöscht. Reaktivierung
     * setzt is_active wieder auf 1 und löscht deactivated_at/by.
     */
    public function setActive(): void
    {
        $this->requireAuth();

        $currentUserId = (int) $_SESSION['userid'];
        $targetId      = (int) ($_POST['id'] ?? 0);
        $action        = (string) ($_POST['action'] ?? '');

        if ($targetId <= 0 || !in_array($action, ['deactivate', 'reactivate'], true)) {
            Flash::set('error', 'invalid-request');
            $this->redirect('users/list');
        }

        if ($targetId === $currentUserId) {
            Flash::set('user', 'edit-self');
            $this->redirect('users/list');
        }

        /** @var User|null $target */
        $target = User::with('userRole')->find($targetId);

        if ($target === null) {
            Flash::set('error', 'user-not-found');
            $this->redirect('users/list');
        }

        if (Gate::denies('user.toggleActive', $target)) {
            Flash::set('user', 'low-permissions');
            $this->redirect('users/list');
        }

        if ($action === 'deactivate') {
            $target->is_active      = false;
            $target->deactivated_at = new \DateTime();
            $target->deactivated_by = $currentUserId;
            $target->save();

            Flash::success('Benutzer wurde deaktiviert.');
            (new AuditLogger())->log(
                $currentUserId,
                'Benutzer deaktiviert [ID: ' . $targetId . ']',
                null,
                'Benutzer',
                1
            );
        } else {
            $target->is_active      = true;
            $target->deactivated_at = null;
            $target->deactivated_by = null;
            $target->save();

            Flash::success('Benutzer wurde reaktiviert.');
            (new AuditLogger())->log(
                $currentUserId,
                'Benutzer reaktiviert [ID: ' . $targetId . ']',
                null,
                'Benutzer',
                1
            );
        }

        $this->redirect('users/edit?id=' . $targetId);
    }

    // -----------------------------------------------------------------------
    //  User-spezifische Helpers (auth/render kommen aus Controller-Base)
    // -----------------------------------------------------------------------

    /**
     * Lädt den per ?id=X übergebenen User samt Rolle und führt die UX-Checks
     * (Existenz, Self-Edit) sowie die Authorization-Prüfung durch, jeweils
     * mit spezifischen Flash-Messages für gute UX. Wird sowohl von edit()
     * als auch update() benutzt.
     */
    private function loadUserForEditing(): User
    {
        $targetId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($targetId <= 0) {
            Flash::set('error', 'invalid-request');
            $this->redirect('users/list');
        }

        /** @var User|null $target */
        $target = User::with('userRole')->find($targetId);
        if ($target === null) {
            Flash::set('error', 'user-not-found');
            $this->redirect('users/list');
        }

        if ($target->id === (int) $_SESSION['userid']) {
            Flash::set('user', 'edit-self');
            $this->redirect('users/list');
        }

        if (Gate::denies('user.update', $target)) {
            Flash::set('user', 'low-permissions');
            $this->redirect('users/list');
        }

        return $target;
    }

    /**
     * Baut die Basis-URL für Invite-Links. Bevorzugt SYSTEM_URL aus der Config,
     * fällt auf den aktuellen Request-Host zurück. Identisch zur Legacy-Logik
     * aus benutzer/registration-codes.php.
     */
    private function resolveSystemUrl(): string
    {
        $sysUrl = (defined('SYSTEM_URL') && SYSTEM_URL !== '' && SYSTEM_URL !== 'CHANGE_ME')
            ? rtrim(SYSTEM_URL, '/')
            : '';
        if ($sysUrl !== '' && !preg_match('#^https?://#i', $sysUrl)) {
            $sysUrl = 'https://' . $sysUrl;
        }
        if ($sysUrl !== '') {
            return $sysUrl;
        }
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
