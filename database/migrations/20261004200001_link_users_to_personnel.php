<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * ADR-0002: Mitarbeiter und Benutzerkonto werden fest verknüpft.
 *
 * - intra_users.aktenid ist die Verknüpfung: ungültige und doppelte Werte
 *   fliegen raus, der Bestand wird über die Discord-ID übernommen (genau
 *   ein aktives Konto und genau ein Mitarbeiter, beide frei), danach
 *   eindeutiger Index und Fremdschlüssel.
 * - Einladungen kennen optional den Mitarbeiter.
 * - Anträge und ausgestellte Dokumente bekommen ID-Spalten statt nur der
 *   Discord-ID, gefüllt aus dem Bestand.
 */
final class LinkUsersToPersonnel extends AbstractMigration
{
    public function up(): void
    {
        $this->linkUsers();

        $codes = $this->table('intra_registration_codes');
        if (!$codes->hasColumn('mitarbeiter_id')) {
            $codes->addColumn('mitarbeiter_id', 'integer', ['null' => true, 'after' => 'label'])
                ->addForeignKey('mitarbeiter_id', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_registration_codes_mitarbeiter'])
                ->update();
        }

        // intra_antraege gehört dem Plugin Anträge; ohne das Plugin gibt es
        // die Tabelle nicht.
        if ($this->hasTable('intra_antraege')) {
            $antraege = $this->table('intra_antraege');
            if (!$antraege->hasColumn('mitarbeiter_id')) {
                $antraege->addColumn('mitarbeiter_id', 'integer', ['null' => true, 'after' => 'discordid'])
                    ->addForeignKey('mitarbeiter_id', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_antraege_mitarbeiter'])
                    ->update();
            }
            // Nur eindeutige Discord-IDs: teilen sich zwei Mitarbeiter eine, bleibt der Antrag offen.
            $this->execute(
                "UPDATE intra_antraege a
                 JOIN (SELECT discordtag, MIN(id) AS id FROM intra_mitarbeiter
                       WHERE discordtag IS NOT NULL AND discordtag <> ''
                       GROUP BY discordtag HAVING COUNT(*) = 1) m ON m.discordtag = a.discordid
                 SET a.mitarbeiter_id = m.id
                 WHERE a.mitarbeiter_id IS NULL"
            );
        }

        $dokumente = $this->table('intra_mitarbeiter_dokumente');
        if (!$dokumente->hasColumn('aussteller_user_id')) {
            $dokumente->addColumn('aussteller_user_id', 'integer', ['null' => true, 'after' => 'ausstellerid'])
                ->addForeignKey('aussteller_user_id', 'intra_users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_dokumente_aussteller_user'])
                ->update();
        }
        $this->execute(
            "UPDATE intra_mitarbeiter_dokumente d
             JOIN (SELECT discord_id, MIN(id) AS id FROM intra_users
                   WHERE discord_id IS NOT NULL AND discord_id <> ''
                   GROUP BY discord_id HAVING COUNT(*) = 1) u ON u.discord_id = d.ausstellerid
             SET d.aussteller_user_id = u.id
             WHERE d.aussteller_user_id IS NULL"
        );
    }

    private function linkUsers(): void
    {
        $users = $this->table('intra_users');
        $constrained = $users->hasIndexByName('uniq_users_aktenid');

        if (!$constrained) {
            // Verweise auf gelöschte Mitarbeiter
            $this->execute(
                'UPDATE intra_users u LEFT JOIN intra_mitarbeiter m ON m.id = u.aktenid
                 SET u.aktenid = NULL WHERE u.aktenid IS NOT NULL AND m.id IS NULL'
            );
            // Mehrere Konten auf einem Mitarbeiter: mehrdeutig, alle bleiben leer.
            $this->execute(
                'UPDATE intra_users u
                 JOIN (SELECT aktenid FROM intra_users WHERE aktenid IS NOT NULL
                       GROUP BY aktenid HAVING COUNT(*) > 1) d ON d.aktenid = u.aktenid
                 SET u.aktenid = NULL'
            );
        }

        // Bestand über die Discord-ID. MySQL erlaubt in einem UPDATE keine
        // Unterabfrage auf dieselbe Tabelle, deshalb erst lesen, dann schreiben.
        $candidates = $this->fetchAll(
            "SELECT u.id AS user_id, m.id AS mitarbeiter_id
             FROM (SELECT discord_id, MIN(id) AS id FROM intra_users
                   WHERE is_active = 1 AND discord_id IS NOT NULL AND discord_id <> ''
                   GROUP BY discord_id HAVING COUNT(*) = 1) u
             JOIN (SELECT discordtag, MIN(id) AS id FROM intra_mitarbeiter
                   WHERE discordtag IS NOT NULL AND discordtag <> ''
                   GROUP BY discordtag HAVING COUNT(*) = 1) m ON m.discordtag = u.discord_id
             JOIN intra_users cur ON cur.id = u.id AND cur.aktenid IS NULL
             WHERE NOT EXISTS (SELECT 1 FROM intra_users l WHERE l.aktenid = m.id)"
        );
        foreach ($candidates as $row) {
            $this->execute(sprintf(
                'UPDATE intra_users SET aktenid = %d WHERE id = %d AND aktenid IS NULL',
                (int) $row['mitarbeiter_id'],
                (int) $row['user_id'],
            ));
        }

        if (!$constrained) {
            $users->addIndex(['aktenid'], ['unique' => true, 'name' => 'uniq_users_aktenid'])
                ->addForeignKey('aktenid', 'intra_mitarbeiter', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE', 'constraint' => 'fk_users_aktenid'])
                ->update();
        }
    }

    public function down(): void
    {
        $users = $this->table('intra_users');
        if ($users->hasForeignKey('aktenid')) {
            $users->dropForeignKey('aktenid')->update();
        }
        if ($users->hasIndexByName('uniq_users_aktenid')) {
            $users->removeIndexByName('uniq_users_aktenid')->update();
        }

        foreach ([
            'intra_registration_codes'    => 'mitarbeiter_id',
            'intra_antraege'              => 'mitarbeiter_id',
            'intra_mitarbeiter_dokumente' => 'aussteller_user_id',
        ] as $tableName => $column) {
            if (!$this->hasTable($tableName)) {
                continue;
            }
            $table = $this->table($tableName);
            if ($table->hasForeignKey($column)) {
                $table->dropForeignKey($column)->update();
            }
            if ($table->hasColumn($column)) {
                $table->removeColumn($column)->update();
            }
        }
    }
}
