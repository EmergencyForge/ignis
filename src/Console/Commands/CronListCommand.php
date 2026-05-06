<?php

declare(strict_types=1);

namespace App\Console\Commands;

use PDO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/intra.php cron:list`
 *
 * Zeigt alle registrierten Cron-Jobs mit Status, letzter/nächster Ausführung
 * und Fail-Counter — nützlich zum Debuggen ohne Admin-UI.
 */
#[AsCommand(
    name: 'cron:list',
    description: 'Listet alle registrierten Cron-Jobs',
)]
final class CronListCommand extends Command
{
    public function __construct(private readonly PDO $pdo)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->pdo
            ->query("SELECT identifier, name, schedule, active, last_status,
                            last_run_at, next_run_at, fail_count
                       FROM intra_cron_jobs
                      ORDER BY identifier")
            ->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false || count($rows) === 0) {
            $output->writeln('<comment>Keine Cron-Jobs registriert.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Identifier', 'Schedule', 'Active', 'Last Status', 'Last Run', 'Next Run', 'Fails']);
        foreach ($rows as $r) {
            $table->addRow([
                $r['identifier'],
                $r['schedule'],
                ((int) $r['active']) === 1 ? 'yes' : 'NO',
                $r['last_status'] ?? '–',
                $r['last_run_at'] ?? '–',
                $r['next_run_at'] ?? '–',
                (string) $r['fail_count'],
            ]);
        }
        $table->render();
        return Command::SUCCESS;
    }
}
