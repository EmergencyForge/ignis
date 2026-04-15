<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use PDO;
use PDOException;

/**
 * eNOTF Admin- und Session-API.
 *
 * Sammelt die einfacheren eNOTF-Endpoints (Status-Polling, Session-
 * Verwaltung, Voranmeldungen). Komplexere Endpoints wie Billing,
 * Bulk-Delete und Save-Fields leben in separaten Klassen — dort ist
 * der Code zu umfangreich für ein gemeinsames Controller-Bundle.
 */
final class EnotfController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

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
            $this->pdo->prepare(
                "UPDATE intra_edivi_prereg SET active = 0
                 WHERE active = 1 AND arrival IS NOT NULL AND arrival < NOW() - INTERVAL 10 MINUTE"
            )->execute();

            if ($ziel) {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM intra_edivi_prereg WHERE ziel = :ziel AND active = 1 ORDER BY arrival ASC"
                );
                $stmt->execute([':ziel' => $ziel]);
            } else {
                $stmt = $this->pdo->query(
                    "SELECT * FROM intra_edivi_prereg WHERE active = 1 ORDER BY arrival ASC"
                );
            }

            return Response::json([
                'success' => true,
                'data'    => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ]);
        } catch (PDOException $e) {
            Logger::error('Enotf: prereg Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler'], 500);
        }
    }

    /**
     * POST /api/enotf/delete-vehicle-session
     * JSON: { "vehicle": "<identifier>" }
     *
     * Deaktiviert alle aktiven Fahrzeug-Sessions für das Kennzeichen.
     */
    public function deleteVehicleSession(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $input   = $request->json();
        $vehicle = $input['vehicle'] ?? null;

        if (!$vehicle) {
            return Response::json(['success' => false, 'error' => 'Fahrzeug fehlt'], 400);
        }

        $this->pdo->prepare(
            "UPDATE intra_enotf_sessions SET active = 0
             WHERE vehicle_identifier = :vehicle AND active = 1"
        )->execute([':vehicle' => $vehicle]);

        return Response::json(['success' => true]);
    }

    /**
     * GET /api/enotf/sync-status?enr=...
     * Gibt `pat_synced` für ein Protokoll plus den letzten
     * EMD-Sync-Zeitpunkt zurück. Wird von der Topbar gepollt.
     */
    public function syncStatus(Request $request): Response
    {
        if (strtoupper($request->method) !== 'GET') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $enr = $request->query['enr'] ?? null;

        $response = [
            'success'       => true,
            'pat_synced'    => null,
            'last_emd_sync' => null,
        ];

        if ($enr) {
            $stmt = $this->pdo->prepare("SELECT pat_synced FROM intra_edivi WHERE enr = :enr LIMIT 1");
            $stmt->execute([':enr' => $enr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $response['pat_synced'] = (int) $row['pat_synced'];
            }
        }

        $syncFile = dirname(__DIR__, 3) . '/storage/last_emd_sync.txt';
        if (file_exists($syncFile)) {
            $response['last_emd_sync'] = trim((string) file_get_contents($syncFile));
        }

        return Response::json($response);
    }

    /**
     * POST /api/enotf/session-update
     * Body: POST `token`
     *
     * Zieht die aktuelle Crew aus der DB-Session und schreibt sie in die
     * PHP-Browser-Session — wird vom Client-Polling aufgerufen wenn eine
     * Crew-Änderung erkannt wurde.
     */
    public function sessionUpdate(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $sessionToken = $request->post['token'] ?? null;
        if (!$sessionToken) {
            return Response::json(['success' => false, 'error' => 'Token fehlt'], 400);
        }

        $stmt = $this->pdo->prepare("
            SELECT s.*
            FROM intra_enotf_session_members m
            JOIN intra_enotf_sessions s ON s.id = m.session_id
            WHERE m.session_token = :token AND s.active = 1
            LIMIT 1
        ");
        $stmt->execute([':token' => $sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return Response::json(['success' => false, 'error' => 'Session nicht gefunden oder inaktiv'], 404);
        }

        // PHP-Session updaten
        $_SESSION['fahrername']       = $session['fahrername'];
        $_SESSION['fahrerquali']      = $session['fahrerquali'];
        $_SESSION['beifahrername']    = $session['beifahrername'];
        $_SESSION['beifahrerquali']   = $session['beifahrerquali'];
        $_SESSION['praktikantname']   = $session['praktikantname'];
        $_SESSION['praktikantquali']  = $session['praktikantquali'];

        return Response::json(['success' => true]);
    }

    /**
     * GET /api/enotf/check-vehicle-session?vehicle=<identifier>
     * Prüft ob ein Fahrzeug eine aktive Session hat und liefert Besatzung
     * + freie Positionen zurück.
     */
    public function checkVehicleSession(Request $request): Response
    {
        if (strtoupper($request->method) !== 'GET') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $vehicleIdentifier = $request->query['vehicle'] ?? null;
        if (!$vehicleIdentifier) {
            return Response::json(['success' => false, 'error' => 'Fahrzeug-Kennung fehlt'], 400);
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM intra_enotf_sessions
            WHERE vehicle_identifier = :vehicle AND active = 1
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([':vehicle' => $vehicleIdentifier]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return Response::json(['success' => true, 'active' => false]);
        }

        $freePositions = [];
        if (empty($session['fahrername']))     $freePositions[] = 'fahrer';
        if (empty($session['beifahrername']))  $freePositions[] = 'beifahrer';
        if (empty($session['praktikantname'])) $freePositions[] = 'praktikant';

        return Response::json([
            'success'        => true,
            'active'         => true,
            'session_id'     => (int) $session['id'],
            'crew'           => $this->extractCrew($session),
            'free_positions' => $freePositions,
            'updated_at'     => $session['updated_at'],
        ]);
    }

    /**
     * GET /api/enotf/session-status?token=<token>
     * Polling-Endpoint vom Client (alle 10 Sekunden) — liefert die
     * aktuelle Crew plus die eigene Position.
     */
    public function sessionStatus(Request $request): Response
    {
        if (strtoupper($request->method) !== 'GET') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $sessionToken = $request->query['token'] ?? null;
        if (!$sessionToken) {
            return Response::json(['success' => false, 'error' => 'Token fehlt'], 400);
        }

        $stmt = $this->pdo->prepare("
            SELECT s.*, m.position AS my_position
            FROM intra_enotf_session_members m
            JOIN intra_enotf_sessions s ON s.id = m.session_id
            WHERE m.session_token = :token
            LIMIT 1
        ");
        $stmt->execute([':token' => $sessionToken]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || (int) $result['active'] === 0) {
            return Response::json(['success' => true, 'active' => false]);
        }

        return Response::json([
            'success'     => true,
            'active'      => true,
            'crew'        => $this->extractCrew($result),
            'my_position' => $result['my_position'],
            'updated_at'  => $result['updated_at'],
        ]);
    }

    // ── POI-Endpoints ─────────────────────────────────────────────────

    /**
     * GET /api/enotf/poi/poi-search?search=<query>
     * Sucht in `intra_edivi_pois` nach Name oder Ort. Leere Query gibt
     * die ersten 50 aktiven POIs zurück.
     */
    public function poiSearch(Request $request): Response
    {
        $searchTerm = (string) ($request->query['search'] ?? '');

        try {
            if ($searchTerm === '') {
                $stmt = $this->pdo->query(
                    "SELECT id, name, strasse, hnr, ort, ortsteil, typ
                     FROM intra_edivi_pois
                     WHERE active = 1
                     ORDER BY name ASC LIMIT 50"
                );
                return Response::json($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
            }

            $searchPattern = '%' . $searchTerm . '%';
            $stmt = $this->pdo->prepare(
                "SELECT id, name, strasse, hnr, ort, ortsteil, typ
                 FROM intra_edivi_pois
                 WHERE active = 1 AND (name LIKE ? OR ort LIKE ?)
                 ORDER BY name ASC LIMIT 50"
            );
            $stmt->execute([$searchPattern, $searchPattern]);
            return Response::json($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (PDOException $e) {
            Logger::error('Enotf: poi-search Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Database error'], 500);
        }
    }

    // ── Share-Endpoints (teilweise, die einfacheren) ──────────────────

    /**
     * GET /api/enotf/share/get-available-vehicles
     * Liefert alle Rettungsdienst-Fahrzeuge außer dem aktuell
     * eingeloggten (für Protokoll-Übergabe).
     */
    public function shareGetAvailableVehicles(Request $request): Response
    {
        if (empty($_SESSION['protfzg'])) {
            return Response::json(['success' => false, 'message' => 'Nicht angemeldet']);
        }

        $currentVehicle = (string) $_SESSION['protfzg'];

        try {
            $stmt = $this->pdo->prepare("
                SELECT identifier, name, kennzeichen, rd_type
                FROM intra_fahrzeuge
                WHERE identifier != :current_vehicle
                  AND rd_type <> 0
                  AND active = 1
                ORDER BY name ASC, identifier ASC
            ");
            $stmt->execute([':current_vehicle' => $currentVehicle]);

            return Response::json([
                'success'  => true,
                'vehicles' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ]);
        } catch (PDOException $e) {
            Logger::error('Enotf: share/get-available-vehicles Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    // ── Private Helper ────────────────────────────────────────────────

    /**
     * Extrahiert die Crew-Felder aus einem Session-DB-Row in das Format,
     * das der Frontend-Client erwartet.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function extractCrew(array $row): array
    {
        return [
            'fahrername'       => $row['fahrername'],
            'fahrerquali'      => $row['fahrerquali'],
            'beifahrername'    => $row['beifahrername'],
            'beifahrerquali'   => $row['beifahrerquali'],
            'praktikantname'   => $row['praktikantname'],
            'praktikantquali'  => $row['praktikantquali'],
        ];
    }
}
