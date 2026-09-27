<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Hub\ChangelogClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Holt die Ankündigungen aus dem Forum (Discourse-Kategorie Ankündigungen)
 * und legt sie im lokalen intra_changelog_cache ab. Das Admin-Dashboard liest
 * danach ausschliesslich aus dem Cache — dieser Command ist die einzige Stelle,
 * an der wir das Forum kontaktieren. Der Name changelog:refresh ist historisch
 * und bleibt, damit bestehende Cron-Einträge weiterlaufen.
 *
 * Cron-Frequenz: alle 30 Minuten (Seed-Migration 20260505000002).
 *
 *   php cli/intra.php changelog:refresh
 *   php cli/intra.php changelog:refresh --limit=10
 */
#[AsCommand(
    name: 'changelog:refresh',
    description: 'Aktualisiert die Forum-Ankündigungen fürs Dashboard',
)]
final class ChangelogRefreshCommand extends Command
{
    public function __construct(
        private readonly ChangelogClient $client,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Anzahl Themen, die gespeichert werden (Hard-Cap 25)',
            '10'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        if ($limit < 1) {
            $limit = 10;
        }

        $output->writeln(sprintf('<info>Refresh Forum-Ankündigungen (limit=%d) …</info>', $limit));
        $result = $this->client->refresh($limit);

        $status = (int) $result['status'];

        $tag = $result['success'] ? 'info' : 'comment';
        $output->writeln(sprintf(
            '<%s>HTTP %d — %s</%s>',
            $tag,
            $status,
            $result['message'],
            $tag,
        ));

        if ($result['success'] || $status === 304) {
            return Command::SUCCESS;
        }

        // Transiente Forum-Probleme sind KEIN Cron-Fehler — Stale-Cache bleibt
        // gemaess Spec stehen, beim naechsten Tick wird erneut probiert. Dafuer
        // den Cron-Job nicht roten Toast werfen lassen.
        //   - 0   = Verbindung/Timeout
        //   - 429 = Rate-Limit
        //   - 5xx = Forum-Server-Fehler
        if ($status === 0 || $status === 429 || $status >= 500) {
            return Command::SUCCESS;
        }

        // Permanente Fehler (403, 404 falsche Kategorie, 4xx allgemein)
        // werden weiterhin als Fehler gemeldet — die brauchen Admin-Aufmerksamkeit.
        return Command::FAILURE;
    }
}
