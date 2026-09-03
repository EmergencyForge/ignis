<?php

namespace Plugin\ManvBoard\Models;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Repository für `intra_manv_lagen` — MANV-Lagen (Massenanfall von
 * Verletzten) inklusive Patienten-Statistik.
 *
 * Läuft über die Eloquent-Capsule (Query Builder); die Rückgabeformate
 * (Assoc-Arrays bzw. null) bleiben für alle Konsumenten unverändert.
 */
class MANVLage
{
    /**
     * Erstellt eine neue MANV-Lage
     */
    public function create(array $data): int
    {
        return (int) Capsule::table('intra_manv_lagen')->insertGetId([
            'einsatznummer' => $data['einsatznummer'],
            'einsatzort' => $data['einsatzort'],
            'einsatzanlass' => $data['einsatzanlass'] ?? null,
            'lna_name' => $data['lna_name'] ?? null,
            'lna_mitarbeiter_id' => $data['lna_mitarbeiter_id'] ?? null,
            'orgl_name' => $data['orgl_name'] ?? null,
            'orgl_mitarbeiter_id' => $data['orgl_mitarbeiter_id'] ?? null,
            'einsatzbeginn' => $data['einsatzbeginn'] ?? date('Y-m-d H:i:s'),
            'erstellt_von' => $data['erstellt_von'] ?? null,
            'notizen' => $data['notizen'] ?? null,
        ]);
    }

    /**
     * Aktualisiert eine MANV-Lage
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = [
            'einsatznummer',
            'einsatzort',
            'einsatzanlass',
            'lna_name',
            'lna_mitarbeiter_id',
            'orgl_name',
            'orgl_mitarbeiter_id',
            'status',
            'einsatzbeginn',
            'einsatzende',
            'notizen',
            'geaendert_von'
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

        Capsule::table('intra_manv_lagen')->where('id', $id)->update($fields);

        return true;
    }

    /**
     * Ruft eine MANV-Lage ab
     */
    public function getById(int $id): ?array
    {
        $row = Capsule::table('intra_manv_lagen')->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    /**
     * Ruft alle MANV-Lagen ab
     */
    public function getAll(?string $status = null): array
    {
        $query = Capsule::table('intra_manv_lagen');
        if ($status) {
            $query->where('status', $status);
        }

        return $query
            ->orderBy('einsatzbeginn', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Löscht eine MANV-Lage
     */
    public function delete(int $id): bool
    {
        Capsule::table('intra_manv_lagen')->where('id', $id)->delete();
        return true;
    }

    /**
     * Ruft Statistiken für eine MANV-Lage ab
     */
    public function getStatistics(int $lageId): array
    {
        $stats = [
            'total_patienten' => 0,
            'sk1' => 0,
            'sk2' => 0,
            'sk3' => 0,
            'sk4' => 0,
            'sk5' => 0,
            'sk6' => 0,
            'tot' => 0,
            'transportiert' => 0,
            'wartend' => 0
        ];

        $result = Capsule::table('intra_manv_patienten')
            ->where('manv_lage_id', $lageId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN sichtungskategorie = 'SK1' THEN 1 ELSE 0 END) as sk1,
                SUM(CASE WHEN sichtungskategorie = 'SK2' THEN 1 ELSE 0 END) as sk2,
                SUM(CASE WHEN sichtungskategorie = 'SK3' THEN 1 ELSE 0 END) as sk3,
                SUM(CASE WHEN sichtungskategorie = 'SK4' THEN 1 ELSE 0 END) as sk4,
                SUM(CASE WHEN sichtungskategorie = 'SK5' THEN 1 ELSE 0 END) as sk5,
                SUM(CASE WHEN sichtungskategorie = 'SK6' THEN 1 ELSE 0 END) as sk6,
                SUM(CASE WHEN sichtungskategorie = 'tot' THEN 1 ELSE 0 END) as tot,
                SUM(CASE WHEN transport_abfahrt IS NOT NULL THEN 1 ELSE 0 END) as transportiert,
                SUM(CASE WHEN transport_abfahrt IS NULL AND sichtungskategorie IS NOT NULL THEN 1 ELSE 0 END) as wartend
            ")
            ->first();

        if ($result) {
            $stats['total_patienten'] = (int) $result->total;
            $stats['sk1'] = (int) $result->sk1;
            $stats['sk2'] = (int) $result->sk2;
            $stats['sk3'] = (int) $result->sk3;
            $stats['sk4'] = (int) $result->sk4;
            $stats['sk5'] = (int) $result->sk5;
            $stats['sk6'] = (int) $result->sk6;
            $stats['tot'] = (int) $result->tot;
            $stats['transportiert'] = (int) $result->transportiert;
            $stats['wartend'] = (int) $result->wartend;
        }

        return $stats;
    }
}
