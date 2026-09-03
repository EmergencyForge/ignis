<?php

declare(strict_types=1);

namespace App\Vehicles;

use App\Logging\Logger;
use App\Models\Vehicle;
use App\Models\VehicleDefect;
use App\Models\VehicleDefectLog;
use App\Notifications\NotificationManager;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Legt eine Mangelmeldung an: Datensatz, Protokollzeile, bei „nicht
 * einsatzfähig" die Sperre des Fahrzeugs, Benachrichtigung an alle mit
 * Fahrzeugrecht. Zwei Wege führen hierher: das Formular im Drawer
 * (Settings\FahrzeugeController::defektStore) und die JSON-API, über die
 * eNOTF-Besatzungen ohne Konto melden (Api\VehicleDefectsController).
 */
final class DefectReporter
{
    /**
     * @param array{vehicle_id: int, title: string, description: string, category: string, vehicle_operable: int} $data
     *        Ergebnis von Requests\Vehicles\CreateDefectRequest::validate()
     * @return int id des neuen Defekts
     */
    public function report(array $data, int $userId, string $username, bool $isEnotfUser): int
    {
        $defect = VehicleDefect::query()->create([
            'vehicle_id'       => $data['vehicle_id'],
            'title'            => $data['title'],
            'description'      => $data['description'],
            'category'         => $data['category'],
            'vehicle_operable' => $data['vehicle_operable'],
            'reported_by'      => $userId,
        ]);

        $defectId = (int) $defect->id;

        $logDetails = 'Defekt gemeldet: ' . $data['title'];
        if ($isEnotfUser && !$userId) {
            $logDetails .= ' (Gemeldet durch: ' . $username . ')';
        }
        $this->log($defectId, $userId, 'created', $logDetails);

        if (!$data['vehicle_operable']) {
            Vehicle::query()->where('id', $data['vehicle_id'])->update(['active' => 0]);
            $this->log($defectId, $userId, 'vehicle_disabled', 'Fahrzeug als nicht einsatzfähig markiert');
        }

        $this->notifyStaff($defectId, $data['vehicle_id'], $data['title'], (bool) $data['vehicle_operable'], $userId);

        return $defectId;
    }

    public function log(int $defectId, int $userId, string $action, ?string $details = null): void
    {
        VehicleDefectLog::query()->create([
            'defect_id' => $defectId,
            'user_id'   => $userId,
            'action'    => $action,
            'details'   => $details,
        ]);
    }

    /** Benachrichtigt alle User mit vehicles.view oder admin-Permission. */
    private function notifyStaff(int $defectId, int $vehicleId, string $title, bool $operable, int $reporterId): void
    {
        try {
            $vehName = (string) (Capsule::table('intra_fahrzeuge')->where('id', $vehicleId)->value('name') ?: 'Unbekannt');

            $notificationManager = new NotificationManager();

            $users = Capsule::table('intra_users as u')
                ->leftJoin('intra_users_roles as r', 'u.role', '=', 'r.id')
                ->where('u.is_active', 1)
                ->get(['u.id', 'u.full_admin', 'r.permissions'])
                ->map(fn ($row) => (array) $row)
                ->all();

            foreach ($users as $u) {
                if ((int) $u['id'] === $reporterId) continue;

                $hasPerm = (bool) $u['full_admin'];
                if (!$hasPerm) {
                    $perms = json_decode((string) ($u['permissions'] ?? '[]'), true);
                    if (is_array($perms) && (in_array('vehicles.view', $perms, true) || in_array('admin', $perms, true))) {
                        $hasPerm = true;
                    }
                }
                if (!$hasPerm) continue;

                $msg = 'Fahrzeug: ' . $vehName;
                if (!$operable) {
                    $msg .= ' — Nicht einsatzfähig!';
                }
                $notificationManager->create(
                    (int) $u['id'],
                    'system',
                    'Neuer Defekt: ' . $title,
                    $msg,
                    (defined('BASE_PATH') ? (string) BASE_PATH : '/') . 'settings/vehicles/defects/index'
                );
            }
        } catch (\Throwable $e) {
            Logger::error('VehicleDefects: Benachrichtigungsfehler', ['error' => $e->getMessage()]);
        }
    }
}
