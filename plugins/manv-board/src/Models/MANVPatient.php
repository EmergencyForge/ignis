<?php

namespace Plugin\ManvBoard\Models;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Repository für `intra_manv_patienten` — Patienten einer MANV-Lage.
 *
 * Läuft über die Eloquent-Capsule (Query Builder); die Rückgabeformate
 * (Assoc-Arrays bzw. null) bleiben für alle Konsumenten unverändert.
 */
class MANVPatient
{
    /**
     * Erstellt einen neuen Patienten
     */
    public function create(array $data): int
    {
        return (int) Capsule::table('intra_manv_patienten')->insertGetId([
            'manv_lage_id' => $data['manv_lage_id'],
            'patienten_nummer' => $data['patienten_nummer'],
            'name' => $data['name'] ?? null,
            'vorname' => $data['vorname'] ?? null,
            'geburtsdatum' => $data['geburtsdatum'] ?? null,
            'geschlecht' => $data['geschlecht'] ?? 'unbekannt',
            'sichtungskategorie' => $data['sichtungskategorie'] ?? null,
            'sichtungskategorie_zeit' => $data['sichtungskategorie_zeit'] ?? ($data['sichtungskategorie'] ? date('Y-m-d H:i:s') : null),
            'sichtungskategorie_geaendert_von' => $data['sichtungskategorie_geaendert_von'] ?? null,
            'transportmittel' => $data['transportmittel'] ?? null,
            'transportmittel_rufname' => $data['transportmittel_rufname'] ?? null,
            'fahrzeug_lokalisation' => $data['fahrzeug_lokalisation'] ?? null,
            'transportziel' => $data['transportziel'] ?? null,
            'verletzungen' => $data['verletzungen'] ?? null,
            'massnahmen' => $data['massnahmen'] ?? null,
            'notizen' => $data['notizen'] ?? null,
            'erstellt_von' => $data['erstellt_von'] ?? null,
        ]);
    }

    /**
     * Aktualisiert einen Patienten
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = [
            'name',
            'vorname',
            'geburtsdatum',
            'geschlecht',
            'sichtungskategorie',
            'sichtungskategorie_zeit',
            'sichtungskategorie_geaendert_von',
            'transportmittel',
            'transportmittel_rufname',
            'fahrzeug_lokalisation',
            'transportziel',
            'transport_abfahrt',
            'transport_ankunft',
            'verletzungen',
            'massnahmen',
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

        Capsule::table('intra_manv_patienten')->where('id', $id)->update($fields);

        return true;
    }

    /**
     * Aktualisiert die Sichtungskategorie
     */
    public function updateSichtung(int $id, string $kategorie, ?int $userId = null): bool
    {
        Capsule::table('intra_manv_patienten')
            ->where('id', $id)
            ->update([
                'sichtungskategorie' => $kategorie,
                'sichtungskategorie_zeit' => date('Y-m-d H:i:s'),
                'sichtungskategorie_geaendert_von' => $userId,
            ]);

        return true;
    }

    /**
     * Ruft einen Patienten ab
     */
    public function getById(int $id): ?array
    {
        $row = Capsule::table('intra_manv_patienten')->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    /**
     * Ruft alle Patienten einer MANV-Lage ab
     */
    public function getByLage(int $lageId, ?string $kategorie = null, ?\App\Support\ListQuery $list = null): array
    {
        $query = Capsule::table('intra_manv_patienten')
            ->where('manv_lage_id', $lageId)
            ->whereNull('transport_abfahrt');

        if ($kategorie) {
            $query->where('sichtungskategorie', $kategorie);
        }

        // Mit ListQuery (Board) sortiert die gewählte Spalte, danach die
        // Patientennummer; sonst die Reihenfolge der Sichtung.
        if ($list !== null) {
            $query->orderBy($list->column(), $list->dir)->orderBy('patienten_nummer', 'asc');
        } else {
            $query->orderBy('sichtungskategorie_zeit', 'desc')->orderBy('patienten_nummer', 'asc');
        }

        return $query
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Löscht einen Patienten
     */
    public function delete(int $id): bool
    {
        Capsule::table('intra_manv_patienten')->where('id', $id)->delete();
        return true;
    }

    /**
     * Generiert die nächste Patientennummer
     */
    public function generateNextPatientNumber(int $lageId): string
    {
        $count = Capsule::table('intra_manv_patienten')
            ->where('manv_lage_id', $lageId)
            ->count() + 1;

        return sprintf('MANV-%03d', $count);
    }

    /**
     * Sucht Patienten
     */
    public function search(int $lageId, string $searchTerm): array
    {
        $searchPattern = '%' . ignis_like_prefix($searchTerm) . '%';

        return Capsule::table('intra_manv_patienten')
            ->where('manv_lage_id', $lageId)
            ->where(function ($query) use ($searchPattern) {
                $query->where('patienten_nummer', 'LIKE', $searchPattern)
                    ->orWhere('name', 'LIKE', $searchPattern)
                    ->orWhere('vorname', 'LIKE', $searchPattern)
                    ->orWhere('notizen', 'LIKE', $searchPattern);
            })
            ->orderBy('sichtungskategorie_zeit', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
