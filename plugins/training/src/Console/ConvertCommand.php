<?php

declare(strict_types=1);

namespace Plugin\Training\Console;

use Plugin\Training\Converter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Jede Minute über intra_cron_jobs (`training.convert`). Ohne
 * `TRAINING_CONVERTER` oder ohne aufrufbares LibreOffice endet der Lauf
 * sofort, die Dateien bleiben dann Download.
 *
 *   php cli/intra.php training:convert
 */
#[AsCommand(name: 'training:convert', description: 'Wandelt PowerPoint-Unterlagen mit LibreOffice in PDF um')]
final class ConvertCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $converter = Converter::configured();
        if ($converter === null) {
            $output->writeln('Kein Umwandler eingerichtet (TRAINING_CONVERTER).');

            return Command::SUCCESS;
        }
        if (!$converter->available()) {
            $output->writeln('<error>TRAINING_CONVERTER ist gesetzt, LibreOffice lässt sich aber nicht aufrufen.</error>');

            return Command::FAILURE;
        }
        $result = $converter->runPending();
        $output->writeln(sprintf('<info>Umgewandelt:</info> %d, <info>fehlgeschlagen:</info> %d', $result['done'], $result['failed']));

        return Command::SUCCESS;
    }
}
