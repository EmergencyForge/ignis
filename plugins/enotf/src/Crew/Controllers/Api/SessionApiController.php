<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers\Api;

use App\Logging\Logger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Crew\Models\Edivi;
use Plugin\Enotf\Crew\Models\EnotfSession;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use Plugin\Enotf\Crew\Support\CrewSessionGuard;
use Plugin\Enotf\EnotfSession as EnotfSessionService;

/**
 * Crew-Session-API für eNOTF v2.
 *
 *   GET  /api/enotf/check-vehicle-session?vehicle=<identifier>
 *   POST /api/enotf/check-conflict
 *   POST /api/enotf/delete-vehicle-session
 *   GET  /api/enotf/session-status   (Token im Header X-Enotf-Session-Token)
 *   POST /api/enotf/session-update   (Token im POST-Body)
 *
 * v2-Gegenstücke zu den v1-Endpoints in Api\EnotfController, gleiche
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
 * beenden, check-vehicle-session ist sonst über die Login-Seite
 * unauthentifiziert erreichbar.
 */
final class SessionApiController
{
    /**
     * GET /api/enotf/check-vehicle-session: aktive Crew-Session eines
     * Fahrzeugs inkl. Besatzung und freier Positionen.
     */
    public function checkVehicleSession(Request $request): Response
    {
        $vehicleIdentifier = (string) ($request->query['vehicle'] ?? '');
        if ($vehicleIdentifier === '') {
            return Response::json(['success' => false, 'error' => 'Fahrzeug-Kennung fehlt'], 400);
        }

        (new EnotfSessionService())->expireIdle($vehicleIdentifier);

        $session = EnotfSession::active()
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

        // Klarnamen nur für vertrauenswürdige Aufrufer: das Join-Panel
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
     * POST /api/enotf/check-conflict: v2-Gegenstück zu v1
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
        if (!CrewPolicy::hasCrewSession()) {
            return Response::json(['error' => 'Not authenticated', 'code' => 'not_logged_in'], 401);
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
            Logger::error('eNOTF: check-conflict Fehler', ['error' => $e->getMessage()]);
            return Response::json(['error' => 'Database error'], 500);
        }
    }

    /**
     * POST /api/enotf/delete-vehicle-session   JSON: { "vehicle": "..." }
     *
     * Deaktiviert alle aktiven Crew-Sessions eines Fahrzeugs, genutzt vom
     * „Session löschen"-Button der Login-Seite (das v1-Pendant hängt
     * hinter hartem User-Auth und ist für Crews ohne Panel-Login
     * unerreichbar). Antwort wie v1: { "success": true }.
     *
     * Zugriff nur für vertrauenswürdige Aufrufer (isTrustedForVehicle):
     * Panel-User, PIN-verifizierte Geräte oder Browser-Sessions mit
     * eigener Bindung an dieses Fahrzeug. Letzteres deckt den Haupt-
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

        (new EnotfSessionService())->deactivateAllForVehicle($vehicle, EnotfSessionService::ENDED_DELETED);

        return Response::json(['success' => true]);
    }

    /**
     * GET /api/enotf/session-status   Header: X-Enotf-Session-Token
     *
     * 10s-Poll des Clients (session-sync.js): liefert Crew + eigene
     * Position. Maßgeblich ist das Token DIESER Browser-Session; der
     * Header muss dazu passen, ein fremdes oder kopiertes Token allein
     * reicht nicht. Beendete, abgelaufene oder fremde Anmeldungen melden
     * { active: false, grund: "…" }, die Crew-Keys der PHP-Session sind
     * dann schon weg und der Client leitet auf die Abmelde-Seite mit Grund.
     *
     * Der Poll zieht die Besatzung selbst in die PHP-Session nach und
     * verlängert die Anmeldung nicht (CrewSessionGuard ohne touch).
     *
     * Das Token kommt AUSSCHLIESSLICH aus dem Header. Als Query-
     * Parameter würde es in Access-Logs, Proxy-Logs und Referern landen.
     */
    public function sessionStatus(Request $request): Response
    {
        $sessionToken = trim((string) ($request->header('X-Enotf-Session-Token') ?? ''));
        if ($sessionToken === '') {
            return Response::json(['success' => false, 'error' => 'Token fehlt'], 400);
        }

        $own = (string) ($_SESSION['enotf_session_token'] ?? '');
        if ($own === '' || !hash_equals($own, $sessionToken)) {
            return Response::json(['success' => true, 'active' => false, 'grund' => 'beendet']);
        }

        $grund = CrewSessionGuard::check(touch: false);
        if ($grund !== null) {
            return Response::json(['success' => true, 'active' => false, 'grund' => $grund]);
        }

        return Response::json([
            'success'     => true,
            'active'      => true,
            'crew'        => $this->extractCrew($_SESSION),
            'my_position' => (string) ($_SESSION['enotf_position'] ?? ''),
        ]);
    }

    /**
     * POST /api/enotf/session-update   Body (Form): token=<token>
     *
     * Zieht die aktuelle Crew aus der DB-Session in die PHP-Browser-
     * Session. Der Abgleich macht das inzwischen selbst, der Endpoint
     * bleibt für den v1-Client (enotf-session-sync.js). Wie beim Status
     * zählt nur das Token dieser Browser-Session.
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

        $own = (string) ($_SESSION['enotf_session_token'] ?? '');
        if ($own === '' || !hash_equals($own, $sessionToken) || CrewSessionGuard::check(touch: false) !== null) {
            return Response::json(['success' => false, 'code' => 'session_ended', 'error' => 'Session nicht gefunden oder inaktiv'], 404);
        }

        return Response::json(['success' => true]);
    }

    /**
     * Darf der Aufrufer Crew-Details dieses Fahrzeugs im Klartext sehen
     * bzw. dessen Sessions verwalten?
     *
     * Wahr für: Panel-User, Geräte mit frisch eingegebenem PIN und
     * Browser-Sessions, die selbst an dieses Fahrzeug gebunden sind,
     * über die aktuelle Crew-Session (protfzg) oder einen eigenen
     * Member-Token einer Session des Fahrzeugs (der Fall „alte Crew ist
     * am Gerät noch angemeldet").
     */
    private function isTrustedForVehicle(string $vehicleIdentifier): bool
    {
        if (!empty($_SESSION['userid'])) {
            return true;
        }
        if (CrewPolicy::pinEntered()) {
            return true;
        }
        if (CrewPolicy::hasCrewSession() && (string) $_SESSION['protfzg'] === $vehicleIdentifier) {
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
            'fahrername'      => $session['fahrername'] ?? null,
            'fahrerquali'     => $session['fahrerquali'] ?? null,
            'beifahrername'   => $session['beifahrername'] ?? null,
            'beifahrerquali'  => $session['beifahrerquali'] ?? null,
            'praktikantname'  => $session['praktikantname'] ?? null,
            'praktikantquali' => $session['praktikantquali'] ?? null,
        ];
    }
}
