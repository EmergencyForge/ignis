<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Exceptions\ValidationException;
use App\Helpers\Flash;
use App\Http\Requests\Users\GenerateRegistrationCodeRequest;
use App\Models\RegistrationCode;
use App\Models\Role;
use App\Models\User;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;

/**
 * UserController — Pilot-Migration für das benutzer/-Modul.
 *
 * Erste konkrete Anwendung des Eloquent-ORM in intraRP. Die Methoden hier
 * werden aktuell von Stub-Files in benutzer/*.php aus aufgerufen, in Phase 3
 * (Router) wandern sie unter zentrale Routes-Definitions.
 *
 * Verantwortlichkeiten:
 *   - Auth & Permission Checks (vor Router-Middleware: inline)
 *   - Datenfetching via Eloquent-Models (App\Models\User, App\Models\Role)
 *   - Side-Effects (Audit-Logs, Flash-Messages)
 *   - View-Rendering via templates/users/*.php oder Redirect
 *
 * Diese Klasse läuft unter PSR-4 Autoloading und wird via DI-Container
 * instanziiert (PDO + AuditLogger Constructor-Injection).
 */
class UserController
{
    public function __construct(
        private PDO $pdo,
    ) {}

    /**
     * GET /benutzer — Benutzer-Liste mit DataTable.
     *
     * Schließt einen LEFT JOIN auf intra_mitarbeiter ein, um den Mitarbeiter-
     * Namen anzuzeigen falls verlinkt. Wenn kein Profil verlinkt ist, zeigen
     * wir "Kein Profil verbunden" wie der Legacy-Code.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('user.viewList', redirectTo: 'index.php');

        $users = User::query()
            ->leftJoin(
                'intra_mitarbeiter',
                'intra_users.discord_id',
                '=',
                'intra_mitarbeiter.discordtag'
            )
            ->select(
                'intra_users.*',
                Capsule::raw(
                    "COALESCE(intra_mitarbeiter.fullname, 'Kein Profil verbunden') as mitarbeiter_fullname"
                )
            )
            ->orderBy('intra_users.username')
            ->get();

        $roles = Role::all()->keyBy('id');

        $this->renderView('users/list', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * GET /benutzer/edit?id=X — Edit-Formular für einen User.
     *
     * Lädt den Ziel-User samt Rolle, prüft Self-Edit + Priority, rendert dann
     * das Edit-Template inkl. der für die Rollen-Auswahl filtrierten Rollen
     * und der user-spezifischen Audit-Log-Subsection.
     */
    public function edit(): void
    {
        $this->requireAuth();
        // Permission-Check ohne Target — vor dem Laden:
        $this->ensure('user.update', redirectTo: 'benutzer/list.php');

        $target = $this->loadUserForEditing();

        $availableRoles = Role::query()
            ->where('priority', '>', (int) ($_SESSION['role_priority'] ?? 0))
            ->orderBy('priority')
            ->get();

        $auditEntries = [];
        if (Gate::allows('user.viewAuditLog')) {
            $auditEntries = Capsule::table('intra_audit_log')
                ->where('user', $target->id)
                ->orderBy('timestamp', 'desc')
                ->get();
        }

        $this->renderView('users/edit', [
            'target'         => $target,
            'availableRoles' => $availableRoles,
            'auditEntries'   => $auditEntries,
        ]);
    }

