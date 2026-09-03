<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers;

use App\Helpers\Flash;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Models\Edivi;
use Plugin\EnotfV2\Models\Quicklink;
use Plugin\EnotfV2\Models\QuicklinkCategory;

/**
 * OverviewController — Protokoll-Liste + Quicklinks fürs eingeloggte
 * Fahrzeug.
 *
 * Listen-Filter: freigegeben=0 AND (fzg_transp=fzg OR fzg_na=fzg)
 * AND hidden=0 AND hidden_user=0, sortiert nach created_at.
 *
 * delete_all (POST): markiert alle offenen Protokolle des Fahrzeugs als
 * User-gelöscht — hidden_user=1, freigegeben=1, freigeber_name =
 * "<Fahrer>, <Beifahrer>", last_edit=NOW(). Kein echtes DELETE.
 *
 * Einzellöschen läuft clientseitig über den v1-Endpoint
 * POST /api/enotf/delete-protocol (403 bei createdby IS NULL oder =1).
 */
class OverviewController extends EnotfV2Controller
{
    /**
     * GET /enotf-v2/overview
     */
    public function index(): void
    {
        $this->bootPage();
        $this->requireCrewSession();

        $vehicle = (string) $_SESSION['protfzg'];

        $protokolle = Edivi::query()
            ->offenFuerFahrzeug($vehicle)
            ->orderBy('created_at')
            ->get([
                'patname', 'patgebdat', 'edatum', 'ezeit', 'enr', 'prot_by',
                'freigegeben', 'pfname', 'createdby', 'ziel_poi', 'ziel_adresse',
            ])
            ->map(fn ($m) => $m->toArray())
            ->all();

        $categories = QuicklinkCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => $m->toArray())
            ->all();

        // Alle aktiven Links in EINEM Query holen und nach Kategorie
        // gruppieren — ein Query pro Kategorie summiert sich auf der
        // latenzbehafteten Remote-Dev-DB spürbar.
        $linksByCategory = array_fill_keys(array_column($categories, 'slug'), []);
        if ($linksByCategory !== []) {
            $links = Quicklink::query()
                ->whereIn('category_slug', array_keys($linksByCategory))
                ->active()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($m) => $m->toArray())
                ->all();
            foreach ($links as $link) {
                $linksByCategory[$link['category_slug']][] = $link;
            }
        }

        $this->renderView('overview', [
            'protokolle'      => $protokolle,
            'categories'      => $categories,
            'linksByCategory' => $linksByCategory,
            'crew'            => $this->crewContext(),
        ]);
    }

    /**
     * POST /enotf-v2/overview (delete_all) — alle offenen Protokolle des
     * Fahrzeugs als User-gelöscht markieren (Semantik exakt v1).
     */
    public function deleteAll(): void
    {
        $this->bootPage();
        $this->requireCrewSession();

        if (!isset($_POST['delete_all'])) {
            $this->redirectAbsolute(EnotfV2Url::page('overview'));
        }

        try {
            $freigeberName = (string) $_SESSION['fahrername'];
            if (!empty($_SESSION['beifahrername'])) {
                $freigeberName .= ', ' . $_SESSION['beifahrername'];
            }

            Edivi::query()
                ->offenFuerFahrzeug((string) $_SESSION['protfzg'])
                ->update([
                    'hidden_user'    => 1,
                    'freigeber_name' => $freigeberName,
                    'last_edit'      => Capsule::raw('NOW()'),
                    'freigegeben'    => 1,
                ]);
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Löschen der Protokolle.');
            \App\Logging\Logger::error('EnotfV2: delete_all fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        $this->redirectAbsolute(EnotfV2Url::page('overview'));
    }
}
