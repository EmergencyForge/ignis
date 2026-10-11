<?php

declare(strict_types=1);

namespace Plugin\Firetab\Controllers\Api;

use App\Auth\Gate;
use App\Logging\Logger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Firetab\Models\FireIncident;
use Plugin\Firetab\Models\FireIncidentLogEntry;
use Plugin\Firetab\Requests\Asu\AsuEventRequest;
use Plugin\Firetab\Requests\Asu\AsuTruppRequest;

/**
 * Atemschutzüberwachung im Einsatz (Reiter „Atemschutz“).
 *
 * Gespeichert werden Trupps, ihre Plätze und Ereignisse mit Serverzeit.
 * Den Zustand (Phase, Rückzugsdruck, fällige Abfragen) rechnet das Tablet
 * aus den Ereignissen, siehe assets/js/firetab-asu-state.js. Alle Tablets
 * eines Einsatzes fragen denselben Stand ab und sehen dasselbe.
 *
 * Zugriff wie bei der Lagekarte: Crew eines dem Einsatz zugeordneten
 * Fahrzeugs oder fire.incident.qm. Schreiben nur, solange der Einsatz
 * nicht abgeschlossen ist.
 */
final class FireAsuController
{
    /** Ereignisse, die im Einsatzprotokoll (Reiter „Protokoll“) landen. */
    private const LOGGED = [
        'angeschlossen' => 'unter Atemschutz angeschlossen',
        'draussen'      => 'aus dem Atemschutzeinsatz zurück',
        'mayday'        => 'MAYDAY',
        'mayday_ende'   => 'Mayday beendet',
    ];