    /**
     * POST /benutzer/edit (mit `new=1`) — Update der Rolle eines Users.
     *
     * Aktuell wird nur das Feld `role` aktualisiert (genau wie der Legacy-Code,
     * der `username` zwar im Form-Field hatte, aber nie ins UPDATE übernommen
     * hat). Das Verhalten bleibt 1:1 erhalten.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->ensure('user.update', redirectTo: 'benutzer/list.php');

        $target = $this->loadUserForEditing();

        $newRoleId = (int) ($_POST['role'] ?? 0);
        if ($newRoleId > 0) {
            $target->role = $newRoleId;
            $target->save();

            Flash::success('Benutzer wurde erfolgreich aktualisiert.');
            (new AuditLogger($this->pdo))->log(
                (int) $_SESSION['userid'],
                'Benutzer aktualisiert [ID: ' . $target->id . ']',
                null,
                'Benutzer',
                1
            );
        }

        $this->redirect('benutzer/list.php');
    }

    /**
     * GET /benutzer/auditlog — Globale Audit-Log-Tabelle.
     *
     * Zeigt alle Einträge mit `global = 1`. Joint sich die Usernamen via
     * Capsule (kein eigener AuditLog-Model in dieser Phase).
     */
    public function auditlog(): void
    {
        $this->requireAuth();
        $this->ensure('user.viewAuditLog', redirectTo: 'index.php');

        $entries = Capsule::table('intra_audit_log')
            ->where('global', 1)
            ->orderBy('timestamp', 'desc')
            ->get();

        $usersById = User::query()
            ->select(['id', 'username'])
            ->get()
            ->keyBy('id');

        $this->renderView('users/auditlog', [
            'entries'   => $entries,
            'usersById' => $usersById,
        ]);
    }

    /**
     * GET /benutzer/registration-codes — Einladungs-Codes verwalten.
     * POST mit `action=generate` → neuen Code erzeugen
     * POST mit `action=delete`   → Code löschen (nur ungenutzte)
     *
     * Internes Dispatching nach REQUEST_METHOD + action — der Stub bleibt
     * dadurch ein 2-Zeiler. Wird in Phase 3 durch echte Routes ersetzt.
     */
    public function registrationCodes(): void
    {
        $this->requireAuth();
        $this->ensure('user.createRegistrationCode', redirectTo: 'index.php');

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
            ->with(['creator', 'usedByUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->renderView('users/registration-codes', [
            'codes'            => $codes,
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
            $this->redirect('benutzer/registration-codes.php');
        }

        $code = bin2hex(random_bytes(8));

        $rc = new RegistrationCode();
        $rc->code       = $code;
        $rc->label      = $data['label'];
        $rc->created_by = (int) $_SESSION['userid'];
        $rc->expires_at = $data['expires_at'];
        $rc->is_used    = false;
        $rc->save();

        $inviteUrl = $this->resolveSystemUrl() . BASE_PATH . 'invite.php?code=' . $code;
        Flash::set(
            'success',
            'Einladungslink erstellt! <br><code class="user-select-all">'
                . htmlspecialchars($inviteUrl)
                . '</code>'
        );

        $this->redirect('benutzer/registration-codes.php');
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

        $this->redirect('benutzer/registration-codes.php');
    }

    /**
     * GET /benutzer/delete?id=X — Endgültiges Löschen eines Users.
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
        $targetId      = (int) ($_GET['id'] ?? 0);

        if ($targetId <= 0) {
            Flash::set('error', 'invalid-request');
            $this->redirect('benutzer/list.php');
        }

        if ($targetId === $currentUserId) {
            Flash::set('user', 'edit-self');
            $this->redirect('benutzer/list.php');
        }

        /** @var User|null $target */
        $target = User::with('userRole')->find($targetId);

        if ($target === null) {
            Flash::set('error', 'user-not-found');
            $this->redirect('benutzer/list.php');
        }

        if (Gate::denies('user.delete', $target)) {
            Flash::set('user', 'low-permissions');
            $this->redirect('benutzer/list.php');
        }

        $target->delete();

        Flash::set('user', 'deleted');
        (new AuditLogger($this->pdo))->log(
            $currentUserId,
            'Benutzer endgültig gelöscht [ID: ' . $targetId . ']',
            null,
            'Benutzer',
            1
        );

        $this->redirect('benutzer/list.php');
    }

