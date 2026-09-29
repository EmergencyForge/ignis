<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ein Postfach gehört einem Konto, nicht einer Discord-ID.
 *
 * Bisher hing das Postfach über `intra_mitarbeiter.discordtag =
 * intra_users.discord_id` am Konto. `discordtag` pflegt aber die
 * Personalverwaltung: wer die IDs zweier Mitarbeiter tauscht, bekäme das
 * fremde Postfach samt aller alten Mails. Deshalb steht das Konto jetzt
 * fest am Postfach (`user_id`, eindeutig, fällt beim Löschen des Kontos
 * auf NULL). Umhängen geht nur ausdrücklich in der Postfachverwaltung.
 *
 * Bestand: ein Postfach bekommt sein Konto hier nur, wenn genau ein
 * aktives Konto zum Mitarbeiter passt (Discord-ID oder `aktenid`) und
 * dieses Konto zu keinem zweiten Postfach passt. Alles andere bleibt frei
 * und bindet sich nach derselben Regel beim ersten Aufruf oder beim
 * nächtlichen `mail:backfill` (Plugin\Mail\Models\Mailbox::autoBind()).
 */
final class BindIntraMailMailboxesToUsers29092026 extends AbstractMigration
{
    public function up(): void
    {
        $this->table('intra_mail_mailboxes')
            ->addColumn('user_id', 'integer', ['null' => true, 'after' => 'mitarbeiter_id'])
            ->addIndex(['user_id'], ['unique' => true])
            ->addForeignKey('user_id', 'intra_users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->update();

        $rows = $this->fetchAll(
            "SELECT mb.id AS mailbox_id, MIN(u.id) AS user_id, COUNT(DISTINCT u.id) AS matches
               FROM intra_mail_mailboxes mb
               JOIN intra_mitarbeiter m ON m.id = mb.mitarbeiter_id
               JOIN intra_users u ON u.is_active = 1
                AND ((COALESCE(m.discordtag, '') <> '' AND u.discord_id = m.discordtag) OR u.aktenid = m.id)
              GROUP BY mb.id"
        );

        $unique = array_filter($rows, static fn (array $row): bool => (int) $row['matches'] === 1);
        $perUser = array_count_values(array_map(static fn (array $row): int => (int) $row['user_id'], $unique));
        foreach ($unique as $row) {
            if ($perUser[(int) $row['user_id']] !== 1) {
                continue; // ein Konto, zwei Postfächer: das klärt die Postfachverwaltung
            }
            $this->execute(sprintf(
                'UPDATE intra_mail_mailboxes SET user_id = %d WHERE id = %d AND user_id IS NULL',
                (int) $row['user_id'],
                (int) $row['mailbox_id'],
            ));
        }
    }

    public function down(): void
    {
        $this->table('intra_mail_mailboxes')
            ->dropForeignKey('user_id')
            ->removeIndex(['user_id'])
            ->removeColumn('user_id')
            ->update();
    }
}
