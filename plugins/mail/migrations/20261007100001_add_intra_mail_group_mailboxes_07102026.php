<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gruppenpostfächer: ein Postfach, das mehrere Konten lesen und aus dem
 * sie senden, etwa für eine Wache, eine Abteilung oder ein Team. Gleicher
 * Aufbau wie in Lex (lex_mail_mailbox_members).
 *
 * Anders als ein Verteiler (`intra_mail_lists`), der nur an seine
 * Mitglieder weitergibt, hat ein Gruppenpostfach eigene Ordner und eine
 * eigene Signatur. Gelesen/ungelesen, Ordner und Entwürfe teilen sich alle
 * Mitglieder.
 *
 * - `kind`: `personal` (gehört einem Mitarbeiter, wie bisher) oder `group`
 *   (gehört niemandem, `mitarbeiter_id` und `user_id` bleiben leer). Die
 *   Provisionierung fasst Gruppenpostfächer nicht an: ein leeres
 *   `mitarbeiter_id` heißt nur bei `personal` „Mitarbeiter gelöscht“.
 * - `intra_mail_mailbox_members`: welche Konten ein Gruppenpostfach lesen,
 *   gepflegt in der Postfachverwaltung (`mail.admin`).
 * - `intra_mail_messages.sent_by_user_id`: wer die Nachricht zuletzt
 *   gespeichert oder gesendet hat; die Mitglieder sehen so, wer für die
 *   Gruppe geschrieben hat. Kein Mail-Inhalt im Audit-Log.
 */
final class AddIntraMailGroupMailboxes07102026 extends AbstractMigration
{
    public function change(): void
    {
        $this->table('intra_mail_mailboxes')
            ->addColumn('kind', 'enum', ['values' => ['personal', 'group'], 'default' => 'personal', 'after' => 'id'])
            ->update();

        $this->table('intra_mail_mailbox_members', ['signed' => false])
            ->addColumn('mailbox_id', 'integer', ['signed' => false])
            ->addColumn('user_id', 'integer')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['mailbox_id', 'user_id'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addForeignKey('mailbox_id', 'intra_mail_mailboxes', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $this->table('intra_mail_messages')
            ->addColumn('sent_by_user_id', 'integer', ['null' => true, 'after' => 'sender_mailbox_id'])
            ->addForeignKey('sent_by_user_id', 'intra_users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
