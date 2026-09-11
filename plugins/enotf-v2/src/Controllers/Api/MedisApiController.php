<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers\Api;

use App\Auth\Permissions;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as DB;
use Plugin\EnotfV2\Catalogs\MedikationCatalog;
use Plugin\EnotfV2\Models\Edivi;
use Plugin\EnotfV2\Models\Medikament;
use Plugin\EnotfV2\Policies\EnotfV2Policy;
use Plugin\EnotfV2\Support\ProtokollAccessGuard;
use Plugin\EnotfV2\Support\ProtokollService;

/**
 * Medikations-API — Read-Modify-Write auf dem JSON-Feld intra_edivi.medis.
 *
 * Speicherformat ist EXAKT das v1-Format (save_medikament.php):
 * JSON-Array von { wirkstoff, zeit, applikation, dosierung, einheit,
 * timestamp } (timestamp = Unix-Millisekunden, fachlicher Schlüssel fürs
 * Löschen), Sonderwert '0' = keine Medikamente. Damit lesen v1-Druck und
 * v1-Protokoll dieselben Daten.
 *
 * Unterschiede zu v1 (bewusst):
 *   - JSON-Antworten statt text/plain.
 *   - Sperr-Prüfung: add/delete werden bei freigegeben=1 mit 403
 *     abgewiesen — v1 prüfte das nicht.
 *   - timestamp wird SERVERSEITIG vergeben statt vom Client.
 *
 *   GET  /api/enotf-v2/medis/{enr}
 *     → { ok, locked, medis: [ {wirkstoff, zeit, ...} ] } (nach zeit absteigend)
 *
 *   POST /api/enotf-v2/medis
 *     Request: { enr, medikament: { wirkstoff, zeit, applikation, dosierung, einheit } }
 *     Validierung: wirkstoff gegen Medikamentenstamm (active=1),
 *     applikation/einheit gegen MedikationCatalog, zeit HH:MM(:SS),
 *     dosierung numerisch (Komma oder Punkt).
 *     → { ok, medikament: {...inkl. timestamp} }
 *
 *   POST /api/enotf-v2/medis/delete
 *     Request: { enr, timestamp }
 *     → { ok } — leeres Array wird als '0' gespeichert (v1-Parität).
 */
