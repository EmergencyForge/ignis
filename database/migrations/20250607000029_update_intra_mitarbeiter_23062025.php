<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Lockert intra_mitarbeiter.charakterid: die Charakter-ID ist ab jetzt
 * optional (NULL erlaubt statt NOT NULL).
 */
class UpdateIntraMitarbeiter23062025 extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_mitarbeiter')
            ->changeColumn('charakterid', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->update();
    }

    public function down(): void
    {
        // Ursprüngliche Definition aus create_intra_mitarbeiter_07062025:
        // varchar(255) NOT NULL
        $this->table('intra_mitarbeiter')
            ->changeColumn('charakterid', 'string', ['limit' => 255, 'null' => false])
            ->update();
    }
}
