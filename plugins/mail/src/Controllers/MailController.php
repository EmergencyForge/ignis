<?php

declare(strict_types=1);

namespace Plugin\Mail\Controllers;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\ErrorPage;
use App\Notifications\NotificationManager;
use App\Security\CsrfProtection;
use App\Session\SessionManager;
use DateTimeImmutable;
use DomainException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Mail\Address;
use EmergencyForge\Mail\DeliveryRole;
use EmergencyForge\Mail\Draft;
use EmergencyForge\Mail\Folder;
use EmergencyForge\Mail\MailboxRef;
use EmergencyForge\Mail\Mailer;
use EmergencyForge\Mail\NewMessage;
use EmergencyForge\Mail\OriginalMessage;
use EmergencyForge\Mail\Recipient;
use EmergencyForge\Mail\RecipientResolver;
use EmergencyForge\Mail\RecipientSet;
use EmergencyForge\Mail\ReplyBuilder;
use EmergencyForge\Mail\SignatureAppender;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use Plugin\Mail\AttachmentStorage;
use Plugin\Mail\MailAddressRules;
use Plugin\Mail\MailBodyRenderer;
use Plugin\Mail\MailDirectory;
use Plugin\Mail\MailMessageStore;
use Plugin\Mail\Models\Attachment;
use Plugin\Mail\Models\Delivery;
use Plugin\Mail\Models\Mailbox;
use Plugin\Mail\Models\MailList;
use Plugin\Mail\Models\Message;
use Plugin\Mail\Models\Signature;

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
    use RendersPages;

    public const MAX_BODY_JSON_BYTES = 200_000;
    public const MAX_SUBJECT_LENGTH = 255;
    public const MAX_RAW_RECIPIENTS = 100;
    public const MAX_RESOLVED_DELIVERIES = 500;

    private const BODY_JSON_MAX_DEPTH = 64;
    private const MOVABLE_FOLDERS = ['inbox', 'archive', 'trash', 'restore'];

    /**
     * ponytail: kein Blättern, die neuesten MAX_LIST_ITEMS je Ordner. Ein
     * Rollenspiel-Postfach läuft nicht über; ListQuery nachrüsten, wenn doch.
     */
    private const MAX_LIST_ITEMS = 100;

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

    // ── Seiten ────────────────────────────────────────────────────

    /** GET /mail — der Posteingang. */
    public function index(Request $request): Response
    {
        return $this->folder($request, 'inbox');
    }

    /** GET /mail/{folder} — Arbeitsbereich mit leerem Lesebereich. */
    public function folder(Request $request, string $folder): Response
    {
        $mailbox = Mailbox::current();

        return $mailbox === null ? $this->noMailboxPage() : $this->renderFolder($mailbox, $folder, null);
    }

    /**
     * GET /mail/{folder}/{id} — derselbe Arbeitsbereich mit der Mail im
     * Lesebereich (schmale Schirme, ohne JS, Link aus der Glocke).
     */
    public function messagePage(Request $request, string $folder, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return $this->noMailboxPage();
        }
        $delivery = $this->deliveryIn((int) $id, $mailbox, $folder);
        if ($delivery === null) {
            return ErrorPage::notFound($request->path);
        }

        return $this->renderFolder($mailbox, $folder, $delivery);
    }

    /** GET /mail/{folder}/{id}/preview — nur der Lesebereich (workbench.js). */
    public function messagePreview(Request $request, string $folder, string $id): Response
    {
        $mailbox  = Mailbox::current();
        $delivery = $mailbox !== null ? $this->deliveryIn((int) $id, $mailbox, $folder) : null;
        if ($mailbox === null || $delivery === null) {
            return Response::html('<p class="ignis-preview__muted">Diese Mail gibt es hier nicht mehr.</p>', 404)->withHeader('Cache-Control', 'private, no-store');
        }

        return $this->page('mail/_reading-pane', $this->readingPane($delivery, $mailbox, $folder));
    }

    /** GET /mail/compose — neue Mail, ohne Schreibzugriff. */
    public function composeNew(Request $request): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return $this->noMailboxPage();
        }

        $to = [];
        $raw = $request->query['to'] ?? null;
        if (is_string($raw) && $raw !== '' && strlen($raw) <= 254) {
            $to[] = MailAddressRules::normalize($raw);
        }

        return $this->composeView($mailbox, [
            'title'      => 'Neue Mail',
            'recipients' => ['to' => $to],
            'bodyJson'   => $this->withSignature(['type' => 'doc', 'content' => [['type' => 'paragraph']]], $mailbox),
        ]);
    }

    /** GET /mail/compose/reply/{id} */
    public function composeReply(Request $request, string $id): Response
    {
        return $this->composeFromOriginal($id, 'Antworten', static fn (OriginalMessage $o, MailboxRef $as): Draft => ReplyBuilder::reply($o, $as));
    }

    /** GET /mail/compose/reply-all/{id} — ohne BCC und ohne mich selbst. */
    public function composeReplyAll(Request $request, string $id): Response
    {
        return $this->composeFromOriginal($id, 'Allen antworten', static fn (OriginalMessage $o, MailboxRef $as): Draft => ReplyBuilder::replyAll($o, $as));
    }

    /** GET /mail/compose/forward/{id} — Anhänge kommen beim Anlegen des Entwurfs mit. */
    public function composeForward(Request $request, string $id): Response
    {
        return $this->composeFromOriginal($id, 'Weiterleiten', static fn (OriginalMessage $o, MailboxRef $as): Draft => ReplyBuilder::forward($o));
    }

    /** GET /mail/compose/draft/{id} — eigenen Entwurf weiter bearbeiten. */
    public function composeDraft(Request $request, string $id): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return $this->noMailboxPage();
        }
        $draft = $this->ownDraft((int) $id, $mailbox);
        if ($draft === null) {
            return ErrorPage::notFound($request->path);
        }

        $header = $draft->header_json ?? [];

        return $this->composeView($mailbox, [
            'title'       => 'Entwurf bearbeiten',
            'draftId'     => $draft->id,
            'subject'     => $draft->subject,
            'recipients'  => ['to' => (array) ($header['to'] ?? []), 'cc' => (array) ($header['cc'] ?? []), 'bcc' => (array) ($header['bcc'] ?? [])],
            'bodyJson'    => $draft->body_json,
            'inReplyTo'   => $draft->in_reply_to,
            'attachments' => $draft->attachments->all(),
        ]);
    }

    /** GET /mail/signature */
    public function signatureForm(Request $request): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return $this->noMailboxPage();
        }
        $own = Signature::query()->where('mailbox_id', $mailbox->id)->first();

        return $this->page('mail/signature', [
            'bodyJson' => $own->body_json ?? $this->defaultSignature() ?? ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            'hasOwn'   => $own !== null,
        ]);
    }

    /**
     * POST /mail/signature — eine leere eigene Signatur ist eine
     * Entscheidung („keine“) und fällt nicht auf die Standard-Signatur zurück.
     */
    public function saveSignature(Request $request): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return $this->noMailboxPage();
        }

        $body = self::decodeBody($request->post['body_json'] ?? null);
        if (!is_array($body)) {
            Flash::error(is_string($body) ? $body : 'Die Signatur fehlt.');

            return Response::redirect(self::basePath() . 'mail/signature');
        }

        Signature::query()->updateOrCreate(['mailbox_id' => $mailbox->id], ['body_json' => $body, 'updated_at' => date('Y-m-d H:i:s')]);
        Flash::success('Signatur gespeichert.');

        return Response::redirect(self::basePath() . 'mail/signature');
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

        $copied = [];
        $draft  = Message::query()->find($draftId);
        if ($draft !== null && $original !== null && self::positiveInt($request->post['forward_from'] ?? null) !== null) {
            $this->attachments->copyAll($original, $draft);
            foreach ($draft->attachments()->get() as $attachment) {
                $copied[] = ['id' => $attachment->id, 'name' => $attachment->original_name];
            }
        }

        return self::json(['success' => true, 'messageId' => $draftId, 'attachments' => $copied]);
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
            $sent = $this->participantMessage((int) $id, $mailbox);

            return $sent !== null && $sent->sender_mailbox_id === $mailbox->id
                ? self::alreadySent()
                : self::json(['success' => false, 'message' => 'Entwurf wurde nicht gefunden.'], 404);
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

        $recipients = $fields['recipients'];
        if ($recipients->to === [] && $recipients->cc === [] && $recipients->bcc === []) {
            return self::json(['success' => false, 'message' => 'Die Mail hat keine Empfänger.'], 422);
        }

        $error = $this->restrictedListError($recipients)
            ?? $this->resolvedCountError($recipients, $mailbox);
        if ($error !== null) {
            return $error;
        }

        // Senden und Zustellen in einer Transaktion, unter Sperre der Zeile:
        // zwei gleichzeitige Sende-Requests stellen nicht doppelt zu, und ein
        // Anhang, der gleichzeitig hochlädt, wartet auf die Sperre und sieht
        // danach „gesendet“ (AttachmentStorage::store()).
        try {
            $result = Capsule::connection()->transaction(function () use ($draft, $mailbox, $fields): array {
                $locked = Message::query()->whereKey($draft->id)->lockForUpdate()->first();
                if ($locked === null || $locked->status !== 'draft' || $locked->sender_mailbox_id !== $mailbox->id) {
                    throw new DomainException('bereits gesendet');
                }

                return $this->mailer->sendDraft(
                    $locked->id,
                    MailDirectory::ref($mailbox),
                    $fields['subject'],
                    $fields['body'] ?? $locked->body_json,
                    $fields['recipients'],
                    $locked->thread_id,
                    $locked->in_reply_to,
                );
            });
        } catch (DomainException) {
            return self::alreadySent();
        } catch (InvalidArgumentException $e) {
            return self::json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // Die Glocke erst nach dem Commit: keine Benachrichtigung zu einer
        // Mail, die es am Ende nicht gibt.
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
            'Content-Disposition'    => self::contentDisposition(AttachmentStorage::nameFor($attachment->original_name, $attachment->mime)),
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

    // ── Seiten-Helfer ─────────────────────────────────────────────

    /**
     * Die Ordner in der Reihenfolge der Leiste.
     *
     * @return array<string, array{label:string, icon:string}>
     */
    public static function folders(): array
    {
        return [
            'inbox'   => ['label' => 'Posteingang', 'icon' => 'fa-inbox'],
            'sent'    => ['label' => 'Gesendet', 'icon' => 'fa-paper-plane'],
            'drafts'  => ['label' => 'Entwürfe', 'icon' => 'fa-file-pen'],
            'archive' => ['label' => 'Archiv', 'icon' => 'fa-box-archive'],
            'trash'   => ['label' => 'Papierkorb', 'icon' => 'fa-trash'],
        ];
    }

    private function renderFolder(Mailbox $mailbox, string $folder, ?Delivery $selected): Response
    {
        $ids = Capsule::table('intra_mail_deliveries as d')
            ->join('intra_mail_messages as m', 'm.id', '=', 'd.message_id')
            ->where('d.mailbox_id', $mailbox->id)
            ->where('d.folder', $folder)
            ->whereNull('d.deleted_at')
            ->orderByRaw('COALESCE(m.sent_at, m.updated_at, m.created_at) DESC')
            ->orderByDesc('m.id')
            ->limit(self::MAX_LIST_ITEMS)
            ->pluck('d.id')
            ->all();
        $loaded = Delivery::query()->with(['message.senderMailbox', 'message.attachments'])->whereKey($ids)->get()->keyBy('id');

        // An sich selbst adressiert und zusammen verschoben: eine Zeile je Mail.
        $unique = [];
        foreach ($ids as $id) {
            $delivery = $loaded->get($id);
            if ($delivery !== null) {
                $unique[$delivery->message_id] ??= $delivery;
            }
        }

        return $this->page('mail/index', [
            'folder'         => $folder,
            'folders'        => self::folders(),
            'mailbox'        => $mailbox,
            'deliveries'     => array_values($unique),
            'unreadCounts'   => self::unreadCounts($mailbox),
            'readingPane'    => $selected !== null ? $this->readingPane($selected, $mailbox, $folder) : null,
            'canManageLists' => self::canManageLists(),
        ]);
    }

    /**
     * Ungelesene je Ordner, nur empfangene Kopien (die Absenderkopie liest
     * niemand „ungelesen“).
     *
     * @return array<string,int>
     */
    public static function unreadCounts(Mailbox $mailbox): array
    {
        return Capsule::table('intra_mail_deliveries')
            ->where('mailbox_id', $mailbox->id)->where('role', '!=', 'sender')
            ->whereNull('deleted_at')->whereNull('read_at')
            ->selectRaw('folder, COUNT(DISTINCT message_id) as c')->groupBy('folder')
            ->pluck('c', 'folder')->map(static fn ($c): int => (int) $c)->all();
    }

    /** Die Kopie der Mail in genau diesem Ordner; ein fremder Ordner in der URL findet nichts. */
    private function deliveryIn(int $messageId, Mailbox $mailbox, string $folder): ?Delivery
    {
        $rows = Delivery::query()->with(['message.senderMailbox', 'message.attachments'])
            ->where('message_id', $messageId)->where('mailbox_id', $mailbox->id)
            ->where('folder', $folder)->where('deleted_at', null)
            ->get();

        // An sich selbst: die empfangene Kopie vor der Absenderkopie.
        return $rows->first(static fn (Delivery $d): bool => $d->role !== 'sender') ?? $rows->first();
    }

    /** @return array<string,mixed> Variablen für _reading-pane.php */
    private function readingPane(Delivery $delivery, Mailbox $mailbox, string $folder): array
    {
        $message = $delivery->message;
        $isDraft = $message->status === 'draft';

        return [
            'folder'        => $folder,
            'message'       => $message,
            'isDraft'       => $isDraft,
            'header'        => self::visibleHeader($message, $mailbox, $delivery),
            'bodyHtml'      => $isDraft ? $this->renderer->render($message->body_json) : (string) $message->body_html,
            'needsMarkRead' => $delivery->read_at === null && $delivery->role !== 'sender',
        ];
    }

    /**
     * BCC-Regel: die Absenderkopie sieht die ganze Liste, ein BCC-Empfänger
     * nur sich selbst, alle anderen nichts. Die einzige Stelle, an der das
     * entschieden wird.
     *
     * @return array{to:list<string>,cc:list<string>,bcc:list<string>}
     */
    public static function visibleHeader(Message $message, Mailbox $mailbox, Delivery $delivery): array
    {
        $header = $message->header_json ?? [];
        $list   = static fn (string $key): array => array_values(array_filter((array) ($header[$key] ?? []), 'is_string'));

        return [
            'to'  => $list('to'),
            'cc'  => $list('cc'),
            'bcc' => $delivery->role === 'sender' ? $list('bcc') : ($delivery->role === 'bcc' ? [$mailbox->address] : []),
        ];
    }

    /**
     * @param callable(OriginalMessage, MailboxRef): Draft $build
     */
    private function composeFromOriginal(string $id, string $title, callable $build): Response
    {
        $mailbox = Mailbox::current();
        if ($mailbox === null) {
            return $this->noMailboxPage();
        }
        $message = $this->participantSentMessage((int) $id, $mailbox);
        if ($message === null) {
            return ErrorPage::notFound();
        }

        $draft = $build(new OriginalMessage(
            id: $message->id,
            threadId: $message->thread_id,
            subject: $message->subject,
            sender: MailDirectory::ref($message->senderMailbox),
            senderDisplayName: $message->senderMailbox->display_name,
            // Die Vorlage ohne BCC: allen antworten geht nie an BCC.
            recipients: new RecipientSet(
                to: array_map(static fn (string $a): Recipient => new Recipient(new Address($a)), array_values(array_filter((array) ($message->header_json['to'] ?? []), 'is_string'))),
                cc: array_map(static fn (string $a): Recipient => new Recipient(new Address($a)), array_values(array_filter((array) ($message->header_json['cc'] ?? []), 'is_string'))),
            ),
            bodyJson: $message->body_json,
            sentAt: DateTimeImmutable::createFromInterface($message->sent_at ?? $message->created_at ?? new DateTimeImmutable()),
        ), MailDirectory::ref($mailbox));

        $addresses = static fn (array $recipients): array => array_map(static fn (Recipient $r): string => $r->address->value, $recipients);

        return $this->composeView($mailbox, [
            'title'        => $title,
            'subject'      => mb_substr($draft->subject, 0, self::MAX_SUBJECT_LENGTH),
            'recipients'   => ['to' => $addresses($draft->recipients->to), 'cc' => $addresses($draft->recipients->cc)],
            'bodyJson'     => $this->withSignature($draft->bodyJson, $mailbox),
            'inReplyTo'    => $draft->forwardAttachments ? null : $message->id,
            'forwardFrom'  => $draft->forwardAttachments ? $message->id : null,
            'forwardNames' => $draft->forwardAttachments ? $message->attachments->pluck('original_name')->all() : [],
        ]);
    }

    /** @param array<string,mixed> $overrides */
    private function composeView(Mailbox $mailbox, array $overrides): Response
    {
        $data = $overrides + [
            'draftId'      => null,
            'subject'      => '',
            'recipients'   => [],
            'bodyJson'     => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            'inReplyTo'    => null,
            'forwardFrom'  => null,
            'forwardNames' => [],
            'attachments'  => [],
        ];

        // Eingetragene Adressen brauchen eine Beschriftung, bevor die
        // Vorschläge des Adressbuchs geladen sind.
        $options = [];
        foreach (['to', 'cc', 'bcc'] as $key) {
            $options[$key] = array_map(fn (string $address): array => ['value' => $address, 'label' => $this->addressLabel($address)], array_values(array_filter((array) ($data['recipients'][$key] ?? []), 'is_string')));
        }
        $data['recipients'] = $options;

        return $this->page('mail/compose', $data);
    }

    private function addressLabel(string $address): string
    {
        $mailbox = $this->directory->findMailbox($address);
        if ($mailbox !== null) {
            return ($mailbox->displayName ?? $address) . ' <' . $mailbox->address . '>';
        }
        $list = $this->directory->findList($address);

        return $list !== null ? ($list->displayName ?? $address) . ' <' . $list->address . '> · Verteiler' : $address;
    }

    /**
     * Hängt die Signatur an: die eigene, sonst die Standard-Signatur. Ohne
     * Inhalt keine Signatur, auch kein einsamer Trenner „-- “.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function withSignature(array $body, Mailbox $mailbox): array
    {
        $own       = Signature::query()->where('mailbox_id', $mailbox->id)->first();
        $signature = $own !== null ? $own->body_json : $this->defaultSignature();
        if ($signature === null || !self::hasText($signature)) {
            return $body;
        }

        return SignatureAppender::append($body, $signature);
    }

    /** @return array<string,mixed>|null */
    private function defaultSignature(): ?array
    {
        $raw = Capsule::table('intra_config')->where('config_key', 'MAIL_DEFAULT_SIGNATURE')->value('config_value');
        $doc = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($doc) && ($doc['type'] ?? null) === 'doc' ? $doc : null;
    }

    /** Enthält ein Editor-Dokument irgendwo Text? Leere Absätze zählen nicht. */
    private static function hasText(mixed $node): bool
    {
        if (!is_array($node)) {
            return false;
        }
        if (($node['type'] ?? null) === 'text' && trim((string) ($node['text'] ?? '')) !== '') {
            return true;
        }
        foreach ((array) ($node['content'] ?? []) as $child) {
            if (self::hasText($child)) {
                return true;
            }
        }

        return false;
    }

    private function noMailboxPage(): Response
    {
        $userId = SessionManager::userId();
        $state  = match (true) {
            $userId === null => 'none',
            Mailbox::ownedBy($userId) !== null => 'inactive',
            Mailbox::mitarbeiterIdForUser($userId) !== null => 'unassigned',
            default => 'none',
        };

        return $this->page('mail/no-mailbox', ['state' => $state]);
    }

    /**
     * Text eines Posts als Editor-Dokument. Die Größe zählt vor dem
     * Dekodieren. null = nicht mitgeschickt, ein String ist die Meldung.
     *
     * @return array<string,mixed>|string|null
     */
    private static function decodeBody(mixed $raw): array|string|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_string($raw)) {
            return 'Ungültiger Text.';
        }
        if (strlen($raw) > self::MAX_BODY_JSON_BYTES) {
            return 'Der Text ist zu groß (höchstens 200 KB).';
        }
        $decoded = json_decode($raw, true, self::BODY_JSON_MAX_DEPTH);

        return is_array($decoded) && ($decoded['type'] ?? null) === 'doc' ? $decoded : 'Ungültiger Text.';
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

        $body = self::decodeBody($post['body_json'] ?? null);
        if (is_string($body)) {
            return $fail($body);
        }

        // Erst zählen, dann prüfen und Objekte bauen: 100.000 gepostete
        // Einträge kosten so nur ein count().
        $raw   = [];
        $total = 0;
        foreach (['to', 'cc', 'bcc'] as $key) {
            $value = $post[$key] ?? [];
            if ($value === '') {
                $value = [];
            }
            if (!is_array($value)) {
                return $fail('Ungültige Empfängerliste.');
            }
            $total    += count($value);
            $raw[$key] = $value;
        }
        if ($total > self::MAX_RAW_RECIPIENTS) {
            return $fail('Zu viele Empfänger (höchstens ' . self::MAX_RAW_RECIPIENTS . ' in An, CC und BCC zusammen).');
        }

        $lists = [];
        foreach ($raw as $key => $value) {
            $lists[$key] = [];
            foreach ($value as $entry) {
                if (!is_string($entry)) {
                    return $fail('Ungültige Empfängeradresse.');
                }
                $entry = trim($entry);
                if ($entry !== '' && strlen($entry) <= 254) {
                    $lists[$key][] = new Recipient(new Address($entry));
                }
            }
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
     * großer Verteiler umginge sonst die Grenze der rohen Eingabe. Keine
     * einzige Zustellung (nur unbekannte Adressen, inaktive oder gesperrte
     * Postfächer, leere Verteiler) ist ebenfalls eine Ablehnung.
     */
    private function resolvedCountError(RecipientSet $recipients, Mailbox $sender): ?Response
    {
        $plan  = RecipientResolver::resolve($recipients, MailDirectory::ref($sender), $this->directory);
        $count = count($plan->deliveries);
        if ($count > self::MAX_RESOLVED_DELIVERIES) {
            return self::json(['success' => false, 'message' => 'Zu viele Empfänger nach Auflösung der Verteiler (höchstens ' . self::MAX_RESOLVED_DELIVERIES . ').'], 422);
        }
        if ($count === 0) {
            return self::json(['success' => false, 'message' => $plan->unresolvedAddresses !== []
                ? 'Kein Empfänger ist zustellbar. Unbekannt, inaktiv oder gesperrt und damit nicht zustellbar: ' . implode(', ', $plan->unresolvedAddresses) . '.'
                : 'Kein Empfänger ist zustellbar: die Verteiler haben keine aktiven Mitglieder.'], 422);
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

    private static function alreadySent(): Response
    {
        return self::json(['success' => false, 'message' => 'Diese Mail ist bereits gesendet.'], 409);
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
