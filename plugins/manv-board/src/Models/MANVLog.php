<?php

namespace Plugin\ManvBoard\Models;

use PDO;

class MANVLog
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Erstellt einen Log-Eintrag
     */
    public function log(int $lageId, string $aktion, ?string $beschreibung = null, ?int $userId = null, ?string $userName = null, ?string $referenzTyp = null, ?int $referenzId = null): int
    {
        $sql = "INSERT INTO intra_manv_log 
                (manv_lage_id, aktion, beschreibung, benutzer_id, benutzer_name, referenz_typ, referenz_id)
                VALUES (:manv_lage_id, :aktion, :beschreibung, :benutzer_id, :benutzer_name, :referenz_typ, :referenz_id)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'manv_lage_id' => $lageId,
            'aktion' => $aktion,
            'beschreibung' => $beschreibung,
            'benutzer_id' => $userId,
            'benutzer_name' => $userName,
            'referenz_typ' => $referenzTyp,
            'referenz_id' => $referenzId
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Ruft alle Log-Einträge einer MANV-Lage ab
     */
    public function getByLage(int $lageId, int $limit = 100): array
    {
        $sql = "SELECT * FROM intra_manv_log 
                WHERE manv_lage_id = ? 
                ORDER BY timestamp DESC 
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$lageId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ruft Log-Einträge für eine bestimmte Referenz ab
     */
    public function getByReference(int $lageId, string $referenzTyp, int $referenzId): array
    {
        $sql = "SELECT * FROM intra_manv_log 
                WHERE manv_lage_id = ? AND referenz_typ = ? AND referenz_id = ?
                ORDER BY timestamp DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$lageId, $referenzTyp, $referenzId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
