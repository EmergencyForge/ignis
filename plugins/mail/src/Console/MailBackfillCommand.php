<?php

declare(strict_types=1);

namespace Plugin\Mail\Console;

use App\Models\Personnel;
use Plugin\Mail\MailboxProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Gleicht die Postfächer mit dem Mitarbeiter-Bestand ab: jeder Mitarbeiter
 * durchläuft dieselbe Regel wie beim Speichern (MailboxProvisioner::
 * sync()), Postfächer gelöschter Mitarbeiter werden stillgelegt.
 * Idempotent, ein zweiter Lauf ändert nichts.
 *
 *   php cli/intra.php mail:backfill
 */
#[AsCommand(
    name: 'mail:backfill',
    description: 'Legt Postfächer für alle Mitarbeiter an bzw. gleicht sie ab',
)]
final class MailBackfillCommand extends Command
{
    public function __construct(private readonly MailboxProvisioner $provisioner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $synced  = 0;
        $skipped = 0;

        foreach (Personnel::query()->orderBy('id')->pluck('id') as $id) {
            if ($this->provisioner->sync((int) $id) === null) {
                $skipped++;
                continue;
            }
            $synced++;
        }
        $orphans = $this->provisioner->deactivateOrphans();

        $output->writeln("<info>Postfächer abgeglichen:</info> $synced, ohne Postfach: $skipped, stillgelegt (Mitarbeiter gelöscht): $orphans");

        return Command::SUCCESS;
    }
}
