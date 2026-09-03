<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers\Api;

use App\Auth\Permissions;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use Plugin\Enotf\Helpers\BloodSugarHelper;
use Plugin\EnotfV2\Catalogs\VitalparameterCatalog;
use Plugin\EnotfV2\Models\EdiviVitalwert;
use Plugin\EnotfV2\Policies\EnotfV2Policy;
use Plugin\EnotfV2\Support\ProtokollAccessGuard;
use Plugin\EnotfV2\Support\ProtokollService;

/**
 * Vitalwerte-API für den Verlauf (Tabelle intra_edivi_vitalparameter_einzelwerte).
 *
 * v2 arbeitet nach außen mit Katalog-Codes (spo2, rrsys, …) statt der
 * deutschen Legacy-Anzeigestrings — GESCHRIEBEN wird aber weiterhin exakt
 * das v1-Format (parameter_name = "SpO₂" usw. aus VitalparameterCatalog),
 * damit v1-Verlauf, Druck und Auswertungen dieselben Daten sehen.
 *
 *   GET  /api/enotf-v2/vitals/{enr}
 *     → { ok, bz_unit, locked, groups: [ { zeitpunkt, werte: [
 *          { id, code, name, wert, wert_anzeige, einheit, einheit_anzeige, erstellt_von } ] } ] }
 *     Gruppiert nach zeitpunkt (aufsteigend), nur geloescht=0.
 *
 *   POST /api/enotf-v2/vitals
 *     Request: { enr, zeitpunkt: "YYYY-MM-DD(T| )HH:MM(:SS)", werte: { code: wert, ... } }
 *     Ein INSERT pro nicht-leerem Wert (v1-Parität zu verlauf/add.php).
 *     Blutzucker kommt in der ANZEIGE-Einheit (ENOTF_BZ_UNIT) an und wird
 *     via BloodSugarHelper::toStorageUnit() in mg/dl gespeichert.
 *     403 wenn freigegeben, 404 wenn ENR unbekannt.
 *
 *   POST /api/enotf-v2/vitals/delete
 *     Request: { enr, id }
 *     Soft-Delete via EdiviVitalwert::softDelete() — der DB-Trigger
 *     blockiert Hard-Deletes ohnehin. Anders als v1 auch hier mit
 *     Sperr-Prüfung bei freigegeben.
 */
final class VitalsApiController
{
    /**
     * GET /api/enotf-v2/vitals/{enr} — Verlauf, gruppiert nach Zeitpunkt.
     */
    public function index(Request $request, string $enr): Response
    {
        if (!EnotfV2Policy::hasCrewSession() && !EnotfV2Policy::viewModule()) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $service   = app(ProtokollService::class);
        $protokoll = $service->findByEnr($enr);
        if ($protokoll === null) {
            return Response::json(['ok' => false, 'error' => 'Protokoll nicht gefunden'], 404);
        }
        if (!ProtokollAccessGuard::canRead($protokoll)) {
            return Response::json(['ok' => false, 'error' => 'Kein Zugriff auf dieses Protokoll'], 403);
        }

        $bzHelper = new BloodSugarHelper();
        $bzUnit   = $bzHelper->getCurrentUnit();

        $werte = EdiviVitalwert::aktiv()
            ->where('enr', $enr)
            ->orderBy('zeitpunkt')
            ->orderBy('id')
            ->get();

        $groups = [];
        foreach ($werte as $wert) {
            $zeitpunkt = (string) $wert->zeitpunkt;
            $code      = VitalparameterCatalog::fromLegacyName((string) $wert->parameter_name);

            $anzeige        = (string) $wert->parameter_wert;
            $anzeigeEinheit = (string) $wert->parameter_einheit;
            if ($code === 'bz') {
                // Speicherung ist immer mg/dl — Anzeige folgt ENOTF_BZ_UNIT
                $anzeige        = $bzHelper->formatValue($wert->parameter_wert, false);
                $anzeigeEinheit = $bzUnit;
            }

            $groups[$zeitpunkt][] = [
                'id'              => (int) $wert->id,
                'code'            => $code,
                'name'            => (string) $wert->parameter_name,
                'wert'            => (string) $wert->parameter_wert,
                'wert_anzeige'    => $anzeige,
                'einheit'         => (string) $wert->parameter_einheit,
                'einheit_anzeige' => $anzeigeEinheit,
                'erstellt_von'    => (string) ($wert->erstellt_von ?? ''),
            ];
        }

        $result = [];
        foreach ($groups as $zeitpunkt => $eintraege) {
            $result[] = ['zeitpunkt' => $zeitpunkt, 'werte' => $eintraege];
        }

        return Response::json([
            'ok'      => true,
            'bz_unit' => $bzUnit,
            'locked'  => $service->istGesperrt($protokoll),
            'groups'  => $result,
        ]);
    }

