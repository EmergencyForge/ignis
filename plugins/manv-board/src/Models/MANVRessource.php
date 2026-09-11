<?php

namespace Plugin\ManvBoard\Models;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Repository für `intra_manv_ressourcen` — Ressourcen (Fahrzeuge,
 * Einheiten) einer MANV-Lage.
 *
 * Läuft über die Eloquent-Capsule (Query Builder); die Rückgabeformate
 * (Assoc-Arrays bzw. null) bleiben für alle Konsumenten unverändert.
 */
class MANVRessource
{
    /**
     * Erstellt eine neue Ressource
     */
    public function create(array $data): int
    {
        return (int) Capsule::table('intra_manv_ressourcen')->insertGetId([
            'manv_lage_id' => $data['manv_lage_id'],
            'typ' => $data['typ'] ?? 'fahrzeug',
            'bezeichnung' => $data['bezeichnung'],
            'rufname' => $data['rufname'] ?? null,
            'fahrzeugtyp' => $data['fahrzeugtyp'] ?? null,
            'lokalisation' => $data['lokalisation'] ?? null,
            'status' => $data['status'] ?? 'verfuegbar',
            'besatzung' => $data['besatzung'] ?? null,
            'notizen' => $data['notizen'] ?? null,
        ]);
    }

    /**
     * Aktualisiert eine Ressource
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = [
            'bezeichnung',
            'rufname',
            'fahrzeugtyp',
            'lokalisation',
            'status',
            'besatzung',
            'notizen'
        ];

        $fields = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        Capsule::table('intra_manv_ressourcen')->where('id', $id)->update($fields);

        return true;
    }

    /**
     * Ruft eine Ressource ab
     */
    public function getById(int $id): ?array
    {
        $row = Capsule::table('intra_manv_ressourcen')->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    /**
     * Ruft alle Ressourcen einer MANV-Lage ab
     */
    public function getByLage(int $lageId, ?string $typ = null): array
    {
        $query = Capsule::table('intra_manv_ressourcen')->where('manv_lage_id', $lageId);

        if ($typ) {
            $query->where('typ', $typ);
        }

        return $query
            ->orderBy('typ')
            ->orderBy('bezeichnung')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Löscht eine Ressource
     */
    public function delete(int $id): bool
    {
        Capsule::table('intra_manv_ressourcen')->where('id', $id)->delete();
        return true;
    }

    /**
     * Ruft verfügbare Fahrzeuge ab
     */
    public function getAvailableVehicles(int $lageId): array
    {
        return Capsule::table('intra_manv_ressourcen')
            ->where('manv_lage_id', $lageId)
            ->where('typ', 'fahrzeug')
            ->where('status', 'verfuegbar')
            ->orderBy('fahrzeugtyp')
            ->orderBy('rufname')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
