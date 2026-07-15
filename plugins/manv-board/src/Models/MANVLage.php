<?php

namespace Plugin\ManvBoard\Models;

use PDO;
use PDOException;

class MANVLage
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Erstellt eine neue MANV-Lage
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO intra_manv_lagen 
                (einsatznummer, einsatzort, einsatzanlass, lna_name, lna_mitarbeiter_id, 
                 orgl_name, orgl_mitarbeiter_id, einsatzbeginn, erstellt_von, notizen)
                VALUES (:einsatznummer, :einsatzort, :einsatzanlass, :lna_name, :lna_mitarbeiter_id,
                        :orgl_name, :orgl_mitarbeiter_id, :einsatzbeginn, :erstellt_von, :notizen)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'einsatznummer' => $data['einsatznummer'],
            'einsatzort' => $data['einsatzort'],
            'einsatzanlass' => $data['einsatzanlass'] ?? null,
            'lna_name' => $data['lna_name'] ?? null,
            'lna_mitarbeiter_id' => $data['lna_mitarbeiter_id'] ?? null,
            'orgl_name' => $data['orgl_name'] ?? null,
            'orgl_mitarbeiter_id' => $data['orgl_mitarbeiter_id'] ?? null,
            'einsatzbeginn' => $data['einsatzbeginn'] ?? date('Y-m-d H:i:s'),
            'erstellt_von' => $data['erstellt_von'] ?? null,
            'notizen' => $data['notizen'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Aktualisiert eine MANV-Lage
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if (in_array($key, [
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
            ])) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE intra_manv_lagen SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Ruft eine MANV-Lage ab
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_manv_lagen WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Ruft alle MANV-Lagen ab
     */
    public function getAll(?string $status = null): array
    {
        $sql = "SELECT * FROM intra_manv_lagen";
        if ($status) {
            $sql .= " WHERE status = :status";
        }
        $sql .= " ORDER BY einsatzbeginn DESC";

        $stmt = $this->pdo->prepare($sql);
        if ($status) {
            $stmt->execute(['status' => $status]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Löscht eine MANV-Lage
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM intra_manv_lagen WHERE id = ?");
        return $stmt->execute([$id]);
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

        $sql = "SELECT 
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
                FROM intra_manv_patienten
                WHERE manv_lage_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$lageId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $stats['total_patienten'] = (int)$result['total'];
            $stats['sk1'] = (int)$result['sk1'];
            $stats['sk2'] = (int)$result['sk2'];
            $stats['sk3'] = (int)$result['sk3'];
            $stats['sk4'] = (int)$result['sk4'];
            $stats['sk5'] = (int)$result['sk5'];
            $stats['sk6'] = (int)$result['sk6'];
            $stats['tot'] = (int)$result['tot'];
            $stats['transportiert'] = (int)$result['transportiert'];
            $stats['wartend'] = (int)$result['wartend'];
        }

        return $stats;
    }
}
