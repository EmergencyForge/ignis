<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\EnotfV2\Models\Edivi;
use Plugin\EnotfV2\Models\EnotfSession;
use Plugin\EnotfV2\Policies\EnotfV2Policy;

/**
 * Crew-Session-API für eNOTF v2.
 *
 *   GET  /api/enotf-v2/check-vehicle-session?vehicle=<identifier>
 *   POST /api/enotf-v2/check-conflict
 *   POST /api/enotf-v2/delete-vehicle-session
 *   GET  /api/enotf-v2/session-status   (Token im Header X-Enotf-Session-Token)
 *   POST /api/enotf-v2/session-update   (Token im POST-Body)
 *
 * v2-Gegenstücke zu den v1-Endpoints in Api\EnotfController — gleiche
 * Antwortstrukturen, aber hinter dem config-gated v2-Auth (die v1-Routen
 * hängen hinter hartem User-Auth und liefern Crews ohne Panel-Login nur
 * 401er). check-vehicle-session füttert das Join-Panel der Login-Seite,
 * session-status/session-update den 10s-Live-Sync (session-sync.js).
 *
 * check-vehicle-session ohne aktive Session: { "success": true, "active": false }
 * check-vehicle-session mit aktiver Session:
 *   { "success": true, "active": true, "session_id": int,
 *     "crew": { fahrername, fahrerquali, beifahrername, beifahrerquali,
 *               praktikantname, praktikantquali },
 *     "free_positions": ["fahrer"|"beifahrer"|"praktikant", ...],
 *     "can_delete": bool,
 *     "updated_at": "..." }
 *
 * Vertrauensregel für Klarnamen und Session-Löschung (siehe
 * isTrustedForVehicle): Panel-User, PIN-verifizierte Geräte und
 * Browser-Sessions mit eigener Bindung an das Fahrzeug. Alle anderen
 * bekommen die Crew-Namen nur maskiert und dürfen keine Sessions
 * beenden — check-vehicle-session ist sonst über die Login-Seite
 * unauthentifiziert erreichbar.
 */
final class SessionApiController
{
    /**
     * GET /api/enotf-v2/check-vehicle-session — aktive Crew-Session eines
     * Fahrzeugs inkl. Besatzung und freier Positionen.
     */
    public function checkVehicleSession(Request $request): Response
    {
        $vehicleIdentifier = (string) ($request->query['vehicle'] ?? '');
        if ($vehicleIdentifier === '') {
            return Response::json(['success' => false, 'error' => 'Fahrzeug-Kennung fehlt'], 400);
        }

        $session = EnotfSession::query()
            ->active()
            ->where('vehicle_identifier', $vehicleIdentifier)
            ->orderByDesc('updated_at')
            ->first();

        if ($session === null) {
            return Response::json(['success' => true, 'active' => false]);
        }

        $freePositions = [];
        if (empty($session->fahrername)) {
            $freePositions[] = 'fahrer';
        }
        if (empty($session->beifahrername)) {
            $freePositions[] = 'beifahrer';
        }
        if (empty($session->praktikantname)) {
            $freePositions[] = 'praktikant';
        }

        // Klarnamen nur für vertrauenswürdige Aufrufer — das Join-Panel
        // funktioniert auch mit maskierten Namen (Positionen + Initial +
        // Nachname reichen zur Orientierung)
        $trusted = $this->isTrustedForVehicle($vehicleIdentifier);
        $mask    = fn (?string $name): ?string => $trusted ? $name : $this->maskName($name);

        return Response::json([
            'success'        => true,
            'active'         => true,
            'session_id'     => (int) $session->id,
            'crew'           => [
                'fahrername'      => $mask($session->fahrername),
                'fahrerquali'     => $session->fahrerquali,
                'beifahrername'   => $mask($session->beifahrername),
                'beifahrerquali'  => $session->beifahrerquali,
                'praktikantname'  => $mask($session->praktikantname),
                'praktikantquali' => $session->praktikantquali,
            ],
            'free_positions' => $freePositions,
            'can_delete'     => $trusted,
            'updated_at'     => (string) $session->updated_at,
        ]);
    }

