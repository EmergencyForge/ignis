<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Calendar\AbsenceSyncService;
use App\Models\Antrag;
use App\Models\AntragTyp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/intra.php calendar:backfill-absences`
 *
 * Spiegelt alle bereits genehmigten Urlaubsantraege in den Kalender —
 * gedacht als einmaliger Sync nach dem Phase-2-Deployment, damit Bestands-
 * Daten nicht erst beim naechsten Status-Wechsel sichtbar werden.
 *
 * Idempotent: AbsenceSyncService nutzt firstOrNew via source/source_ref_id,
 * d.h. ein bereits gespiegelter Antrag bekommt nur ein Update statt Duplikat.
 */
#[AsCommand(
    name: 'calendar:backfill-absences',
    description: 'Spiegelt alle bereits genehmigten Urlaubsanträge als Absence-Events in den Kalender',
)]
final class CalendarBackfillAbsencesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $urlaubTyp = AntragTyp::query()
            ->whereRaw('LOWER(name) = ?', ['urlaubsantrag'])
            ->first(['id']);

        if ($urlaubTyp === null) {
            $output->writeln('<comment>Antragstyp "Urlaubsantrag" nicht gefunden — Backfill uebersprungen.</comment>');
            return Command::SUCCESS;
        }

        $approved = Antrag::query()
            ->where('antragstyp_id', (int) $urlaubTyp->id)
            ->where('cirs_status', Antrag::STATUS_ACCEPTED)
            ->with('daten')
            ->get();

        if ($approved->isEmpty()) {
            $output->writeln('<info>Keine genehmigten Urlaubsantraege gefunden.</info>');
            return Command::SUCCESS;
        }

        $output->writeln("Verarbeite <info>{$approved->count()}</info> genehmigte Urlaubsantraege …");

        $created = 0;
        $skipped = 0;
        foreach ($approved as $antrag) {
            $von = (string) ($antrag->getFieldValue('von_datum') ?? '');
            $bis = (string) ($antrag->getFieldValue('bis_datum') ?? '');
            if ($von === '' || $bis === '') {
                $skipped++;
                $output->writeln("  · Antrag #{$antrag->uniqueid}: keine von/bis-Daten <comment>(uebersprungen)</comment>");
                continue;
            }
            $event = AbsenceSyncService::syncFromAntrag($antrag, $von, $bis);
            if ($event === null) {
                $skipped++;
                $output->writeln("  · Antrag #{$antrag->uniqueid}: nicht parsebare Datumsangaben <comment>(uebersprungen)</comment>");
                continue;
            }
            $created++;
            $output->writeln("  · Antrag #{$antrag->uniqueid} → Event #{$event->id} (<info>{$von}</info> bis <info>{$bis}</info>)");
        }

        $output->writeln('');
        $output->writeln("Fertig: <info>{$created}</info> gespiegelt, <comment>{$skipped}</comment> uebersprungen.");
        return Command::SUCCESS;
    }
}
