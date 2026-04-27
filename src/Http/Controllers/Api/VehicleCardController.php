<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;

/**
 * GET /api/vehicles/{id}/card — Hover-Card-Fragment für ein Fahrzeug.
 *
 * Liefert ein kompaktes HTML-Fragment für die hauseigene Hover-Card-
 * Komponente (siehe assets/js/modules/user-hover-card.js, Type
 * `vehicle`). Wird in Templates getriggert über
 * `data-vehicle-card="{id}"` an einem Anchor-Element.
 *
 * Auth: Session-Login reicht.
 */
final class VehicleCardController extends Controller
{
    private const RD_TYPE_LABELS = [
        0 => 'Andere',
        1 => 'RD – mit NA',
        2 => 'RD – ohne NA',
        3 => 'Feuerwehr',
    ];

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();

        $vehicleId = (int) $id;
        if ($vehicleId <= 0) {
            return Response::html('Ungültige Fahrzeug-ID.', 400);
        }

        $vehicle = Capsule::table('intra_fahrzeuge')
            ->where('id', $vehicleId)
            ->first();

        if ($vehicle === null) {
            return Response::html('Fahrzeug nicht gefunden.', 404);
        }

        // Offene Defekte aggregieren — getrennt nach „blockierend"
        // (vehicle_operable = 0) und „nur informativ", damit die Card
        // den Einsatzbereitschafts-Status sofort sichtbar macht.
        $defectStats = Capsule::table('intra_fahrzeuge_defects')
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', ['open', 'in_progress', 'deferred'])
            ->selectRaw(
                'SUM(CASE WHEN vehicle_operable = 0 THEN 1 ELSE 0 END) AS blocking, '
                . 'SUM(CASE WHEN vehicle_operable = 1 THEN 1 ELSE 0 END) AS informational'
            )
            ->first();

        $blocking      = (int) ($defectStats->blocking ?? 0);
        $informational = (int) ($defectStats->informational ?? 0);
        $rdTypeLabel   = self::RD_TYPE_LABELS[(int) ($vehicle->rd_type ?? 0)] ?? 'Andere';

        ob_start();
        include __DIR__ . '/../../../../assets/components/profiles/_vehicle-hover-card.php';
        return Response::html((string) ob_get_clean());
    }
}
