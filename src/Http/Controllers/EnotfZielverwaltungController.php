<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Flash;
use App\Utils\AuditLogger;
use PDOException;

/**
 * EnotfZielverwaltungController — Zielkrankenhäuser für eNOTF-Protokolle.
 *
 * Verwaltet `intra_edivi_ziele` (id, name, identifier, priority, transport,
 * active). Liste ist für edivi.view-User sichtbar; CRUD nur für Admins.
 */
class EnotfZielverwaltungController extends Controller
{
    /**
     * GET /enotf/admin/zielverwaltung
     */
    public function index(): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'edivi.view'])) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index.php');
        }

        $stmt = $this->pdo->prepare("SELECT * FROM intra_edivi_ziele ORDER BY priority ASC, name ASC");
        $stmt->execute();
        $ziele = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->renderView('enotf/admin/zielverwaltung/index', [
            'ziele' => $ziele,
        ]);
    }

    /**
     * POST /enotf/admin/zielverwaltung/create
     */
    public function store(): void
    {
        $this->ensureAdmin();

        $name       = trim((string) ($_POST['name'] ?? ''));
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $priority   = isset($_POST['priority']) ? (int) $_POST['priority'] : 0;
        $transport  = isset($_POST['transport']) ? 1 : 0;
        $active     = isset($_POST['active']) ? 1 : 0;

        if ($name === '' || $identifier === '') {
            Flash::set('error', 'missing-fields');
            $this->redirectToList();
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO intra_edivi_ziele (name, identifier, priority, transport, active)
                 VALUES (:name, :identifier, :priority, :transport, :active)"
            );
            $stmt->execute([
                ':name'       => $name,
                ':identifier' => $identifier,
                ':priority'   => $priority,
                ':transport'  => $transport,
                ':active'     => $active,
            ]);

            Flash::set('target', 'created');
            (new AuditLogger($this->pdo))->log(
                (int) $_SESSION['userid'],
                'Ziel erstellt',
                'Name: ' . $name,
                'Ziele',
                1
            );
        } catch (PDOException $e) {
            \App\Logging\Logger::error('ZielverwaltungStore: Fehler', ['error' => $e->getMessage()]);
            Flash::set('error', 'exception');
        }

        $this->redirectToList();
    }

    /**
     * POST /enotf/admin/zielverwaltung/update
     */
    public function update(): void
    {
        $this->ensureAdmin();

        $id         = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name       = trim((string) ($_POST['name'] ?? ''));
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $priority   = isset($_POST['priority']) ? (int) $_POST['priority'] : 0;
        $transport  = isset($_POST['transport']) ? 1 : 0;
        $active     = isset($_POST['active']) ? 1 : 0;

        if ($id <= 0 || $name === '' || $identifier === '') {
            Flash::set('error', 'missing-fields');
            $this->redirectToList();
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE intra_edivi_ziele
                 SET name = :name, identifier = :identifier, priority = :priority,
                     transport = :transport, active = :active
                 WHERE id = :id"
            );
            $stmt->execute([
                ':name'       => $name,
                ':identifier' => $identifier,
                ':priority'   => $priority,
                ':transport'  => $transport,
                ':active'     => $active,
                ':id'         => $id,
            ]);

            Flash::set('success', 'updated');
            (new AuditLogger($this->pdo))->log(
                (int) $_SESSION['userid'],
                'Ziel aktualisiert [ID: ' . $id . ']',
                null,
                'Ziele',
                1
            );
        } catch (PDOException $e) {
            \App\Logging\Logger::error('ZielverwaltungUpdate: Fehler', ['error' => $e->getMessage()]);
            Flash::set('error', 'exception');
        }

        $this->redirectToList();
    }

    /**
     * POST /enotf/admin/zielverwaltung/delete
     */
    public function destroy(): void
    {
        $this->ensureAdmin();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            Flash::set('target', 'invalid-id');
            $this->redirectToList();
        }

        try {
            $check = $this->pdo->prepare("SELECT id FROM intra_edivi_ziele WHERE id = :id");
            $check->execute([':id' => $id]);
            if (!$check->fetch()) {
                Flash::set('target', 'not-found');
                $this->redirectToList();
            }

            $stmt = $this->pdo->prepare("DELETE FROM intra_edivi_ziele WHERE id = :id");
            $stmt->execute([':id' => $id]);

            Flash::set('target', 'deleted');
            (new AuditLogger($this->pdo))->log(
                (int) $_SESSION['userid'],
                'Ziel gelöscht [ID: ' . $id . ']',
                null,
                'Ziele',
                1
            );
        } catch (PDOException $e) {
            \App\Logging\Logger::error('ZielverwaltungDestroy: Fehler', ['error' => $e->getMessage()]);
            Flash::set('error', 'exception');
        }

        $this->redirectToList();
    }

    private function ensureAdmin(): void
    {
        $this->requireAuth();
        if (!Permissions::check('admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirectToList();
        }
    }

    private function redirectToList(): never
    {
        header('Location: ' . EnotfUrl::adminZielverwaltung());
        exit;
    }
}
