<?php

declare(strict_types=1);

namespace Plugin\Training\Console;

use Plugin\Training\Expiry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Täglich über intra_cron_jobs (`training.expiry`).
 *
 *   php cli/intra.php training:expiry
 */
#[AsCommand(name: 'training:expiry', description: 'Meldet 30 Tage vor Ablauf eines bestandenen Lehrgangs an Person und Leitung')]
final class ExpiryCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Erinnert:</info> ' . Expiry::remind());

        return Command::SUCCESS;
    }
}