    /** GET /api/fire/asu/state?incident_id= */
    public function state(Request $request): Response
    {
        $incidentId = (int) ($request->query['incident_id'] ?? 0);
        if (($denied = $this->deny($incidentId, false)) !== null) {
            return $denied;
        }

        $trupps = Capsule::table('intra_fire_asu_trupps')
            ->where('incident_id', $incidentId)
            ->orderBy('id')
            ->get(['id', 'name', 'role', 'task', 'expected_minutes'])
            ->map(fn ($t) => (array) $t)->all();

        $members = Capsule::table('intra_fire_asu_members')
            ->whereIn('trupp_id', array_column($trupps, 'id') ?: [0])
            ->orderByRaw("FIELD(position, 'tf', 'tm1', 'tm2')")
            ->get(['id', 'trupp_id', 'position', 'name'])
            ->groupBy('trupp_id');

        foreach ($trupps as &$t) {
            $t['id']               = (int) $t['id'];
            $t['expected_minutes'] = (int) $t['expected_minutes'];
            $t['members']          = array_map(fn ($m) => [
                'id'       => (int) $m->id,
                'position' => $m->position,
                'name'     => $m->name,
            ], ($members[$t['id']] ?? collect())->all());
        }
        unset($t);

        // Zeiten als Unix-Millisekunden aus der Datenbank, damit Tablet und
        // Ereignisse dieselbe Uhr nutzen, egal in welcher Zeitzone PHP läuft.
        $events = Capsule::table('intra_fire_asu_events')
            ->where('incident_id', $incidentId)
            ->orderBy('id')
            ->selectRaw('id, trupp_id, member_id, type, bar, note, device, client_uuid,
                UNIX_TIMESTAMP(created_at) * 1000 AS at, voided_at IS NOT NULL AS voided')
            ->get()
            ->map(fn ($e) => [
                'id'        => (int) $e->id,
                'trupp_id'  => (int) $e->trupp_id,
                'member_id' => $e->member_id !== null ? (int) $e->member_id : null,
                'type'      => $e->type,
                'bar'       => $e->bar !== null ? (int) $e->bar : null,
                'note'      => $e->note,
                'device'    => $e->device,
                'client_uuid' => $e->client_uuid,
                'at'        => (int) $e->at,
                'voided'    => (bool) $e->voided,
            ])->all();

        $now = (int) Capsule::connection()->selectOne('SELECT UNIX_TIMESTAMP(NOW()) * 1000 AS now')->now;

        return Response::json([
            'success'   => true,
            'now'       => $now,
            'finalized' => (bool) FireIncident::where('id', $incidentId)->value('finalized'),
            'trupps'    => $trupps,
            'events'    => $events,
        ]);
    }

    /** POST /api/fire/asu/trupp: anlegen oder ändern */
    public function saveTrupp(Request $request): Response
    {
        $data = AsuTruppRequest::validate($request->json() ?? $request->post);
        if (($denied = $this->deny($data['incident_id'], true)) !== null) {
            return $denied;
        }

        $truppId = $data['trupp_id'];
        if ($truppId !== null && !$this->truppInIncident($truppId, $data['incident_id'])) {
            return Response::json(['success' => false, 'message' => 'Trupp nicht gefunden.'], 404);
        }

        Capsule::connection()->transaction(function () use ($data, &$truppId) {
            $row = [
                'name'             => $data['name'],
                'role'             => $data['role'],
                'task'             => $data['task'] !== '' ? $data['task'] : null,
                'expected_minutes' => $data['expected_minutes'],
            ];
            if ($truppId === null) {
                $truppId = (int) Capsule::table('intra_fire_asu_trupps')->insertGetId($row + [
                    'incident_id' => $data['incident_id'],
                    'vehicle_id'  => $this->vehicleId(),
                ]);
            } else {
                Capsule::table('intra_fire_asu_trupps')->where('id', $truppId)->update($row);
            }

            foreach (AsuTruppRequest::POSITIONS as $position) {
                $name  = $data['members'][$position] ?? '';
                $where = ['trupp_id' => $truppId, 'position' => $position];
                if ($name === '') {
                    Capsule::table('intra_fire_asu_members')->where($where)->delete();
                } else {
                    Capsule::table('intra_fire_asu_members')->updateOrInsert($where, ['name' => $name]);
                }
            }
        });

        return Response::json(['success' => true, 'trupp_id' => $truppId]);
    }

    /** POST /api/fire/asu/trupp/delete: nur ohne gültige Ereignisse, also bei Fehleingaben */
    public function deleteTrupp(Request $request): Response
    {
        $body       = $request->json() ?? $request->post;
        $incidentId = (int) ($body['incident_id'] ?? 0);
        $truppId    = (int) ($body['trupp_id'] ?? 0);
        if (($denied = $this->deny($incidentId, true)) !== null) {
            return $denied;
        }
        if (!$this->truppInIncident($truppId, $incidentId)) {
            return Response::json(['success' => false, 'message' => 'Trupp nicht gefunden.'], 404);
        }
        $used = Capsule::table('intra_fire_asu_events')->where('trupp_id', $truppId)->whereNull('voided_at')->exists();
        if ($used) {
            return Response::json(['success' => false, 'message' => 'Der Trupp hat schon Einträge und bleibt im Protokoll.'], 409);
        }
        Capsule::table('intra_fire_asu_trupps')->where('id', $truppId)->delete();

        return Response::json(['success' => true]);
    }

    /** POST /api/fire/asu/event */
    public function event(Request $request): Response
    {
        $data = AsuEventRequest::validate($request->json() ?? $request->post);
        if (($denied = $this->deny($data['incident_id'], true)) !== null) {
            return $denied;
        }
        if (!$this->truppInIncident($data['trupp_id'], $data['incident_id'])) {
            return Response::json(['success' => false, 'message' => 'Trupp nicht gefunden.'], 404);
        }
        if ($data['member_id'] !== null
            && !Capsule::table('intra_fire_asu_members')->where('id', $data['member_id'])->where('trupp_id', $data['trupp_id'])->exists()) {
            return Response::json(['success' => false, 'message' => 'Die Person gehört nicht zu diesem Trupp.'], 422);
        }

        // Nach einem Netzabbruch schickt das Tablet dieselbe Meldung erneut
        $existing = Capsule::table('intra_fire_asu_events')->where('client_uuid', $data['client_uuid'])->value('id');
        if ($existing !== null) {
            return Response::json(['success' => true, 'event_id' => (int) $existing, 'duplicate' => true]);
        }

        $eventId = (int) Capsule::table('intra_fire_asu_events')->insertGetId([
            'incident_id' => $data['incident_id'],
            'trupp_id'    => $data['trupp_id'],
            'member_id'   => $data['member_id'],
            'type'        => $data['type'],
            'bar'         => $data['bar'],
            'note'        => $data['note'],
            'client_uuid' => $data['client_uuid'],
            'device'      => $this->deviceName(),
            'created_at'  => Capsule::connection()->raw('NOW()'),
        ]);

        if (isset(self::LOGGED[$data['type']])) {
            $name = (string) Capsule::table('intra_fire_asu_trupps')->where('id', $data['trupp_id'])->value('name');
            $this->log($data['incident_id'], 'asu_' . $data['type'], 'Atemschutz: ' . $name . ' ' . self::LOGGED[$data['type']]);
        }

        return Response::json(['success' => true, 'event_id' => $eventId]);
    }

    /** POST /api/fire/asu/event/void: Fehleingabe zurücknehmen, bleibt sichtbar im Ablauf */
    public function voidEvent(Request $request): Response
    {
        $body       = $request->json() ?? $request->post;
        $incidentId = (int) ($body['incident_id'] ?? 0);
        $eventId    = (int) ($body['event_id'] ?? 0);
        if (($denied = $this->deny($incidentId, true)) !== null) {
            return $denied;
        }
        $updated = Capsule::table('intra_fire_asu_events')
            ->where('id', $eventId)
            ->where('incident_id', $incidentId)
            ->whereNull('voided_at')
            ->update(['voided_at' => Capsule::connection()->raw('NOW()')]);

        return $updated > 0
            ? Response::json(['success' => true])
            : Response::json(['success' => false, 'message' => 'Eintrag nicht gefunden oder schon zurückgenommen.'], 404);
    }

    /**
     * Antwort für fehlende Rechte, sonst null. Reguläre Crews nur am
     * Einsatz ihres angemeldeten Fahrzeugs, QM überall.
     */
    private function deny(int $incidentId, bool $write): ?Response
    {
        if (!Gate::allows('fireIncident.accessModule')) {
            return Response::json(['success' => false, 'message' => 'Kein Zugriff auf fireTab.'], 403);
        }
        $incident = $incidentId > 0 ? FireIncident::find($incidentId) : null;
        if ($incident === null) {
            return Response::json(['success' => false, 'message' => 'Einsatz nicht gefunden.'], 404);
        }
        if (!Gate::allows('fireIncident.manageQm')) {
            $vehicleId = $this->vehicleId();
            $assigned  = $vehicleId !== null && Capsule::table('intra_fire_incident_vehicles')
                ->where('incident_id', $incidentId)
                ->where('vehicle_id', $vehicleId)
                ->exists();
            if (!$assigned) {
                return Response::json(['success' => false, 'message' => 'Ihr Fahrzeug ist diesem Einsatz nicht zugeordnet.'], 403);
            }
        }
        if ($write && $incident->finalized) {
            return Response::json(['success' => false, 'message' => 'Der Einsatz ist abgeschlossen, die Überwachung ist gesperrt.'], 409);
        }

        return null;
    }

    private function truppInIncident(int $truppId, int $incidentId): bool
    {
        return Capsule::table('intra_fire_asu_trupps')->where('id', $truppId)->where('incident_id', $incidentId)->exists();
    }

    private function vehicleId(): ?int
    {
        return isset($_SESSION['einsatz_vehicle_id']) ? (int) $_SESSION['einsatz_vehicle_id'] : null;
    }

    private function deviceName(): ?string
    {
        $name = $_SESSION['einsatz_vehicle_name'] ?? $_SESSION['cirs_username'] ?? null;

        return $name !== null ? mb_substr((string) $name, 0, 100) : null;
    }

    private function log(int $incidentId, string $type, string $text): void
    {
        try {
            FireIncidentLogEntry::create([
                'incident_id'        => $incidentId,
                'created_by'         => isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : 0,
                'vehicle_id'         => $this->vehicleId(),
                'operator_id'        => $_SESSION['einsatz_operator_id'] ?? null,
                'action_type'        => $type,
                'action_description' => $text,
                'created_at'         => Capsule::connection()->raw('NOW()'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('FireAsu: Protokolleintrag fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
