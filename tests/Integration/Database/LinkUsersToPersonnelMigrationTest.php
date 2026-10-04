<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Phinx\Db\Adapter\MysqlAdapter;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Migration zu ADR-0002: räumt aktenid auf, übernimmt den Bestand über
 * die Discord-ID nur bei eindeutigen Paaren und füllt die neuen
 * ID-Spalten an Anträgen und Dokumenten.
 *
 * Läuft ohne Transaktion, weil die Migration Schema ändert (DDL beendet
 * jede offene Transaktion). Der Test nimmt die Migration zurück, legt
 * Daten an, die die Constraints sonst verbieten, und spielt sie wieder ein.
 */
final class LinkUsersToPersonnelMigrationTest extends IntegrationTestCase
{
    protected bool $useTransactions = false;

    /** @var array<string, list<int>> */
    private array $created = ['intra_antraege' => [], 'intra_mitarbeiter_dokumente' => [], 'intra_users' => [], 'intra_mitarbeiter' => [], 'intra_antrag_typen' => []];

    private function migration(): \LinkUsersToPersonnel
    {
        require_once dirname(__DIR__, 3) . '/database/migrations/20261004000001_link_users_to_personnel.php';
        $adapter = new MysqlAdapter(['name' => $_ENV['DB_NAME'], 'connection' => Capsule::connection()->getPdo()]);
        $migration = new \LinkUsersToPersonnel('testing', 20261004000001);
        $migration->setAdapter($adapter);

        return $migration;
    }

    /** @param array<string,mixed> $row */
    private function insert(string $table, array $row): int
    {
        $id = (int) Capsule::table($table)->insertGetId($row);
        $this->created[$table][] = $id;

        return $id;
    }

    private function user(?string $discordId, ?int $aktenid = null, bool $active = true): int
    {
        return $this->insert('intra_users', [
            'username'   => 'mig_' . uniqid(),
            'discord_id' => $discordId,
            'aktenid'    => $aktenid,
            'role'       => (int) Capsule::table('intra_users_roles')->min('id'),
            'is_active'  => $active ? 1 : 0,
        ]);
    }

    private function person(?string $discordtag): int
    {
        return $this->insert('intra_mitarbeiter', [
            'fullname'   => 'Migration ' . uniqid(),
            'dienstnr'   => 'MIG-' . uniqid(),
            'gebdatum'   => '1990-01-01',
            'einstdatum' => '2024-01-01',
            'geschlecht' => 0,
            'charakterid' => '',
            'discordtag' => $discordtag,
            'dienstgrad' => (int) Capsule::table('intra_mitarbeiter_dienstgrade')->min('id'),
            'qualird'    => (int) Capsule::table('intra_mitarbeiter_rdquali')->min('id'),
            'qualifw2'   => (int) Capsule::table('intra_mitarbeiter_fwquali')->min('id'),
        ]);
    }

    private function aktenid(int $userId): ?int
    {
        $value = Capsule::table('intra_users')->where('id', $userId)->value('aktenid');

        return $value === null ? null : (int) $value;
    }

    #[Test]
    public function bereinigt_uebernimmt_eindeutige_paare_und_sichert_mit_index_und_fremdschluessel(): void
    {
        $migration = $this->migration();
        $migration->down();

        try {
            // Eindeutig: ein aktives Konto, ein Mitarbeiter, beide frei.
            $unique       = $this->user('510000000000000001');
            $uniquePerson = $this->person('510000000000000001');
            // Zwei Konten mit derselben Discord-ID.
            $twinA = $this->user('510000000000000002');
            $twinB = $this->user('510000000000000002');
            $this->person('510000000000000002');
            // Zwei Mitarbeiter mit derselben Discord-ID.
            $single = $this->user('510000000000000003');
            $this->person('510000000000000003');
            $this->person('510000000000000003');
            // Deaktiviertes Konto.
            $inactive = $this->user('510000000000000004', null, false);
            $this->person('510000000000000004');
            // Verweis auf einen fehlenden Mitarbeiter.
            $dangling = $this->user(null, 2147480000);
            // Zwei Konten auf demselben Mitarbeiter.
            $shared = $this->person(null);
            $dupA   = $this->user(null, $shared);
            $dupB   = $this->user(null, $shared);
            // Schon verknüpft: bleibt, auch wenn Discord woanders hinzeigt.
            $kept       = $this->person(null);
            $keptUser   = $this->user('510000000000000005', $kept);
            $this->person('510000000000000005');

            $typ = $this->insert('intra_antrag_typen', ['name' => 'Migrationstest']);
            $antragUnique = $this->insert('intra_antraege', ['uniqueid' => 'M' . random_int(100000, 999999), 'antragstyp_id' => $typ, 'name_dn' => 'x', 'discordid' => '510000000000000001']);
            $antragAmbig  = $this->insert('intra_antraege', ['uniqueid' => 'M' . random_int(100000, 999999), 'antragstyp_id' => $typ, 'name_dn' => 'x', 'discordid' => '510000000000000003']);
            $docUnique = $this->insert('intra_mitarbeiter_dokumente', ['docid' => random_int(1000000, 9999999), 'ausstellerid' => '510000000000000001', 'profileid' => $uniquePerson]);
            $docAmbig  = $this->insert('intra_mitarbeiter_dokumente', ['docid' => random_int(1000000, 9999999), 'ausstellerid' => '510000000000000002', 'profileid' => $uniquePerson]);

            $migration->up();
            // Ein zweiter Lauf ändert nichts und scheitert nicht.
            $migration->up();

            $this->assertSame($uniquePerson, $this->aktenid($unique));
            $this->assertNull($this->aktenid($twinA));
            $this->assertNull($this->aktenid($twinB));
            $this->assertNull($this->aktenid($single));
            $this->assertNull($this->aktenid($inactive));
            $this->assertNull($this->aktenid($dangling));
            $this->assertNull($this->aktenid($dupA));
            $this->assertNull($this->aktenid($dupB));
            $this->assertSame($kept, $this->aktenid($keptUser));

            $this->assertSame($uniquePerson, (int) Capsule::table('intra_antraege')->where('id', $antragUnique)->value('mitarbeiter_id'));
            $this->assertNull(Capsule::table('intra_antraege')->where('id', $antragAmbig)->value('mitarbeiter_id'));
            $this->assertSame($unique, (int) Capsule::table('intra_mitarbeiter_dokumente')->where('id', $docUnique)->value('aussteller_user_id'));
            $this->assertNull(Capsule::table('intra_mitarbeiter_dokumente')->where('id', $docAmbig)->value('aussteller_user_id'));

            $index = Capsule::connection()->select("SHOW INDEX FROM intra_users WHERE Key_name = 'uniq_users_aktenid'");
            $this->assertNotEmpty($index);
            $this->assertSame(0, (int) $index[0]->Non_unique);
            $this->assertTrue(Capsule::schema()->hasColumn('intra_registration_codes', 'mitarbeiter_id'));

            // Fremdschlüssel: ein gelöschter Mitarbeiter löst die Verknüpfung.
            Capsule::table('intra_mitarbeiter')->where('id', $uniquePerson)->delete();
            $this->assertNull($this->aktenid($unique));
            $this->assertNull(Capsule::table('intra_antraege')->where('id', $antragUnique)->value('mitarbeiter_id'));
        } finally {
            if (!Capsule::schema()->hasColumn('intra_antraege', 'mitarbeiter_id')) {
                $migration->up();
            }
            foreach ($this->created as $table => $ids) {
                Capsule::table($table)->whereIn('id', $ids)->delete();
            }
        }
    }
}
