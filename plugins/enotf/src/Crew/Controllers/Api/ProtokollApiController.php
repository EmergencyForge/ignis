<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers\Api;

use App\Auth\Permissions;
use App\Logging\Logger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Crew\Models\Edivi;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use Plugin\Enotf\Crew\Support\ProtokollAccessGuard;
use Plugin\Enotf\Crew\Support\ProtokollService;

/**
 * eNOTF-v2-Protokoll-API.
 *
 * Anders als der v1-save-fields-Endpoint (urlencoded, EIN Feld pro
 * Request, text/plain-Antwort) spricht v2 durchgehend
 * JSON und nimmt mehrere Felder pro Request an:
 *
 *   POST /api/enotf/save-fields
 *     Request:  { "enr": "...", "fields": { "feld": "wert", ... } }
 *     Antwort:  { "ok": bool, "updated": ["feld", ...], "errors": { "feld": "Meldung" } }
 *     404 wenn ENR unbekannt, 403 wenn freigegeben=1, 422 wenn eine im
 *     Batch angeforderte Freigabe am serverseitigen Gate scheitert
 *     (offene Pflichtangaben / kein Protokollant / leerer Freigeber).
 *
 *   GET /api/enotf/protokoll/{enr}
 *     Antwort:  { "ok": true, "protokoll": { ...alle Spalten... } }
 *
 * Auth: AuthMiddleware('ENOTF_REQUIRE_USER_AUTH') auf der Route (User-
 * Gate nur wenn per Config aktiv) + Crew-Session-Check hier, dieselbe
 * Drei-Schichten-Logik wie die Web-Seiten. Eingeloggte User mit
 * edivi-Permissions dürfen ohne Crew-Session lesen (Admin-Tooling),
 * schreiben aber nur mit edivi.edit. Crew-Sessions kommen zusätzlich
 * nur an Protokolle des eigenen Fahrzeugs (ProtokollAccessGuard).
 */
final class ProtokollApiController
{
    /**
     * POST /api/enotf/save-fields: Mehrfeld-Autosave (JSON).
     */
    public function saveFields(Request $request): Response
    {
        if (!CrewPolicy::hasCrewSession() && !Permissions::check(['admin', 'edivi.edit'])) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $input  = $request->json();
        $enr    = isset($input['enr']) && is_string($input['enr']) ? trim($input['enr']) : '';
        $fields = $input['fields'] ?? null;

        if ($enr === '' || !is_array($fields) || $fields === []) {
            return Response::json([
                'ok'      => false,
                'updated' => [],
                'errors'  => ['_request' => 'enr und fields (Objekt mit mindestens einem Feld) sind erforderlich'],
            ], 400);
        }

        $service   = app(ProtokollService::class);
        $protokoll = $service->findByEnr($enr);
        if ($protokoll === null) {
            return Response::json([
                'ok'      => false,
                'updated' => [],
                'errors'  => ['_request' => 'Protokoll nicht gefunden'],
            ], 404);
        }
        if (!ProtokollAccessGuard::canWrite($protokoll)) {
            return Response::json([
                'ok'      => false,
                'updated' => [],
                'errors'  => ['_request' => 'Kein Zugriff auf dieses Protokoll'],
            ], 403);
        }

        try {
            // Panel-User mit admin/edivi.edit dürfen das Freigabe-Gate
            // (offene Pflichtangaben) übersteuern, QM-Korrekturfälle
            $result = $service->saveFields($enr, $fields, Permissions::check(['admin', 'edivi.edit']));
        } catch (\PDOException $e) {
            Logger::error('eNOTF: save-fields Fehler', ['error' => $e->getMessage()]);
            return Response::json(['ok' => false, 'updated' => [], 'errors' => ['_request' => 'Datenbankfehler']], 500);
        }

        if ($result['status'] === ProtokollService::STATUS_NOT_FOUND) {
            return Response::json([
                'ok'      => false,
                'updated' => [],
                'errors'  => ['_request' => 'Protokoll nicht gefunden'],
            ], 404);
        }
        if ($result['status'] === ProtokollService::STATUS_RELEASED) {
            return Response::json([
                'ok'      => false,
                'updated' => [],
                'errors'  => ['_request' => 'Protokoll ist freigegeben und kann nicht mehr bearbeitet werden'],
            ], 403);
        }

        // Abgewiesene Freigabe (Gate: offene Pflichtangaben / fehlender
        // Protokollant / leerer Freigeber) → 422, der Client zeigt
        // errors.freigeber als Dialog an
        $httpStatus = isset($result['errors']['freigeber']) ? 422 : 200;

        return Response::json([
            'ok'      => $result['errors'] === [],
            'updated' => $result['updated'],
            'errors'  => (object) $result['errors'],
        ], $httpStatus);
    }

