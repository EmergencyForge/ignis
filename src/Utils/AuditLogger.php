<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Schreibt ins Prüfprotokoll (`intra_audit_log`).
 *
 * `action`, `details` und `module` sind für Menschen: so steht es in der
 * Audit-Ansicht. `context` ist für Maschinen — die Kennungen, auf die sich
 * die Meldung bezieht, als JSON.
 *
 * Der Kontext ist nachgerüstet, und der Grund steht in
 * {@see \App\Support\Activity}: ohne ihn muss eine Auswertung `ID: 12` aus
 * dem Fließtext klauben und aufpassen, dass sie nicht 123 trifft. Neue
 * Aufrufe geben ihn mit; alte Zeilen bleiben lesbar, weil die Auswertung
 * weiterhin auf den Text zurückfällt.
 */
class AuditLogger
{
    /**
     * @param array<string,scalar|null> $context Kennungen zum Eintrag,
     *                                           z. B. `['vehicle_id' => 12]`
     */
    public function log(
        int $userId,
        string $action,
        ?string $details = null,
        ?string $module = 'System',
        ?int $global = 0,
        array $context = [],
    ): void {
        try {
            Capsule::table('intra_audit_log')->insert([
                'user'    => $userId,
                'module'  => $module,
                'action'  => $action,
                'details' => $details,
                'context' => $context === []
                    ? null
                    : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'global'  => $global,
            ]);
        } catch (\PDOException $e) {
            \App\Logging\Logger::error('Audit log failed: ' . $e->getMessage());
        }
    }
}
