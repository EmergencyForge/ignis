<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Api;

use App\Auth\Gate;
use Plugin\Enotf\Events\EnotfProtocolReleased;
use App\Events\EventDispatcher;
use App\Helpers\Flash;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as DB;
use PDOException;
use Plugin\Enotf\Models\Edivi;

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
                ->where('arrival', '<', DB::raw('NOW() - INTERVAL 10 MINUTE'))
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

        DB::table('intra_enotf_sessions')
            ->where('vehicle_identifier', $vehicle)
            ->where('active', 1)
            ->update(['active' => 0]);

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
            $row = Edivi::where('enr', $enr)->first(['pat_synced']);
            if ($row) {
                $response['pat_synced'] = (int) $row->pat_synced;
            }
        }

        $syncFile = dirname(__DIR__, 4) . '/storage/last_emd_sync.txt';
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

        $session = DB::table('intra_enotf_session_members as m')
            ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.session_token', $sessionToken)
            ->where('s.active', 1)
            ->select('s.*')
            ->first();

        if (!$session) {
            return Response::json(['success' => false, 'error' => 'Session nicht gefunden oder inaktiv'], 404);
        }

        $session = (array) $session;

        // PHP-Session updaten
        \App\Session\SessionManager::updateEnotfCrew([
            'fahrername'      => $session['fahrername'],
            'fahrerquali'     => $session['fahrerquali'],
            'beifahrername'   => $session['beifahrername'],
            'beifahrerquali'  => $session['beifahrerquali'],
            'praktikantname'  => $session['praktikantname'],
            'praktikantquali' => $session['praktikantquali'],
        ]);

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

        $session = DB::table('intra_enotf_sessions')
            ->where('vehicle_identifier', $vehicleIdentifier)
            ->where('active', 1)
            ->orderByDesc('updated_at')
            ->first();

        if (!$session) {
            return Response::json(['success' => true, 'active' => false]);
        }

        $session = (array) $session;

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

        $result = DB::table('intra_enotf_session_members as m')
            ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.session_token', $sessionToken)
            ->select('s.*', 'm.position as my_position')
            ->first();

        $result = $result ? (array) $result : null;

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
            $query = DB::table('intra_edivi_pois')
                ->select('id', 'name', 'strasse', 'hnr', 'ort', 'ortsteil', 'typ')
                ->where('active', 1)
                ->orderBy('name')
                ->limit(50);

            if ($searchTerm !== '') {
                $searchPattern = '%' . $searchTerm . '%';
                $query->where(function ($q) use ($searchPattern) {
                    $q->where('name', 'LIKE', $searchPattern)
                        ->orWhere('ort', 'LIKE', $searchPattern);
                });
            }

            return Response::json($query->get()->map(fn ($r) => (array) $r)->all());
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
            $vehicles = DB::table('intra_fahrzeuge')
                ->select('identifier', 'name', 'kennzeichen', 'rd_type')
                ->where('identifier', '!=', $currentVehicle)
                ->where('rd_type', '<>', 0)
                ->where('active', 1)
                ->orderBy('name')
                ->orderBy('identifier')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            return Response::json([
                'success'  => true,
                'vehicles' => $vehicles,
            ]);
        } catch (PDOException $e) {
            Logger::error('Enotf: share/get-available-vehicles Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /**
     * POST /api/enotf/check-conflict
     * Form: enr, prot_by — prüft ob für die ENR bereits ein Protokoll
     * mit dem für das aktuelle Fahrzeug relevanten Slot (RTW vs. NEF)
     * angelegt wurde.
     */
    public function checkConflict(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['error' => 'Method not allowed'], 405);
        }
        if (empty($_SESSION['fahrername']) || empty($_SESSION['protfzg'])) {
            return Response::json(['error' => 'Not authenticated'], 401);
        }

        $enr = (string) ($request->post['enr'] ?? '');
        if ($enr === '') {
            return Response::json(['conflict' => false]);
        }

        try {
            $fahrzeug = DB::table('intra_fahrzeuge')
                ->where('identifier', $_SESSION['protfzg'])
                ->first(['identifier', 'rd_type']);
            $isDoctorVehicle = $fahrzeug && (int) $fahrzeug->rd_type === 1;

            $existing = Edivi::where('enr', $enr)->first(['fzg_transp', 'fzg_na']);

            if (!$existing) {
                return Response::json(['conflict' => false]);
            }

            $currentField = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';
            if (empty($existing[$currentField])) {
                return Response::json(['conflict' => false]);
            }

            $vehicleName = DB::table('intra_fahrzeuge')
                ->where('identifier', $existing[$currentField])
                ->value('name') ?? $existing[$currentField];
            $protocolType = $isDoctorVehicle ? 'Notarzt-Protokoll' : 'Rettungsdienst-Protokoll';

            return Response::json([
                'conflict' => true,
                'message'  => "Für die Einsatznummer {$enr} ist bereits ein {$protocolType} vom Fahrzeug {$vehicleName} vorhanden.",
            ]);
        } catch (PDOException $e) {
            Logger::error('Enotf: check-conflict Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Database error'], 500);
        }
    }

    /**
     * POST /api/enotf/patient-sync
     * JSON: { "enr": "..." } — markiert die Patientendaten eines
     * Protokolls als "bereit zum Senden" (pat_synced = 2). Wird vom
     * nächsten Vehicle-Sync mitgenommen.
     */
    public function patientSync(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $data = $request->json();
        $enr  = $data['enr'] ?? null;
        if (!$enr) {
            return Response::json(['success' => false, 'error' => 'Ungültige Anfrage'], 400);
        }

        try {
            $row = Edivi::where('enr', $enr)
                ->first(['pat_vorname', 'pat_nachname', 'patgebdat', 'pat_synced']);

            if (!$row) {
                return Response::json(['success' => false, 'error' => 'Protokoll nicht gefunden'], 404);
            }
            if (empty($row['pat_vorname']) && empty($row['pat_nachname'])) {
                return Response::json(['success' => false, 'error' => 'Keine Patientendaten vorhanden'], 400);
            }

            Edivi::where('enr', $enr)->update(['pat_synced' => 2]);

            return Response::json([
                'success'    => true,
                'pat_synced' => 2,
                'message'    => 'Patientendaten zum Senden markiert',
            ]);
        } catch (PDOException $e) {
            Logger::error('Enotf: patient-sync Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'error' => 'Datenbankfehler'], 500);
        }
    }

    /**
     * POST /api/enotf/poi/save-field
     * Form: enr + transp_poi/transp_adresse und/oder ziel_poi/ziel_adresse.
     * Aktualisiert die POI/Adress-Felder eines Protokolls.
     */
    public function poiSaveField(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Invalid request method']);
        }

        $enr            = $request->post['enr'] ?? null;
        $transpPoi      = $request->post['transp_poi'] ?? null;
        $transpAdresse  = $request->post['transp_adresse'] ?? null;
        $zielPoi        = $request->post['ziel_poi'] ?? null;
        $zielAdresse    = $request->post['ziel_adresse'] ?? null;

        if (empty($enr)) {
            return Response::json(['success' => false, 'message' => 'Missing required parameters']);
        }

        $isTransport = !empty($transpPoi) || !empty($transpAdresse);
        $isZiel      = !empty($zielPoi) || !empty($zielAdresse);
        if (!$isTransport && !$isZiel) {
            return Response::json(['success' => false, 'message' => 'No fields to update']);
        }

        if (!empty($transpAdresse)) {
            json_decode($transpAdresse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::json(['success' => false, 'message' => 'Invalid JSON format for transp_adresse']);
            }
        }
        if (!empty($zielAdresse)) {
            json_decode($zielAdresse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::json(['success' => false, 'message' => 'Invalid JSON format for ziel_adresse']);
            }
        }

        try {
            $fields = [];
            if ($isTransport) {
                $fields['transp_poi']     = $transpPoi;
                $fields['transp_adresse'] = $transpAdresse;
            }
            if ($isZiel) {
                $fields['ziel_poi']     = $zielPoi;
                $fields['ziel_adresse'] = $zielAdresse;
            }
            $fields['last_edit'] = DB::raw('NOW()');

            Edivi::where('enr', $enr)->update($fields);

            return Response::json(['success' => true, 'message' => 'Fields updated successfully']);
        } catch (PDOException $e) {
            Logger::error('Enotf: poi/save-field Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Database error']);
        }
    }

    /**
     * POST /api/enotf/delete-protocol
     * JSON: { "enr": "..." }
     *
     * Soft-Delete eines Protokolls (hidden_user = 1, freigegeben = 1).
     * Protokolle, die von der Leitstelle (createdby = 1) angelegt wurden,
     * dürfen nicht gelöscht werden.
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
        if (empty($input['enr'])) {
            return Response::json(['success' => false, 'message' => 'enr fehlt'], 400);
        }

        $enr     = $input['enr'];
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

            if (!$protocol) {
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
                'last_edit'      => DB::raw('NOW()'),
                'freigegeben'    => 1,
            ]);

            return Response::json(['success' => true, 'message' => 'Protokoll erfolgreich gelöscht']);
        } catch (PDOException $e) {
            Logger::error('Enotf: delete-protocol Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Interner Fehler'], 500);
        }
    }

    // ── Share-Endpoints (Protokoll-Übergabe zwischen Fahrzeugen) ──────

    /**
     * GET /api/enotf/share/check-requests
     * Pollt die älteste pending Share-Anfrage für das aktuelle Fahrzeug.
     */
    public function shareCheckRequests(Request $request): Response
    {
        if (empty($_SESSION['protfzg'])) {
            return Response::json(['success' => false, 'message' => 'Nicht angemeldet']);
        }

        try {
            $req = DB::table('intra_edivi_share_requests as sr')
                ->join('intra_edivi as ed', 'sr.source_protocol_id', '=', 'ed.id')
                ->where('sr.target_vehicle', $_SESSION['protfzg'])
                ->where('sr.status', 'pending')
                ->orderBy('sr.created_at')
                ->select(
                    'sr.id', 'sr.source_enr', 'sr.source_protocol_id', 'sr.source_vehicle', 'sr.created_at',
                    'ed.enr', 'ed.patname', 'ed.prot_by', 'ed.edatum', 'ed.ezeit'
                )
                ->first();

            if ($req) {
                return Response::json(['success' => true, 'has_requests' => true, 'request' => (array) $req]);
            }
            return Response::json(['success' => true, 'has_requests' => false]);
        } catch (PDOException $e) {
            Logger::error('Enotf: share/check-requests Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /**
     * GET /api/enotf/share/get-own-protocols
     * Liefert die letzten 20 nicht-freigegebenen Protokolle des
     * aktuellen Fahrzeugs — Auswahl-Liste für den Share-Dialog.
     */
    public function shareGetOwnProtocols(Request $request): Response
    {
        if (empty($_SESSION['protfzg'])) {
            return Response::json(['success' => false, 'message' => 'Nicht angemeldet']);
        }

        try {
            $vehicle = $_SESSION['protfzg'];

            $protocols = DB::table('intra_edivi')
                ->select('id', 'enr', 'patname', 'edatum', 'ezeit', 'prot_by')
                ->where(function ($q) use ($vehicle) {
                    $q->where('fzg_transp', $vehicle)->orWhere('fzg_na', $vehicle);
                })
                ->where('freigegeben', 0)
                ->where(function ($q) {
                    $q->where('hidden', 0)->orWhereNull('hidden');
                })
                ->where(function ($q) {
                    $q->where('hidden_user', 0)->orWhereNull('hidden_user');
                })
                ->orderByDesc('edatum')
                ->orderByDesc('ezeit')
                ->limit(20)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            return Response::json([
                'success'   => true,
                'protocols' => $protocols,
                'count'     => count($protocols),
            ]);
        } catch (PDOException $e) {
            Logger::error('Enotf: share/get-own-protocols Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /**
     * POST /api/enotf/share/reject-request
     * JSON: { "request_id": <int> } — markiert eine Share-Anfrage als
     * abgelehnt.
     */
    public function shareRejectRequest(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Ungültige Anfragemethode']);
        }
        if (empty($_SESSION['protfzg']) || empty($_SESSION['fahrername'])) {
            return Response::json(['success' => false, 'message' => 'Nicht angemeldet']);
        }

        $input     = $request->json();
        $requestId = $input['request_id'] ?? null;
        if (!$requestId) {
            return Response::json(['success' => false, 'message' => 'Fehlende Parameter']);
        }

        $conn = DB::connection();

        try {
            $conn->beginTransaction();

            $exists = DB::table('intra_edivi_share_requests')
                ->where('id', $requestId)
                ->where('target_vehicle', $_SESSION['protfzg'])
                ->where('status', 'pending')
                ->exists();

            if (!$exists) {
                $conn->rollBack();
                return Response::json(['success' => false, 'message' => 'Anfrage nicht gefunden oder bereits bearbeitet']);
            }

            DB::table('intra_edivi_share_requests')
                ->where('id', $requestId)
                ->update([
                    'status'      => 'rejected',
                    'response_at' => DB::raw('NOW()'),
                    'response_by' => $_SESSION['fahrername'],
                ]);

            $conn->commit();

            return Response::json(['success' => true, 'message' => 'Anfrage wurde abgelehnt']);
        } catch (PDOException $e) {
            if ($conn->transactionLevel() > 0) {
                $conn->rollBack();
            }
            Logger::error('Enotf: share/reject-request Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /**
     * POST /api/enotf/share/send-request
     * JSON: { "protocol_id": <int>, "enr": "...", "target_vehicle": "..." }
     *
     * Legt eine neue Share-Anfrage an. Pro Fahrzeug darf nur eine
     * pending Anfrage pro Quell-Protokoll existieren.
     */
    public function shareSendRequest(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Ungültige Anfragemethode']);
        }
        if (empty($_SESSION['protfzg'])) {
            return Response::json(['success' => false, 'message' => 'Nicht angemeldet']);
        }

        $input         = $request->json();
        $protocolId    = $input['protocol_id'] ?? null;
        $enr           = $input['enr'] ?? null;
        $targetVehicle = $input['target_vehicle'] ?? null;

        if (!$protocolId || !$enr || !$targetVehicle) {
            return Response::json(['success' => false, 'message' => 'Fehlende Parameter']);
        }

        try {
            $pending = DB::table('intra_edivi_share_requests')
                ->where('source_protocol_id', $protocolId)
                ->where('target_vehicle', $targetVehicle)
                ->where('status', 'pending')
                ->exists();

            if ($pending) {
                return Response::json([
                    'success' => false,
                    'message' => 'Es existiert bereits eine ausstehende Anfrage für dieses Fahrzeug',
                ]);
            }

            $requestId = DB::table('intra_edivi_share_requests')->insertGetId([
                'source_enr'         => $enr,
                'source_protocol_id' => $protocolId,
                'source_vehicle'     => $_SESSION['protfzg'],
                'target_vehicle'     => $targetVehicle,
                'status'             => 'pending',
                'created_at'         => DB::raw('NOW()'),
            ]);

            return Response::json([
                'success'    => true,
                'message'    => 'Anfrage wurde erfolgreich gesendet',
                'request_id' => (int) $requestId,
            ]);
        } catch (PDOException $e) {
            Logger::error('Enotf: share/send-request Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler beim Senden der Anfrage']);
        }
    }

    // ── Billing (API-Key-Auth, extern aufgerufen) ──────────────────────

    /**
     * POST /api/enotf/billing
     * JSON: { "intraRP_API_Key": "...", "timestamp": <unix> }
     *
     * Gibt freigegebene Protokolle mit billing_sent=0 zurück und markiert
     * sie als abgerechnet. Auth via API-Key im JSON-Body (externer Abruf).
     */
    public function billing(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $data = $request->json();
        if (!is_array($data)) {
            return Response::json(['success' => false, 'error' => 'Ungültiges JSON'], 400);
        }

        if (!isset($data['intraRP_API_Key']) || $data['intraRP_API_Key'] !== API_KEY) {
            return Response::json(['success' => false, 'error' => 'Nicht autorisiert', 'hint' => 'API-Key stimmt nicht überein'], 401);
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
                    DB::raw('COALESCE(fzg_t.name, fzg_na_tbl.name) AS vehicle_callsign')
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
                ->update(['billing_sent' => 1, 'billing_sent_at' => DB::raw('NOW()')]);

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

        // Feld-Namen stammen aus der Whitelist, $days ist int-gecastet —
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

    /**
     * POST /api/enotf/save-fields
     * Form: enr, field, value
     *
     * Speichert ein einzelnes Feld eines Protokolls. Sonderbehandlung
     * für `freigeber` (Freigabe + EnotfProtocolReleased-Event) und
     * `c_zugang` (JSON-Validierung mit Whitelist-Checks).
     *
     * Antwortet mit Klartext (text/plain) statt JSON, da das Frontend
     * die Response als direkten Statustext verwendet.
     */
    public function saveFields(Request $request): Response
    {
        // Generische Input-Shape via FormRequest. Fehlende Felder → 400 text.
        // (Kein JSON — der Endpoint antwortet grundsätzlich text/plain, deshalb
        // fangen wir die ValidationException hier manuell und übersetzen sie.)
        try {
            $data = \Plugin\Enotf\Requests\SaveFieldRequest::validate($request->post);
        } catch (\App\Exceptions\ValidationException $e) {
            return Response::text($e->firstError() ?? 'Missing data', 400);
        }

        $enr   = $data['enr'];
        $field = $data['field'];
        $value = $data['value'];

        // Freigabe-Sonderfall: eigener Code-Pfad mit Event-Fire
        if ($field === 'freigeber') {
            return $this->handleFreigabe($enr, $value);
        }

        // Alle anderen Felder: Whitelist-Check + feldspezifische Validation
        if (!in_array($field, self::ALLOWED_FIELDS, true)) {
            return Response::text("Invalid field: {$field}", 400);
        }

        // Feldspezifische Validation/Transformation
        if ($field === 'c_zugang') {
            $err = $this->validateCZugang($value);
            if ($err !== null) {
                return Response::text($err, 400);
            }
        }
        if (in_array($field, self::DATE_FIELDS, true) && $value !== null && $value !== '') {
            $converted = $this->convertDateValue($value);
            if ($converted === false) {
                return Response::text('Ungültiges Datumsformat', 400);
            }
            $value = $converted;
        }

        // Protokoll-Status prüfen + Update durchführen
        try {
            $status = $this->loadProtokollReleaseStatus($enr);
            if ($status === 'not_found') {
                return Response::text('Protokoll nicht gefunden.', 404);
            }
            if ($status === 'released') {
                return Response::text('Protokoll ist freigegeben und kann nicht mehr bearbeitet werden.', 403);
            }

            $this->writeProtokollField($enr, $field, $value);

            return Response::text($this->successMessageFor($field, $value));
        } catch (PDOException $e) {
            Logger::error('Enotf: save-fields Fehler', ['error' => $e->getMessage()]);
            return Response::text('Datenbankfehler', 500);
        }
    }

    /** Date-Feld-Liste für Datums-Konvertierung (DD.MM.YYYY → YYYY-MM-DD). */
    private const DATE_FIELDS = ['edatum', 'patgebdat', 'symptombeginn_datum'];

    /**
     * @return 'not_found'|'released'|'editable'
     */
    private function loadProtokollReleaseStatus(string $enr): string
    {
        $row = Edivi::where('enr', $enr)->first(['freigegeben']);

        if (!$row) {
            return 'not_found';
        }
        return (int) $row->freigegeben === 1 ? 'released' : 'editable';
    }

    /**
     * Schreibt das Feld + Side-Effects (patname-Sync bei Vor-/Nachname).
     * Field-Name ist durch Whitelist abgesichert → SQL-Injection ausgeschlossen.
     */
    private function writeProtokollField(string $enr, string $field, mixed $value): void
    {
        Edivi::where('enr', $enr)->update([$field => $value, 'last_edit' => DB::raw('NOW()')]);

        // Namensänderung → patname + pat_synced synchron halten (Side-Effect)
        if ($field === 'pat_vorname' || $field === 'pat_nachname') {
            $c = Edivi::where('enr', $enr)->first(['pat_vorname', 'pat_nachname']);
            $vn = trim($c?->pat_vorname ?? '');
            $nn = trim($c?->pat_nachname ?? '');
            $combined = $nn . ($nn !== '' && $vn !== '' ? ', ' : '') . $vn;
            Edivi::where('enr', $enr)->update(['patname' => $combined, 'pat_synced' => 0]);
        }
    }

    /** Erfolgsmeldung zum geschriebenen Feld. */
    private function successMessageFor(string $field, mixed $value): string
    {
        if ($field === 'c_zugang') {
            return match (true) {
                $value === '0'                   => "Zugang auf 'Kein Zugang' gesetzt",
                $value === null || $value === '' => 'Zugang zurückgesetzt',
                default                          => 'Zugang erfolgreich gespeichert',
            };
        }
        return 'Field updated';
    }

    // ── Share: Accept Request ────────────────────────────────────────

    /**
     * POST /api/enotf/share/accept-request
     * JSON: { "request_id": <int>, "action": "merge"|"new", "target_enr": "..." }
     *
     * Akzeptiert eine Protokoll-Übergabe. Bei "merge" werden die Daten
     * ins Zielprotokoll geschrieben, bei "new" wird ein neues Protokoll
     * mit den Quelldaten + aktuellem Fahrzeug angelegt.
     */
    public function shareAcceptRequest(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Ungültige Anfragemethode']);
        }
        if (empty($_SESSION['protfzg']) || empty($_SESSION['fahrername'])) {
            return Response::json(['success' => false, 'message' => 'Nicht angemeldet']);
        }

        $input     = $request->json();
        $requestId = $input['request_id'] ?? null;
        $action    = $input['action'] ?? null;
        $targetEnr = $input['target_enr'] ?? null;

        if (!$requestId || !$action) {
            return Response::json(['success' => false, 'message' => 'Fehlende Parameter']);
        }
        if ($action === 'merge' && !$targetEnr) {
            return Response::json(['success' => false, 'message' => 'Für das Zusammenführen muss ein Zielprotokoll ausgewählt werden']);
        }

        $conn = DB::connection();

        try {
            $conn->beginTransaction();

            // Lade Share-Request + Quell-Protokoll. Bei gleichnamigen Spalten
            // (id, created_at, ...) gewinnt wie beim alten `sr.*, ed.*`-Select
            // die zuletzt selektierte — also die Protokoll-Spalte.
            $reqData = DB::table('intra_edivi_share_requests as sr')
                ->join('intra_edivi as ed', 'sr.source_protocol_id', '=', 'ed.id')
                ->where('sr.id', $requestId)
                ->where('sr.target_vehicle', $_SESSION['protfzg'])
                ->where('sr.status', 'pending')
                ->select('sr.*', 'ed.*')
                ->first();

            if (!$reqData) {
                $conn->rollBack();
                return Response::json(['success' => false, 'message' => 'Anfrage nicht gefunden oder bereits bearbeitet']);
            }

            $reqData = (array) $reqData;

            $currentVehicle = $_SESSION['protfzg'];
            $vehicleInfo    = $this->getVehicleInfo($currentVehicle);
            $isDoctorVehicle = $vehicleInfo['isDoctorVehicle'];
            $fzgField   = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';
            $persoField1 = $isDoctorVehicle ? 'fzg_na_perso' : 'fzg_transp_perso';
            $persoField2 = $isDoctorVehicle ? 'fzg_na_perso_2' : 'fzg_transp_perso_2';
            $persoField3 = $isDoctorVehicle ? 'fzg_na_perso_3' : 'fzg_transp_perso_3';

            $fahrer = $this->formatCrewMember('fahrername', 'fahrerquali');
            $beifahrer = $this->formatCrewMember('beifahrername', 'beifahrerquali');
            $praktikant = $this->formatCrewMember('praktikantname', 'praktikantquali');

            $newEnr = null;
            $actionTaken = '';
            $message = '';

            if ($action === 'merge') {
                $result = $this->handleShareMerge(
                    $reqData, $targetEnr, $currentVehicle, $isDoctorVehicle,
                    $fzgField, $persoField1, $persoField2, $persoField3,
                    $fahrer, $beifahrer, $praktikant
                );
                if ($result['error']) {
                    $conn->rollBack();
                    return Response::json(['success' => false, 'message' => $result['error']]);
                }
                $actionTaken = 'merged';
                $message = 'Daten wurden erfolgreich in das bestehende Protokoll übernommen';
            } else {
                $result = $this->handleShareNewProtocol(
                    $reqData, $currentVehicle, $fzgField,
                    $persoField1, $persoField2, $persoField3,
                    $fahrer, $beifahrer, $praktikant
                );
                $newEnr = $result['new_enr'];
                $actionTaken = 'new_protocol';
                $message = 'Neues Protokoll wurde erfolgreich erstellt';
            }

            // Share-Request als akzeptiert markieren
            DB::table('intra_edivi_share_requests')
                ->where('id', $requestId)
                ->update([
                    'status'       => 'accepted',
                    'response_at'  => DB::raw('NOW()'),
                    'response_by'  => $_SESSION['fahrername'],
                    'action_taken' => $actionTaken,
                    'new_enr'      => $newEnr,
                ]);

            $conn->commit();

            return Response::json([
                'success' => true,
                'message' => $message,
                'action'  => $action,
                'new_enr' => $newEnr,
            ]);
        } catch (PDOException $e) {
            if ($conn->transactionLevel() > 0) {
                $conn->rollBack();
            }
            Logger::error('Enotf: share/accept-request Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    // ── Private Helper ────────────────────────────────────────────────

    /** Felder die der save-fields-Endpoint schreiben darf. */
    private const ALLOWED_FIELDS = [
        'pat_vorname', 'pat_nachname', 'patgebdat', 'patsex',
        'edatum', 'ezeit', 'eort', 'eart', 'ebesonderheiten', 'elokation',
        'awfrei_1', 'awsicherung_1', 'awsicherung_neu', 'hws_immo',
        'zyanose_1', 'o2gabe', 'b_symptome', 'b_auskult', 'b_beatmung',
        'spo2', 'atemfreq', 'etco2',
        'c_zugang', 'c_kreislauf', 'c_ekg', 'c_puls_rad', 'c_puls_reg', 'c_rekap', 'c_blutung',
        'rrsys', 'rrdias', 'herzfreq',
        'medis', 'entlastungspunktion',
        'd_bewusstsein', 'd_ex_1', 'd_pupillenw_1', 'd_pupillenw_2',
        'd_lichtreakt_1', 'd_lichtreakt_2', 'd_gcs_1', 'd_gcs_2', 'd_gcs_3',
        'v_muster_k', 'v_muster_k1', 'v_muster_t', 'v_muster_t1',
        'v_muster_a', 'v_muster_a1', 'v_muster_al', 'v_muster_al1',
        'v_muster_bl', 'v_muster_bl1', 'v_muster_w', 'v_muster_w1',
        'sz_nrs', 'sz_toleranz_1', 'bz', 'temp', 'psych',
        'anmerkungen', 'diagnose_haupt', 'diagnose_weitere', 'diagnose',
        'fzg_transp', 'fzg_transp_perso', 'fzg_transp_perso_2', 'fzg_transp_perso_3',
        'fzg_na', 'fzg_na_perso', 'fzg_na_perso_2', 'fzg_na_perso_3', 'fzg_sonst',
        'transportziel', 'pfname', 'prot_by',
        'uebergabe_ort', 'uebergabe_an',
        'na_nachf', 'rettungstechnik', 'lagerung',
        'waerme_passiv', 'waerme_aktiv',
        'e_reposition', 'e_verband', 'e_krintervention', 'e_kuehlung',
        'e_narkose', 'e_tourniquet', 'e_cpr',
        'salarm', 's1', 's2', 's3', 's4', 'spat', 's7', 's8', 'sende',
        'symptombeginn_datum', 'symptombeginn_zeit', 'symptombeginn_geschaetzt', 'symptombeginn_nf',
        'naca_initial', 'naca_uebergabe',
        'sonderrechte_anfahrt', 'sonderrechte_transport',
    ];

    /** Felder die beim Share-Merge/-New NICHT übernommen werden. */
    private const SHARE_EXCLUDED_FIELDS = [
        'id', 'enr', 'sendezeit',
        'fzg_transp', 'fzg_transp_perso', 'fzg_transp_perso_2', 'fzg_transp_perso_3',
        'fzg_na', 'fzg_na_perso', 'fzg_na_perso_2', 'fzg_na_perso_3', 'fzg_sonst',
        'freigegeben', 'freigeber_name', 'last_edit', 'hidden', 'hidden_user',
        'bearbeiter', 'qmkommentar',
        // Felder aus der share_requests-Tabelle (JOIN-Artefakte)
        'source_enr', 'source_protocol_id', 'source_vehicle', 'target_vehicle',
        'status', 'created_at', 'updated_at', 'response_at', 'response_by',
        'action_taken', 'new_enr',
    ];

    /** Freigabe speichern + EnotfProtocolReleased Event feuern */
    private function handleFreigabe(string $enr, ?string $value): Response
    {
        if (empty($value)) {
            return Response::text('Freigeber darf nicht leer sein.', 400);
        }

        Edivi::where('enr', $enr)->update([
            'freigeber_name' => $value,
            'freigegeben'    => 1,
            'last_edit'      => DB::raw('NOW()'),
        ]);

        try {
            $protokoll = Edivi::where('enr', $enr)->first();

            if ($protokoll) {
                app(EventDispatcher::class)->fire(new EnotfProtocolReleased($protokoll->getAttributes()));
            }
        } catch (\Throwable $e) {
            Logger::error('EnotfProtocolReleased: Event-Fire Fehler', ['error' => $e->getMessage()]);
        }

        return Response::text('Freigeber erfolgreich gespeichert und freigegeben.');
    }

    /** Validiert c_zugang-JSON. Gibt null bei OK, Error-Text bei Fehler. */
    private function validateCZugang(mixed $value): ?string
    {
        if ($value === '0' || $value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'Ungültiges JSON-Format';
        }

        $zugaenge = isset($decoded['art']) ? [$decoded] : (is_array($decoded) ? $decoded : null);
        if ($zugaenge === null) {
            return 'Ungültige Datenstruktur';
        }

        $allowedArts     = ['pvk', 'zvk', 'io'];
        $allowedGroessen = ['24G', '22G', '20G', '18G', '18G_kurz', '17G', '16G', '14G', '15mm', '25mm', '45mm'];
        $allowedSeiten   = ['links', 'rechts', ''];
        $seenLocations   = [];

        foreach ($zugaenge as $z) {
            foreach (['art', 'groesse', 'ort'] as $f) {
                if (!isset($z[$f]) || $z[$f] === '') {
                    return "Pflichtfeld fehlt: {$f}";
                }
            }
            if (!array_key_exists('seite', $z)) {
                return 'Pflichtfeld fehlt: seite';
            }

            $locKey = $z['art'] . '-' . $z['ort'] . '-' . $z['seite'];
            if (in_array($locKey, $seenLocations, true)) {
                return 'Doppelter Zugang an gleicher Position nicht erlaubt';
            }
            $seenLocations[] = $locKey;

            if (!in_array($z['art'], $allowedArts, true))         return 'Ungültige Zugangsart: ' . $z['art'];
            if (!in_array($z['groesse'], $allowedGroessen, true))  return 'Ungültige Zugangsgröße: ' . $z['groesse'];
            if (!in_array($z['seite'], $allowedSeiten, true))      return 'Ungültige Seite: ' . $z['seite'];
        }

        return null;
    }

    /** Konvertiert DD.MM.YYYY → YYYY-MM-DD mit Validierung. Gibt false bei Fehler. */
    private function convertDateValue(string $value): string|false
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            // Bereits ISO
        } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            $value = $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        } else {
            return false;
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : false;
    }

    /** @return array{isDoctorVehicle: bool} */
    private function getVehicleInfo(string $vehicleId): array
    {
        $fzg = DB::table('intra_fahrzeuge')
            ->where('identifier', $vehicleId)
            ->first(['rd_type']);
        return ['isDoctorVehicle' => $fzg && (int) $fzg->rd_type === 1];
    }

    /** Formatiert einen Session-Crew-Eintrag als "Name (Quali)" oder null. */
    private function formatCrewMember(string $nameKey, string $qualiKey): ?string
    {
        $name  = $_SESSION[$nameKey] ?? '';
        $quali = $_SESSION[$qualiKey] ?? '';
        return ($name !== '' && $quali !== '') ? "{$name} ({$quali})" : null;
    }

    /** Share-Merge: Quelldaten in bestehendes Protokoll übernehmen. */
    private function handleShareMerge(
        array $reqData, string $targetEnr, string $currentVehicle, bool $isDoctorVehicle,
        string $fzgField, string $persoField1, string $persoField2, string $persoField3,
        ?string $fahrer, ?string $beifahrer, ?string $praktikant
    ): array {
        $target = Edivi::where('enr', $targetEnr)->where('freigegeben', 0)->first();

        if (!$target) {
            return ['error' => 'Zielprotokoll nicht gefunden oder bereits freigegeben'];
        }

        // Fahrzeug + Crew-Felder für das empfangende Fahrzeug setzen
        $vehFields = [];
        if (empty($target[$fzgField])) {
            $vehFields[$fzgField] = $currentVehicle;
        }
        foreach ([[$persoField1, $fahrer], [$persoField2, $beifahrer], [$persoField3, $praktikant]] as [$pf, $pv]) {
            if (empty($target[$pf]) && $pv !== null) {
                $vehFields[$pf] = $pv;
            }
        }
        $vehFields['prot_by'] = $isDoctorVehicle ? 1 : 0;

        // Alle nicht-leeren Quell-Felder übernehmen (außer excluded)
        $updates = [];
        foreach ($reqData as $f => $v) {
            if (in_array($f, self::SHARE_EXCLUDED_FIELDS, true) || str_starts_with($f, 'sr_')) continue;
            if ($v !== null && $v !== '') {
                $updates[$f] = $v;
            }
        }
        foreach ($vehFields as $f => $v) {
            $updates[$f] = $v;
        }

        if (!empty($updates)) {
            Edivi::where('enr', $targetEnr)->update($updates);
        }

        return ['error' => null];
    }

    /** Share-New: Neues Protokoll aus Quelldaten + aktuellem Fahrzeug erstellen. */
    private function handleShareNewProtocol(
        array $reqData, string $currentVehicle, string $fzgField,
        string $persoField1, string $persoField2, string $persoField3,
        ?string $fahrer, ?string $beifahrer, ?string $praktikant
    ): array {
        $originalEnr = $reqData['enr'];

        // Freie ENR finden
        if (Edivi::where('enr', $originalEnr)->exists()) {
            $suffix = 1;
            do {
                $newEnr = $originalEnr . '_' . $suffix++;
            } while (Edivi::where('enr', $newEnr)->exists());
        } else {
            $newEnr = $originalEnr;
        }

        $insert = [
            'enr'     => $newEnr,
            $fzgField => $currentVehicle,
        ];

        foreach ([[$persoField1, $fahrer], [$persoField2, $beifahrer], [$persoField3, $praktikant]] as [$pf, $pv]) {
            if ($pv !== null) {
                $insert[$pf] = $pv;
            }
        }

        foreach ($reqData as $f => $v) {
            if (in_array($f, self::SHARE_EXCLUDED_FIELDS, true) || str_starts_with($f, 'sr_') || $f === 'enr') continue;
            if ($v !== null && $v !== '') {
                $insert[$f] = $v;
            }
        }

        DB::table('intra_edivi')->insert($insert);

        return ['new_enr' => $newEnr];
    }

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