    /**
     * GET /api/enotf/protokoll/{enr}: Volldaten als JSON.
     */
    public function show(Request $request, string $enr): Response
    {
        if (!CrewPolicy::hasCrewSession() && !CrewPolicy::viewModule()) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $protokoll = app(ProtokollService::class)->findByEnr($enr);
        if ($protokoll === null) {
            return Response::json(['ok' => false, 'error' => 'Protokoll nicht gefunden'], 404);
        }
        if (!ProtokollAccessGuard::canRead($protokoll)) {
            return Response::json(['ok' => false, 'error' => 'Kein Zugriff auf dieses Protokoll'], 403);
        }

        return Response::json([
            'ok'        => true,
            'protokoll' => $protokoll,
        ]);
    }

    /**
     * POST /api/enotf/delete-protocol   JSON: { "enr": "..." }
     *
     * Soft-Delete eines Protokolls durch die Crew (Overview-Swipe).
     * Semantik = v1 POST /api/enotf/delete-protocol: nur Protokolle des
     * eigenen Fahrzeugs (fzg_transp ODER fzg_na), die weder versteckt
     * noch freigegeben sind; Leitstellen-Protokolle (createdby NULL
     * oder 1) sind tabu (403). Gelöscht wird per hidden_user=1 +
     * freigegeben=1, freigeber_name = Fahrer(, Beifahrer). Der v1-
     * Endpoint hängt hinter hartem User-Auth und liefert Crews ohne
     * Panel-Login nur 401er. Hier reicht die Crew-Session.
     */
    public function deleteProtocol(Request $request): Response
    {
        if (empty($_SESSION['protfzg']) || empty($_SESSION['fahrername'])) {
            return Response::json(['success' => false, 'message' => 'Nicht autorisiert'], 401);
        }
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
        }

        $input = $request->json();
        if (empty($input['enr']) || !is_string($input['enr'])) {
            return Response::json(['success' => false, 'message' => 'enr fehlt'], 400);
        }

        $enr     = trim($input['enr']);
        $vehicle = $_SESSION['protfzg'];

        try {
            $protocol = Edivi::where('enr', $enr)
                ->where(function ($q) use ($vehicle) {
                    $q->where('fzg_transp', $vehicle)->orWhere('fzg_na', $vehicle);
                })
                ->where('hidden', 0)
                ->where('hidden_user', 0)
                ->where('freigegeben', 0)
                ->first(['enr', 'createdby', 'hidden_user']);

            if ($protocol === null) {
                return Response::json(['success' => false, 'message' => 'Protokoll nicht gefunden oder nicht zugänglich'], 404);
            }

            // createdby NULL oder 1 = Leitstelle → nicht löschbar
            if ($protocol['createdby'] === null || (int) $protocol['createdby'] === 1) {
                return Response::json([
                    'success' => false,
                    'message' => 'Protokolle der Leitstelle können nicht gelöscht werden',
                ], 403);
            }

            $freigeber = $_SESSION['fahrername'];
            if (!empty($_SESSION['beifahrername'])) {
                $freigeber .= ', ' . $_SESSION['beifahrername'];
            }

            Edivi::where('enr', $enr)->update([
                'hidden_user'    => 1,
                'freigeber_name' => $freigeber,
                'last_edit'      => Capsule::connection()->raw('NOW()'),
                'freigegeben'    => 1,
            ]);

            return Response::json(['success' => true, 'message' => 'Protokoll erfolgreich gelöscht']);
        } catch (\PDOException $e) {
            Logger::error('eNOTF: delete-protocol Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Interner Fehler'], 500);
        }
    }
}
