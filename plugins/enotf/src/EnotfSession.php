<?php

declare(strict_types=1);

namespace Plugin\Enotf;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * EnotfSession: Service für die Verwaltung der eNOTF-Crew-Sessions.
 *
 * Kapselt die Tabellen `intra_enotf_sessions` (1 Zeile pro Fahrzeug-Crew) und
 * `intra_enotf_session_members` (1 Zeile pro Crew-Mitglied mit Token).
 *
 * Eine Crew-Session besteht aus:
 *   - 1 Fahrzeug (`vehicle_identifier`)
 *   - 0–3 Positionen (fahrer, beifahrer, praktikant) mit Name + Quali
 *   - 1 active-Flag (alte Sessions bleiben für Historie/Audit)
 *
 * Member-Token werden in `$_SESSION['enotf_session_token']` gespeichert
 * und identifizieren das einzelne Crew-Mitglied innerhalb der Session.
 * Ob eine Browser-Session noch gilt, entscheidet bei jeder Anfrage diese
 * Tabelle (Crew\Support\CrewSessionGuard), nicht die PHP-Session.
 *
 * Endet eine Session, steht der Grund in `ended_reason` (ENDED_*).
 */
class EnotfSession
{
    /** Abgemeldet über die Abmelde-Seite („alle“ oder die letzte Position) */
    public const ENDED_LOGOUT = 'logout';
    /** Eine neue Besatzung hat sich auf dem Fahrzeug angemeldet */
    public const ENDED_REPLACED = 'replaced';
    /** Zu lange nicht benutzt (ENOTF_SESSION_IDLE_HOURS) */
    public const ENDED_EXPIRED = 'expired';
    /** Fahrzeug außer Dienst (Defekt, active = 0) */
    public const ENDED_OUT_OF_SERVICE = 'out_of_service';
    /** Über „Session löschen“ auf der Login-Seite beendet */
    public const ENDED_DELETED = 'deleted';

    public const POSITIONS = ['fahrer', 'beifahrer', 'praktikant'];

    /** Stunden ohne Nutzung, solange ENOTF_SESSION_IDLE_HOURS fehlt */
    public const IDLE_HOURS_DEFAULT = 12;

    /** Seltener schreiben: last_activity wird höchstens so oft erneuert */
    private const TOUCH_INTERVAL = 60;

    /**
     * Sekunden ohne Nutzung, nach denen eine Session abläuft.
     */
    public static function idleTimeout(): int
    {
        $hours = defined('ENOTF_SESSION_IDLE_HOURS') ? (int) constant('ENOTF_SESSION_IDLE_HOURS') : self::IDLE_HOURS_DEFAULT;
        if ($hours < 1 || $hours > 168) {
            $hours = self::IDLE_HOURS_DEFAULT;
        }

        return $hours * 3600;
    }

    /**
     * Beendet abgelaufene Sessions. Läuft beim Zugriff (Login-Seite,
     * Abgleich), ein Cron ist dafür nicht nötig.
     */
    public function expireIdle(?string $vehicleIdentifier = null): void
    {
        $query = Capsule::table('intra_enotf_sessions')
            ->where('active', 1)
            ->whereRaw('last_activity < NOW() - INTERVAL ? SECOND', [self::idleTimeout()]);
        if ($vehicleIdentifier !== null) {
            $query->where('vehicle_identifier', $vehicleIdentifier);
        }
        $query->update(['active' => 0, 'ended_reason' => self::ENDED_EXPIRED]);
    }

