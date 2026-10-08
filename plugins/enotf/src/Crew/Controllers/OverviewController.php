<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers;

use App\Helpers\Flash;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Crew\Models\Edivi;
use Plugin\Enotf\Crew\Models\Quicklink;
use Plugin\Enotf\Crew\Models\QuicklinkCategory;

/**
 * OverviewController: Protokoll-Liste + Quicklinks fürs eingeloggte
 * Fahrzeug.
 *
 * Listen-Filter: freigegeben=0 AND (fzg_transp=fzg OR fzg_na=fzg)
 * AND hidden=0 AND hidden_user=0, sortiert nach created_at.
 *
 * delete_all (POST): markiert alle offenen Protokolle des Fahrzeugs als
 * User-gelöscht: hidden_user=1, freigegeben=1, freigeber_name =
 * "<Fahrer>, <Beifahrer>", last_edit=NOW(). Kein echtes DELETE.
 *
 * Einzellöschen läuft clientseitig über den v1-Endpoint
 * POST /api/enotf/delete-protocol (403 bei createdby IS NULL oder =1).
 */
class OverviewController extends CrewController
{
    /**
     * GET /enotf/overview
     */
    public function index(): void
    {
        $this->bootPage();
        $this->requireCrewSession();

        $vehicle = (string) $_SESSION['protfzg'];

        $protokolle = Edivi::offenFuerFahrzeug($vehicle)
            ->orderBy('created_at')
            ->get([
                'patname', 'patgebdat', 'edatum', 'ezeit', 'enr', 'prot_by',
                'freigegeben', 'pfname', 'createdby', 'ziel_poi', 'ziel_adresse',
            ])
            ->toArray();

        $categories = QuicklinkCategory::active()
            ->orderBy('sort_order')
            ->get()
            ->toArray();

        // Alle aktiven Links in EINEM Query holen und nach Kategorie
        // gruppieren, ein Query pro Kategorie summiert sich auf der
        // latenzbehafteten Remote-Dev-DB spürbar.
        $linksByCategory = array_fill_keys(array_column($categories, 'slug'), []);
        if ($linksByCategory !== []) {
            $links = Quicklink::active()
                ->whereIn('category_slug', array_keys($linksByCategory))
                ->orderBy('sort_order')
                ->get()
                ->toArray();
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
     * POST /enotf/overview (delete_all): alle offenen Protokolle des
     * Fahrzeugs als User-gelöscht markieren (Semantik exakt v1).
     */
    public function deleteAll(): void
    {
        $this->bootPage();
        $this->requireCrewSession();

        if (!isset($_POST['delete_all'])) {
            $this->redirectAbsolute(EnotfUrl::page('overview'));
        }

        try {
            $freigeberName = (string) $_SESSION['fahrername'];
            if (!empty($_SESSION['beifahrername'])) {
                $freigeberName .= ', ' . $_SESSION['beifahrername'];
            }

            Edivi::offenFuerFahrzeug((string) $_SESSION['protfzg'])
                ->update([
                    'hidden_user'    => 1,
                    'freigeber_name' => $freigeberName,
                    'last_edit'      => Capsule::connection()->raw('NOW()'),
                    'freigegeben'    => 1,
                ]);
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Löschen der Protokolle.');
            \App\Logging\Logger::error('eNOTF: delete_all fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        $this->redirectAbsolute(EnotfUrl::page('overview'));
    }
}
