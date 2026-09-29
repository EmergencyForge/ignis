<?php

declare(strict_types=1);

namespace Plugin\Mail\Console;

use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Mail\AttachmentStorage;
use Plugin\Mail\Models\Attachment;
use Plugin\Mail\Models\Delivery;
use Plugin\Mail\Models\Message;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Räumt zweierlei auf, nächtlich über intra_cron_jobs (`mail.cleanup`):
 *
 * - Entwürfe, die seit STALE_AFTER_DAYS Tagen niemand angefasst hat,
 *   samt Anhängen, Zustellung und Zeile. Ein Entwurf hat nur die eine
 *   Zustellung seines Besitzers, niemand sonst braucht ihn.
 * - Anhänge gesendeter Mails, von denen jeder Beteiligte seine Kopie
 *   endgültig gelöscht hat. Die Nachrichtenzeile bleibt (Thread,
 *   `in_reply_to`), nur Dateien und Anhangzeilen gehen.
 *
 * Idempotent: die Filter sind reine Lesebedingungen.
 *
 *   php cli/intra.php mail:cleanup
 */
#[AsCommand(
    name: 'mail:cleanup',
    description: 'Entfernt alte Entwürfe und Anhänge von Mails, die niemand mehr hat',
)]
final class MailCleanupCommand extends Command
{
    private const STALE_AFTER_DAYS = 7;

    public function __construct(private readonly AttachmentStorage $attachments)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::STALE_AFTER_DAYS * 86400);

        $drafts = 0;
        $files  = 0;
        foreach (Message::query()->where('status', 'draft')->where('updated_at', '<', $cutoff)->with('attachments')->get() as $draft) {
            foreach ($draft->attachments as $attachment) {
                $this->attachments->delete($attachment);
                $files++;
            }
            Delivery::query()->where('message_id', $draft->id)->delete();
            $draft->delete();
            $drafts++;
        }

        $abandoned = Capsule::table('intra_mail_attachments as a')
            ->join('intra_mail_messages as m', 'm.id', '=', 'a.message_id')
            ->where('m.status', 'sent')
            ->whereNotExists(static function ($q): void {
                $q->selectRaw('1')->from('intra_mail_deliveries as d')
                    ->whereColumn('d.message_id', 'a.message_id')
                    ->whereNull('d.deleted_at');
            })
            ->pluck('a.id')
            ->all();
        foreach (Attachment::query()->whereKey($abandoned)->get() as $attachment) {
            $this->attachments->delete($attachment);
            $files++;
        }

        $output->writeln("<info>Entwürfe entfernt:</info> $drafts, Anhänge entfernt: $files");

        return Command::SUCCESS;
    }
}
