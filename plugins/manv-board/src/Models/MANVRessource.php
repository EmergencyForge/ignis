<?php

namespace Plugin\ManvBoard\Models;

use PDO;

class MANVRessource
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Erstellt eine neue Ressource
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO intra_manv_ressourcen 
                (manv_lage_id, typ, bezeichnung, rufname, fahrzeugtyp, lokalisation, status, besatzung, notizen)
                VALUES (:manv_lage_id, :typ, :bezeichnung, :rufname, :fahrzeugtyp, :lokalisation, :status, :besatzung, :notizen)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'manv_lage_id' => $data['manv_lage_id'],
            'typ' => $data['typ'] ?? 'fahrzeug',
            'bezeichnung' => $data['bezeichnung'],
            'rufname' => $data['rufname'] ?? null,
            'fahrzeugtyp' => $data['fahrzeugtyp'] ?? null,
            'lokalisation' => $data['lokalisation'] ?? null,
            'status' => $data['status'] ?? 'verfuegbar',
            'besatzung' => $data['besatzung'] ?? null,
            'notizen' => $data['notizen'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Aktualisiert eine Ressource
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'bezeichnung',
            'rufname',
            'fahrzeugtyp',
            'lokalisation',
            'status',
            'besatzung',
            'notizen'
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

        $sql = "UPDATE intra_manv_ressourcen SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Ruft eine Ressource ab
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM intra_manv_ressourcen WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Ruft alle Ressourcen einer MANV-Lage ab
     */
    public function getByLage(int $lageId, ?string $typ = null): array
    {
        $sql = "SELECT * FROM intra_manv_ressourcen WHERE manv_lage_id = ?";
        $params = [$lageId];

        if ($typ) {
            $sql .= " AND typ = ?";
            $params[] = $typ;
        }

        $sql .= " ORDER BY typ, bezeichnung";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Löscht eine Ressource
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM intra_manv_ressourcen WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Ruft verfügbare Fahrzeuge ab
     */
    public function getAvailableVehicles(int $lageId): array
    {
        $sql = "SELECT * FROM intra_manv_ressourcen 
                WHERE manv_lage_id = ? AND typ = 'fahrzeug' AND status = 'verfuegbar'
                ORDER BY fahrzeugtyp, rufname";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$lageId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
