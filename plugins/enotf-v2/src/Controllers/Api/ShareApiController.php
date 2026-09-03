<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as DB;
use PDOException;
use Plugin\EnotfV2\Models\Edivi;
use Plugin\EnotfV2\Policies\EnotfV2Policy;
use Plugin\EnotfV2\Support\ProtokollAccessGuard;

/**
 * Share-API für eNOTF v2 — Protokoll-Übergabe zwischen Fahrzeugen.
 *
 * v2-Gegenstücke zu den v1-Share-Endpoints in Plugin\Enotf\Controllers\
 * Api\EnotfController: gleiche Tabellen (intra_edivi_share_requests),
 * gleiche Antwortstrukturen, gleiche Merge-/New-Semantik — aber hinter
 * dem config-gated v2-Auth (Route) plus Crew-Session-Pflicht (hier im
 * Controller), damit Crews ohne User-Login teilen können, wenn das
 * User-Gate deaktiviert ist.
 *
 * Flow:
 *   1. Quell-Crew: POST send-request → pending-Eintrag in
 *      intra_edivi_share_requests (max. eine pending Anfrage je
 *      Quell-Protokoll und Zielfahrzeug).
 *   2. Ziel-Crew pollt GET check-requests (Overview + Protokollseiten)
 *      und bekommt die älteste pending Anfrage für ihr Fahrzeug.
 *   3. POST accept-request mit action "merge" (Daten in eigenes offenes
 *      Protokoll übernehmen, target_enr Pflicht) oder "new" (neues
 *      Protokoll aus den Quelldaten + eigener Crew; bei ENR-Kollision
 *      wird ein _1/_2/…-Suffix angehängt) — beides in einer Transaktion.
 *      Alternativ POST reject-request.
 *
 * SHARE_EXCLUDED_FIELDS ist die exakte v1-Liste: Identitäts-, Fahrzeug-,
 * Freigabe- und QM-Felder des Ziels bleiben unangetastet, dazu die
 * JOIN-Artefakte aus der share_requests-Tabelle.
 */
final class ShareApiController
{
    /** Felder die beim Share-Merge/-New NICHT übernommen werden (v1-identisch). */
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

