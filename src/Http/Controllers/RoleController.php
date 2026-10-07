<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\Flash;
use App\Http\Requests\Roles\CreateRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\Role;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Exceptions\ValidationException;

/**
 * RoleController: Pilot-Migration für das users/roles-Modul.
 *
 * Verwaltet Rollen mit ihren Permissions. Permissions selbst sind in
 * config/permissions.php als gruppierte Liste definiert.
 *
 * Die Methoden entsprechen den ursprünglichen Files:
 *   index():   users/roles/index.php   (View)
 *   store():   users/roles/create  (POST)
 *   update():  users/roles/update  (POST)
 *   destroy(): users/roles/delete  (POST)
 *
 *
 */
class RoleController extends Controller
{
    /**
     * GET /users/roles: Rollenverwaltung mit DataTable + Edit/Create-Modals.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('role.viewList', redirectTo: 'index');

        $roles            = Role::query()->orderBy('priority')->get();
        $permissionGroups = require dirname(__DIR__, 3) . '/config/permissions.php';

        // Permissions aktiver Plugins in den Katalog aufnehmen, damit sie
        // in der Rollen-Verwaltung zuweisbar sind.
        try {
            $permissionGroups = app(\App\Plugins\PluginLoader::class)->mergePermissionGroups($permissionGroups);
        } catch (\Throwable $e) {
            \App\Logging\Logger::warning('Plugin-Permissions nicht geladen: ' . $e->getMessage());
        }

        $this->renderView('roles/index', [
            'roles'            => $roles,
            'permissionGroups' => $permissionGroups,
        ]);
    }

    /**
     * POST /users/roles/create: Neue Rolle anlegen.
     * Erfordert `full_admin`. Input wird via CreateRoleRequest validiert.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->ensure('role.create', redirectTo: 'users/roles/index');
        $this->requireMethod('POST');

        try {
            $data = CreateRoleRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('users/roles/index');
        }

        try {
            $role              = new Role();
            $role->name        = $data['name'];
            $role->priority    = $data['priority'];
            $role->color       = $data['color'];
            $role->permissions = $data['permissions'];
            $role->save();

            Flash::set('role', 'created');
            (new AuditLogger())->log(
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

        $this->redirect('users/roles/index');
    }

    /**
     * POST /users/roles/update: Bestehende Rolle aktualisieren.
     * Erfordert `full_admin`. Input wird via UpdateRoleRequest validiert.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->ensure('role.update', redirectTo: 'users/roles/index');
        $this->requireMethod('POST');

        try {
            $data = UpdateRoleRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('users/roles/index');
        }

        try {
            /** @var Role|null $role */
            $role = Role::find($data['id']);
            if ($role === null) {
                Flash::set('role', 'not-found');
                $this->redirect('users/roles/index');
            }

            $role->name        = $data['name'];
            $role->priority    = $data['priority'];
            $role->color       = $data['color'];
            $role->permissions = $data['permissions'];
            $role->save();

            Flash::set('success', 'updated');
            (new AuditLogger())->log(
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

        $this->redirect('users/roles/index');
    }

    /**
     * POST /users/roles/delete: Rolle löschen.
     * Erfordert `full_admin`. Lehnt Löschen ab, wenn Rolle nicht existiert.
     */
    public function destroy(): void
    {
        $this->requireAuth();
        $this->ensure('role.delete', redirectTo: 'users/roles/index');
        $this->requireMethod('POST');

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            Flash::set('role', 'invalid-id');
            $this->redirect('users/roles/index');
        }

        try {
            /** @var Role|null $role */
            $role = Role::find($id);
            if ($role === null) {
                Flash::set('role', 'not-found');
                $this->redirect('users/roles/index');
            }

            $role->delete();

            Flash::set('role', 'deleted');
            (new AuditLogger())->log(
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

        $this->redirect('users/roles/index');
    }

    // -----------------------------------------------------------------------
    //  Role-spezifische Helpers (auth/render kommen aus Controller-Base)
    // -----------------------------------------------------------------------

    /**
     * Hard-Stop für Endpoints, die nur per POST aufgerufen werden dürfen.
     * Ist Controller-spezifisch (Default-Redirect zur Rollen-Liste), daher
     * nicht in der Base-Klasse.
     */
    private function requireMethod(string $method): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            $this->redirect('users/roles/index');
        }
    }
}
