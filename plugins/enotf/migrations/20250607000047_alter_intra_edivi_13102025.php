<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Getrennte Sichtbarkeits-Flags: `hidden_user` blendet ein Protokoll nur für
 * den einreichenden Nutzer aus, unabhängig vom administrativen `hidden`.
 */
class AlterIntraEdivi13102025 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_edivi')
            ->addColumn('hidden_user', 'boolean', ['null' => false, 'default' => 0, 'after' => 'hidden'])
            ->update();
    }
}
