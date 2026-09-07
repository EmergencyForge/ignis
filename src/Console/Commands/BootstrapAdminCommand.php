<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Legt das erste Administratorkonto an, ohne Rückfragen.
 *
 * Gedacht für automatisch bereitgestellte Instanzen: dort klickt niemand
 * setup.php durch, und der erste Benutzer muss stehen, bevor sich jemand
 * über Discord anmeldet. Wiederholte Aufrufe mit derselben Discord-ID
 * heben das bestehende Konto auf full_admin, statt ein zweites anzulegen.
 *
 * Die Rolle wird gesetzt wie beim ersten Discord-Login in auth/callback.php:
 * `intra_users.role` ist NOT NULL mit Fremdschlüssel auf
 * `intra_users_roles.id`, ein Konto ohne Rolle lässt sich nicht anlegen.
 *
 *   php cli/intra.php bootstrap:admin --discord-id=123 --username=Josua
 */
#[AsCommand(
    name: 'bootstrap:admin',
    description: 'Legt das erste Administratorkonto an',
)]
final class BootstrapAdminCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('discord-id', null, InputOption::VALUE_REQUIRED, 'Discord-ID des Kontos')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Anzeigename');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $discordId = trim((string) $input->getOption('discord-id'));
        $username  = trim((string) $input->getOption('username'));

        if ($discordId === '') {
            $output->writeln('<error>--discord-id fehlt.</error>');
            return Command::FAILURE;
        }
        if ($username === '') {
            $output->writeln('<error>--username fehlt.</error>');
            return Command::FAILURE;
        }

        $adminRole = Role::query()->where('admin', 1)->first();

        if (!$adminRole) {
            $output->writeln('<error>Keine Admin-Rolle in intra_users_roles. Laufen die Migrationen?</error>');
            return Command::FAILURE;
        }

        $user = User::query()->firstOrNew(['discord_id' => $discordId]);
        $neu  = !$user->exists;

        $user->username   = $username;
        $user->role       = $adminRole->id;
        $user->full_admin = true;
        $user->save();

        $output->writeln($neu
            ? "<info>Konto angelegt:</info> $username ($discordId)"
            : "<info>Konto aktualisiert:</info> $username ($discordId)");

        return Command::SUCCESS;
    }
}
