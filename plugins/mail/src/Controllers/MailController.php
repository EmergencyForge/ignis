<?php

declare(strict_types=1);

namespace Plugin\Mail\Controllers;

use App\Auth\Permissions;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationManager;
use App\Security\CsrfProtection;
use App\Session\SessionManager;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Mail\Address;
use EmergencyForge\Mail\DeliveryRole;
use EmergencyForge\Mail\Folder;
use EmergencyForge\Mail\MailboxRef;
use EmergencyForge\Mail\Mailer;
use EmergencyForge\Mail\NewMessage;
use EmergencyForge\Mail\Recipient;
use EmergencyForge\Mail\RecipientResolver;
use EmergencyForge\Mail\RecipientSet;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use Plugin\Mail\AttachmentStorage;
use Plugin\Mail\MailBodyRenderer;
use Plugin\Mail\MailDirectory;
use Plugin\Mail\MailMessageStore;
use Plugin\Mail\Models\Attachment;
use Plugin\Mail\Models\Delivery;
use Plugin\Mail\Models\Mailbox;
use Plugin\Mail\Models\MailList;
use Plugin\Mail\Models\Message;

/**
 * Das eigene Postfach: Entwürfe, Senden, Ordner, Anhänge, Adressbuch.
 *
 * „Postfach“ ist immer das des angemeldeten Nutzers (Mailbox::current()),
 * es gibt keinen Parameter für ein fremdes. Jede Aktion prüft zusätzlich,
 * dass dieses Postfach an der Nachricht beteiligt ist; `mail.admin` ist
 * davon ausdrücklich nicht ausgenommen — ein Admin liest keine fremden
 * Mails.
 *
 * Schreibende JSON-Antworten tragen `csrf_token` und alle JSON-Antworten
 * `Cache-Control: private, no-store` (json()). ignis hat einen Token je
 * Sitzung, der sich nicht dreht; der Token in der Antwort hält das
 * Verfassen trotzdem auf dem Stand, falls die Sitzung neu angemeldet wurde.
 *
 * Grenzen wie in Lex: Betreff 255 Zeichen, Text 200 KB (die Größe wird vor
 * dem Dekodieren geprüft), höchstens 100 Einträge in An/CC/BCC und 500
 * Zustellungen nach Auflösung der Verteiler. Jeder gepostete Wert, der ein
 * Text sein soll, muss auch einer sein (`is_string`), ein Array an seiner
 * Stelle ist eine Ablehnung und keine Warnung.
 */
final class MailController extends Controller
{
    public const MAX_BODY_JSON_BYTES = 200_000;
    public const MAX_SUBJECT_LENGTH = 255;
    public const MAX_RAW_RECIPIENTS = 100;
    public const MAX_RESOLVED_DELIVERIES = 500;

    private const BODY_JSON_MAX_DEPTH = 64;
    private const MOVABLE_FOLDERS = ['inbox', 'archive', 'trash', 'restore'];

    private readonly Mailer $mailer;

    public function __construct(
        private readonly MailMessageStore $store,
        private readonly MailDirectory $directory,
        private readonly MailBodyRenderer $renderer,
        private readonly AttachmentStorage $attachments,
        private readonly NotificationManager $notifications,
    ) {
        $this->mailer = new Mailer($store, $directory, $renderer);
    }

    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    // ── Entwürfe und Senden ───────────────────────────────────────

