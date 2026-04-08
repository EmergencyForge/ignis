<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permissions;
use App\Helpers\Flash;
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
        $this->requirePermission(['admin', 'users.view'], redirectTo: 'index.php');

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
        $this->requirePermission(['admin', 'users.delete'], redirectTo: 'benutzer/list.php');

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

        if (!$this->canModify($target)) {
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
        $this->requirePermission(['admin', 'users.delete'], redirectTo: 'benutzer/list.php');

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

        if (!$this->canModify($target)) {
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
     * Prüft ob der Aufrufer das Ziel-User-Objekt überhaupt verändern darf:
     *   - Ziel darf kein full_admin sein
     *   - Ziel-Rolle muss eine NIEDRIGERE Priorität haben (höhere Zahl)
     */
    private function canModify(User $target): bool
    {
        if ($target->full_admin) {
            return false;
        }
        $targetPriority = (int) ($target->userRole?->priority ?? 0);
        $ownPriority    = (int) ($_SESSION['role_priority'] ?? 0);
        return $targetPriority > $ownPriority;
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }
    }

    /**
     * @param string|array<int,string> $permission
     */
    private function requirePermission(string|array $permission, string $redirectTo = 'index.php'): void
    {
        if (!Permissions::check($permission)) {
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
