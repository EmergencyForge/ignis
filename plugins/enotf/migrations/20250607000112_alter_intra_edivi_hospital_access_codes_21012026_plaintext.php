<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Umstellung der Krankenhaus-Zugangs-Codes von gehashter auf Klartext-
 * Speicherung: bestehende (gehashte) Codes werden geleert, da sie sich
 * nicht zurückrechnen lassen — die Codes müssen danach neu generiert
 * werden.
 */
class AlterIntraEdiviHospitalAccessCodes21012026Plaintext extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_edivi_hospital_access_codes')->truncate();
    }

    public function down(): void
    {
        // Die gelöschten Hash-Codes lassen sich nicht wiederherstellen — no-op.
    }
}
