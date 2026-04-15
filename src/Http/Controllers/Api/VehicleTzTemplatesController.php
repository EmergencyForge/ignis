<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\Permissions;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Utils\AuditLogger;
use PDO;
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
    public function __construct(
        private readonly PDO $pdo,
    ) {}

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
        if (!Permissions::check(['admin', 'vehicles.manage'])) {
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
        if (!Permissions::check(['admin', 'vehicles.view'])) {
            return Response::json(['success' => false, 'message' => 'Keine Berechtigung']);
        }

        try {
            $stmt = $this->pdo->query("
                SELECT t.*, u.username AS created_by_name
                FROM intra_fahrzeuge_tz_templates t
                LEFT JOIN intra_users u ON t.created_by = u.id
                ORDER BY t.name ASC
            ");
            return Response::json(['success' => true, 'templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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
            $existStmt = $this->pdo->prepare("SELECT id FROM intra_fahrzeuge_tz_templates WHERE name = ?");
            $existStmt->execute([$name]);
            $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $this->pdo->prepare("
                    UPDATE intra_fahrzeuge_tz_templates
                    SET grundzeichen = :grundzeichen, organisation = :organisation, fachaufgabe = :fachaufgabe,
                        einheit = :einheit, symbol = :symbol, typ = :typ, text = :text
                    WHERE id = :id
                ");
                $stmt->execute(array_merge($fields, [':id' => $existing['id']]));
                $resultId      = (int) $existing['id'];
                $resultMessage = "Vorlage '{$name}' aktualisiert";
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO intra_fahrzeuge_tz_templates
                    (name, grundzeichen, organisation, fachaufgabe, einheit, symbol, typ, text, created_by)
                    VALUES (:name, :grundzeichen, :organisation, :fachaufgabe, :einheit, :symbol, :typ, :text, :created_by)
                ");
                $stmt->execute(array_merge(
                    [':name' => $name],
                    $fields,
                    [':created_by' => $_SESSION['userid'] ?? null]
                ));
                $resultId      = (int) $this->pdo->lastInsertId();
                $resultMessage = "Vorlage '{$name}' gespeichert";
            }

            (new AuditLogger($this->pdo))->log(
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
            $nameStmt = $this->pdo->prepare("SELECT name FROM intra_fahrzeuge_tz_templates WHERE id = ?");
            $nameStmt->execute([$id]);
            $tpl = $nameStmt->fetch(PDO::FETCH_ASSOC);

            $this->pdo->prepare("DELETE FROM intra_fahrzeuge_tz_templates WHERE id = ?")->execute([$id]);

            (new AuditLogger($this->pdo))->log(
                $_SESSION['userid'] ?? 0,
                "TZ-Vorlage gelöscht: " . ($tpl['name'] ?? $id),
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
            $tplStmt = $this->pdo->prepare("SELECT * FROM intra_fahrzeuge_tz_templates WHERE id = ?");
            $tplStmt->execute([$templateId]);
            $tpl = $tplStmt->fetch(PDO::FETCH_ASSOC);

            if (!$tpl) {
                return Response::json(['success' => false, 'message' => 'Vorlage nicht gefunden']);
            }

            // tz_name bleibt individuell pro Fahrzeug — wird nicht überschrieben
            $stmt = $this->pdo->prepare("
                UPDATE intra_fahrzeuge
                SET grundzeichen = :grundzeichen, organisation = :organisation, fachaufgabe = :fachaufgabe,
                    einheit = :einheit, symbol = :symbol, typ = :typ, text = :text
                WHERE veh_type = :veh_type
            ");
            $stmt->execute([
                ':grundzeichen' => $tpl['grundzeichen'],
                ':organisation' => $tpl['organisation'],
                ':fachaufgabe'  => $tpl['fachaufgabe'],
                ':einheit'      => $tpl['einheit'],
                ':symbol'       => $tpl['symbol'],
                ':typ'          => $tpl['typ'],
                ':text'         => $tpl['text'],
                ':veh_type'     => $vehType,
            ]);

            $affected = $stmt->rowCount();

            (new AuditLogger($this->pdo))->log(
                $_SESSION['userid'] ?? 0,
                "TZ-Vorlage '{$tpl['name']}' auf {$affected} Fahrzeuge vom Typ '{$vehType}' angewendet",
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
