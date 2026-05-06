<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FailedJobsReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Listet fehlgeschlagene Jobs aus der `intra_failed_jobs`-Tabelle auf.
 *
 *   php cli/intra.php queue:failed:list
 *   php cli/intra.php queue:failed:list --limit=20
 */
#[AsCommand(
    name: 'queue:failed:list',
    description: 'Listet fehlgeschlagene Jobs auf',
)]
final class QueueFailedListCommand extends Command
{
    public function __construct(
        private readonly FailedJobsReader $reader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximale Anzahl Einträge', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->reader->tableExists()) {
            $output->writeln('<comment>Tabelle intra_failed_jobs existiert nicht — Migration noch nicht gelaufen.</comment>');
            return Command::SUCCESS;
        }

        $limit = max(1, (int) $input->getOption('limit'));
        $jobs  = $this->reader->getRecent($limit);
        $stats = $this->reader->getStats();

        $output->writeln("<info>Fehlgeschlagene Jobs:</info> {$stats['total']} gesamt, {$stats['last_24h']} in 24h, {$stats['last_7d']} in 7d");
        $output->writeln('');

        if (empty($jobs)) {
            $output->writeln('<comment>Keine fehlgeschlagenen Jobs.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Queue', 'Job-Klasse', 'Fehler', 'Zeitpunkt']);
        foreach ($jobs as $job) {
            $table->addRow([
                $job['id'],
                $job['queue'],
                $this->shortClass((string) ($job['job_class'] ?? '–')),
                mb_strimwidth((string) ($job['short_message'] ?? '–'), 0, 60, '…'),
                $job['failed_at_formatted'],
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }

    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