    /**
     * Session-Zeile und Position zu einem Member-Token, auch inaktive
     * (der Aufrufer braucht dann den Grund), mit dem Dienststatus des
     * Fahrzeugs (vehicle_active, null wenn das Fahrzeug unbekannt ist).
     *
     * @return array<string,mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $row = Capsule::table('intra_enotf_session_members as m')
            ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
            ->leftJoin('intra_fahrzeuge as f', 'f.identifier', '=', 's.vehicle_identifier')
            ->where('m.session_token', $token)
            ->select('s.*', 'm.position as member_position', 'f.active as vehicle_active')
            // In der DB gerechnet: PHP und MySQL können andere Zeitzonen haben
            ->selectRaw('TIMESTAMPDIFF(SECOND, s.last_activity, NOW()) as idle_seconds')
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Echte Nutzung vermerken (höchstens einmal pro Minute ein Write).
     *
     * @param array<string,mixed> $session Zeile aus findByToken()
     */
    public function touch(array $session): void
    {
        if ((int) ($session['idle_seconds'] ?? PHP_INT_MAX) < self::TOUCH_INTERVAL) {
            return;
        }

        Capsule::table('intra_enotf_sessions')
            ->where('id', (int) $session['id'])
            ->where('active', 1)
            ->update(['last_activity' => Capsule::raw('NOW()')]);
    }

    /**
     * Ist die Session zu lange nicht benutzt worden?
     *
     * @param array<string,mixed> $session Zeile aus findByToken()
     */
    public static function isIdle(array $session): bool
    {
        return (int) ($session['idle_seconds'] ?? 0) > self::idleTimeout();
    }

    /**
     * Beendet eine einzelne Session mit Grund.
     */
    public function end(int $sessionId, string $reason): void
    {
        Capsule::table('intra_enotf_sessions')
            ->where('id', $sessionId)
            ->where('active', 1)
            ->update(['active' => 0, 'ended_reason' => $reason]);
    }

