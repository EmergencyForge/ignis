<?php

declare(strict_types=1);

namespace Plugin\Training\Console;

use App\Logging\Logger;
use Plugin\Training\Exams;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Gibt Theorieversuche ab, deren Zeit samt Kulanz abgelaufen ist, auch wenn
 * der Browser zu ist. Läuft alle fünf Minuten über intra_cron_jobs
 * (`training.complete`).
 *
 *   php cli/intra.php training:complete
 */
#[AsCommand(name: 'training:complete', description: 'Gibt abgelaufene Prüfungsversuche ab')]
final class CompleteAttemptsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $closed = (new Exams())->closeOverdue();
        if ($closed > 0) {
            Logger::info('Abgelaufene Prüfungsversuche abgegeben', ['anzahl' => $closed]);
        }
        $output->writeln("<info>Abgegeben:</info> $closed");

        return Command::SUCCESS;
    }
}