    /**
     * GET /api/enotf-v2/share/get-available-vehicles
     * Alle aktiven Rettungsdienst-Fahrzeuge außer dem eigenen.
     */
    public function getAvailableVehicles(Request $request): Response
    {
        if (!EnotfV2Policy::hasCrewSession()) {
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
            Logger::error('EnotfV2: share/get-available-vehicles Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /**
     * GET /api/enotf-v2/share/check-requests
     * Älteste pending Share-Anfrage für das eigene Fahrzeug (Poll-Ziel).
     */
    public function checkRequests(Request $request): Response
    {
        if (!EnotfV2Policy::hasCrewSession()) {
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
            Logger::error('EnotfV2: share/check-requests Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /**
     * GET /api/enotf-v2/share/get-own-protocols
     * Letzte 20 offene Protokolle des eigenen Fahrzeugs (Merge-Auswahl).
     */
    public function getOwnProtocols(Request $request): Response
    {
        if (!EnotfV2Policy::hasCrewSession()) {
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
            Logger::error('EnotfV2: share/get-own-protocols Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /**
     * POST /api/enotf-v2/share/send-request
     * JSON: { "protocol_id": <int>, "enr": "...", "target_vehicle": "..." }
     *
     * Legt eine neue Share-Anfrage an. Pro Zielfahrzeug darf nur eine
     * pending Anfrage pro Quell-Protokoll existieren.
     */
    public function sendRequest(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Ungültige Anfragemethode']);
        }
        if (!EnotfV2Policy::hasCrewSession()) {
            return Response::json(['success' => false, 'message' => 'Nicht angemeldet']);
        }

        $input         = $request->json();
        $protocolId    = $input['protocol_id'] ?? null;
        $enr           = $input['enr'] ?? null;
        $targetVehicle = $input['target_vehicle'] ?? null;

        if (!$protocolId || !$enr || !$targetVehicle) {
            return Response::json(['success' => false, 'message' => 'Fehlende Parameter']);
        }

        // Nur eigene Protokolle dürfen geteilt werden — sonst ließe sich
        // über send-request + accept der Inhalt fremder Protokolle kopieren.
        $source = Edivi::query()->where('id', (int) $protocolId)->where('enr', $enr)
            ->first(['id', 'enr', 'fzg_transp', 'fzg_na']);
        if ($source === null || !ProtokollAccessGuard::canWrite($source->getAttributes())) {
            return Response::json(['success' => false, 'message' => 'Kein Zugriff auf dieses Protokoll'], 403);
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
            Logger::error('EnotfV2: share/send-request Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler beim Senden der Anfrage']);
        }
    }

    /**
     * POST /api/enotf-v2/share/reject-request
     * JSON: { "request_id": <int> } — markiert eine Share-Anfrage als abgelehnt.
     */
    public function rejectRequest(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Ungültige Anfragemethode']);
        }
        if (!EnotfV2Policy::hasCrewSession()) {
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
            Logger::error('EnotfV2: share/reject-request Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    /**
     * POST /api/enotf-v2/share/accept-request
     * JSON: { "request_id": <int>, "action": "merge"|"new", "target_enr": "..." }
     *
     * Akzeptiert eine Protokoll-Übergabe. Bei "merge" werden die Daten
     * ins Zielprotokoll geschrieben, bei "new" wird ein neues Protokoll
     * mit den Quelldaten + aktuellem Fahrzeug angelegt. Läuft komplett
     * in einer Transaktion (Datenübernahme + Statuswechsel der Anfrage).
     */
    public function acceptRequest(Request $request): Response
    {
        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Ungültige Anfragemethode']);
        }
        if (!EnotfV2Policy::hasCrewSession()) {
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

            // Share-Request + Quell-Protokoll laden. Bei gleichnamigen Spalten
            // (id, created_at, ...) gewinnt wie in v1 (`sr.*, ed.*`) die
            // zuletzt selektierte — also die Protokoll-Spalte.
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

            $currentVehicle  = (string) $_SESSION['protfzg'];
            $isDoctorVehicle = $this->isDoctorVehicle($currentVehicle);
            $fzgField    = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';
            $persoField1 = $isDoctorVehicle ? 'fzg_na_perso'   : 'fzg_transp_perso';
            $persoField2 = $isDoctorVehicle ? 'fzg_na_perso_2' : 'fzg_transp_perso_2';
            $persoField3 = $isDoctorVehicle ? 'fzg_na_perso_3' : 'fzg_transp_perso_3';

            $fahrer     = $this->formatCrewMember('fahrername', 'fahrerquali');
            $beifahrer  = $this->formatCrewMember('beifahrername', 'beifahrerquali');
            $praktikant = $this->formatCrewMember('praktikantname', 'praktikantquali');

            $newEnr      = null;
            $actionTaken = '';
            $message     = '';

            if ($action === 'merge') {
                $result = $this->handleMerge(
                    $reqData, (string) $targetEnr, $currentVehicle, $isDoctorVehicle,
                    $fzgField, $persoField1, $persoField2, $persoField3,
                    $fahrer, $beifahrer, $praktikant
                );
                if ($result['error']) {
                    $conn->rollBack();
                    return Response::json(['success' => false, 'message' => $result['error']]);
                }
                $actionTaken = 'merged';
                $message     = 'Daten wurden erfolgreich in das bestehende Protokoll übernommen';
            } else {
                $result = $this->handleNewProtocol(
                    $reqData, $currentVehicle, $fzgField,
                    $persoField1, $persoField2, $persoField3,
                    $fahrer, $beifahrer, $praktikant
                );
                $newEnr      = $result['new_enr'];
                $actionTaken = 'new_protocol';
                $message     = 'Neues Protokoll wurde erfolgreich erstellt';
            }

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
            Logger::error('EnotfV2: share/accept-request Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Datenbankfehler']);
        }
    }

    // ── Private Helper ────────────────────────────────────────────────

    /** NA-Fahrzeug? (rd_type = 1 → Notarzt-Slot fzg_na statt fzg_transp) */
    private function isDoctorVehicle(string $vehicleId): bool
    {
        $fzg = DB::table('intra_fahrzeuge')
            ->where('identifier', $vehicleId)
            ->first(['rd_type']);
        return $fzg && (int) $fzg->rd_type === 1;
    }

    /** Formatiert einen Session-Crew-Eintrag als "Name (Quali)" oder null. */
    private function formatCrewMember(string $nameKey, string $qualiKey): ?string
    {
        $name  = $_SESSION[$nameKey] ?? '';
        $quali = $_SESSION[$qualiKey] ?? '';
        return ($name !== '' && $quali !== '') ? "{$name} ({$quali})" : null;
    }

    /**
     * Merge: Quelldaten in ein bestehendes offenes Protokoll übernehmen.
     * Eigene Fahrzeug-/Crew-Felder werden nur gesetzt, wenn sie im Ziel
     * noch leer sind — die Zuweisungen des Empfängers bleiben erhalten.
     *
     * @param array<string, mixed> $reqData
     * @return array{error: ?string}
     */
    private function handleMerge(
        array $reqData, string $targetEnr, string $currentVehicle, bool $isDoctorVehicle,
        string $fzgField, string $persoField1, string $persoField2, string $persoField3,
        ?string $fahrer, ?string $beifahrer, ?string $praktikant
    ): array {
        $target = Edivi::where('enr', $targetEnr)->where('freigegeben', 0)->first();

        if (!$target) {
            return ['error' => 'Zielprotokoll nicht gefunden oder bereits freigegeben'];
        }

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

    /**
     * New: Neues Protokoll aus Quelldaten + aktuellem Fahrzeug anlegen.
     * Bei belegter ENR wird ein numerisches Suffix (_1, _2, …) angehängt.
     *
     * @param array<string, mixed> $reqData
     * @return array{new_enr: string}
     */
    private function handleNewProtocol(
        array $reqData, string $currentVehicle, string $fzgField,
        string $persoField1, string $persoField2, string $persoField3,
        ?string $fahrer, ?string $beifahrer, ?string $praktikant
    ): array {
        $originalEnr = $reqData['enr'];

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
}
