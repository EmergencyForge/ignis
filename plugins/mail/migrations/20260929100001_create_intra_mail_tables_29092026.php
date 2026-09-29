<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Internes Mailmodul (ForgeBoard #129): Postfächer, Nachrichten,
 * Zustellungen, Verteiler, Anhänge, Signaturen und die Adress-Historie.
 * Gleicher Aufbau wie `lex_mail_*` in Lex, mit den Nachträgen aus dessen
 * Review gleich eingebaut.
 *
 * - Ein Postfach gehört einem Mitarbeiter. Mitarbeiter werden hart
 *   gelöscht, deshalb trägt das Postfach Adresse und Anzeigenamen selbst
 *   und `mitarbeiter_id` fällt beim Löschen auf NULL (das Postfach bleibt
 *   als inaktiv stehen, alte Mails behalten ihren Absender).
 * - `locked` ist die Sperre der Administration, getrennt von `active`:
 *   `active` folgt dem Mitarbeiter (Archiv-Dienstgrad, gelöscht), die
 *   Provisionierung setzt es bei jedem Speichern neu. `locked` nimmt sie
 *   nie zurück, nur „Entsperren“ in der Postfachverwaltung.
 * - `intra_mail_address_history` hält frühere Adressen fest. Eine
 *   freigewordene Adresse bleibt dem Postfach vorbehalten, das sie hatte,
 *   sonst bekäme ein anderes Postfach die Antworten auf alte Mails.
 * - Zustellungen sind eindeutig je Nachricht, Postfach UND Rolle: an sich
 *   selbst adressiert gibt es eine Absender- und eine Empfängerzeile.
 * - `header_json` ist der Empfänger-Schnappschuss mit BCC. Wer BCC sehen
 *   darf, entscheidet die Anzeige, nicht das Schema.
 * - `body_html` ist MEDIUMTEXT: der Text darf 200 KB Editor-JSON haben,
 *   das gerenderte HTML passt nicht sicher in 64 KB.
 */
final class CreateIntraMailTables29092026 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_mail_mailboxes', ['signed' => false])
            ->addColumn('mitarbeiter_id', 'integer', ['null' => true])
            ->addColumn('address', 'string', ['limit' => 190])
            ->addColumn('display_name', 'string', ['limit' => 150])
            ->addColumn('domain', 'string', ['limit' => 100])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('locked', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['mitarbeiter_id'], ['unique' => true])
            ->addIndex(['address'], ['unique' => true])
            ->addForeignKey('mitarbeiter_id', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        $this->table('intra_mail_address_history', ['signed' => false])
            ->addColumn('address', 'string', ['limit' => 190])
            ->addColumn('mailbox_id', 'integer', ['signed' => false])
            ->addColumn('released_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['address'], ['unique' => true])
            ->addForeignKey('mailbox_id', 'intra_mail_mailboxes', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('intra_mail_lists', ['signed' => false])
            ->addColumn('address', 'string', ['limit' => 190])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('kind', 'string', ['limit' => 10]) // static|dynamic
            // Nur bei kind=dynamic: {"role_ids":[],"rank_ids":[],"rd_quali_ids":[],"fw_quali_ids":[]}
            ->addColumn('rule', 'json', ['null' => true])
            // Wer an den Verteiler schreiben darf: all = jeder mit mail.use,
            // managers = nur mit mail.lists.manage.
            ->addColumn('senders', 'string', ['limit' => 10, 'default' => 'all'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['address'], ['unique' => true])
            ->create();

        $this->table('intra_mail_list_members', ['signed' => false])
            ->addColumn('list_id', 'integer', ['signed' => false])
            ->addColumn('mailbox_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['list_id', 'mailbox_id'], ['unique' => true])
            ->addForeignKey('list_id', 'intra_mail_lists', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('mailbox_id', 'intra_mail_mailboxes', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('intra_mail_messages', ['signed' => false])
            ->addColumn('sender_mailbox_id', 'integer', ['signed' => false])
            ->addColumn('subject', 'string', ['limit' => 255])
            ->addColumn('body_json', 'json')
            ->addColumn('body_html', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_MEDIUM])
            ->addColumn('header_json', 'json', ['null' => true])
            ->addColumn('thread_id', 'string', ['limit' => 40])
            ->addColumn('in_reply_to', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'draft']) // draft|sent
            ->addColumn('sent_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['thread_id'])
            ->addIndex(['sender_mailbox_id'])
            ->addIndex(['status', 'updated_at'])
            ->addForeignKey('sender_mailbox_id', 'intra_mail_mailboxes', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('in_reply_to', 'intra_mail_messages', 'id', ['delete' => 'SET_NULL'])
            ->create();

        $this->table('intra_mail_deliveries', ['signed' => false])
            ->addColumn('message_id', 'integer', ['signed' => false])
            ->addColumn('mailbox_id', 'integer', ['signed' => false])
            ->addColumn('role', 'string', ['limit' => 10]) // sender|to|cc|bcc
            ->addColumn('folder', 'string', ['limit' => 10]) // inbox|sent|drafts|archive|trash
            ->addColumn('read_at', 'timestamp', ['null' => true])
            ->addColumn('flagged', 'boolean', ['default' => false])
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['message_id', 'mailbox_id', 'role'], ['unique' => true])
            ->addIndex(['mailbox_id', 'folder', 'deleted_at', 'read_at'])
            ->addForeignKey('message_id', 'intra_mail_messages', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('mailbox_id', 'intra_mail_mailboxes', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('intra_mail_attachments', ['signed' => false])
            ->addColumn('message_id', 'integer', ['signed' => false])
            ->addColumn('path', 'string', ['limit' => 255])
            ->addColumn('original_name', 'string', ['limit' => 255])
            ->addColumn('mime', 'string', ['limit' => 100])
            ->addColumn('size', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['message_id'])
            ->addForeignKey('message_id', 'intra_mail_messages', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('intra_mail_signatures', ['signed' => false])
            ->addColumn('mailbox_id', 'integer', ['signed' => false])
            ->addColumn('body_json', 'json')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true])
            ->addIndex(['mailbox_id'], ['unique' => true])
            ->addForeignKey('mailbox_id', 'intra_mail_mailboxes', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
