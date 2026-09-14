<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FabricaIdentities extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_fabrica_identities')
            ->addColumn('issuer', 'string', ['limit' => 255, 'collation' => 'utf8mb4_bin'])
            ->addColumn('subject', 'string', ['limit' => 36, 'collation' => 'utf8mb4_bin'])
            ->addColumn('user_id', 'integer', ['signed' => true])
            ->addIndex(['issuer', 'subject'], ['unique' => true])
            ->addIndex(['issuer', 'user_id'], ['unique' => true])
            ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
