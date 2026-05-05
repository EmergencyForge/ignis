<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Hub\BlogClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Holt aktuelle Blog-Posts vom Hub (emergencyforge.de) und legt sie im
 * lokalen `intra_blog_cache` ab. Schwester-Command zu changelog:refresh,
 * gleiches Verhalten bei transienten Hub-Fehlern (Timeout/429/5xx →
 * SUCCESS, kein Cron-Toast-Spam).
 *
 *   php cli/intra.php blog:refresh
 *   php cli/intra.php blog:refresh --limit=10 --category=announcement
 */
#[AsCommand(
    name: 'blog:refresh',
    description: 'Aktualisiert den lokalen Blog-Cache vom Hub',
)]
final class BlogRefreshCommand extends Command
{
    public function __construct(
        private readonly BlogClient $client,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Anzahl Posts die der Hub liefern soll (Hard-Cap 25)',
            '10'
        );
        $this->addOption(
            'category',
            null,
            InputOption::VALUE_REQUIRED,
            'Optional: nur Posts dieser Kategorie (announcement|devlog|community|tutorial)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        if ($limit < 1) {
            $limit = 10;
        }
        $category = $input->getOption('category');
        $category = is_string($category) && $category !== '' ? $category : null;

        $output->writeln(sprintf('<info>Refresh Blog-Cache (limit=%d%s) …</info>',
            $limit,
            $category !== null ? ", category={$category}" : '',
        ));
        $result = $this->client->refresh($limit, $category);

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

        // Transiente Hub-Probleme sind KEIN Cron-Fehler — gleiche Logik wie
        // ChangelogRefreshCommand. Stale-Cache bleibt, beim naechsten Tick
        // wird erneut probiert.
        if ($status === 0 || $status === 429 || $status >= 500) {
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