    /**
     * GET /benutzer/toggle-active?id=X&action=deactivate|reactivate
     *
     * Soft-Delete: Benutzer wird deaktiviert statt gelöscht. Reaktivierung
     * setzt is_active wieder auf 1 und löscht deactivated_at/by.
     */
    public function setActive(): void
    {
        $this->requireAuth();

        $currentUserId = (int) $_SESSION['userid'];
        $targetId      = (int) ($_GET['id'] ?? 0);
        $action        = (string) ($_GET['action'] ?? '');

        if ($targetId <= 0 || !in_array($action, ['deactivate', 'reactivate'], true)) {
            Flash::set('error', 'invalid-request');
            $this->redirect('benutzer/list.php');
        }

        if ($targetId === $currentUserId) {
            Flash::set('user', 'edit-self');
            $this->redirect('benutzer/list.php');
        }

        /** @var User|null $target */
        $target = User::with('userRole')->find($targetId);

        if ($target === null) {
            Flash::set('error', 'user-not-found');
            $this->redirect('benutzer/list.php');
        }

        if (Gate::denies('user.toggleActive', $target)) {
            Flash::set('user', 'low-permissions');
            $this->redirect('benutzer/list.php');
        }

        if ($action === 'deactivate') {
            $target->is_active      = false;
            $target->deactivated_at = new \DateTime();
            $target->deactivated_by = $currentUserId;
            $target->save();

            Flash::success('Benutzer wurde deaktiviert.');
            (new AuditLogger($this->pdo))->log(
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
            (new AuditLogger($this->pdo))->log(
                $currentUserId,
                'Benutzer reaktiviert [ID: ' . $targetId . ']',
                null,
                'Benutzer',
                1
            );
        }

        $this->redirect('benutzer/edit.php?id=' . $targetId);
    }

    // -----------------------------------------------------------------------
    //  Helper-Methoden — werden in Phase 3 in Middleware ausgelagert
    // -----------------------------------------------------------------------

    /**
     * Lädt den per ?id=X übergebenen User samt Rolle und führt die UX-Checks
     * (Existenz, Self-Edit) sowie die Authorization-Prüfung durch — jeweils
     * mit spezifischen Flash-Messages für gute UX. Wird sowohl von edit()
     * als auch update() benutzt.
     */
    private function loadUserForEditing(): User
    {
        $targetId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($targetId <= 0) {
            Flash::set('error', 'invalid-request');
            $this->redirect('benutzer/list.php');
        }

        /** @var User|null $target */
        $target = User::with('userRole')->find($targetId);
        if ($target === null) {
            Flash::set('error', 'user-not-found');
            $this->redirect('benutzer/list.php');
        }

        if ($target->id === (int) $_SESSION['userid']) {
            Flash::set('user', 'edit-self');
            $this->redirect('benutzer/list.php');
        }

        if (Gate::denies('user.update', $target)) {
            Flash::set('user', 'low-permissions');
            $this->redirect('benutzer/list.php');
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

    private function requireAuth(): void
    {
        if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }
    }

    /**
     * Wrapper um Gate::allows: bei Denial wird Flash + Redirect gemacht.
     * Aktionen, die spezifischere Flash-Messages brauchen (z.B. "edit-self"),
     * machen den Gate-Check inline statt diesen Helper zu nutzen.
     */
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
     * Rendert ein PHP-Template aus templates/. View-Daten werden via extract()
     * in den lokalen Scope geschoben, damit das Template direkt darauf zugreifen
     * kann ($users statt $viewData['users']).
     *
     * Stellt zusätzlich `$pdo` im Template-Scope bereit, weil bestehende
     * Partials (navbar.php, global-announcements.php, footer.php, ...) das
     * Variable als lokale Referenz erwarten — im Legacy-Flow wurde es vom
     * `require database.php` automatisch gesetzt, mit dem Controller-Flow
     * müssen wir es explizit reichen. Wird über die Phase-3-Templates
     * (Twig + Layouts) entfallen.
     *
     * @param array<string,mixed> $data
     */
    private function renderView(string $view, array $data = []): void
    {
        $templatePath = dirname(__DIR__, 3) . '/templates/' . $view . '.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException("View not found: $view ($templatePath)");
        }
        // Legacy-Compat: bestehende Partials erwarten ein lokales $pdo
        $pdo = $this->pdo;

        extract($data, EXTR_SKIP);
        require $templatePath;
    }
}
