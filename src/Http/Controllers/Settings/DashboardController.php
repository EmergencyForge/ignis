<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * DashboardController — Dashboard-Konfiguration (Kategorien + Tiles).
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->ensureManage('index.php');

        $categories = Capsule::table('intra_dashboard_categories')
            ->orderBy('priority')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $tiles = Capsule::table('intra_dashboard_tiles')
            ->orderBy('priority')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Group tiles by category
        $tilesByCategory = [];
        foreach ($tiles as $tile) {
            $tilesByCategory[(int) $tile['category']][] = $tile;
        }

        $this->renderView('settings/dashboard/index', [
            'categories'      => $categories,
            'tilesByCategory' => $tilesByCategory,
        ]);
    }

    // ── Categories ─────────────────────────────────────────

    public function categoryStore(): void
    {
        $this->ensureManage('settings/dashboard/index.php');

        $title    = trim($_POST['title'] ?? '');
        $priority = (int) ($_POST['priority'] ?? 0);

        if ($title === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/dashboard/index.php');
        }

        try {
            Capsule::table('intra_dashboard_categories')->insert([
                'title'    => $title,
                'priority' => $priority,
            ]);
            Flash::set('dashboard.category', 'created');
            $this->audit('Kategorie erstellt', 'Titel: ' . $title);
        } catch (PDOException $e) {
            error_log('Category creation failed: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/dashboard/index.php');
    }

    public function categoryUpdate(): void
    {
        $this->ensureManage('settings/dashboard/index.php');

        $id       = (int) ($_POST['id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $priority = (int) ($_POST['priority'] ?? 0);

        if ($id <= 0 || $title === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/dashboard/index.php');
        }

        try {
            Capsule::table('intra_dashboard_categories')->where('id', $id)->update([
                'title'    => $title,
                'priority' => $priority,
            ]);
            Flash::set('success', 'updated');
            $this->audit('Kategorie aktualisiert [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('Category update failed: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/dashboard/index.php');
    }

    public function categoryDestroy(): void
    {
        $this->ensureManage('settings/dashboard/index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('dashboard.category', 'invalid-id');
            $this->redirect('settings/dashboard/index.php');
        }

        $exists = Capsule::table('intra_dashboard_categories')->where('id', $id)->exists();
        if (!$exists) {
            Flash::set('dashboard.category', 'not-found');
            $this->redirect('settings/dashboard/index.php');
        }

        try {
            Capsule::table('intra_dashboard_categories')->where('id', $id)->delete();
            Flash::set('dashboard.category', 'deleted');
            $this->audit('Kategorie gelöscht [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/dashboard/index.php');
    }

    // ── Tiles ──────────────────────────────────────────────

    public function tileStore(): void
    {
        $this->ensureManage('settings/dashboard/index.php');

        $category = (int) ($_POST['category'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $url      = trim($_POST['url'] ?? '#');
        $icon     = trim($_POST['icon'] ?? 'external-link-alt');
        $priority = (int) ($_POST['priority'] ?? 0);

        if ($category <= 0 || $title === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/dashboard/index.php');
        }

        try {
            Capsule::table('intra_dashboard_tiles')->insert([
                'category' => $category,
                'title'    => $title,
                'url'      => $url,
                'icon'     => $icon,
                'priority' => $priority,
            ]);
            Flash::set('dashboard.tile', 'created');
            $this->audit('Verlinkung erstellt', 'Titel: ' . $title);
        } catch (PDOException $e) {
            error_log('Tile creation error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/dashboard/index.php');
    }

    public function tileUpdate(): void
    {
        $this->ensureManage('settings/dashboard/index.php');

        $id       = (int) ($_POST['id'] ?? 0);
        $category = (int) ($_POST['category'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $url      = trim($_POST['url'] ?? '#');
        $icon     = trim($_POST['icon'] ?? 'external-link-alt');
        $priority = (int) ($_POST['priority'] ?? 0);

        if ($id <= 0 || $category <= 0 || $title === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/dashboard/index.php');
        }

        try {
            Capsule::table('intra_dashboard_tiles')->where('id', $id)->update([
                'category' => $category,
                'title'    => $title,
                'url'      => $url,
                'icon'     => $icon,
                'priority' => $priority,
            ]);
            Flash::set('success', 'updated');
            $this->audit('Verlinkung aktualisiert [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('Tile update failed: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/dashboard/index.php');
    }

    public function tileDestroy(): void
    {
        $this->ensureManage('settings/dashboard/index.php');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('dashboard.tile', 'invalid-id');
            $this->redirect('settings/dashboard/index.php');
        }

        $exists = Capsule::table('intra_dashboard_tiles')->where('id', $id)->exists();
        if (!$exists) {
            Flash::set('dashboard.tile', 'not-found');
            $this->redirect('settings/dashboard/index.php');
        }

        try {
            Capsule::table('intra_dashboard_tiles')->where('id', $id)->delete();
            Flash::set('dashboard.tile', 'deleted');
            $this->audit('Verlinkung gelöscht [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/dashboard/index.php');
    }

    private function ensureManage(string $redirect): void
    {
        $this->requireAuth();
        if (!Permissions::check(['admin', 'dashboard.manage'])) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }

    private function audit(string $action, ?string $details): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }
        $logger = new AuditLogger($this->pdo);
        $logger->log($_SESSION['userid'], $action, $details, 'Dashboard', 1);
    }
}
