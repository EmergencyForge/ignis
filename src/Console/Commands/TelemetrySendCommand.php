<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Telemetry\TelemetryManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sendet einen Telemetrie-Heartbeat an den EmergencyForge-Hub.
 *
 * Ersetzt den alten `cron/telemetry-cron.php` für CLI-fähige Setups —
 * der Legacy-Cron bleibt bestehen, damit existierende Crontabs nichts
 * ändern müssen, aber neue Installationen sollten den Command nutzen.
 *
 *   php cli/intra.php telemetry:send
 *   php cli/intra.php telemetry:send --force   # auch wenn kein Heartbeat fällig
 */
#[AsCommand(
    name: 'telemetry:send',
    description: 'Sendet einen Telemetrie-Heartbeat',
)]
final class TelemetrySendCommand extends Command
{
    public function __construct(
        private readonly TelemetryManager $telemetry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Heartbeat senden auch wenn noch nicht fällig');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->telemetry->isEnabled()) {
            $output->writeln('<comment>Telemetrie ist deaktiviert.</comment>');
            return Command::SUCCESS;
        }

        $force = (bool) $input->getOption('force');
        if (!$force && !$this->telemetry->shouldSendHeartbeat()) {
            $output->writeln('<comment>Heartbeat noch nicht fällig (--force zum Erzwingen).</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Sende Telemetrie-Heartbeat …</info>');
        $result = $this->telemetry->sendHeartbeat();

        if (!empty($result['success'])) {
            $output->writeln('<info>' . ($result['message'] ?? 'OK') . '</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>' . ($result['message'] ?? 'Heartbeat fehlgeschlagen') . '</error>');
        return Command::FAILURE;
    }
}
