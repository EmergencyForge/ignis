<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers\Api;

use App\Auth\Permissions;
use App\Logging\Logger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as DB;
use Plugin\Enotf\Crew\Models\Edivi;
use Plugin\Enotf\Crew\Models\EdiviPoi;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use Plugin\Enotf\Crew\Support\ProtokollAccessGuard;
use Plugin\Enotf\Crew\Support\ProtokollService;

/**
 * POI- und Adress-API für eNOTF v2 (Section Rettungsdaten).
 *
 * Ersetzt die v1-Endpoints poi/poi-search und poi/save-field:
 *
 *   GET /api/enotf/poi/search?search=<query>
 *     Sucht aktive POIs (LIKE auf name/ort, LIMIT 50); leere Query
 *     liefert die ersten 50.
 *     Antwort: { "ok": true, "pois": [{id,name,strasse,hnr,ort,ortsteil,typ}] }
 *
 *   POST /api/enotf/poi/save-address
 *     Request: { "enr": "...", "target": "transp"|"ziel",
 *                "poi": "Name", "adresse": {strasse,hnr,ort,ortsteil} }
 *     Schreibt transp_poi/transp_adresse bzw. ziel_poi/ziel_adresse.
 *     Die Adresse wird strukturell validiert (nur die vier bekannten
 *     Schlüssel, Werte skalars) und serverseitig neu serialisiert,
 *     anders als v1, das den Client-JSON-String nur auf Syntax prüfte
 *     und roh durchreichte.
 *     404 wenn ENR unbekannt, 403 wenn freigegeben=1 (Sperrlogik aus
 *     ProtokollService, v1 prüfte die Freigabe hier gar nicht).
 *
 * Auth wie ProtokollApiController: AuthMiddleware('ENOTF_REQUIRE_USER_AUTH')
 * auf der Route, Crew-Session-Check hier; eingeloggte User mit
 * edivi-Rechten dürfen ohne Crew-Session lesen bzw. mit edivi.edit
 * schreiben (Admin-Tooling).
 */
final class PoiApiController
{
    /** Erlaubte Adress-Schlüssel: exakt die v1-JSON-Struktur. */
    private const ADRESS_KEYS = ['strasse', 'hnr', 'ort', 'ortsteil'];

    /**
     * GET /api/enotf/poi/search?search=<query>
     */
    public function poiSearch(Request $request): Response
    {
        if (!CrewPolicy::hasCrewSession() && !CrewPolicy::viewModule()) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $search = trim((string) ($request->query['search'] ?? ''));

        try {
            $query = EdiviPoi::active()
                ->orderBy('name');

            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'LIKE', $like)
                        ->orWhere('ort', 'LIKE', $like);
                });
            }

            $pois = $query->limit(50)
                ->get(['id', 'name', 'strasse', 'hnr', 'ort', 'ortsteil', 'typ'])
                ->toArray();

            return Response::json(['ok' => true, 'pois' => $pois]);
        } catch (\Throwable $e) {
            Logger::error('eNOTF: poi/search Fehler', ['error' => $e->getMessage()]);
            return Response::json(['ok' => false, 'error' => 'Datenbankfehler'], 500);
        }
    }

    /**
     * POST /api/enotf/poi/save-address
     */
    public function saveAddress(Request $request): Response
    {
        if (!CrewPolicy::hasCrewSession() && !Permissions::check(['admin', 'edivi.edit'])) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $input  = $request->json();
        $enr    = isset($input['enr']) && is_string($input['enr']) ? trim($input['enr']) : '';
        $target = isset($input['target']) && is_string($input['target']) ? $input['target'] : '';
        $poi    = isset($input['poi']) && is_scalar($input['poi']) ? trim((string) $input['poi']) : '';

        if ($enr === '' || !in_array($target, ['transp', 'ziel'], true)) {
            return Response::json([
                'ok'    => false,
                'error' => 'enr und target (transp|ziel) sind erforderlich',
            ], 400);
        }

        $adresse = $this->normalizeAdresse($input['adresse'] ?? null);
        if ($adresse === null) {
            return Response::json([
                'ok'    => false,
                'error' => 'adresse muss ein Objekt mit den Schlüsseln strasse, hnr, ort, ortsteil sein',
            ], 400);
        }

        $service   = app(ProtokollService::class);
        $protokoll = $service->findByEnr($enr);
        if ($protokoll === null) {
            return Response::json(['ok' => false, 'error' => 'Protokoll nicht gefunden'], 404);
        }
        if (!ProtokollAccessGuard::canWrite($protokoll)) {
            return Response::json(['ok' => false, 'error' => 'Kein Zugriff auf dieses Protokoll'], 403);
        }
        if ($service->istGesperrt($protokoll)) {
            return Response::json([
                'ok'    => false,
                'error' => 'Protokoll ist freigegeben und kann nicht mehr bearbeitet werden',
            ], 403);
        }

        // Spaltennamen kommen aus der Whitelist oben, nie aus dem Request
        $poiColumn     = $target . '_poi';
        $adresseColumn = $target . '_adresse';

        try {
            Edivi::query()->where('enr', $enr)->update([
                $poiColumn     => $poi,
                $adresseColumn => json_encode($adresse, JSON_UNESCAPED_UNICODE),
                'last_edit'    => DB::connection()->raw('NOW()'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('eNOTF: poi/save-address Fehler', ['error' => $e->getMessage()]);
            return Response::json(['ok' => false, 'error' => 'Datenbankfehler'], 500);
        }

        return Response::json(['ok' => true]);
    }

    /**
     * Validiert und normalisiert die Adresse auf exakt die v1-Struktur
     * {strasse, hnr, ort, ortsteil} (alle Werte als String, fehlende
     * Schlüssel = ''). null bei struktureller Verletzung.
     *
     * @return array{strasse:string,hnr:string,ort:string,ortsteil:string}|null
     */
    private function normalizeAdresse(mixed $adresse): ?array
    {
        if (!is_array($adresse)) {
            return null;
        }

        foreach ($adresse as $key => $value) {
            if (!in_array($key, self::ADRESS_KEYS, true)) {
                return null;
            }
            if ($value !== null && !is_scalar($value)) {
                return null;
            }
        }

        $normalized = [];
        foreach (self::ADRESS_KEYS as $key) {
            $normalized[$key] = trim((string) ($adresse[$key] ?? ''));
        }

        return $normalized;
    }
}
