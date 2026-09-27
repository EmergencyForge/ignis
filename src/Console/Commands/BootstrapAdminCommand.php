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
 * Dabei wird das Konto auch wieder aktiv geschaltet. auth/callback.php weist
 * ein Konto mit is_active = 0 beim Login ab; ohne diese Zeile meldete der
 * Befehl auf einem gesperrten Konto "Konto aktualisiert" und Exit 0, und der
 * Mensch käme trotzdem nicht rein.
 *
 * Die Rolle wird gesetzt wie beim ersten Discord-Login in auth/callback.php:
 * `intra_users.role` ist NOT NULL mit Fremdschlüssel auf
 * `intra_users_roles.id`, ein Konto ohne Rolle lässt sich nicht anlegen.
 *
 * Konten aus dem Sync haben keine Discord-ID. Die findet `--id` über die
 * lokale ID; angelegt wird dabei nichts, und der Name bleibt, wenn keiner
 * mitkommt.
 *
 *   php cli/intra.php bootstrap:admin --discord-id=123 --username=Josua
 *   php cli/intra.php bootstrap:admin --id=7
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
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Lokale ID eines bestehenden Kontos')
            ->addOption('discord-id', null, InputOption::VALUE_REQUIRED, 'Discord-ID des Kontos')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Anzeigename');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id        = trim((string) $input->getOption('id'));
        $discordId = trim((string) $input->getOption('discord-id'));
        $username  = trim((string) $input->getOption('username'));

        if ($id !== '') {
            if ($discordId !== '') {
                $output->writeln('<error>--id und --discord-id schließen sich aus.</error>');
                return Command::FAILURE;
            }
            if (!ctype_digit($id)) {
                $output->writeln('<error>--id muss eine Zahl sein.</error>');
                return Command::FAILURE;
            }
        } else {
            if ($discordId === '') {
                $output->writeln('<error>--discord-id fehlt.</error>');
                return Command::FAILURE;
            }
            if ($username === '') {
                $output->writeln('<error>--username fehlt.</error>');
                return Command::FAILURE;
            }
        }

        $adminRole = Role::query()->where('admin', 1)->first();

        if (!$adminRole) {
            $output->writeln('<error>Keine Admin-Rolle in intra_users_roles. Laufen die Migrationen?</error>');
            return Command::FAILURE;
        }

        if ($id !== '') {
            $user = User::query()->find((int) $id);
            if ($user === null) {
                $output->writeln("<error>Kein Konto mit der ID $id.</error>");
                return Command::FAILURE;
            }
        } else {
            $user = User::query()->firstOrNew(['discord_id' => $discordId]);
        }
        $neu = !$user->exists;

        if ($username !== '') {
            $user->username = $username;
        }
        $user->role       = $adminRole->id;
        $user->full_admin = true;
        $user->is_active  = true;
        // Wie beim regulaeren Reaktivieren im UserController: die Historie
        // gehoert mit abgeraeumt, sonst traegt ein aktives Konto weiter ein
        // Deaktivierungsdatum und verwirrt beim naechsten Blick in die Tabelle.
        $user->deactivated_at = null;
        $user->deactivated_by = null;
        $user->save();

        $output->writeln($neu
            ? "<info>Konto angelegt:</info> {$user->username} ($discordId)"
            : "<info>Konto aktualisiert:</info> {$user->username} (" . ($id !== '' ? "ID $id" : $discordId) . ')');

        return Command::SUCCESS;
    }
}
