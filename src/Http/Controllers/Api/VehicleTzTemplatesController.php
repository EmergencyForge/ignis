<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Gate;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Models\Vehicle;
use App\Models\VehicleTzTemplate;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * Taktische-Zeichen-Vorlagen für Fahrzeuge (Fire-Tactical-Map-Symbole).
 *
 * Der Admin kann wiederverwendbare Vorlagen anlegen und dann auf alle
 * Fahrzeuge eines Typs anwenden — Kommando- oder Einsatzleitwagen
 * bekommen z.B. automatisch ihr spezifisches Symbol.
 */
final class VehicleTzTemplatesController
{
    /**
     * GET|POST /api/vehicles/tz-templates?action=list|save|delete|apply_to_type
     */
    public function handle(Request $request): Response
    {
        $action = (string) ($request->query['action'] ?? $request->post['action'] ?? '');

        if ($action === 'list') {
            return $this->list();
        }

        // Alle Schreib-Aktionen erfordern vehicles.manage
        if (Gate::denies('vehicle.manage')) {
            return Response::json(['success' => false, 'message' => 'Keine Berechtigung']);
        }

        if (strtoupper($request->method) !== 'POST') {
            return Response::json(['success' => false, 'message' => 'Unbekannte Aktion']);
        }

        return match ($action) {
            'save'          => $this->save($request),
            'delete'        => $this->delete($request),
            'apply_to_type' => $this->applyToType($request),
            default         => Response::json(['success' => false, 'message' => 'Unbekannte Aktion']),
        };
    }

    private function list(): Response
    {
        if (Gate::denies('vehicle.view')) {
            return Response::json(['success' => false, 'message' => 'Keine Berechtigung']);
        }

        try {
            $templates = Capsule::table('intra_fahrzeuge_tz_templates as t')
                ->leftJoin('intra_users as u', 't.created_by', '=', 'u.id')
                ->select('t.*', 'u.username as created_by_name')
                ->orderBy('t.name')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            return Response::json(['success' => true, 'templates' => $templates]);
        } catch (PDOException $e) {
            Logger::error('TzTemplates: list Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function save(Request $request): Response
    {
        $name = trim((string) ($request->post['name'] ?? ''));
        if ($name === '') {
            return Response::json(['success' => false, 'message' => 'Name ist erforderlich']);
        }

        $fields = [
            'grundzeichen' => trim((string) ($request->post['grundzeichen'] ?? '')) ?: null,
            'organisation' => trim((string) ($request->post['organisation'] ?? '')) ?: null,
            'fachaufgabe'  => trim((string) ($request->post['fachaufgabe']  ?? '')) ?: null,
            'einheit'      => trim((string) ($request->post['einheit']      ?? '')) ?: null,
            'symbol'       => trim((string) ($request->post['symbol']       ?? '')) ?: null,
            'typ'          => trim((string) ($request->post['typ']          ?? '')) ?: null,
            'text'         => trim((string) ($request->post['text']         ?? '')) ?: null,
        ];

        try {
            $existing = VehicleTzTemplate::query()->where('name', $name)->first();

            if ($existing) {
                $existing->update($fields);
                $resultId      = (int) $existing->id;
                $resultMessage = "Vorlage '{$name}' aktualisiert";
            } else {
                $template = VehicleTzTemplate::create(array_merge(
                    ['name' => $name],
                    $fields,
                    ['created_by' => $_SESSION['userid'] ?? null]
                ));
                $resultId      = (int) $template->id;
                $resultMessage = "Vorlage '{$name}' gespeichert";
            }

            (new AuditLogger())->log(
                $_SESSION['userid'] ?? 0,
                "TZ-Vorlage gespeichert: {$name}",
                null,
                'Fahrzeuge',
                1
            );

            return Response::json([
                'success' => true,
                'message' => $resultMessage,
                'id'      => $resultId,
            ]);
        } catch (PDOException $e) {
            Logger::error('TzTemplates: save Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
    }

    private function delete(Request $request): Response
    {
        $id = (int) ($request->post['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['success' => false, 'message' => 'Ungültige ID']);
        }

        try {
            $tpl = VehicleTzTemplate::find($id);

            VehicleTzTemplate::query()->where('id', $id)->delete();

            (new AuditLogger())->log(
                $_SESSION['userid'] ?? 0,
                "TZ-Vorlage gelöscht: " . ($tpl->name ?? $id),
                null,
                'Fahrzeuge',
                1
            );

            return Response::json(['success' => true, 'message' => 'Vorlage gelöscht']);
        } catch (PDOException $e) {
            Logger::error('TzTemplates: delete Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function applyToType(Request $request): Response
    {
        $templateId = (int) ($request->post['template_id'] ?? 0);
        $vehType    = trim((string) ($request->post['veh_type'] ?? ''));

        if ($templateId <= 0 || $vehType === '') {
            return Response::json([
                'success' => false,
                'message' => 'Template-ID und Fahrzeugtyp erforderlich',
            ]);
        }

        try {
            $tpl = VehicleTzTemplate::find($templateId);

            if (!$tpl) {
                return Response::json(['success' => false, 'message' => 'Vorlage nicht gefunden']);
            }

            // tz_name bleibt individuell pro Fahrzeug — wird nicht überschrieben
            $affected = Vehicle::query()
                ->where('veh_type', $vehType)
                ->update([
                    'grundzeichen' => $tpl->grundzeichen,
                    'organisation' => $tpl->organisation,
                    'fachaufgabe'  => $tpl->fachaufgabe,
                    'einheit'      => $tpl->einheit,
                    'symbol'       => $tpl->symbol,
                    'typ'          => $tpl->typ,
                    'text'         => $tpl->text,
                ]);

            (new AuditLogger())->log(
                $_SESSION['userid'] ?? 0,
                "TZ-Vorlage '{$tpl->name}' auf {$affected} Fahrzeuge vom Typ '{$vehType}' angewendet",
                null,
                'Fahrzeuge',
                1
            );

            return Response::json([
                'success'  => true,
                'message'  => "Vorlage auf {$affected} Fahrzeug(e) angewendet",
                'affected' => $affected,
            ]);
        } catch (PDOException $e) {
            Logger::error('TzTemplates: apply_to_type Fehler', ['error' => $e->getMessage()]);
            return Response::json(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
    }
}
