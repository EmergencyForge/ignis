<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Telemetry\GlobalAnnouncementManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Announcements-Wartung: Cache vom EmergencyForge-Hub neu laden +
 * alte Dismissals wegräumen.
 *
 * Ersetzt den Announcement-Teil des alten `cron/telemetry-cron.php` —
 * wird einmal täglich via Cron ausgeführt:
 *
 *   php cli/intra.php announcements:refresh
 *   php cli/intra.php announcements:refresh --keep-days=60
 */
#[AsCommand(
    name: 'announcements:refresh',
    description: 'Aktualisiert den Announcements-Cache und räumt alte Dismissals auf',
)]
final class AnnouncementsRefreshCommand extends Command
{
    public function __construct(
        private readonly GlobalAnnouncementManager $announcements,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'keep-days',
            null,
            InputOption::VALUE_REQUIRED,
            'Dismissals älter als N Tage werden gelöscht',
            '90'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->announcements->isEnabled()) {
            $output->writeln('<comment>Announcements sind deaktiviert.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Aktualisiere Announcements-Cache …</info>');
        $result = $this->announcements->refreshCache();
        $output->writeln(($result['success'] ?? false)
            ? '<info>' . ($result['message'] ?? 'OK') . '</info>'
            : '<error>' . ($result['message'] ?? 'Refresh fehlgeschlagen') . '</error>'
        );

        $keepDays = (int) $input->getOption('keep-days');
        if ($keepDays < 1) {
            $keepDays = 90;
        }

        $output->writeln(sprintf('<info>Räume Dismissals älter als %d Tage auf …</info>', $keepDays));
        $deleted = $this->announcements->cleanupOldDismissals($keepDays);
        $output->writeln(sprintf('<info>Gelöscht: %d Einträge</info>', $deleted));

        return Command::SUCCESS;
    }
}
