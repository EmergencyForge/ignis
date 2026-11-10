<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Echtheitsprüfung von Urkunden: Prüfcode und Widerruf am Dokument, der
 * Schalter „Prüfcode aufdrucken“ an der Vorlage und die Abfragen der
 * öffentlichen Seite /verify für die Grenze je Anschluss.
 */
final class DocumentVerification extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_documents')
            ->addColumn('verification_code', 'char', ['limit' => 10, 'null' => true, 'after' => 'pdf_path'])
            ->addColumn('revoked_at', 'datetime', ['null' => true, 'after' => 'issued_at'])
            ->addColumn('revoked_by', 'integer', ['null' => true, 'after' => 'revoked_at'])
            ->addColumn('revoke_reason', 'string', ['limit' => 500, 'null' => true, 'after' => 'revoked_by'])
            ->addIndex(['verification_code'], ['unique' => true])
            ->addForeignKey('revoked_by', 'intra_users', 'id', ['delete' => 'SET_NULL'])
            ->update();

        $this->table('intra_document_templates')
            ->addColumn('print_verification', 'boolean', ['default' => false, 'null' => false, 'after' => 'category'])
            ->update();

        $this->table('intra_verification_lookups')
            ->addColumn('ip_hash', 'char', ['limit' => 64])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['ip_hash', 'created_at'])
            ->create();
    }
}
