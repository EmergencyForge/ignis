<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\AutoMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Führt ausstehende Phinx-Migrations aus.
 *
 * Nutzt den bestehenden `App\Database\AutoMigrator`, der auch beim
 * normalen Request-Lifecycle läuft — der Command ist nur der CLI-Hook.
 *
 *   php cli/intra.php migrate
 */
#[AsCommand(
    name: 'migrate',
    description: 'Führt ausstehende Datenbank-Migrations aus',
)]
final class MigrateCommand extends Command
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db   = $_ENV['DB_NAME'] ?? '(unbekannt)';

        $output->writeln("<info>intraRP Datenbank-Migration</info>");
        $output->writeln("  Host:     $host");
        $output->writeln("  Database: $db");
        $output->writeln('');

        try {
            $migrator = new AutoMigrator($this->pdo);
            $migrator->runIfNeeded();
        } catch (\Throwable $e) {
            $output->writeln('<error>Migration fehlgeschlagen: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Migration abgeschlossen.</info>');
        return Command::SUCCESS;
    }
}
