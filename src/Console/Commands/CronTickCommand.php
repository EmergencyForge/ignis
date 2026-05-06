<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Cron\CronScheduler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/intra.php cron:tick`
 *
 * Arbeitet alle fälligen Cron-Jobs ab. Für Unix-Cron-Setups gedacht
 * (`* * * * * php cli/intra.php cron:tick`), funktioniert aber ebenso
 * über manuelle Aufrufe oder den HTTP-Endpoint `public/cron.php`.
 */
#[AsCommand(
    name: 'cron:tick',
    description: 'Führt alle fälligen Cron-Jobs aus',
)]
final class CronTickCommand extends Command
{
    public function __construct(private readonly CronScheduler $scheduler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startedAt = microtime(true);
        $executed  = $this->scheduler->tick();
        $duration  = round((microtime(true) - $startedAt) * 1000);

        $output->writeln("<info>cron:tick</info> — {$executed} Job(s) in {$duration}ms ausgeführt.");
        return Command::SUCCESS;
    }
}
