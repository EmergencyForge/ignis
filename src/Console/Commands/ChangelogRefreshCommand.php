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
 * Holt die letzten Changelog-Eintraege vom Hub (emergencyforge.de) und legt
 * sie im lokalen intra_changelog_cache ab. Das Admin-Dashboard liest danach
 * ausschliesslich aus dem Cache — dieser Command ist die einzige Stelle,
 * an der wir den Hub kontaktieren.
 *
 * Empfohlene Cron-Frequenz: alle 15-30 Minuten. Hub setzt Cache-Control
 * max-age=600 + ETag, also bekommt jeder zweite/dritte Refresh nur ein 304.
 *
 *   php cli/intra.php changelog:refresh
 *   php cli/intra.php changelog:refresh --limit=10
 */
#[AsCommand(
    name: 'changelog:refresh',
    description: 'Aktualisiert den lokalen Changelog-Cache vom Hub',
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
            'Anzahl Eintraege die der Hub liefern soll (Hard-Cap 25)',
            '10'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        if ($limit < 1) {
            $limit = 10;
        }

        $output->writeln(sprintf('<info>Refresh Changelog-Cache (limit=%d) …</info>', $limit));
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

        // Transiente Hub-Probleme sind KEIN Cron-Fehler — Stale-Cache bleibt
        // gemaess Spec stehen, beim naechsten Tick wird erneut probiert. Dafuer
        // den Cron-Job nicht roten Toast werfen lassen.
        //   - 0   = Verbindung/Timeout
        //   - 429 = Rate-Limit
        //   - 5xx = Hub-Server-Fehler / Hub noch nicht deployed
        if ($status === 0 || $status === 429 || $status >= 500) {
            return Command::SUCCESS;
        }

        // Permanente Fehler (401 falscher Token, 404 Endpoint, 4xx allgemein)
        // werden weiterhin als Fehler gemeldet — die brauchen Admin-Aufmerksamkeit.
        return Command::FAILURE;
    }
}
