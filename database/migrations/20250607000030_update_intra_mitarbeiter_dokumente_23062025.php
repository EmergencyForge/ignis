<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ändert intra_mitarbeiter_dokumente.ausstellerid von int(11) NOT NULL zu
 * VARCHAR(255) NULL — Aussteller können seither auch als freie Kennung
 * (z. B. Discord-ID) hinterlegt werden.
 */
class UpdateIntraMitarbeiterDokumente23062025 extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_mitarbeiter_dokumente')
            ->changeColumn('ausstellerid', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->update();
    }

    public function down(): void
    {
        // Ursprüngliche Definition aus create_intra_mitarbeiter_dokumente_07062025:
        // int(11) NOT NULL
        $this->table('intra_mitarbeiter_dokumente')
            ->changeColumn('ausstellerid', 'integer', ['null' => false])
            ->update();
    }
}
