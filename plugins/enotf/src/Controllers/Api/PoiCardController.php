<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;

/**
 * GET /api/pois/{id}/card — Hover-Card-Fragment für einen POI.
 *
 * Liefert ein kompaktes HTML-Fragment für die hauseigene Hover-Card-
 * Komponente (siehe assets/js/modules/user-hover-card.js, Type `poi`).
 * Wird in Templates getriggert über `data-poi-card="{id}"` an einem
 * Anchor-Element.
 *
 * Auth: Session-Login reicht — der Inhalt ist nicht sensibler als das,
 * was in den Protokoll-/Schnittstelle-Listen ohnehin sichtbar ist.
 */
final class PoiCardController extends Controller
{
    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();

        $poiId = (int) $id;
        if ($poiId <= 0) {
            return Response::html('Ungültige POI-ID.', 400);
        }

        $poi = Capsule::table('intra_edivi_pois')
            ->where('id', $poiId)
            ->first();

        if ($poi === null) {
            return Response::html('POI nicht gefunden.', 404);
        }

        // Departments laden, falls vorhanden — gibt eine kompakte
        // Verfügbarkeits-Aggregation in der Card.
        $departments = Capsule::table('intra_edivi_hospital_departments as d')
            ->leftJoin('intra_edivi_hospital_availability as a', 'd.id', '=', 'a.department_id')
            ->where('d.poi_id', $poiId)
            ->orderBy('d.sort_order')
            ->select(
                'd.id',
                'd.name',
                Capsule::raw("COALESCE(a.status, 'not_staffed') AS status"),
            )
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        ob_start();
        include __DIR__ . '/../../../../assets/components/profiles/_poi-hover-card.php';
        return Response::html((string) ob_get_clean());
    }
}
