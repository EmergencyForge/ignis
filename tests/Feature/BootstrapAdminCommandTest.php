<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\FeatureTestCase;

final class BootstrapAdminCommandTest extends FeatureTestCase
{
    public function test_legt_einen_full_admin_an(): void
    {
        $tester = $this->commandTester('bootstrap:admin');

        $code = $tester->execute([
            '--discord-id' => '111222333444555666',
            '--username'   => 'Josua',
        ]);

        self::assertSame(0, $code);

        $user = User::query()->where('discord_id', '111222333444555666')->first();
        self::assertNotNull($user);
        self::assertTrue($user->full_admin);

        // intra_users.role ist NOT NULL mit Fremdschluessel; das Konto muss
        // auf der Admin-Rolle landen, wie beim ersten Discord-Login.
        $adminRole = \App\Models\Role::query()->where('admin', 1)->first();
        self::assertNotNull($adminRole);
        self::assertSame((int) $adminRole->id, (int) $user->role);
    }

    public function test_ist_wiederholbar_und_legt_nicht_doppelt_an(): void
    {
        $tester = $this->commandTester('bootstrap:admin');
        $args   = ['--discord-id' => '999', '--username' => 'Doppelt'];

        self::assertSame(0, $tester->execute($args));

        // Ein deaktiviertes Konto weist auth/callback.php beim Login ab. Der
        // zweite Aufruf muss den Zugang wiederherstellen, sonst meldet der
        // Befehl "Konto aktualisiert" und Exit 0, und der Mensch kommt
        // trotzdem nicht rein.
        User::query()->where('discord_id', '999')->update([
            'is_active'      => 0,
            'deactivated_at' => '2026-01-01 12:00:00',
            'deactivated_by' => 1,
        ]);

        self::assertSame(0, $tester->execute($args));

        self::assertSame(1, User::query()->where('discord_id', '999')->count());

        $user = User::query()->where('discord_id', '999')->first();
        self::assertNotNull($user);
        self::assertTrue(
            (bool) $user->is_active,
            'Der wiederholte Aufruf muss ein deaktiviertes Konto wieder aktivieren.'
        );

        // Der regulaere Weg im UserController raeumt beide Spalten mit ab. Bleibt
        // die Historie stehen, traegt ein aktives Konto weiter ein
        // Deaktivierungsdatum.
        self::assertNull($user->deactivated_at, 'deactivated_at muss abgeraeumt sein.');
        self::assertNull($user->deactivated_by, 'deactivated_by muss abgeraeumt sein.');
    }

    public function test_verweigert_eine_leere_discord_id(): void
    {
        $tester = $this->commandTester('bootstrap:admin');

        $code = $tester->execute(['--discord-id' => '', '--username' => 'Leer']);

        self::assertSame(1, $code);
        self::assertSame(0, User::query()->where('username', 'Leer')->count());
    }

    public function test_hebt_ein_konto_ohne_discord_id_ueber_die_lokale_id(): void
    {
        $tester = $this->commandTester('bootstrap:admin');
        self::assertSame(0, $tester->execute(['--discord-id' => '777', '--username' => 'Sync']));

        // Wie ein Konto aus dem Sync: keine Discord-ID, gesperrt.
        $user = User::query()->where('discord_id', '777')->first();
        self::assertNotNull($user);
        User::query()->whereKey($user->id)->update(['discord_id' => null, 'full_admin' => 0, 'is_active' => 0]);

        self::assertSame(0, $tester->execute(['--id' => (string) $user->id]));

        $user = User::query()->find($user->id);
        self::assertNotNull($user);
        self::assertTrue($user->full_admin);
        self::assertTrue((bool) $user->is_active);
        self::assertSame('Sync', $user->username, 'Ohne --username bleibt der Name.');
    }

    public function test_legt_ueber_die_lokale_id_nichts_an(): void
    {
        $tester = $this->commandTester('bootstrap:admin');

        self::assertSame(1, $tester->execute(['--id' => '999999999']));
        self::assertStringContainsString('999999999', $tester->getDisplay());
    }

    public function test_verweigert_id_und_discord_id_zusammen(): void
    {
        $tester = $this->commandTester('bootstrap:admin');

        $code = $tester->execute(['--id' => '1', '--discord-id' => '999', '--username' => 'Beides']);

        self::assertSame(1, $code);
        self::assertSame(0, User::query()->where('username', 'Beides')->count());
    }
}