final class MedisApiController
{
    /**
     * GET /api/enotf-v2/medis/{enr} — Gaben-Liste, neueste zuerst.
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

        $medis = $this->decodeMedis($protokoll['medis'] ?? null);
        usort($medis, static fn (array $a, array $b): int => strcmp((string) ($b['zeit'] ?? ''), (string) ($a['zeit'] ?? '')));

        return Response::json([
            'ok'     => true,
            'locked' => $service->istGesperrt($protokoll),
            'medis'  => $medis,
        ]);
    }

    /**
     * POST /api/enotf-v2/medis — eine Medikamentengabe anhängen.
     */
    public function add(Request $request): Response
    {
        if (!EnotfV2Policy::hasCrewSession() && !Permissions::check(['admin', 'edivi.edit'])) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $input      = $request->json() ?? [];
        $enr        = isset($input['enr']) && is_string($input['enr']) ? trim($input['enr']) : '';
        $medikament = $input['medikament'] ?? null;

        if ($enr === '' || !is_array($medikament)) {
            return Response::json(['ok' => false, 'error' => 'enr und medikament sind erforderlich'], 400);
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
            // v1 ließ Medikamenten-Writes auch nach Freigabe durch — v2 sperrt
            return Response::json(['ok' => false, 'error' => 'Protokoll ist freigegeben und kann nicht mehr bearbeitet werden'], 403);
        }

        // Eingaben trimmen und validieren (v1-Regeln aus save_medikament.php)
        foreach ($medikament as $key => $value) {
            if (is_string($value)) {
                $medikament[$key] = trim($value);
            }
        }

        foreach (['wirkstoff', 'zeit', 'applikation', 'dosierung', 'einheit'] as $pflichtfeld) {
            if (!isset($medikament[$pflichtfeld]) || $medikament[$pflichtfeld] === '' || !is_string($medikament[$pflichtfeld])) {
                return Response::json(['ok' => false, 'error' => 'Pflichtfeld fehlt: ' . $pflichtfeld], 400);
            }
        }

        $wirkstoffAktiv = Medikament::active()
            ->where('wirkstoff', $medikament['wirkstoff'])
            ->exists();
        if (!$wirkstoffAktiv) {
            return Response::json(['ok' => false, 'error' => 'Ungültiger Wirkstoff: ' . $medikament['wirkstoff']], 400);
        }

        if (!isset(MedikationCatalog::APPLIKATIONSWEGE[$medikament['applikation']])) {
            return Response::json(['ok' => false, 'error' => 'Ungültige Applikationsart: ' . $medikament['applikation']], 400);
        }
        if (!isset(MedikationCatalog::EINHEITEN[$medikament['einheit']])) {
            return Response::json(['ok' => false, 'error' => 'Ungültige Einheit: ' . $medikament['einheit']], 400);
        }
        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $medikament['zeit'])) {
            return Response::json(['ok' => false, 'error' => 'Ungültiges Zeitformat: ' . $medikament['zeit']], 400);
        }
        if (!preg_match('/^[0-9]+([.,][0-9]+)?$/', $medikament['dosierung'])) {
            return Response::json(['ok' => false, 'error' => 'Ungültige Dosierung: ' . $medikament['dosierung']], 400);
        }

        // Eintrag im v1-Format — timestamp serverseitig (Unix-Millisekunden)
        $eintrag = [
            'wirkstoff'   => $medikament['wirkstoff'],
            'zeit'        => $medikament['zeit'],
            'applikation' => $medikament['applikation'],
            'dosierung'   => $medikament['dosierung'],
            'einheit'     => $medikament['einheit'],
            'timestamp'   => (int) round(microtime(true) * 1000),
        ];

        $medis   = $this->decodeMedis($protokoll['medis'] ?? null);
        $medis[] = $eintrag;

        if (!$this->writeMedis($enr, $medis)) {
            return Response::json(['ok' => false, 'error' => 'Datenbankfehler'], 500);
        }

        return Response::json(['ok' => true, 'medikament' => $eintrag]);
    }

    /**
     * POST /api/enotf-v2/medis/delete — eine Gabe per timestamp entfernen.
     */
    public function delete(Request $request): Response
    {
        if (!EnotfV2Policy::hasCrewSession() && !Permissions::check(['admin', 'edivi.edit'])) {
            return Response::json(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $input     = $request->json() ?? [];
        $enr       = isset($input['enr']) && is_string($input['enr']) ? trim($input['enr']) : '';
        $timestamp = $input['timestamp'] ?? null;

        if ($enr === '' || $timestamp === null || $timestamp === '') {
            return Response::json(['ok' => false, 'error' => 'enr und timestamp sind erforderlich'], 400);
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

        $medis = $this->decodeMedis($protokoll['medis'] ?? null);

        // Lockerer Vergleich wie v1 ($med['timestamp'] != $timestamp) —
        // Altdaten können den timestamp als String tragen
        $verbleibend = array_values(array_filter(
            $medis,
            static fn (array $med): bool => (string) ($med['timestamp'] ?? '') !== (string) $timestamp
        ));

        if (count($verbleibend) === count($medis)) {
            return Response::json(['ok' => false, 'error' => 'Medikament mit diesem Timestamp nicht gefunden'], 404);
        }

        if (!$this->writeMedis($enr, $verbleibend)) {
            return Response::json(['ok' => false, 'error' => 'Datenbankfehler'], 500);
        }

        return Response::json(['ok' => true]);
    }

    // ── Interna ───────────────────────────────────────────────────────

    /**
     * medis-Spalte dekodieren: '0'/leer/NULL → [], sonst JSON-Array.
     *
     * @return list<array<string,mixed>>
     */
    private function decodeMedis(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === MedikationCatalog::KEINE_MEDIKAMENTE) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    /**
     * medis-Spalte schreiben — leeres Array als '0' (v1-Parität),
     * last_edit wird mitgezogen.
     *
     * @param list<array<string,mixed>> $medis
     */
    private function writeMedis(string $enr, array $medis): bool
    {
        $json = $medis === []
            ? MedikationCatalog::KEINE_MEDIKAMENTE
            : json_encode($medis, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            Logger::error('EnotfV2: medis-JSON-Encoding fehlgeschlagen', ['enr' => $enr]);
            return false;
        }

        try {
            Edivi::query()->where('enr', $enr)->update([
                'medis'     => $json,
                'last_edit' => DB::raw('NOW()'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('EnotfV2: medis-Write fehlgeschlagen', ['enr' => $enr, 'error' => $e->getMessage()]);
            return false;
        }

        return true;
    }
}
