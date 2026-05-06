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
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Löscht fehlgeschlagene Jobs aus der `intra_failed_jobs`-Tabelle.
 *
 *   php cli/intra.php queue:failed:clear 42
 *   php cli/intra.php queue:failed:clear --all
 *   php cli/intra.php queue:failed:clear --all --force   # ohne Confirm
 */
#[AsCommand(
    name: 'queue:failed:clear',
    description: 'Löscht fehlgeschlagene Jobs',
)]
final class QueueFailedClearCommand extends Command
{
    public function __construct(
        private readonly FailedJobsReader $reader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'ID des zu löschenden Jobs')
            ->addOption('all',   null, InputOption::VALUE_NONE, 'Alle fehlgeschlagenen Jobs löschen')
            ->addOption('force', 'f',  InputOption::VALUE_NONE, 'Keine Bestätigung abfragen')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->reader->tableExists()) {
            $output->writeln('<comment>Tabelle intra_failed_jobs existiert nicht.</comment>');
            return Command::SUCCESS;
        }

        $all   = (bool) $input->getOption('all');
        $force = (bool) $input->getOption('force');
        $id    = $input->getArgument('id');

        if ($all) {
            if (!$force) {
                $helper   = $this->getHelper('question');
                $question = new ConfirmationQuestion('Wirklich ALLE fehlgeschlagenen Jobs löschen? [y/N] ', false);
                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('<comment>Abgebrochen.</comment>');
                    return Command::SUCCESS;
                }
            }
            $count = $this->reader->deleteAll();
            $output->writeln("<info>$count Jobs gelöscht.</info>");
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

        if ($this->reader->delete($jobId)) {
            $output->writeln("<info>Job $jobId gelöscht.</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<error>Job $jobId nicht gefunden.</error>");
        return Command::FAILURE;
    }
}
