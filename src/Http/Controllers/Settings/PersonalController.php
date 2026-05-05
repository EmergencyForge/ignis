<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * PersonalController — Stammdaten-Verwaltung für Personal:
 * Dienstgrade, Feuerwehr-Qualis (FW), Rettungsdienst-Qualis (RD),
 * Fachdienste (FD).
 *
 * Alle 4 Bereiche folgen demselben CRUD-Pattern: Liste mit Modal-Edit,
 * separate POST-Endpoints für create/update/delete. Per Bereich gibt es
 * eine `*Index()`-Methode für die View und 3 Action-Methoden.
 */
class PersonalController extends Controller
{
    // ── Dienstgrade ─────────────────────────────────────────────

    public function dienstgradeIndex(): void
    {
        $this->requireAuth();
        $this->ensureView();

        $ranks = Capsule::table('intra_mitarbeiter_dienstgrade')
            ->orderBy('priority')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/personnel/ranks', ['ranks' => $ranks]);
    }

    public function dienstgradStore(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/ranks/index.php');

        $name     = trim($_POST['name'] ?? '');
        $name_m   = trim($_POST['name_m'] ?? '');
        $name_w   = trim($_POST['name_w'] ?? '');
        $priority = (int) ($_POST['priority'] ?? 0);
        $badge    = trim($_POST['badge'] ?? '') !== '' ? trim($_POST['badge']) : null;
        $archive  = isset($_POST['archive']) ? 1 : 0;

        if ($name === '' || $name_m === '' || $name_w === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/personnel/ranks/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_dienstgrade')->insert([
                'name'     => $name,
                'name_m'   => $name_m,
                'name_w'   => $name_w,
                'priority' => $priority,
                'badge'    => $badge,
                'archive'  => $archive,
            ]);
            Flash::set('rank', 'created');
            $this->audit('Dienstgrad erstellt', 'Name: ' . $name, 'Dienstgrade');
        } catch (PDOException $e) {
            error_log('PDO Error (create dienstgrad): ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/ranks/index');
    }

    public function dienstgradUpdate(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/ranks/index.php');

        $id       = (int) ($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $name_m   = trim($_POST['name_m'] ?? '');
        $name_w   = trim($_POST['name_w'] ?? '');
        $priority = (int) ($_POST['priority'] ?? 0);
        $badge    = trim($_POST['badge'] ?? '') !== '' ? trim($_POST['badge']) : null;
        $archive  = isset($_POST['archive']) ? 1 : 0;

        if ($id <= 0 || $name === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/personnel/ranks/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_dienstgrade')->where('id', $id)->update([
                'name'     => $name,
                'name_m'   => $name_m,
                'name_w'   => $name_w,
                'priority' => $priority,
                'badge'    => $badge,
                'archive'  => $archive,
            ]);
            Flash::set('success', 'updated');
            $this->audit('Dienstgrad aktualisiert [ID: ' . $id . ']', null, 'Dienstgrade');
        } catch (PDOException $e) {
            error_log('PDO Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/ranks/index');
    }

    public function dienstgradDelete(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/ranks/index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('rank', 'invalid-id');
            $this->redirect('settings/personnel/ranks/index');
        }

        $exists = Capsule::table('intra_mitarbeiter_dienstgrade')->where('id', $id)->exists();
        if (!$exists) {
            Flash::set('rank', 'not-found');
            $this->redirect('settings/personnel/ranks/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_dienstgrade')->where('id', $id)->delete();
            Flash::set('rank', 'deleted');
            $this->audit('Dienstgrad gelöscht [ID: ' . $id . ']', null, 'Dienstgrade');
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/ranks/index');
    }

    // ── FW-Qualifikationen ──────────────────────────────────────

    public function fwQualiIndex(): void
    {
        $this->requireAuth();
        $this->ensureView();

        $qualis = Capsule::table('intra_mitarbeiter_fwquali')
            ->orderBy('priority')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/personnel/fdskills', ['qualis' => $qualis]);
    }

    public function fwQualiStore(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/fdskills/index.php');

        $shortname = trim($_POST['shortname'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $name_m    = trim($_POST['name_m'] ?? '');
        $name_w    = trim($_POST['name_w'] ?? '');
        $priority  = (int) ($_POST['priority'] ?? 0);
        $none      = isset($_POST['none']) ? 1 : 0;

        if ($shortname === '' || $name === '' || $name_m === '' || $name_w === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/personnel/fdskills/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_fwquali')->insert([
                'shortname' => $shortname,
                'name'      => $name,
                'name_m'    => $name_m,
                'name_w'    => $name_w,
                'priority'  => $priority,
                'none'      => $none,
            ]);
            Flash::set('quali', 'created');
            $this->audit('FW-Qualifikation erstellt', 'Name: ' . $name, 'FW-Qualifikationen');
        } catch (PDOException $e) {
            error_log('PDO Error (create fwquali): ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/fdskills/index');
    }

    public function fwQualiUpdate(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/fdskills/index.php');

        $id        = (int) ($_POST['id'] ?? 0);
        $shortname = trim($_POST['shortname'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $name_m    = trim($_POST['name_m'] ?? '');
        $name_w    = trim($_POST['name_w'] ?? '');
        $priority  = (int) ($_POST['priority'] ?? 0);
        $none      = isset($_POST['none']) ? 1 : 0;

        if ($id <= 0 || $name === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/personnel/fdskills/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_fwquali')->where('id', $id)->update([
                'shortname' => $shortname,
                'name'      => $name,
                'name_m'    => $name_m,
                'name_w'    => $name_w,
                'priority'  => $priority,
                'none'      => $none,
            ]);
            Flash::set('success', 'updated');
            $this->audit('FW-Qualifikation aktualisiert [ID: ' . $id . ']', null, 'FW-Qualifikationen');
        } catch (PDOException $e) {
            error_log('PDO Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/fdskills/index');
    }

    public function fwQualiDelete(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/fdskills/index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('quali', 'invalid-id');
            $this->redirect('settings/personnel/fdskills/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_fwquali')->where('id', $id)->delete();
            Flash::set('quali', 'deleted');
            $this->audit('FW-Qualifikation gelöscht [ID: ' . $id . ']', null, 'FW-Qualifikationen');
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/fdskills/index');
    }

    // ── RD-Qualifikationen ──────────────────────────────────────

    public function rdQualiIndex(): void
    {
        $this->requireAuth();
        $this->ensureView();

        $qualis = Capsule::table('intra_mitarbeiter_rdquali')
            ->orderBy('priority')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/personnel/ambskills', ['qualis' => $qualis]);
    }

    public function rdQualiStore(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/ambskills/index.php');

        $name       = trim($_POST['name'] ?? '');
        $name_m     = trim($_POST['name_m'] ?? '');
        $name_w     = trim($_POST['name_w'] ?? '');
        $abkuerzung = trim($_POST['abkuerzung'] ?? '') !== '' ? trim($_POST['abkuerzung']) : null;
        $priority   = (int) ($_POST['priority'] ?? 0);
        $none       = isset($_POST['none']) ? 1 : 0;
        $trainable  = isset($_POST['trainable']) ? 1 : 0;

        if ($name === '' || $name_m === '' || $name_w === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/personnel/ambskills/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_rdquali')->insert([
                'name'       => $name,
                'name_m'     => $name_m,
                'name_w'     => $name_w,
                'abkuerzung' => $abkuerzung,
                'priority'   => $priority,
                'none'       => $none,
                'trainable'  => $trainable,
            ]);
            Flash::set('quali', 'created');
            $this->audit('RD-Qualifikation erstellt', 'Name: ' . $name, 'RD-Qualifikationen');
        } catch (PDOException $e) {
            error_log('PDO Error (create rdquali): ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/ambskills/index');
    }

    public function rdQualiUpdate(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/ambskills/index.php');

        $id         = (int) ($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $name_m     = trim($_POST['name_m'] ?? '');
        $name_w     = trim($_POST['name_w'] ?? '');
        $abkuerzung = trim($_POST['abkuerzung'] ?? '') !== '' ? trim($_POST['abkuerzung']) : null;
        $priority   = (int) ($_POST['priority'] ?? 0);
        $none       = isset($_POST['none']) ? 1 : 0;
        $trainable  = isset($_POST['trainable']) ? 1 : 0;

        if ($id <= 0 || $name === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/personnel/ambskills/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_rdquali')->where('id', $id)->update([
                'name'       => $name,
                'name_m'     => $name_m,
                'name_w'     => $name_w,
                'abkuerzung' => $abkuerzung,
                'priority'   => $priority,
                'none'       => $none,
                'trainable'  => $trainable,
            ]);
            Flash::set('success', 'updated');
            $this->audit('RD-Qualifikation aktualisiert [ID: ' . $id . ']', null, 'RD-Qualifikationen');
        } catch (PDOException $e) {
            error_log('PDO Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/ambskills/index');
    }

    public function rdQualiDelete(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/ambskills/index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('quali', 'invalid-id');
            $this->redirect('settings/personnel/ambskills/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_rdquali')->where('id', $id)->delete();
            Flash::set('quali', 'deleted');
            $this->audit('RD-Qualifikation gelöscht [ID: ' . $id . ']', null, 'RD-Qualifikationen');
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/ambskills/index');
    }

    // ── Fachdienste (FD) ────────────────────────────────────────

    public function fdQualiIndex(): void
    {
        $this->requireAuth();
        $this->ensureView();

        $qualis = Capsule::table('intra_mitarbeiter_fdquali')
            ->orderBy('sgnr')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/personnel/specialties', ['qualis' => $qualis]);
    }

    public function fdQualiStore(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/specialties/index.php');

        $sgnr     = (int) ($_POST['sgnr'] ?? 0);
        $sgname   = trim($_POST['sgname'] ?? '');
        $disabled = isset($_POST['disabled']) ? 1 : 0;

        if ($sgnr <= 0 || $sgname === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/personnel/specialties/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_fdquali')->insert([
                'sgnr'     => $sgnr,
                'sgname'   => $sgname,
                'disabled' => $disabled,
            ]);
            Flash::set('quali', 'created');
            $this->audit('Fachdienst erstellt', 'Name: ' . $sgname, 'Fachdienste');
        } catch (PDOException $e) {
            error_log('PDO Error (create fdquali): ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/specialties/index');
    }

    public function fdQualiUpdate(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/specialties/index.php');

        $id       = (int) ($_POST['id'] ?? 0);
        $sgnr     = (int) ($_POST['sgnr'] ?? 0);
        $sgname   = trim($_POST['sgname'] ?? '');
        $disabled = isset($_POST['disabled']) ? 1 : 0;

        if ($id <= 0 || $sgname === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/personnel/specialties/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_fdquali')->where('id', $id)->update([
                'sgnr'     => $sgnr,
                'sgname'   => $sgname,
                'disabled' => $disabled,
            ]);
            Flash::set('success', 'updated');
            $this->audit('Fachdienst aktualisiert [ID: ' . $id . ']', null, 'Fachdienste');
        } catch (PDOException $e) {
            error_log('PDO Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/specialties/index');
    }

    public function fdQualiDelete(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/personnel/specialties/index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('quali', 'invalid-id');
            $this->redirect('settings/personnel/specialties/index');
        }

        try {
            Capsule::table('intra_mitarbeiter_fdquali')->where('id', $id)->delete();
            Flash::set('quali', 'deleted');
            $this->audit('Fachdienst gelöscht [ID: ' . $id . ']', null, 'Fachdienste');
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/personnel/specialties/index');
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * View-Guard: erlaubt admin oder personnel.view.
     * Redirect zur Index, falls keine Berechtigung.
     */
    private function ensureView(): void
    {
        if (!Gate::allows('personnel.viewList')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }
    }

    /**
     * Admin-only Guard. Bei Denial: Flash + Redirect zur angegebenen Seite.
     */
    private function ensureAdmin(string $redirect): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }

    /**
     * Schreibt einen Audit-Log-Eintrag, sofern ein User-Login vorliegt.
     */
    private function audit(string $action, ?string $details, string $category): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }
        $logger = new AuditLogger($this->pdo);
        $logger->log($_SESSION['userid'], $action, $details, $category, 1);
    }
}