    /**
     * POST /api/enotf-v2/check-conflict — v2-Gegenstück zu v1
     * POST /api/enotf/check-conflict (dort hart auth-gated → 401 für
     * Crews ohne User-Login). Prüft vor dem Anlegen, ob für die ENR im
     * eigenen Fahrzeug-Slot (fzg_na bei NA-Fahrzeug, sonst fzg_transp)
     * bereits ein Protokoll existiert. Antwortstruktur wie v1.
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
            $fahrzeug = Capsule::table('intra_fahrzeuge')
                ->where('identifier', $_SESSION['protfzg'])
                ->first(['identifier', 'rd_type']);
            $isDoctorVehicle = $fahrzeug && (int) $fahrzeug->rd_type === 1;

            $existing = Edivi::query()->where('enr', $enr)->first(['fzg_transp', 'fzg_na']);
            if ($existing === null) {
                return Response::json(['conflict' => false]);
            }

            $currentField = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';
            if (empty($existing[$currentField])) {
                return Response::json(['conflict' => false]);
            }

            $vehicleName = Capsule::table('intra_fahrzeuge')
                ->where('identifier', $existing[$currentField])
                ->value('name') ?? $existing[$currentField];
            $protocolType = $isDoctorVehicle ? 'Notarzt-Protokoll' : 'Rettungsdienst-Protokoll';

            return Response::json([
                'conflict' => true,
                'message'  => "Für die Einsatznummer {$enr} ist bereits ein {$protocolType} vom Fahrzeug {$vehicleName} vorhanden.",
            ]);
        } catch (\PDOException $e) {
            Logger::error('EnotfV2: check-conflict Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Database error'], 500);
        }
    }

    /**
     * POST /api/enotf-v2/delete-vehicle-session   JSON: { "vehicle": "..." }
     *
     * Deaktiviert alle aktiven Crew-Sessions eines Fahrzeugs — genutzt vom
     * „Session löschen"-Button der Login-Seite (das v1-Pendant hängt
     * hinter hartem User-Auth und ist für Crews ohne Panel-Login
     * unerreichbar). Antwort wie v1: { "success": true }.
     *
     * Zugriff nur für vertrauenswürdige Aufrufer (isTrustedForVehicle):
     * Panel-User, PIN-verifizierte Geräte oder Browser-Sessions mit
     * eigener Bindung an dieses Fahrzeug — Letzteres deckt den Haupt-
     * anwendungsfall „alte Crew hat sich am Gerät nicht abgemeldet" ab.
     * Ein fremdes Gerät ohne jede Verifikation kann damit keine fremden
     * Crews mehr abmelden; ihm bleibt der Weg über „Neue Besatzung".
     */
    public function deleteVehicleSession(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $input   = $request->json();
        $vehicle = $input['vehicle'] ?? null;

        if (!$vehicle || !is_string($vehicle)) {
            return Response::json(['success' => false, 'error' => 'Fahrzeug fehlt'], 400);
        }

        if (!$this->isTrustedForVehicle($vehicle)) {
            return Response::json(['success' => false, 'error' => 'Nicht autorisiert'], 403);
        }

        Capsule::table('intra_enotf_sessions')
            ->where('vehicle_identifier', $vehicle)
            ->where('active', 1)
            ->update(['active' => 0]);

        return Response::json(['success' => true]);
    }

