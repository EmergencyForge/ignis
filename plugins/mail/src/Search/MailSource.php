<?php

declare(strict_types=1);

namespace Plugin\Mail\Search;

use App\Auth\Permissions;
use App\Search\SearchSourceInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Mail\Models\Mailbox;

/**
 * Gruppe „Mails“ der globalen Suche: Betreff, Absender und Text, nur die
 * eigenen, nicht gelöschten Kopien. Der Empfängerkopf (`header_json`,
 * mit BCC) wird nie durchsucht und nie gezeigt, ein Treffer verrät also
 * keinem Empfänger, wer sonst in BCC stand.
 *
 * Ein Textfund zählt nur, wenn der Suchbegriff auch im Klartext steht —
 * `body_html` enthält Tags, „strong“ oder „href“ träfe sonst jede Mail.
 */
final class MailSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'mails';
    }

    public function label(): string
    {
        return 'Mails';
    }

    public function allowed(): bool
    {
        return Permissions::check(['admin', 'mail.use']) && Mailbox::current() !== null;
    }

    public function search(string $q, int $limit): array
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return [];
        }

        $like = '%' . ignis_like_prefix($q) . '%';
        $rows = Capsule::table('intra_mail_deliveries as d')
            ->join('intra_mail_messages as m', 'm.id', '=', 'd.message_id')
            ->join('intra_mail_mailboxes as s', 's.id', '=', 'm.sender_mailbox_id')
            ->where('d.mailbox_id', $mailbox->id)
            ->whereNull('d.deleted_at')
            ->where(static function ($w) use ($like): void {
                $w->where('m.subject', 'like', $like)
                    ->orWhere('s.display_name', 'like', $like)
                    ->orWhere('s.address', 'like', $like)
                    ->orWhere('m.body_html', 'like', $like);
            })
            ->orderByRaw('COALESCE(m.sent_at, m.updated_at, m.created_at) DESC')
            ->limit($limit * 4)
            ->get(['m.id', 'm.subject', 'm.body_html', 'm.status', 'm.sent_at', 'd.folder', 'd.role', 's.display_name', 's.address']);

        $needle = mb_strtolower($q);
        $items  = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if (isset($items[$id])) {
                continue;
            }
            $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $row->body_html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $hit  = str_contains(mb_strtolower((string) $row->subject . ' ' . $row->display_name . ' ' . $row->address), $needle)
                || str_contains(mb_strtolower($text), $needle);
            if (!$hit) {
                continue;
            }

            $items[$id] = [
                'label' => (string) $row->subject !== '' ? (string) $row->subject : '(kein Betreff)',
                'sub'   => ($row->status === 'draft' ? 'Entwurf' : 'Von ' . $row->display_name)
                    . ($row->sent_at !== null ? ' · ' . date('d.m.Y', (int) strtotime((string) $row->sent_at)) : ''),
                'href'  => search_base_path() . 'mail/' . $row->folder . '/' . $id,
            ];
            if (count($items) >= $limit) {
                break;
            }
        }

        return array_values($items);
    }
}
