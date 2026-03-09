<?php

namespace App\Utils;

use PDO;
use PDOException;

require __DIR__ . '/../../assets/config/database.php';

class AuditLogger
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function log(int $userId, string $action, ?string $details = NULL, ?string $module = 'System', ?int $global = 0): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO intra_audit_log (user, module, action, details, global)
                VALUES (:userid, :module, :action, :details, :global)
            ");

            $stmt->execute([
                'userid' => $userId,
                'module' => $module,
                'action' => $action,
                'details' => $details,
                'global' => $global,
            ]);
        } catch (PDOException $e) {
            \App\Logging\Logger::error('Audit log failed: ' . $e->getMessage());
        }
    }
}
