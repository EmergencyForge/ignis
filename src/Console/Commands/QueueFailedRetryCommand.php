<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\FailedJobsReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Legt fehlgeschlagene Jobs zurück in die Queue. Einzeln per ID oder
 * alle auf einmal mit --all.
 *
 *   php cli/intra.php queue:failed:retry 42
 *   php cli/intra.php queue:failed:retry --all
 */
#[AsCommand(
    name: 'queue:failed:retry',
    description: 'Legt fehlgeschlagene Jobs erneut in die Queue',
)]
final class QueueFailedRetryCommand extends Command
{
    public function __construct(
        private readonly FailedJobsReader $reader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id',  InputArgument::OPTIONAL, 'ID des fehlgeschlagenen Jobs')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Alle fehlgeschlagenen Jobs erneut versuchen')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->reader->tableExists()) {
            $output->writeln('<comment>Tabelle intra_failed_jobs existiert nicht.</comment>');
            return Command::SUCCESS;
        }

        $all = (bool) $input->getOption('all');
        $id  = $input->getArgument('id');

        if ($all) {
            $count = $this->reader->retryAll();
            $output->writeln("<info>$count Jobs wurden erneut in die Queue gelegt.</info>");
            return Command::SUCCESS;
        }

        if ($id === null) {
            $output->writeln('<error>Bitte eine ID angeben oder --all verwenden.</error>');
            return Command::FAILURE;
        }

        $jobId = (int) $id;
        if ($jobId <= 0) {
            $output->writeln('<error>Ungültige ID.</error>');
            return Command::FAILURE;
        }

        if ($this->reader->retry($jobId)) {
            $output->writeln("<info>Job $jobId wurde erneut in die Queue gelegt.</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<error>Job $jobId konnte nicht re-queued werden (nicht gefunden oder DB-Fehler).</error>");
        return Command::FAILURE;
    }
}