    /**
     * GET /api/enotf-v2/session-status   Header: X-Enotf-Session-Token
     *
     * 10s-Poll des Clients (session-sync.js): liefert Crew + eigene
     * Position zur Session des Tokens. Deaktivierte oder unbekannte
     * Sessions melden { active: false } — der Client leitet dann auf die
     * Abmelde-Seite um. Das Token selbst ist die Berechtigung (wie v1);
     * ohne Token gibt es 400.
     *
     * Das Token kommt AUSSCHLIESSLICH aus dem Header — als Query-
     * Parameter würde es in Access-Logs, Proxy-Logs und Referern landen.
     * Einziger Konsument ist session-sync.js, das den Header setzt.
     */
    public function sessionStatus(Request $request): Response
    {
        $sessionToken = trim((string) ($request->header('X-Enotf-Session-Token') ?? ''));
        if ($sessionToken === '') {
            return Response::json(['success' => false, 'error' => 'Token fehlt'], 400);
        }

        $result = Capsule::table('intra_enotf_session_members as m')
            ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.session_token', $sessionToken)
            ->select('s.*', 'm.position as my_position')
            ->first();

        $result = $result !== null ? (array) $result : null;

        if ($result === null || (int) $result['active'] === 0) {
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

    /**
     * POST /api/enotf-v2/session-update   Body (Form): token=<token>
     *
     * Zieht die aktuelle Crew aus der DB-Session in die PHP-Browser-
     * Session — der Client ruft das auf, wenn der session-status-Poll
     * eine Crew-Änderung gemeldet hat (v1-Semantik, Api\EnotfController).
     */
    public function sessionUpdate(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'error' => 'Methode nicht erlaubt'], 405);
        }

        $sessionToken = (string) ($request->post['token'] ?? '');
        if ($sessionToken === '') {
            return Response::json(['success' => false, 'error' => 'Token fehlt'], 400);
        }

        $session = Capsule::table('intra_enotf_session_members as m')
            ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.session_token', $sessionToken)
            ->where('s.active', 1)
            ->select('s.*')
            ->first();

        if ($session === null) {
            return Response::json(['success' => false, 'error' => 'Session nicht gefunden oder inaktiv'], 404);
        }

        $session = (array) $session;

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
     * Darf der Aufrufer Crew-Details dieses Fahrzeugs im Klartext sehen
     * bzw. dessen Sessions verwalten?
     *
     * Wahr für: Panel-User, Geräte mit frisch eingegebenem PIN und
     * Browser-Sessions, die selbst an dieses Fahrzeug gebunden sind —
     * über die aktuelle Crew-Session (protfzg) oder einen eigenen
     * Member-Token einer Session des Fahrzeugs (der Fall „alte Crew ist
     * am Gerät noch angemeldet").
     */
    private function isTrustedForVehicle(string $vehicleIdentifier): bool
    {
        if (!empty($_SESSION['userid'])) {
            return true;
        }
        if (EnotfV2Policy::pinEntered()) {
            return true;
        }
        if (EnotfV2Policy::hasCrewSession() && (string) $_SESSION['protfzg'] === $vehicleIdentifier) {
            return true;
        }

        $token = (string) ($_SESSION['enotf_session_token'] ?? '');
        if ($token !== '') {
            return Capsule::table('intra_enotf_session_members as m')
                ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
                ->where('m.session_token', $token)
                ->where('s.vehicle_identifier', $vehicleIdentifier)
                ->exists();
        }

        return false;
    }

    /**
     * Maskiert einen Klarnamen für nicht verifizierte Aufrufer:
     * "Max Mustermann" → "M. Mustermann", einzelne Namen → "M.".
     * Der Nachname bleibt lesbar, damit das Join-Panel der Login-Seite
     * erkennbar hält, wer angemeldet ist.
     */
    private function maskName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return $name;
        }

        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = (string) array_shift($parts);
        $initial = mb_substr($first, 0, 1) . '.';

        return $parts === [] ? $initial : $initial . ' ' . implode(' ', $parts);
    }

    /**
     * Crew-Felder einer Session-Zeile als Anzeige-Struktur (v1-Format).
     *
     * @param array<string,mixed> $session
     * @return array<string,mixed>
     */
    private function extractCrew(array $session): array
    {
        return [
            'fahrername'      => $session['fahrername'],
            'fahrerquali'     => $session['fahrerquali'],
            'beifahrername'   => $session['beifahrername'],
            'beifahrerquali'  => $session['beifahrerquali'],
            'praktikantname'  => $session['praktikantname'],
            'praktikantquali' => $session['praktikantquali'],
        ];
    }
}
