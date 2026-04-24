<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Logging\Logger;
use Illuminate\Queue\QueueManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/intra.php queue:work`
 *
 * Arbeitet Jobs aus der DB-Queue ab. Beendet sich nach `--max-time`
 * Sekunden oder `--max-jobs` Jobs — whatever comes first. Per Default
 * wird beim ersten leeren Poll sofort beendet, damit Cron-getriggerte
 * Worker schnell zurückkehren (wichtig für HTTP-Trigger mit Timeout).
 *
 * Mit `--daemon` wird der klassische Poll-und-Warte-Loop aktiviert —
 * nur sinnvoll für persistent laufende CLI-Worker (z.B. auf einem VPS
 * unter Supervisor oder systemd).
 */
#[AsCommand(
    name: 'queue:work',
    description: 'Arbeitet Jobs aus der Queue ab',
)]
final class QueueWorkCommand extends Command
{
    public function __construct(
        private readonly QueueManager $queueManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('queue',    null, InputOption::VALUE_REQUIRED, 'Queue-Name',                          'default')
            ->addOption('max-time', null, InputOption::VALUE_REQUIRED, 'Max. Laufzeit in Sekunden',           '55')
            ->addOption('max-jobs', null, InputOption::VALUE_REQUIRED, 'Max. Jobs pro Worker-Lauf',           '50')
            ->addOption('sleep',    null, InputOption::VALUE_REQUIRED, 'Sleep zwischen leeren Polls (Daemon-Modus)', '3')
            ->addOption('daemon',   null, InputOption::VALUE_NONE,     'Poll-und-Warte-Loop aktivieren (für persistent laufende Worker)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = (string) $input->getOption('queue');
        $maxTime   = max(1, (int) $input->getOption('max-time'));
        $maxJobs   = max(1, (int) $input->getOption('max-jobs'));
        $sleep     = max(1, (int) $input->getOption('sleep'));
        $daemon    = (bool) $input->getOption('daemon');

        Logger::info('QueueWork: started', [
            'queue'    => $queueName,
            'max_time' => $maxTime,
            'max_jobs' => $maxJobs,
            'daemon'   => $daemon,
            'pid'      => getmypid(),
        ]);

        $output->writeln("<info>ıgnıs Queue Worker</info>");
        $output->writeln("Queue: <comment>$queueName</comment>  Max-Time: <comment>{$maxTime}s</comment>  Max-Jobs: <comment>$maxJobs</comment>  Daemon: <comment>" . ($daemon ? 'yes' : 'no') . "</comment>");

        $connection = $this->queueManager->connection();
        $startTime  = time();
        $processed  = 0;

        while (true) {
            if ((time() - $startTime) >= $maxTime) {
                $output->writeln('<comment>Max-Time erreicht — beende</comment>');
                break;
            }
            if ($processed >= $maxJobs) {
                $output->writeln('<comment>Max-Jobs erreicht — beende</comment>');
                break;
            }

            /** @var \Illuminate\Contracts\Queue\Job|null $job */
            $job = $connection->pop($queueName);

            if ($job === null) {
                if (!$daemon) {
                    // Cron-getriggerter Modus: sofort beenden, nächster
                    // Cron-Lauf holt dazugekommene Jobs ab
                    break;
                }
                // Daemon-Modus: warten und nochmal pollen
                sleep($sleep);
                continue;
            }

            try {
                $job->fire();
                $processed++;
                $output->writeln("<info>✓</info> Job processed (attempts: {$job->attempts()})");
            } catch (\Throwable $e) {
                Logger::error('QueueWork: job threw outside fire()', ['error' => $e->getMessage()]);
                $output->writeln("<error>✗ Job failed: {$e->getMessage()}</error>");
            }
        }

        $duration = time() - $startTime;
        Logger::info('QueueWork: finished', ['processed' => $processed, 'duration' => $duration]);
        $output->writeln("<info>Fertig:</info> $processed Jobs in {$duration}s");

        return Command::SUCCESS;
    }
}
