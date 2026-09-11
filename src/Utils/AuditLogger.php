<?php

namespace App\Utils;

use Illuminate\Database\Capsule\Manager as Capsule;

class AuditLogger
{
    public function log(int $userId, string $action, ?string $details = NULL, ?string $module = 'System', ?int $global = 0): void
    {
        try {
            Capsule::table('intra_audit_log')->insert([
                'user' => $userId,
                'module' => $module,
                'action' => $action,
                'details' => $details,
                'global' => $global,
            ]);
        } catch (\PDOException $e) {
            \App\Logging\Logger::error('Audit log failed: ' . $e->getMessage());
        }
    }
}
