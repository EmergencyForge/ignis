<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;
use Plugin\Enotf\Requests\QuicklinkCategoryRequest;
use Plugin\Enotf\Requests\QuicklinkRequest;

/**
 * EnotfController: eNOTF-Quicklinks und -Kategorien.
 */
class EnotfController extends Controller
{
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 3) . '/templates';
    }

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
        $this->ensureAdmin('settings/enotf/index');

        $input        = $this->quicklinkInput();
        $title        = $input['title'];
        $url          = $input['url'];
        $icon         = $input['icon'];
        $categorySlug = $input['category'];
        $sortOrder    = $input['sort_order'];
        $colWidth     = $input['col_width'];
        $active       = $input['active'];

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
        $this->ensureAdmin('settings/enotf/index');

        $input        = $this->quicklinkInput();
        $id           = $input['id'];
        $title        = $input['title'];
        $url          = $input['url'];
        $icon         = $input['icon'];
        $categorySlug = $input['category'];
        $sortOrder    = $input['sort_order'];
        $colWidth     = $input['col_width'];
        $active       = $input['active'];

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
        $this->ensureAdmin('settings/enotf/index');

        $id = $this->quicklinkInput()['id'];
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
        $this->ensureAdmin('settings/enotf/kategorien/index');

        $input     = $this->categoryInput();
        $name      = $input['name'];
        $slug      = $input['slug'];
        $sortOrder = $input['sort_order'];
        $active    = $input['active'];

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
        $this->ensureAdmin('settings/enotf/kategorien/index');

        $input     = $this->categoryInput();
        $id        = $input['id'];
        $name      = $input['name'];
        $slug      = $input['slug'];
        $sortOrder = $input['sort_order'];
        $active    = $input['active'];

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
        $this->ensureAdmin('settings/enotf/kategorien/index');

        $id = $this->categoryInput()['id'];
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

    /**
     * @return array<string,mixed>
     */
    private function quicklinkInput(): array
    {
        try {
            return QuicklinkRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::set('error', $e->firstError() ?? 'Ungültige Daten.');
            $this->redirect('settings/enotf/index');
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function categoryInput(): array
    {
        try {
            return QuicklinkCategoryRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::set('error', $e->firstError() ?? 'Ungültige Daten.');
            $this->redirect('settings/enotf/kategorien/index');
        }
    }

    private function ensureAdmin(string $redirect): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }
}
