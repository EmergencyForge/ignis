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
        User::query()->where('discord_id', '999')->update(['is_active' => 0]);

        self::assertSame(0, $tester->execute($args));

        self::assertSame(1, User::query()->where('discord_id', '999')->count());

        $user = User::query()->where('discord_id', '999')->first();
        self::assertNotNull($user);
        self::assertTrue(
            (bool) $user->is_active,
            'Der wiederholte Aufruf muss ein deaktiviertes Konto wieder aktivieren.'
        );
    }

    public function test_verweigert_eine_leere_discord_id(): void
    {
        $tester = $this->commandTester('bootstrap:admin');

        $code = $tester->execute(['--discord-id' => '', '--username' => 'Leer']);

        self::assertSame(1, $code);
        self::assertSame(0, User::query()->where('username', 'Leer')->count());
    }
}
