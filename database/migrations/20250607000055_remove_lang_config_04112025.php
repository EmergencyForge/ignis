<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Entfernt den ungenutzten LANG-Eintrag aus intra_config.
 */
class RemoveLangConfig04112025 extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key = 'LANG'");
    }

    public function down(): void
    {
        // Der LANG-Eintrag stammte aus keiner versionierten Migration — sein
        // ursprünglicher Inhalt ist nicht rekonstruierbar. Auf frischen
        // Installationen ist das DELETE ohnehin ein No-op.
    }
}
