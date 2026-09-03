<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Klinik-Freigabecodes: kurzlebige sechsstellige Codes, mit denen die
 * aufnehmende Klinik ein Protokoll (`enr`) einsehen kann. Codes sind
 * eindeutig und laufen über `expires_at` ab.
 */
class CreateIntraEdiviKlinikcodes14122025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_edivi_klinikcodes')) {
            return;
        }

        $this->table('intra_edivi_klinikcodes', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('enr',        'string',   ['limit' => 255, 'null' => false])
            ->addColumn('code',       'string',   ['limit' => 6, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addIndex(['code'],       ['unique' => true, 'name' => 'code'])
            ->addIndex(['enr'],        ['name' => 'enr'])
            ->addIndex(['expires_at'], ['name' => 'expires_at'])
            ->create();
    }
}
