<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Federation\FederationSyncService;
use App\Logging\Logger;
use PDO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/intra.php federation:sync`
 *
 * Syncht alle Federation-Links, deren `last_sync_at` länger als
 * `sync_interval_minutes` zurückliegt. Personal, eNOTF und Einsätze
 * werden pro Link abgerufen, wenn der zugehörige Consume-Flag gesetzt ist.
 */
#[AsCommand(
    name: 'federation:sync',
    description: 'Syncht alle fälligen Federation-Links',
)]
final class FederationSyncCommand extends Command
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly FederationSyncService $sync,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $links = $this->getDueLinks();
        if (count($links) === 0) {
            $output->writeln('<comment>Keine Links fällig.</comment>');
            return Command::SUCCESS;
        }

        $total = 0;
        $errors = 0;
        foreach ($links as $link) {
            $linkId = (int) $link['id'];
            $label  = (string) ($link['instance_name'] ?? $link['instance_url'] ?? 'link #' . $linkId);

            if ((int) $link['consume_personnel'] === 1) {
                $r = $this->sync->syncPersonnel($linkId);
                $this->reportRun($output, 'personnel', $label, $r, $total, $errors);
            }
            if ((int) $link['consume_enotf'] === 1) {
                $r = $this->sync->syncEnotf($linkId);
                $this->reportRun($output, 'enotf', $label, $r, $total, $errors);
            }
            if ((int) $link['consume_fire'] === 1) {
                $r = $this->sync->syncFireIncidents($linkId);
                $this->reportRun($output, 'fire', $label, $r, $total, $errors);
            }
        }

        $output->writeln("<info>federation:sync</info> — {$total} Datensatz-Gruppen synchronisiert, {$errors} Fehler.");
        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getDueLinks(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM intra_federation_links
              WHERE is_active = 1
                AND (last_sync_at IS NULL
                     OR last_sync_at < DATE_SUB(NOW(), INTERVAL COALESCE(sync_interval_minutes, 60) MINUTE))"
        );
        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @param array<string,mixed> $result
     */
    private function reportRun(
        OutputInterface $output,
        string $kind,
        string $label,
        array $result,
        int &$total,
        int &$errors,
    ): void {
        $success = (bool) ($result['success'] ?? false);
        $records = (int) ($result['records'] ?? 0);
        if ($success) {
            $total++;
            $output->writeln("  <info>✓</info> {$kind} {$label}: {$records} Einträge");
        } else {
            $errors++;
            $err = (string) ($result['error'] ?? 'unknown');
            $output->writeln("  <error>✗</error> {$kind} {$label}: {$err}");
            Logger::warning('federation:sync step failed', [
                'kind'  => $kind,
                'label' => $label,
                'error' => $err,
            ]);
        }
    }
}
