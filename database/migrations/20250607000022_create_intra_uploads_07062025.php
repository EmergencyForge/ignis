<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Upload-Log: Metadaten zu hochgeladenen Dateien (Name, Typ, Größe,
 * Uploader, Zeitpunkt).
 */
class CreateIntraUploads07062025 extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('intra_uploads')) {
            return;
        }

        $this->table('intra_uploads', [
            'signed'    => true,
            'engine'    => 'InnoDB',
            'encoding'  => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('file_name',   'string',   ['limit' => 255, 'null' => false])
            ->addColumn('file_type',   'string',   ['limit' => 255, 'null' => false])
            ->addColumn('file_size',   'string',   ['limit' => 255, 'null' => false])
            ->addColumn('user_name',   'string',   ['limit' => 255, 'null' => false])
            ->addColumn('upload_time', 'datetime', ['null' => false])
            ->create();
    }
}
