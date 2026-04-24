<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Utils\SystemUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/intra.php updates:check`
 *
 * Prüft täglich auf neue Releases und cached das Ergebnis, damit die
 * Admin-UI-Dashboards ohne GitHub-Roundtrip auskommen. Nutzt den
 * bestehenden `SystemUpdater::checkForUpdatesCached(forceRefresh: true)`.
 */
#[AsCommand(
    name: 'updates:check',
    description: 'Prüft auf neue Releases und aktualisiert den Update-Cache',
)]
final class UpdatesCheckCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $updater = new SystemUpdater();
            $result  = $updater->checkForUpdatesCached(forceRefresh: true);
        } catch (\Throwable $e) {
            $output->writeln('<error>Update-Check fehlgeschlagen: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $available = (bool) ($result['available'] ?? false);
        $latest    = (string) ($result['latest_version'] ?? '?');
        $current   = (string) ($result['current_version'] ?? '?');

        if ($available) {
            $output->writeln("<info>updates:check</info> — Neue Version verfügbar: <comment>{$latest}</comment> (aktuell: {$current})");
        } else {
            $output->writeln("<info>updates:check</info> — Installation ist aktuell ({$current}).");
        }
        return Command::SUCCESS;
    }
}