    /**
     * POST /mail/drafts — legt den Entwurf an. Verfassen öffnet ohne
     * Schreibzugriff; der Entwurf entsteht erst mit der ersten Änderung.
     * `in_reply_to` und `forward_from` gelten nur für gesendete Mails, an
     * denen das eigene Postfach beteiligt ist; der Thread kommt von dort,
     * nie vom Client.
     */
    public function createDraft(Request $request): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return self::noMailbox();
        }

        $fields = $this->readComposeFields($request->post);
        if ($fields instanceof Response) {
            return $fields;
        }

        $original = null;
        foreach (['in_reply_to', 'forward_from'] as $key) {
            $id = self::positiveInt($request->post[$key] ?? null);
            if ($id === null) {
                continue;
            }
            $original = $this->participantSentMessage($id, $mailbox);
            if ($original === null) {
                return self::json(['success' => false, 'message' => 'Nachricht wurde nicht gefunden.'], 404);
            }
        }

        $draftId = $this->store->saveMessage(new NewMessage(
            sender: MailDirectory::ref($mailbox),
            subject: $fields['subject'],
            bodyJson: $fields['body'] ?? ['type' => 'doc', 'content' => []],
            bodyHtml: null,
            threadId: $original->thread_id ?? bin2hex(random_bytes(16)),
            inReplyTo: $original?->id,
            recipients: $fields['recipients'],
        ));
        $this->store->deliver($draftId, MailDirectory::ref($mailbox), DeliveryRole::Sender, Folder::Drafts);

        if ($original !== null && self::positiveInt($request->post['forward_from'] ?? null) !== null) {
            $draft = Message::query()->find($draftId);
            if ($draft !== null) {
                $this->attachments->copyAll($original, $draft);
            }
        }

        return self::json(['success' => true, 'messageId' => $draftId]);
    }

    /** POST /mail/drafts/{id} — Entwurf an Ort und Stelle speichern (Autosave). */
    public function updateDraft(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return self::noMailbox();
        }
        $draft = $this->ownDraft((int) $id, $mailbox);
        if ($draft === null) {
            return self::json(['success' => false, 'message' => 'Entwurf wurde nicht gefunden.'], 404);
        }

        $fields = $this->readComposeFields($request->post);
        if ($fields instanceof Response) {
            return $fields;
        }

        $this->store->updateMessage($draft->id, new NewMessage(
            sender: MailDirectory::ref($mailbox),
            subject: $fields['subject'],
            bodyJson: $fields['body'] ?? $draft->body_json,
            bodyHtml: null,
            threadId: $draft->thread_id,
            inReplyTo: $draft->in_reply_to,
            recipients: $fields['recipients'],
        ));

        return self::json(['success' => true, 'messageId' => $draft->id]);
    }

    /**
     * POST /mail/drafts/{id}/send — derselbe Datensatz wird gesendet, die ID
     * bleibt (Anhänge hängen daran). Ohne mitgeschickte Felder gilt der
     * gespeicherte Stand des Entwurfs.
     */
    public function sendDraft(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return self::noMailbox();
        }
        $draft = $this->ownDraft((int) $id, $mailbox);
        if ($draft === null) {
            return self::json(['success' => false, 'message' => 'Entwurf wurde nicht gefunden.'], 404);
        }

        $post = $request->post;
        if (!array_key_exists('subject', $post)) {
            $post['subject'] = $draft->subject;
        }
        if (!isset($post['to']) && !isset($post['cc']) && !isset($post['bcc'])) {
            $header = $draft->header_json ?? [];
            foreach (['to', 'cc', 'bcc'] as $key) {
                $post[$key] = array_values(array_filter((array) ($header[$key] ?? []), 'is_string'));
            }
        }

        $fields = $this->readComposeFields($post);
        if ($fields instanceof Response) {
            return $fields;
        }

        $error = $this->restrictedListError($fields['recipients'])
            ?? $this->resolvedCountError($fields['recipients'], $mailbox);
        if ($error !== null) {
            return $error;
        }

        try {
            $result = $this->mailer->sendDraft(
                $draft->id,
                MailDirectory::ref($mailbox),
                $fields['subject'],
                $fields['body'] ?? $draft->body_json,
                $fields['recipients'],
                $draft->thread_id,
                $draft->in_reply_to,
            );
        } catch (InvalidArgumentException $e) {
            return self::json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $this->notifyRecipients($draft->id, $mailbox, $fields['subject']);

        return self::json(['success' => true, 'messageId' => $result['messageId'], 'unresolvedAddresses' => $result['unresolvedAddresses']]);
    }

    // ── Ordner und Lesen ──────────────────────────────────────────

    /**
     * POST /mail/messages/{id}/move — archivieren, in den Papierkorb,
     * zurück (`restore`: gesendete Kopie nach „Gesendet“, sonst Posteingang).
     */
    public function move(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return self::noMailbox();
        }

        $folder = $request->post['folder'] ?? null;
        if (!is_string($folder) || !in_array($folder, self::MOVABLE_FOLDERS, true)) {
            return self::json(['success' => false, 'message' => 'Ungültiger Ordner.'], 422);
        }
        $message = $this->participantMessage((int) $id, $mailbox);
        if ($message === null || $message->status !== 'sent') {
            return self::json(['success' => false, 'message' => 'Nachricht wurde nicht gefunden.'], 404);
        }

        $own = Delivery::query()->where('message_id', $message->id)->where('mailbox_id', $mailbox->id)->whereNull('deleted_at');
        if ($folder === 'restore') {
            (clone $own)->where('role', 'sender')->update(['folder' => 'sent']);
            (clone $own)->where('role', '!=', 'sender')->update(['folder' => 'inbox']);
        } else {
            $own->update(['folder' => $folder]);
        }

        return self::json(['success' => true]);
    }

    /**
     * POST /mail/messages/{id}/read — gelesen (Standard) oder ungelesen
     * (`read=0`). Die Glocken-Einträge zu dieser Mail gehen mit auf gelesen.
     */
    public function markRead(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return self::noMailbox();
        }
        $message = $this->participantMessage((int) $id, $mailbox);
        if ($message === null) {
            return self::json(['success' => false, 'message' => 'Nachricht wurde nicht gefunden.'], 404);
        }

        $raw  = $request->post['read'] ?? '1';
        $read = !is_string($raw) || $raw !== '0';
        $this->store->markRead($message->id, MailDirectory::ref($mailbox), $read);

        $userId = SessionManager::userId();
        if ($read && $userId !== null) {
            Capsule::table('intra_notifications')
                ->where('user_id', $userId)->where('type', 'mail')->where('is_read', 0)
                ->where('link', self::basePath() . 'mail/inbox/' . $message->id)
                ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
        }

        return self::json(['success' => true]);
    }

    /**
     * POST /mail/messages/{id}/delete — die eigene Kopie endgültig löschen
     * (weicher Vermerk, die anderen Beteiligten behalten ihre). Bei einem
     * Entwurf gibt es niemanden sonst: Anhänge samt Dateien gehen gleich mit.
     */
    public function delete(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return self::noMailbox();
        }
        $message = $this->participantMessage((int) $id, $mailbox);
        if ($message === null) {
            return self::json(['success' => false, 'message' => 'Nachricht wurde nicht gefunden.'], 404);
        }

        if ($message->status === 'draft') {
            foreach ($message->attachments as $attachment) {
                $this->attachments->delete($attachment);
            }
        }
        $this->store->delete($message->id, MailDirectory::ref($mailbox));

        return self::json(['success' => true]);
    }

    // ── Anhänge ───────────────────────────────────────────────────

    /** POST /mail/drafts/{id}/attachments */
    public function uploadAttachment(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return self::noMailbox();
        }
        $draft = $this->ownDraft((int) $id, $mailbox);
        if ($draft === null) {
            return self::json(['success' => false, 'message' => 'Entwurf wurde nicht gefunden.'], 404);
        }

        $file = $request->files['file'] ?? null;
        if (!is_array($file) || is_array($file['name'] ?? null)) {
            return self::json(['success' => false, 'message' => 'Es wurde keine Datei ausgewählt.'], 422);
        }

        try {
            $attachment = $this->attachments->store($draft, $file);
        } catch (InvalidArgumentException $e) {
            return self::json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return self::json(['success' => true, 'attachmentId' => $attachment->id, 'name' => $attachment->original_name]);
    }

    /**
     * GET /mail/attachments/{id} — nur an Beteiligte mit einer nicht
     * gelöschten Kopie, als Download, nie inline.
     */
    public function downloadAttachment(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        $attachment = Attachment::query()->find((int) $id);
        if ($mailbox === null || $attachment === null || $this->participantMessage($attachment->message_id, $mailbox) === null) {
            return Response::text('Anhang wurde nicht gefunden.', 404)->withHeader('Cache-Control', 'private, no-store');
        }

        $file = $this->attachments->absolutePath($attachment);
        $contents = $file !== null ? file_get_contents($file) : false;
        if ($contents === false) {
            return Response::text('Anhang wurde nicht gefunden.', 404)->withHeader('Cache-Control', 'private, no-store');
        }

        return new Response(200, $contents, [
            'Content-Type'           => $attachment->mime,
            'Content-Disposition'    => self::contentDisposition($attachment->original_name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    /** POST /mail/attachments/{id}/delete — nur am eigenen Entwurf. */
    public function deleteAttachment(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return self::noMailbox();
        }
        $attachment = Attachment::query()->find((int) $id);
        if ($attachment === null || $this->ownDraft($attachment->message_id, $mailbox) === null) {
            return self::json(['success' => false, 'message' => 'Anhang wurde nicht gefunden.'], 404);
        }

        $this->attachments->delete($attachment);

        return self::json(['success' => true]);
    }

    // ── Adressbuch ────────────────────────────────────────────────

    /**
     * GET /mail/addressbook?q= — zustellbare Postfächer und Verteiler für
     * An/CC/BCC. Verteiler, an die der Nutzer nicht schreiben darf, fehlen.
     */
    public function addressbook(Request $request): Response
    {
        $raw = $request->query['q'] ?? '';
        $q   = is_string($raw) ? mb_substr(trim($raw), 0, 100) : '';
        $like = '%' . ignis_like_prefix($q) . '%';

        $mailboxes = Mailbox::query()->where('active', true)->where('locked', false)
            ->when($q !== '', static fn ($query) => $query->where(static fn ($w) => $w->where('address', 'like', $like)->orWhere('display_name', 'like', $like)))
            ->orderBy('display_name')->limit(20)->get(['address', 'display_name']);

        $lists = MailList::query()
            ->when(!self::canManageLists(), static fn ($query) => $query->where('senders', MailList::SENDERS_ALL))
            ->when($q !== '', static fn ($query) => $query->where(static fn ($w) => $w->where('address', 'like', $like)->orWhere('name', 'like', $like)))
            ->orderBy('name')->limit(20)->get(['address', 'name']);

        $results = [];
        foreach ($mailboxes as $mailbox) {
            $results[] = ['address' => $mailbox->address, 'label' => $mailbox->display_name . ' <' . $mailbox->address . '>'];
        }
        foreach ($lists as $list) {
            $results[] = ['address' => $list->address, 'label' => $list->name . ' <' . $list->address . '> · Verteiler'];
        }

        return self::json(['success' => true, 'results' => $results], 200, withToken: false);
    }

    // ── Prüfungen ─────────────────────────────────────────────────

    /**
     * Liest Betreff, Text und Empfänger eines Verfassen-Posts. Bei einem
     * Fehler die 422-Antwort, sonst die Felder (`body` null = nicht
     * mitgeschickt, der Aufrufer behält dann den gespeicherten Text).
     *
     * @param array<string,mixed> $post
     * @return array{subject: string, body: array<string,mixed>|null, recipients: RecipientSet}|Response
     */
    private function readComposeFields(array $post): array|Response
    {
        $fail = static fn (string $message): Response => self::json(['success' => false, 'message' => $message], 422);

        $subject = $post['subject'] ?? '';
        if (!is_string($subject)) {
            return $fail('Ungültiger Betreff.');
        }
        $subject = trim($subject);
        if (!mb_check_encoding($subject, 'UTF-8') || mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            return $fail('Der Betreff ist zu lang (höchstens ' . self::MAX_SUBJECT_LENGTH . ' Zeichen).');
        }

        $body = null;
        $raw  = $post['body_json'] ?? null;
        if ($raw !== null && $raw !== '') {
            if (!is_string($raw)) {
                return $fail('Ungültiger Text.');
            }
            // Die Größe zählt vor dem Dekodieren.
            if (strlen($raw) > self::MAX_BODY_JSON_BYTES) {
                return $fail('Der Text ist zu groß (höchstens 200 KB).');
            }
            $decoded = json_decode($raw, true, self::BODY_JSON_MAX_DEPTH);
            if (!is_array($decoded) || ($decoded['type'] ?? null) !== 'doc') {
                return $fail('Ungültiger Text.');
            }
            $body = $decoded;
        }

        $total = 0;
        $lists = [];
        foreach (['to', 'cc', 'bcc'] as $key) {
            $value = $post[$key] ?? [];
            if ($value === '' || $value === []) {
                $lists[$key] = [];
                continue;
            }
            if (!is_array($value)) {
                return $fail('Ungültige Empfängerliste.');
            }
            $recipients = [];
            foreach ($value as $entry) {
                if (!is_string($entry)) {
                    return $fail('Ungültige Empfängeradresse.');
                }
                $entry = trim($entry);
                if ($entry !== '' && strlen($entry) <= 254) {
                    $recipients[] = new Recipient(new Address($entry));
                }
            }
            $total += count($value);
            $lists[$key] = $recipients;
        }
        if ($total > self::MAX_RAW_RECIPIENTS) {
            return $fail('Zu viele Empfänger (höchstens ' . self::MAX_RAW_RECIPIENTS . ' in An, CC und BCC zusammen).');
        }

        return [
            'subject'    => $subject,
            'body'       => $body,
            'recipients' => new RecipientSet($lists['to'], $lists['cc'], $lists['bcc']),
        ];
    }

    /**
     * Verteiler mit `senders = managers` nehmen nur Post von der
     * Verteiler-Verwaltung an — in An, CC und BCC gleichermaßen.
     */
    private function restrictedListError(RecipientSet $recipients): ?Response
    {
        if (self::canManageLists()) {
            return null;
        }

        $addresses = array_map(
            static fn (Recipient $r): string => $r->address->value,
            [...$recipients->to, ...$recipients->cc, ...$recipients->bcc],
        );
        if ($addresses === []) {
            return null;
        }

        $restricted = MailList::query()->whereIn('address', $addresses)->where('senders', MailList::SENDERS_MANAGERS)->orderBy('name')->first();
        if ($restricted === null) {
            return null;
        }

        return self::json([
            'success' => false,
            'message' => 'An den Verteiler „' . $restricted->name . '“ (' . $restricted->address . ') darf nur schreiben, wer Verteiler verwaltet.',
        ], 422);
    }

    /**
     * Zählt die Zustellungen nach Auflösung der Verteiler: ein einzelner
     * großer Verteiler umginge sonst die Grenze der rohen Eingabe.
     */
    private function resolvedCountError(RecipientSet $recipients, Mailbox $sender): ?Response
    {
        $plan = RecipientResolver::resolve($recipients, MailDirectory::ref($sender), $this->directory);
        if (count($plan->deliveries) > self::MAX_RESOLVED_DELIVERIES) {
            return self::json(['success' => false, 'message' => 'Zu viele Empfänger nach Auflösung der Verteiler (höchstens ' . self::MAX_RESOLVED_DELIVERIES . ').'], 422);
        }

        return null;
    }

    /** Glocke: ein Eintrag je Empfänger-Konto, ohne den Absender selbst. */
    private function notifyRecipients(int $messageId, Mailbox $sender, string $subject): void
    {
        $mailboxIds = array_values(array_map('intval', Delivery::query()
            ->where('message_id', $messageId)->where('role', '!=', 'sender')
            ->pluck('mailbox_id')->unique()->all()));

        $userIds = array_values(array_diff(Mailbox::userIdsFor($mailboxIds), [(int) SessionManager::userId()]));
        if ($userIds === []) {
            return;
        }

        $this->notifications->notify('mail', $userIds, [
            'title'   => 'Neue Mail von ' . $sender->display_name,
            'message' => $subject,
            'link'    => self::basePath() . 'mail/inbox/' . $messageId,
        ]);
    }

    /** Die Nachricht, wenn das Postfach eine nicht gelöschte Kopie davon hat. */
    private function participantMessage(int $messageId, Mailbox $mailbox): ?Message
    {
        $message = Message::query()->find($messageId);
        if ($message === null) {
            return null;
        }

        $isParticipant = Delivery::query()->where('message_id', $message->id)->where('mailbox_id', $mailbox->id)
            ->whereNull('deleted_at')->exists();

        return $isParticipant ? $message : null;
    }

    /** Antworten und Weiterleiten gehen nur auf gesendete Mails. */
    private function participantSentMessage(int $messageId, Mailbox $mailbox): ?Message
    {
        $message = $this->participantMessage($messageId, $mailbox);

        return $message !== null && $message->status === 'sent' ? $message : null;
    }

    /** Ein Entwurf dieses Postfachs, den es nicht selbst gelöscht hat. */
    private function ownDraft(int $messageId, Mailbox $mailbox): ?Message
    {
        $message = $this->participantMessage($messageId, $mailbox);

        return $message !== null && $message->status === 'draft' && $message->sender_mailbox_id === $mailbox->id ? $message : null;
    }

    public static function canManageLists(): bool
    {
        return Permissions::check(['admin', 'mail.lists.manage']);
    }

    private static function positiveInt(mixed $value): ?int
    {
        return (is_string($value) || is_int($value)) && preg_match('/^[1-9]\d{0,9}$/', (string) $value) === 1 ? (int) $value : null;
    }

    /**
     * JSON mit `Cache-Control: private, no-store`; schreibende Antworten
     * tragen den aktuellen CSRF-Token.
     *
     * @param array<string,mixed> $data
     */
    public static function json(array $data, int $status = 200, bool $withToken = true): Response
    {
        if ($withToken) {
            $data['csrf_token'] = CsrfProtection::getResponseToken();
        }

        return Response::json($data, $status)->withHeader('Cache-Control', 'private, no-store');
    }

    private static function noMailbox(): Response
    {
        return self::json(['success' => false, 'message' => 'Kein aktives Postfach.'], 403);
    }

    public static function basePath(): string
    {
        return defined('BASE_PATH') ? (string) BASE_PATH : '/';
    }

    /**
     * Content-Disposition nach RFC 6266/5987: ASCII-Ersatz in `filename`,
     * der volle Name percent-kodiert in `filename*`.
     */
    public static function contentDisposition(string $name): string
    {
        $ascii = str_replace(['"', '\\'], '_', (string) preg_replace('/[^\x20-\x7E]/u', '_', $name));
        if ($ascii === '') {
            $ascii = 'anhang';
        }

        return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }
}
