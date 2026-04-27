<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * EnotfController — eNOTF-Quicklinks und -Kategorien.
 */
class EnotfController extends Controller
{
    // ── Quicklinks ─────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        if (!Gate::allows('enotf.viewAdminList')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $quicklinks = Capsule::table('intra_enotf_quicklinks')
            ->orderBy('category_slug')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Map slug → name for display
        $cats = Capsule::table('intra_enotf_categories')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
        $catNames = [];
        foreach ($cats as $cat) {
            $catNames[$cat['slug']] = $cat['name'];
        }

        // Active categories for select dropdowns
        $activeCategories = Capsule::table('intra_enotf_categories')
            ->where('active', 1)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/enotf/index', [
            'quicklinks'       => $quicklinks,
            'catNames'         => $catNames,
            'activeCategories' => $activeCategories,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/enotf/index.php');

        $title        = trim($_POST['title'] ?? '');
        $url          = trim($_POST['url'] ?? '');
        $icon         = trim($_POST['icon'] ?? 'fa-solid fa-link');
        $categorySlug = trim($_POST['category'] ?? 'schnellzugriff');
        $sortOrder    = (int) ($_POST['sort_order'] ?? 0);
        $colWidth     = trim($_POST['col_width'] ?? 'col-6');
        $active       = isset($_POST['active']) ? 1 : 0;

        if ($title === '' || $url === '') {
            Flash::set('error', 'Titel und URL dürfen nicht leer sein.');
            $this->redirect('settings/enotf/index');
        }

        try {
            Capsule::table('intra_enotf_quicklinks')->insert([
                'title'         => $title,
                'url'           => $url,
                'icon'          => $icon,
                'category_slug' => $categorySlug,
                'sort_order'    => $sortOrder,
                'col_width'     => $colWidth,
                'active'        => $active,
            ]);
            Flash::set('success', 'Link wurde erfolgreich erstellt.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Erstellen des Links: ' . $e->getMessage());
            error_log('Fehler beim Erstellen eines eNOTF Quicklinks: ' . $e->getMessage());
        }

        $this->redirect('settings/enotf/index');
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/enotf/index.php');

        $id           = (int) ($_POST['id'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $url          = trim($_POST['url'] ?? '');
        $icon         = trim($_POST['icon'] ?? 'fa-solid fa-link');
        $categorySlug = trim($_POST['category'] ?? 'schnellzugriff');
        $sortOrder    = (int) ($_POST['sort_order'] ?? 0);
        $colWidth     = trim($_POST['col_width'] ?? 'col-6');
        $active       = isset($_POST['active']) ? 1 : 0;

        if ($id <= 0 || $title === '' || $url === '') {
            Flash::set('error', 'Ungültige Daten.');
            $this->redirect('settings/enotf/index');
        }

        try {
            Capsule::table('intra_enotf_quicklinks')->where('id', $id)->update([
                'title'         => $title,
                'url'           => $url,
                'icon'          => $icon,
                'category_slug' => $categorySlug,
                'sort_order'    => $sortOrder,
                'col_width'     => $colWidth,
                'active'        => $active,
            ]);
            Flash::set('success', 'Link wurde erfolgreich aktualisiert.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Aktualisieren des Links: ' . $e->getMessage());
            error_log('Fehler beim Aktualisieren eines eNOTF Quicklinks: ' . $e->getMessage());
        }

        $this->redirect('settings/enotf/index');
    }

    public function destroy(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/enotf/index.php');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'Ungültige ID.');
            $this->redirect('settings/enotf/index');
        }

        try {
            Capsule::table('intra_enotf_quicklinks')->where('id', $id)->delete();
            Flash::set('success', 'Link wurde erfolgreich gelöscht.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Löschen des Links: ' . $e->getMessage());
            error_log('Fehler beim Löschen eines eNOTF Quicklinks: ' . $e->getMessage());
        }

        $this->redirect('settings/enotf/index');
    }

    // ── Categories ─────────────────────────────────────────

    public function categoriesIndex(): void
    {
        $this->requireAuth();
        if (!Gate::allows('enotf.viewAdminList')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $categories = Capsule::table('intra_enotf_categories')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/enotf/kategorien/index', ['categories' => $categories]);
    }

    public function categoryStore(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/enotf/kategorien/index.php');

        $name      = trim($_POST['name'] ?? '');
        $slug      = strtolower(trim($_POST['slug'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $active    = isset($_POST['active']) ? 1 : 0;

        if ($name === '' || $slug === '') {
            Flash::set('error', 'Name und Slug dürfen nicht leer sein.');
            $this->redirect('settings/enotf/kategorien/index');
        }

        $exists = Capsule::table('intra_enotf_categories')->where('slug', $slug)->exists();
        if ($exists) {
            Flash::set('error', 'Dieser Slug existiert bereits.');
            $this->redirect('settings/enotf/kategorien/index');
        }

        try {
            Capsule::table('intra_enotf_categories')->insert([
                'name'       => $name,
                'slug'       => $slug,
                'sort_order' => $sortOrder,
                'active'     => $active,
            ]);
            Flash::set('success', 'Kategorie wurde erfolgreich erstellt.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Erstellen der Kategorie: ' . $e->getMessage());
            error_log('Fehler beim Erstellen einer eNOTF Kategorie: ' . $e->getMessage());
        }

        $this->redirect('settings/enotf/kategorien/index');
    }

    public function categoryUpdate(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/enotf/kategorien/index.php');

        $id        = (int) ($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $slug      = strtolower(trim($_POST['slug'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $active    = isset($_POST['active']) ? 1 : 0;

        if ($id <= 0 || $name === '' || $slug === '') {
            Flash::set('error', 'Ungültige Daten.');
            $this->redirect('settings/enotf/kategorien/index');
        }

        $exists = Capsule::table('intra_enotf_categories')
            ->where('slug', $slug)
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            Flash::set('error', 'Dieser Slug existiert bereits.');
            $this->redirect('settings/enotf/kategorien/index');
        }

        try {
            Capsule::table('intra_enotf_categories')->where('id', $id)->update([
                'name'       => $name,
                'slug'       => $slug,
                'sort_order' => $sortOrder,
                'active'     => $active,
            ]);
            Flash::set('success', 'Kategorie wurde erfolgreich aktualisiert.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Aktualisieren der Kategorie: ' . $e->getMessage());
            error_log('Fehler beim Aktualisieren einer eNOTF Kategorie: ' . $e->getMessage());
        }

        $this->redirect('settings/enotf/kategorien/index');
    }

    public function categoryDestroy(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('settings/enotf/kategorien/index.php');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'Ungültige ID.');
            $this->redirect('settings/enotf/kategorien/index');
        }

        try {
            $category = Capsule::table('intra_enotf_categories')->where('id', $id)->first();
            if ($category) {
                $linkCount = Capsule::table('intra_enotf_quicklinks')
                    ->where('category_slug', $category->slug)
                    ->count();
                if ($linkCount > 0) {
                    Flash::set('error', 'Diese Kategorie kann nicht gelöscht werden, da noch ' . $linkCount . ' Link(s) zugewiesen sind.');
                    $this->redirect('settings/enotf/kategorien/index');
                }
            }

            Capsule::table('intra_enotf_categories')->where('id', $id)->delete();
            Flash::set('success', 'Kategorie wurde erfolgreich gelöscht.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Löschen der Kategorie: ' . $e->getMessage());
            error_log('Fehler beim Löschen einer eNOTF Kategorie: ' . $e->getMessage());
        }

        $this->redirect('settings/enotf/kategorien/index');
    }

    private function ensureAdmin(string $redirect): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }
}
