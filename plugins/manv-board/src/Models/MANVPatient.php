<?php

namespace Plugin\ManvBoard\Models;

use PDO;
use PDOException;

class MANVPatient
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Erstellt einen neuen Patienten
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO intra_manv_patienten 
                (manv_lage_id, patienten_nummer, name, vorname, geburtsdatum, geschlecht, 
                 sichtungskategorie, sichtungskategorie_zeit, sichtungskategorie_geaendert_von,
                 transportmittel, transportmittel_rufname, fahrzeug_lokalisation, transportziel,
                 verletzungen, massnahmen, notizen, erstellt_von)
                VALUES (:manv_lage_id, :patienten_nummer, :name, :vorname, :geburtsdatum, :geschlecht,
                        :sichtungskategorie, :sichtungskategorie_zeit, :sichtungskategorie_geaendert_von,
                        :transportmittel, :transportmittel_rufname, :fahrzeug_lokalisation, :transportziel,
                        :verletzungen, :massnahmen, :notizen, :erstellt_von)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
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
            'erstellt_von' => $data['erstellt_von'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Aktualisiert einen Patienten
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

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

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE intra_manv_patienten SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Aktualisiert die Sichtungskategorie
     */
    public function updateSichtung(int $id, string $kategorie, ?int $userId = null): bool
    {
        $sql = "UPDATE intra_manv_patienten 
                SET sichtungskategorie = :kategorie, 
                    sichtungskategorie_zeit = :zeit,
                    sichtungskategorie_geaendert_von = :user_id
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'kategorie' => $kategorie,
            'zeit' => date('Y-m-d H:i:s'),
            'user_id' => $userId
        ]);
    }

    /**
     * Ruft einen Patienten ab
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_manv_patienten WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Ruft alle Patienten einer MANV-Lage ab
     */
    public function getByLage(int $lageId, ?string $kategorie = null): array
    {
        $sql = "SELECT * FROM intra_manv_patienten WHERE manv_lage_id = ? AND transport_abfahrt IS NULL";
        $params = [$lageId];

        if ($kategorie) {
            $sql .= " AND sichtungskategorie = ?";
            $params[] = $kategorie;
        }

        $sql .= " ORDER BY sichtungskategorie_zeit DESC, patienten_nummer ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Löscht einen Patienten
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM intra_manv_patienten WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Generiert die nächste Patientennummer
     */
    public function generateNextPatientNumber(int $lageId): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count FROM intra_manv_patienten WHERE manv_lage_id = ?"
        );
        $stmt->execute([$lageId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$result['count'] + 1;

        return sprintf('MANV-%03d', $count);
    }

    /**
     * Sucht Patienten
     */
    public function search(int $lageId, string $searchTerm): array
    {
        $sql = "SELECT * FROM intra_manv_patienten 
                WHERE manv_lage_id = ? 
                AND (patienten_nummer LIKE ? OR name LIKE ? OR vorname LIKE ? OR notizen LIKE ?)
                ORDER BY sichtungskategorie_zeit DESC";

        $stmt = $this->pdo->prepare($sql);
        $searchPattern = "%$searchTerm%";
        $stmt->execute([$lageId, $searchPattern, $searchPattern, $searchPattern, $searchPattern]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
