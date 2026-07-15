<?php

declare(strict_types=1);

namespace Plugin\Enotf;

use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;

/**
 * EnotfSession — Service für die Verwaltung der eNOTF-Crew-Sessions.
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
 */
class EnotfSession
{
    public function __construct(private PDO $pdo) {}

    /**
     * Findet die aktive Session für ein Fahrzeug.
     *
     * @return array<string,mixed>|null
     */
    public function findActiveByVehicle(string $vehicleIdentifier): ?array
    {
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
        Capsule::table('intra_enotf_sessions')
            ->where('vehicle_identifier', $vehicleIdentifier)
            ->where('active', 1)
            ->update(['active' => 0]);

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
     *
     * @return array{session_token:string,session_data:array<string,mixed>}|null
     */
    public function joinSession(int $sessionId, string $position, string $name, ?string $quali): ?array
    {
        $allowedPositions = ['fahrer', 'beifahrer', 'praktikant'];
        if (!in_array($position, $allowedPositions, true)) {
            return null;
        }

        $posNameCol  = $position . 'name';
        $posQualiCol = $position . 'quali';

        Capsule::table('intra_enotf_sessions')
            ->where('id', $sessionId)
            ->update([
                $posNameCol  => $name,
                $posQualiCol => $quali,
            ]);

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
        $allowedPositions = ['fahrer', 'beifahrer', 'praktikant'];
        if (!in_array($position, $allowedPositions, true)) {
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
            Capsule::table('intra_enotf_sessions')
                ->where('id', $sessionId)
                ->update(['active' => 0]);
        }
    }

    /**
     * Deaktiviert ALLE aktiven Sessions für ein Fahrzeug (Komplett-Logout).
     */
    public function deactivateAllForVehicle(string $vehicleIdentifier): void
    {
        Capsule::table('intra_enotf_sessions')
            ->where('vehicle_identifier', $vehicleIdentifier)
            ->where('active', 1)
            ->update(['active' => 0]);
    }
}
