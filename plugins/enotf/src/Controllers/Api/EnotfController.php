<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Api;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Logging\Logger;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as DB;
use PDOException;

/**
 * eNOTF-API außerhalb der Crew: Ankunftstafel, Abrechnung für den
 * Spielserver und das Aufräumen leerer Protokolle aus der Prüfliste.
 */
final class EnotfController
{
    /**
     * GET /api/enotf/prereg?klinik=...
     * Aktive Voranmeldungen abfragen. Deaktiviert gleichzeitig veraltete
     * Einträge (ankunftszeit älter als 10 Min).
     */
    public function prereg(Request $request): Response
    {
        date_default_timezone_set('Europe/Berlin');
        $ziel = $request->query['klinik'] ?? null;

        try {
            DB::table('intra_edivi_prereg')
                ->where('active', 1)
                ->whereNotNull('arrival')
                ->where('arrival', '<', DB::connection()->raw('NOW() - INTERVAL 10 MINUTE'))
                ->update(['active' => 0]);

            $query = DB::table('intra_edivi_prereg')
                ->where('active', 1)
                ->orderBy('arrival');
            if ($ziel) {
                $query->where('ziel', $ziel);
            }

            return Response::json([
                'success' => true,
                'data'    => $query->get()->map(fn ($r) => (array) $r)->all(),
            ]);
        } catch (PDOException $e) {
            Logger::error('Enotf: prereg Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler'], 500);
        }
    }

    // ── POI-Endpoints ─────────────────────────────────────────────────

    // ── Share-Endpoints (teilweise, die einfacheren) ──────────────────

    // ── Share-Endpoints (Protokoll-Übergabe zwischen Fahrzeugen) ──────

    // ── Billing (API-Key-Auth, extern aufgerufen) ──────────────────────

    /**
     * POST /api/enotf/billing
     * JSON: { "intraRP_API_Key": "...", "timestamp": <unix> }
     *
     * Gibt freigegebene Protokolle mit billing_sent=0 zurück und markiert
     * sie als abgerechnet. Den API-Key prüft die ApiKeyMiddleware der Route
     * (Header X-API-Key oder intraRP_API_Key im JSON-Body).
     */
    public function billing(Request $request): Response
    {
        $data = $request->json();
        if (!is_array($data)) {
            return Response::json(['success' => false, 'error' => 'Ungültiges JSON'], 400);
        }

        $timestamp = $data['timestamp'] ?? null;
        if (!$timestamp) {
            return Response::json(['success' => false, 'error' => 'Erforderliche Felder fehlen', 'message' => 'timestamp ist erforderlich'], 400);
        }

        try {
            $date = date('Y-m-d H:i:s', (int) $timestamp);

            $protocols = DB::table('intra_edivi as e')
                ->leftJoin('intra_fahrzeuge as fzg_t', 'e.fzg_transp', '=', 'fzg_t.identifier')
                ->leftJoin('intra_fahrzeuge as fzg_na_tbl', 'e.fzg_na', '=', 'fzg_na_tbl.identifier')
                ->select(
                    'e.id', 'e.enr as missionNumber', 'e.patname as name', 'e.patgebdat as birthdate',
                    'e.transportziel', 'e.prot_by', 'e.fzg_transp', 'e.fzg_na', 'e.created_at',
                    DB::connection()->raw('COALESCE(fzg_t.name, fzg_na_tbl.name) AS vehicle_callsign')
                )
                ->where(function ($q) {
                    $q->whereNull('e.billing_sent')->orWhere('e.billing_sent', 0);
                })
                ->where('e.freigegeben', 1)
                ->where('e.created_at', '<=', $date)
                ->orderBy('e.created_at')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            if (empty($protocols)) {
                return Response::json(['success' => true, 'count' => 0, 'protocols' => []]);
            }

            $result = [];
            $ids    = [];
            foreach ($protocols as $p) {
                $ids[] = $p['id'];
                $result[] = [
                    'name'            => $p['name'] ?? '',
                    'birthdate'       => $p['birthdate'] ?? '',
                    'transport'       => in_array($p['transportziel'], [2, 21, 22], true),
                    'missionNumber'   => $p['missionNumber'] ?? '',
                    'protocolType'    => (int) ($p['prot_by'] ?? 0),
                    'vehicleCallsign' => $p['vehicle_callsign'] ?? '',
                ];
            }

            DB::table('intra_edivi')
                ->whereIn('id', $ids)
                ->update(['billing_sent' => 1, 'billing_sent_at' => DB::connection()->raw('NOW()')]);

            return Response::json(['success' => true, 'count' => count($result), 'protocols' => $result]);
        } catch (PDOException $e) {
            Logger::error('Enotf: billing Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Billing-Verarbeitungsfehler'], 500);
        }
    }

    // ── Bulk-Delete (Admin) ──────────────────────────────────────────

    /**
     * GET|POST /api/enotf/bulk-delete-empty
     *
     * GET  → liefert verfügbare Prüf-Felder.
     * POST → Preview (mit `preview`-Flag) oder tatsächliches Soft-Delete
     *        leerer Protokolle anhand ausgewählter Felder + Zeitraum.
     *
     * Erfordert Permission `admin` oder `edivi.edit`.
     */
    public function bulkDeleteEmpty(Request $request): Response
    {
        if (!isset($_SESSION['userid'], $_SESSION['permissions'])) {
            return Response::json(['success' => false, 'message' => 'Nicht authentifiziert']);
        }
        if (Gate::denies('enotf.bulkDelete')) {
            return Response::json(['success' => false, 'message' => 'Keine Berechtigung']);
        }

        $availableFields = [
            'patname'        => 'Patientenname',
            'patgebdat'      => 'Geburtsdatum',
            'fahrzeuge'      => 'Transportfahrzeug ODER Notarztfahrzeug',
            'ziel_adresse'   => 'Zieladresse',
            'transp_adresse' => 'Einsatzort (Von-Adresse)',
        ];

        if (strtoupper($request->method) === 'GET') {
            return Response::json(['success' => true, 'fields' => $availableFields]);
        }

        $selectedFields = $request->post['fields'] ?? ['patname'];
        $isPreview      = isset($request->post['preview']);
        $timePeriod     = $request->post['timePeriod'] ?? '30';

        $fieldsToCheck = array_intersect($selectedFields, array_keys($availableFields));
        if (empty($fieldsToCheck)) {
            return Response::json(['success' => false, 'message' => 'Keine gültigen Felder ausgewählt']);
        }

        $conditions = [];
        foreach ($fieldsToCheck as $field) {
            $conditions[] = match ($field) {
                'patgebdat'      => "({$field} IS NULL OR {$field} = '0000-00-00')",
                'fahrzeuge'      => "((fzg_transp IS NULL OR fzg_transp = '') OR (fzg_na IS NULL OR fzg_na = ''))",
                'ziel_adresse',
                'transp_adresse' => "({$field} IS NULL OR {$field} = '' OR {$field} = '{}' OR {$field} = '[]')",
                default          => "({$field} IS NULL OR {$field} = '' OR {$field} = 'Unbekannt')",
            };
        }
        $whereClause = implode(' AND ', $conditions);

        // Feld-Namen stammen aus der Whitelist, $days ist int-gecastet,
        // die Raw-Fragmente enthalten keine User-Eingaben.
        $makeQuery = function () use ($whereClause, $timePeriod) {
            $query = DB::table('intra_edivi')
                ->where('hidden', '<>', 1)
                ->whereRaw("({$whereClause})");
            if ($timePeriod !== 'all') {
                $days = max(1, (int) $timePeriod);
                $query->whereRaw("sendezeit > DATE_SUB(NOW(), INTERVAL {$days} DAY)");
            }
            return $query;
        };

        $selectedLabel = implode(', ', array_map(fn($f) => $availableFields[$f] ?? $f, $fieldsToCheck));

        try {
            if ($isPreview) {
                $protocols = $makeQuery()
                    ->select('id', 'enr', 'patname', 'sendezeit', 'pfname')
                    ->orderByDesc('sendezeit')
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
                return Response::json([
                    'success'             => true,
                    'protocols'           => $protocols,
                    'count'               => count($protocols),
                    'selectedFieldsLabel' => $selectedLabel,
                ]);
            }

            $count = (int) $makeQuery()->count();

            if ($count === 0) {
                return Response::json(['success' => true, 'message' => 'Keine leeren Protokolle gefunden', 'deleted' => 0]);
            }

            $bearbeiter = $_SESSION['username'] ?? 'System';
            $affected = $makeQuery()->update([
                'hidden'           => 1,
                'protokoll_status' => 4,
                'bearbeiter'       => $bearbeiter,
            ]) ?: $count;

            $timeLabel = $timePeriod === 'all' ? 'alle' : "letzte {$timePeriod} Tage";

            (new AuditLogger())->log(
                $_SESSION['userid'],
                "Bulk-Delete: {$affected} leere Protokolle gelöscht",
                "Gelöschte Protokolle mit leeren Feldern ({$selectedLabel}), Zeitraum: {$timeLabel}",
                'eNOTF',
                0
            );

            Flash::set('success', "Es wurden {$affected} leere Protokolle erfolgreich gelöscht.");

            return Response::json(['success' => true, 'message' => "{$affected} Protokolle wurden gelöscht", 'deleted' => $affected]);
        } catch (PDOException $e) {
            Logger::error('Enotf: bulk-delete Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Fehler beim Löschen: ' . $e->getMessage()]);
        }
    }

    // ── Save-Fields (Einzelfeld-Update) ──────────────────────────────

    // ── Share: Accept Request ────────────────────────────────────────

    // ── Private Helper ────────────────────────────────────────────────

}
