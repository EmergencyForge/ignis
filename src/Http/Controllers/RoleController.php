<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permissions;
use App\Exceptions\ValidationException;
use App\Helpers\Flash;
use App\Http\Requests\Roles\CreateRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\Role;
use App\Utils\AuditLogger;
use PDO;

/**
 * RoleController — Pilot-Migration für das benutzer/rollen/-Modul.
 *
 * Verwaltet Rollen mit ihren Permissions. Permissions selbst sind in
 * config/permissions.php als gruppierte Liste definiert.
 *
 * Die Methoden entsprechen den ursprünglichen Files:
 *   index()   — benutzer/rollen/index.php   (View)
 *   store()   — benutzer/rollen/create.php  (POST)
 *   update()  — benutzer/rollen/update.php  (POST)
 *   destroy() — benutzer/rollen/delete.php  (POST)
 *
 * In Phase 3 (Router) werden diese unter zentrale Routes wandern.
 */
class RoleController
{
    public function __construct(
        private PDO $pdo,
    ) {}

    /**
     * GET /benutzer/rollen — Rollenverwaltung mit DataTable + Edit/Create-Modals.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['admin', 'users.view'], redirectTo: 'index.php');

        $roles            = Role::query()->orderBy('priority')->get();
        $permissionGroups = require dirname(__DIR__, 3) . '/config/permissions.php';

        $this->renderView('roles/index', [
            'roles'            => $roles,
            'permissionGroups' => $permissionGroups,
        ]);
    }

    /**
     * POST /benutzer/rollen/create — Neue Rolle anlegen.
     * Erfordert `full_admin`. Input wird via CreateRoleRequest validiert.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->requireFullAdmin();
        $this->requireMethod('POST');

        try {
            $data = CreateRoleRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('benutzer/rollen/index.php');
        }

        try {
            $role              = new Role();
            $role->name        = $data['name'];
            $role->priority    = $data['priority'];
            $role->color       = $data['color'];
            $role->permissions = $data['permissions'];
            $role->save();

            Flash::set('role', 'created');
            (new AuditLogger($this->pdo))->log(
                (int) $_SESSION['userid'],
                'Rolle erstellt',
                'Name: ' . $data['name'],
                'Rollen',
                1
            );
        } catch (\Throwable $e) {
            error_log('Role create error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('benutzer/rollen/index.php');
    }

    /**
     * POST /benutzer/rollen/update — Bestehende Rolle aktualisieren.
     * Erfordert `full_admin`. Input wird via UpdateRoleRequest validiert.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->requireFullAdmin();
        $this->requireMethod('POST');

        try {
            $data = UpdateRoleRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('benutzer/rollen/index.php');
        }

        try {
            /** @var Role|null $role */
            $role = Role::find($data['id']);
            if ($role === null) {
                Flash::set('role', 'not-found');
                $this->redirect('benutzer/rollen/index.php');
            }

            $role->name        = $data['name'];
            $role->priority    = $data['priority'];
            $role->color       = $data['color'];
            $role->permissions = $data['permissions'];
            $role->save();

            Flash::set('success', 'updated');
            (new AuditLogger($this->pdo))->log(
                (int) $_SESSION['userid'],
                'Rolle aktualisiert [ID: ' . $data['id'] . ']',
                'Name: ' . $data['name'],
                'Rollen',
                1
            );
        } catch (\Throwable $e) {
            error_log('Role update error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('benutzer/rollen/index.php');
    }

    /**
     * POST /benutzer/rollen/delete — Rolle löschen.
     * Erfordert `full_admin`. Lehnt Löschen ab, wenn Rolle nicht existiert.
     */
    public function destroy(): void
    {
        $this->requireAuth();
        $this->requireFullAdmin();
        $this->requireMethod('POST');

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            Flash::set('role', 'invalid-id');
            $this->redirect('benutzer/rollen/index.php');
        }

        try {
            /** @var Role|null $role */
            $role = Role::find($id);
            if ($role === null) {
                Flash::set('role', 'not-found');
                $this->redirect('benutzer/rollen/index.php');
            }

            $role->delete();

            Flash::set('role', 'deleted');
            (new AuditLogger($this->pdo))->log(
                (int) $_SESSION['userid'],
                'Rolle gelöscht [ID: ' . $id . ']',
                null,
                'Rollen',
                1
            );
        } catch (\Throwable $e) {
            error_log('Role delete error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('benutzer/rollen/index.php');
    }

    // -----------------------------------------------------------------------
    //  Helpers — werden in Phase 3 in Middleware ausgelagert
    // -----------------------------------------------------------------------

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

    private function requireFullAdmin(): void
    {
        if (!Permissions::check('full_admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('benutzer/rollen/index.php');
        }
    }

    private function requireMethod(string $method): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            $this->redirect('benutzer/rollen/index.php');
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
        // Legacy-Compat: bestehende Partials erwarten ein lokales $pdo
        $pdo = $this->pdo;

        extract($data, EXTR_SKIP);
        require $templatePath;
    }
}