    /**
     * POST /api/enotf-v2/vitals — mehrere Parameter zu EINEM Zeitpunkt anlegen.
     */
    public function add(Request $request): Response
    {
        if (!EnotfV2Policy::hasCrewSession() && !Permissions::check(['admin', 'edivi.edit'])) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $input = $request->json() ?? [];
        $enr   = isset($input['enr']) && is_string($input['enr']) ? trim($input['enr']) : '';
        $werte = $input['werte'] ?? null;

        if ($enr === '' || !is_array($werte)) {
            return Response::json(['ok' => false, 'error' => 'enr und werte sind erforderlich'], 400);
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
            return Response::json(['ok' => false, 'error' => 'Protokoll ist freigegeben und kann nicht mehr bearbeitet werden'], 403);
        }

        $zeitpunkt = $this->normalizeZeitpunkt(isset($input['zeitpunkt']) ? (string) $input['zeitpunkt'] : '');
        if ($zeitpunkt === null) {
            return Response::json(['ok' => false, 'error' => 'Ungültiger Zeitpunkt (erwartet YYYY-MM-DD HH:MM)'], 400);
        }

        $bzHelper    = new BloodSugarHelper();
        $erstelltVon = (string) ($_SESSION['username'] ?? $_SESSION['fahrername'] ?? 'Unbekannt');

        $gespeichert = 0;
        try {
            foreach ($werte as $code => $wert) {
                $code = (string) $code;
                $info = VitalparameterCatalog::PARAMETER[$code] ?? null;
                if ($info === null) {
                    return Response::json(['ok' => false, 'error' => 'Unbekannter Parameter: ' . $code], 400);
                }

                $wert = is_scalar($wert) ? trim((string) $wert) : '';
                if ($wert === '') {
                    continue;
                }

                // BZ kommt in der Anzeige-Einheit an, gespeichert wird mg/dl
                if ($code === 'bz') {
                    $wert = (string) $bzHelper->toStorageUnit($wert);
                }

                EdiviVitalwert::create([
                    'enr'               => $enr,
                    'zeitpunkt'         => $zeitpunkt,
                    'parameter_name'    => $info['name'],
                    'parameter_wert'    => $wert,
                    'parameter_einheit' => $info['einheit'],
                    'erstellt_von'      => $erstelltVon,
                ]);
                $gespeichert++;
            }
        } catch (\Throwable $e) {
            Logger::error('EnotfV2: Vitalwerte-Insert fehlgeschlagen', ['enr' => $enr, 'error' => $e->getMessage()]);
            return Response::json(['ok' => false, 'error' => 'Datenbankfehler'], 500);
        }

        if ($gespeichert === 0) {
            return Response::json(['ok' => false, 'error' => 'Keine Werte zum Speichern übergeben'], 400);
        }

        return Response::json(['ok' => true, 'saved' => $gespeichert, 'zeitpunkt' => $zeitpunkt]);
    }

    /**
     * POST /api/enotf-v2/vitals/delete — Soft-Delete eines Einzelwerts.
     */
    public function delete(Request $request): Response
    {
        if (!EnotfV2Policy::hasCrewSession() && !Permissions::check(['admin', 'edivi.edit'])) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $input = $request->json() ?? [];
        $enr   = isset($input['enr']) && is_string($input['enr']) ? trim($input['enr']) : '';
        $id    = isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0;

        if ($enr === '' || $id <= 0) {
            return Response::json(['ok' => false, 'error' => 'enr und id sind erforderlich'], 400);
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
            return Response::json(['ok' => false, 'error' => 'Protokoll ist freigegeben und kann nicht mehr bearbeitet werden'], 403);
        }

        $wert = EdiviVitalwert::aktiv()
            ->where('id', $id)
            ->where('enr', $enr)
            ->first();
        if ($wert === null) {
            return Response::json(['ok' => false, 'error' => 'Messwert nicht gefunden'], 404);
        }

        $geloeschtVon = (string) ($_SESSION['username'] ?? $_SESSION['fahrername'] ?? 'Unbekannt');
        $wert->softDelete($geloeschtVon);

        return Response::json(['ok' => true]);
    }

    /**
     * "YYYY-MM-DDTHH:MM(:SS)" bzw. "YYYY-MM-DD HH:MM(:SS)" → "Y-m-d H:i:s",
     * null bei ungültigem Input.
     */
    private function normalizeZeitpunkt(string $value): ?string
    {
        $value = str_replace('T', ' ', trim($value));
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
            return null;
        }
        if (strlen($value) === 16) {
            $value .= ':00';
        }

        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return ($dt && $dt->format('Y-m-d H:i:s') === $value) ? $value : null;
    }
}