    /**
     * Findet die aktive Session für ein Fahrzeug.
     *
     * @return array<string,mixed>|null
     */
    public function findActiveByVehicle(string $vehicleIdentifier): ?array
    {
        $this->expireIdle($vehicleIdentifier);

        $row = Capsule::table('intra_enotf_sessions')
            ->where('vehicle_identifier', $vehicleIdentifier)
            ->where('active', 1)
            ->orderByDesc('updated_at')
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Erstellt eine neue Crew-Session und legt den Ersteller als Member an.
     *
     * @param array{fahrername:string,fahrerquali:?string,beifahrername:?string,beifahrerquali:?string,praktikantname:?string,praktikantquali:?string} $crew
     * @return array{session_id:int,session_token:string,position:string}
     */
    public function createSession(string $vehicleIdentifier, array $crew): array
    {
        // Bestehende aktive Sessions für dieses Fahrzeug deaktivieren
        $this->deactivateAllForVehicle($vehicleIdentifier, self::ENDED_REPLACED);

        $sessionId = (int) Capsule::table('intra_enotf_sessions')->insertGetId([
            'vehicle_identifier' => $vehicleIdentifier,
            'fahrername'         => $crew['fahrername'] ?? null,
            'fahrerquali'        => $crew['fahrerquali'] ?? null,
            'beifahrername'      => $crew['beifahrername'] ?? null,
            'beifahrerquali'     => $crew['beifahrerquali'] ?? null,
            'praktikantname'     => $crew['praktikantname'] ?? null,
            'praktikantquali'    => $crew['praktikantquali'] ?? null,
        ]);

        $token = bin2hex(random_bytes(32));
        Capsule::table('intra_enotf_session_members')->insert([
            'session_id'    => $sessionId,
            'session_token' => $token,
            'position'      => 'fahrer',
        ]);

        return [
            'session_id'    => $sessionId,
            'session_token' => $token,
            'position'      => 'fahrer',
        ];
    }

    /**
     * Aktualisiert die Crew-Daten einer bestehenden Session.
     *
     * @param array<string, mixed> $crew
     */
    public function updateCrew(int $sessionId, array $crew): void
    {
        Capsule::table('intra_enotf_sessions')
            ->where('id', $sessionId)
            ->update([
                'fahrername'      => $crew['fahrername'] ?? null,
                'fahrerquali'     => $crew['fahrerquali'] ?? null,
                'beifahrername'   => $crew['beifahrername'] ?? null,
                'beifahrerquali'  => $crew['beifahrerquali'] ?? null,
                'praktikantname'  => $crew['praktikantname'] ?? null,
                'praktikantquali' => $crew['praktikantquali'] ?? null,
            ]);
    }

    /**
     * Findet die aktive Session-ID für einen Member-Token + Fahrzeug.
     */
    public function findSessionIdByTokenAndVehicle(string $token, string $vehicleIdentifier): ?int
    {
        $id = Capsule::table('intra_enotf_session_members as m')
            ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.session_token', $token)
            ->where('s.vehicle_identifier', $vehicleIdentifier)
            ->where('s.active', 1)
            ->value('s.id');

        return $id ? (int) $id : null;
    }

    /**
     * Lässt einen User einer bestehenden Session beitreten.
     * Setzt die Position-Spalten der Session und legt einen Member-Eintrag an.
     * Nur eine freie Position einer aktiven Session lässt sich besetzen,
     * sonst null (die Login-Seite filtert das auch, entscheidend ist aber
     * dieser Check).
     *
     * @return array{session_token:string,session_data:array<string,mixed>}|null
     */
    public function joinSession(int $sessionId, string $position, string $name, ?string $quali): ?array
    {
        if (!in_array($position, self::POSITIONS, true)) {
            return null;
        }

        $posNameCol  = $position . 'name';
        $posQualiCol = $position . 'quali';

        $claimed = Capsule::table('intra_enotf_sessions')
            ->where('id', $sessionId)
            ->where('active', 1)
            ->where(function ($q) use ($posNameCol) {
                $q->whereNull($posNameCol)->orWhere($posNameCol, '');
            })
            ->update([
                $posNameCol  => $name,
                $posQualiCol => $quali,
            ]);
        if ($claimed === 0) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        Capsule::table('intra_enotf_session_members')->insert([
            'session_id'    => $sessionId,
            'session_token' => $token,
            'position'      => $position,
        ]);

        $sessionData = (array) Capsule::table('intra_enotf_sessions')->where('id', $sessionId)->first();

        return [
            'session_token' => $token,
            'session_data'  => $sessionData,
        ];
    }

    /**
     * Entfernt einen Member aus einer Session und leert seine Position.
     * Wenn keine Position mehr besetzt ist, wird die Session deaktiviert.
     */
    public function removeMember(string $sessionToken, string $position): void
    {
        if (!in_array($position, self::POSITIONS, true)) {
            return;
        }

        $sessionId = Capsule::table('intra_enotf_session_members as m')
            ->join('intra_enotf_sessions as s', 's.id', '=', 'm.session_id')
            ->where('m.session_token', $sessionToken)
            ->where('s.active', 1)
            ->value('s.id');

        if (!$sessionId) {
            return;
        }

        $sessionId   = (int) $sessionId;
        $posNameCol  = $position . 'name';
        $posQualiCol = $position . 'quali';

        Capsule::table('intra_enotf_sessions')
            ->where('id', $sessionId)
            ->update([
                $posNameCol  => null,
                $posQualiCol => null,
            ]);

        Capsule::table('intra_enotf_session_members')
            ->where('session_token', $sessionToken)
            ->delete();

        $remaining = (array) Capsule::table('intra_enotf_sessions')
            ->where('id', $sessionId)
            ->select('fahrername', 'beifahrername', 'praktikantname')
            ->first();

        if (
            empty($remaining['fahrername']) &&
            empty($remaining['beifahrername']) &&
            empty($remaining['praktikantname'])
        ) {
            $this->end($sessionId, self::ENDED_LOGOUT);
        }
    }

    /**
     * Deaktiviert ALLE aktiven Sessions für ein Fahrzeug (Komplett-Logout).
     */
    public function deactivateAllForVehicle(string $vehicleIdentifier, string $reason = self::ENDED_LOGOUT): void
    {
        Capsule::table('intra_enotf_sessions')
            ->where('vehicle_identifier', $vehicleIdentifier)
            ->where('active', 1)
            ->update(['active' => 0, 'ended_reason' => $reason]);
    }
}
