<?php

declare(strict_types=1);

namespace Plugin\Mail\Controllers;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Notifications\NotificationManager;
use App\Session\SessionManager;
use App\Support\ListQuery;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\UniqueConstraintViolationException;
use Plugin\Mail\MailAddressRules;
use Plugin\Mail\Models\Mailbox;
use Plugin\Mail\SignatureText;

/**
 * Postfachverwaltung und Mail-Einstellungen (`mail.admin`).
 *
 * Verwaltet werden Postfächer, nicht ihr Inhalt: Adresse korrigieren,
 * sperren und entsperren, Domain wechseln (nur mit `mail.domain.choose`,
 * nur unter den erlaubten Domains). Diese Seiten lesen keine Nachricht,
 * keinen Betreff und keine Zustellung, nicht einmal Zähler dazu. Das
 * Audit-Log bekommt Postfach-Id und Adressen, nie Inhalte.
 *
 * Das Konto eines Postfachs (`user_id`) hängt hier nur um, wer es
 * ausdrücklich tut („Konto zuordnen“), und nie auf das eigene Konto.
 *
 * Eine geänderte Adresse bleibt dem Postfach vorbehalten
 * (intra_mail_address_history). Die eigene Adresse ändert hier niemand:
 * sich selbst eine Adresse aussuchen und die alte freigeben, das macht
 * ein anderer Admin.
 */
final class MailAdminController extends Controller
{
    use RendersPages;

    private const PATTERNS = ['initial_dot_last', 'first_dot_last'];

    public function __construct(
        private readonly MailAddressRules $rules,
        private readonly NotificationManager $notifications,
    ) {}

    /** GET /settings/mail/mailboxes */
    public function mailboxes(Request $request): Response
    {
        $list = ListQuery::fromQuery($request->query, [
            'name'    => 'mb.display_name',
            'address' => 'mb.address',
            'state'   => '(mb.locked * 2 + (1 - mb.active))',
        ], 'name', 'asc', 50);

        $query = Capsule::table('intra_mail_mailboxes as mb')
            ->leftJoin('intra_mitarbeiter as m', 'm.id', '=', 'mb.mitarbeiter_id')
            ->select('mb.id', 'mb.address', 'mb.display_name', 'mb.domain', 'mb.active', 'mb.locked', 'mb.mitarbeiter_id', 'mb.user_id', 'm.fullname as owner');
        if ($list->q !== '') {
            $like = $list->like();
            $query->where(static fn ($w) => $w->where('mb.address', 'like', $like)->orWhere('mb.display_name', 'like', $like));
        }

        return $this->page('settings/mailboxes', ['rows' => $list->paginate($query)->all(), 'list' => $list, 'ownId' => $this->ownMailboxId()]);
    }

    /** GET /settings/mail/mailboxes/{id}/edit */
    public function editMailbox(Request $request, string $id): Response
    {
        $mailbox = Mailbox::query()->find((int) $id);
        if ($mailbox === null) {
            return $this->mailboxNotFound();
        }

        return $this->mailboxForm($mailbox, ['local' => strstr($mailbox->address, '@', true) ?: '', 'domain' => $mailbox->domain]);
    }

    /** POST /settings/mail/mailboxes/{id} */
    public function updateMailbox(Request $request, string $id): Response
    {
        $mailbox = Mailbox::query()->find((int) $id);
        if ($mailbox === null) {
            return $this->mailboxNotFound();
        }
        if ($mailbox->id === $this->ownMailboxId()) {
            Flash::error('Die Adresse deines eigenen Postfachs ändert eine andere Person mit Postfachverwaltung.');

            return Response::redirect(MailController::basePath() . 'settings/mail/mailboxes');
        }

        $local     = is_string($request->post['local'] ?? null) ? trim($request->post['local']) : '';
        $rawDomain = $request->post['domain'] ?? null;
        // Ohne `mail.domain.choose` bleibt die Domain; ein mitgeschickter Wert zählt nicht.
        $domain  = Permissions::check(['admin', 'mail.domain.choose']) && is_string($rawDomain) ? MailAddressRules::normalize($rawDomain) : $mailbox->domain;
        $address = MailAddressRules::compose($local, $domain);

        $error = $this->rules->validate($address, $mailbox->id, null, $mailbox->domain);
        if ($error !== null) {
            Flash::error($error);

            return $this->mailboxForm($mailbox, ['local' => $local, 'domain' => $domain], 422);
        }
        if ($address === $mailbox->address) {
            Flash::info('Keine Änderungen.');

            return Response::redirect(MailController::basePath() . 'settings/mail/mailboxes');
        }

        $before        = $mailbox->address;
        $domainChanged = $domain !== $mailbox->domain;
        try {
            Capsule::connection()->transaction(static function () use ($mailbox, $address, $domain, $before): void {
                $mailbox->address = $address;
                $mailbox->domain  = $domain;
                $mailbox->setAttribute('updated_at', date('Y-m-d H:i:s'));
                $mailbox->save();
                // Die alte Adresse bleibt diesem Postfach vorbehalten.
                MailAddressRules::reserve($before, $mailbox->id);
                Capsule::table('intra_mail_address_history')->where('address', $address)->where('mailbox_id', $mailbox->id)->delete();
            });
        } catch (UniqueConstraintViolationException) {
            Flash::error('Die Adresse ' . $address . ' ist bereits vergeben.');

            return $this->mailboxForm($mailbox, ['local' => $local, 'domain' => $domain], 422);
        }

        self::audit($domainChanged ? 'Postfach-Domain geändert' : 'Postfach-Adresse geändert', $before . ' → ' . $address, ['mailbox_id' => $mailbox->id, 'von' => $before, 'auf' => $address]);
        Flash::success('Die Adresse lautet jetzt ' . $address . '.');

        return Response::redirect(MailController::basePath() . 'settings/mail/mailboxes');
    }

    /** POST /settings/mail/mailboxes/{id}/lock */
    public function lockMailbox(Request $request, string $id): Response
    {
        return $this->setLocked((int) $id, true);
    }

    /** POST /settings/mail/mailboxes/{id}/unlock */
    public function unlockMailbox(Request $request, string $id): Response
    {
        return $this->setLocked((int) $id, false);
    }

    /**
     * POST /settings/mail/mailboxes/{id}/account — „Konto zuordnen“: das
     * Postfach an ein anderes Konto hängen (`user_id`) oder die Zuordnung
     * lösen (leer). Ziel darf nur ein aktives Konto sein, dessen
     * Discord-ID zum Mitarbeiter passt und das noch kein Postfach hat, nie
     * das eigene; das eigene Postfach hängt hier niemand um. Ins
     * Audit-Log kommen nur IDs, das bisherige Konto bekommt Bescheid.
     */
    public function assignAccount(Request $request, string $id): Response
    {
        $mailbox = Mailbox::query()->find((int) $id);
        if ($mailbox === null) {
            return $this->mailboxNotFound();
        }
        $back = MailController::basePath() . 'settings/mail/mailboxes/' . $mailbox->id . '/edit';
        $me   = (int) SessionManager::userId();

        $raw    = $request->post['user_id'] ?? null;
        $target = is_string($raw) && preg_match('/^[1-9]\d{0,9}$/', $raw) === 1 ? (int) $raw : null;
        $error  = match (true) {
            $mailbox->user_id === $me => 'Die Zuordnung deines eigenen Postfachs ändert eine andere Person mit Postfachverwaltung.',
            !is_string($raw) || ($raw !== '' && $target === null) => 'Ungültige Eingabe.',
            $target === $me => 'Ein Postfach lässt sich nicht dem eigenen Konto zuordnen.',
            $target !== null && $target !== $mailbox->user_id && !array_key_exists($target, $this->eligibleAccounts($mailbox)) => 'Dieses Konto passt nicht zur Discord-ID des Mitarbeiters oder hat schon ein Postfach.',
            default => null,
        };
        if ($error !== null) {
            Flash::error($error);

            return Response::redirect($back);
        }
        if ($target === $mailbox->user_id) {
            Flash::info('Keine Änderungen.');

            return Response::redirect($back);
        }

        $before = $mailbox->user_id;
        $mailbox->user_id = $target;
        $mailbox->setAttribute('updated_at', date('Y-m-d H:i:s'));
        try {
            $mailbox->save();
        } catch (UniqueConstraintViolationException) {
            Flash::error('Dieses Konto hat inzwischen ein Postfach.');

            return Response::redirect($back);
        }
        Mailbox::forget();

        self::audit(
            $target === null ? 'Postfach-Konto gelöst' : 'Postfach-Konto zugeordnet',
            'Postfach #' . $mailbox->id . ': Konto #' . ($before ?? '–') . ' → #' . ($target ?? '–'),
            ['mailbox_id' => $mailbox->id, 'von' => $before, 'auf' => $target],
        );
        if ($before !== null) {
            $this->notifications->notify('system', [$before], [
                'title'   => 'Dein Postfach wurde umgehängt',
                'message' => 'Das Postfach ' . $mailbox->address . ' gehört nicht mehr zu deinem Konto. Fragen dazu beantwortet die Postfachverwaltung.',
            ]);
        }

        Flash::success($target === null
            ? 'Das Postfach ist keinem Konto mehr zugeordnet. Passt genau ein Konto zum Mitarbeiter, bindet es sich bei dessen nächster Anmeldung wieder.'
            : 'Das Postfach gehört jetzt dem gewählten Konto.');

        return Response::redirect($back);
    }

    /** GET /settings/mail */
    public function settings(Request $request): Response
    {
        return $this->settingsForm($this->currentSettings());
    }

    /**
     * POST /settings/mail — prüft jeden Wert; die Werte stehen in intra_config
     * als nicht editierbar, damit die allgemeine Konfigurationsseite sie nicht
     * ungeprüft überschreibt.
     */
    public function saveSettings(Request $request): Response
    {
        $post = $request->post;
        $text = static fn (string $key): ?string => is_string($post[$key] ?? null) ? $post[$key] : null;

        $form = [
            'domain'    => MailAddressRules::normalize($text('domain') ?? ''),
            'pattern'   => $text('pattern') ?? '',
            'allowed'   => trim($text('allowed') ?? ''),
            'signature' => $text('signature'),
        ];

        $error = null;
        if (in_array(null, [$text('domain'), $text('pattern'), $text('allowed'), $form['signature']], true)) {
            $error = 'Ungültige Eingabe.';
        } elseif (!MailAddressRules::isDomain($form['domain'])) {
            $error = 'Die Standard-Domain ist keine gültige Domain (z. B. ignis.ef).';
        } elseif (!in_array($form['pattern'], self::PATTERNS, true)) {
            $error = 'Bitte ein Adressmuster wählen.';
        } else {
            $invalid = array_filter(preg_split('/[\s,;]+/', $form['allowed']) ?: [], static fn (string $d): bool => $d !== '' && !MailAddressRules::isDomain(MailAddressRules::normalize($d)));
            $error = $invalid !== []
                ? 'Keine gültige Domain: ' . implode(', ', $invalid) . '.'
                : SignatureText::validate((string) $form['signature']);
        }
        if ($error !== null) {
            Flash::error($error);

            return $this->settingsForm(['signature' => (string) $form['signature']] + $form, 422);
        }

        $values = [
            'MAIL_DOMAIN'            => $form['domain'],
            'MAIL_ADDRESS_PATTERN'   => $form['pattern'],
            'MAIL_ALLOWED_DOMAINS'   => implode(', ', array_values(array_diff(MailAddressRules::parseDomains($form['allowed']), [$form['domain']]))),
            'MAIL_DEFAULT_SIGNATURE' => SignatureText::toJson((string) $form['signature']),
        ];
        $before  = Capsule::table('intra_config')->whereIn('config_key', array_keys($values))->pluck('config_value', 'config_key')->all();
        $changed = [];
        foreach ($values as $key => $value) {
            $old = (string) ($before[$key] ?? '');
            // Die Signatur zählt am Text, nicht am JSON: Formatierung aus
            // einem älteren Wert bleibt sonst bei jedem Speichern „geändert“.
            $same = $key === 'MAIL_DEFAULT_SIGNATURE' ? SignatureText::toText($old) === SignatureText::toText($value) : $old === $value;
            if ($same) {
                continue;
            }
            Capsule::table('intra_config')->where('config_key', $key)->update([
                'config_value' => $value,
                'updated_by'   => SessionManager::userId(),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            // Adressen und Muster ins Log, die Signatur nur als „geändert“.
            $changed[$key] = $key === 'MAIL_DEFAULT_SIGNATURE' ? 'geändert' : ['von' => $old, 'auf' => $value];
        }

        if ($changed === []) {
            Flash::info('Keine Änderungen.');
        } else {
            self::audit('Mail-Einstellungen geändert', implode(', ', array_keys($changed)), $changed);
            Flash::success('Mail-Einstellungen gespeichert. Sie gelten für neue Postfächer.');
        }

        return Response::redirect(MailController::basePath() . 'settings/mail');
    }

    // ── Helfer ────────────────────────────────────────────────────

    private function setLocked(int $id, bool $locked): Response
    {
        $mailbox = Mailbox::query()->find($id);
        if ($mailbox === null) {
            return $this->mailboxNotFound();
        }
        // Wie bei der Adresse: das eigene Postfach sperrt und entsperrt eine andere Person.
        if ($mailbox->id === $this->ownMailboxId()) {
            Flash::error('Dein eigenes Postfach sperrt oder entsperrt eine andere Person mit Postfachverwaltung.');

            return Response::redirect(MailController::basePath() . 'settings/mail/mailboxes');
        }
        if ($mailbox->locked !== $locked) {
            $mailbox->locked = $locked;
            $mailbox->setAttribute('updated_at', date('Y-m-d H:i:s'));
            $mailbox->save();
            self::audit($locked ? 'Postfach gesperrt' : 'Postfach entsperrt', $mailbox->address, ['mailbox_id' => $mailbox->id, 'address' => $mailbox->address]);
        }

        Flash::success($locked
            ? 'Postfach ' . $mailbox->address . ' ist gesperrt. Es stellt nichts mehr zu und lässt sich nicht öffnen.'
            : ($mailbox->active
                ? 'Postfach ' . $mailbox->address . ' ist entsperrt.'
                : 'Die Sperre ist aufgehoben. Das Postfach bleibt inaktiv, weil der Mitarbeiter ausgeschieden oder gelöscht ist.'));

        return Response::redirect(MailController::basePath() . 'settings/mail/mailboxes');
    }

    private function ownMailboxId(): ?int
    {
        $userId = SessionManager::userId();

        return $userId !== null ? Mailbox::ownedBy($userId)?->id : null;
    }

    /** @param array{local:string, domain:string} $form */
    private function mailboxForm(Mailbox $mailbox, array $form, int $status = 200): Response
    {
        $domains = $this->rules->allowedDomains();
        if (!in_array($mailbox->domain, $domains, true)) {
            array_unshift($domains, $mailbox->domain); // bisherige Domain bleibt wählbar
        }
        $owner   = $mailbox->mitarbeiter_id !== null ? Capsule::table('intra_mitarbeiter')->where('id', $mailbox->mitarbeiter_id)->value('fullname') : null;
        $account = $mailbox->user_id !== null ? Capsule::table('intra_users')->where('id', $mailbox->user_id)->value('username') : null;

        return $this->page('settings/mailbox-edit', [
            'mailbox'    => $mailbox,
            'owner'      => is_string($owner) ? $owner : null,
            'account'    => is_string($account) ? $account : null,
            'candidates' => $this->eligibleAccounts($mailbox),
            'form'      => $form,
            'domains'   => $domains,
            'canChoose' => Permissions::check(['admin', 'mail.domain.choose']),
            'isOwn'     => $mailbox->id === $this->ownMailboxId(),
        ], $status);
    }

    /**
     * Konten, denen das Postfach gehören darf: aktiv, Discord-ID gleich
     * dem aktuellen `discordtag` des Mitarbeiters, ohne eigenes Postfach
     * und nicht das Konto, das gerade verwaltet.
     *
     * @return array<int,string> Id => Benutzername
     */
    private function eligibleAccounts(Mailbox $mailbox): array
    {
        $tag = $mailbox->mitarbeiter_id !== null
            ? trim((string) Capsule::table('intra_mitarbeiter')->where('id', $mailbox->mitarbeiter_id)->value('discordtag'))
            : '';
        if ($tag === '') {
            return [];
        }

        return Capsule::table('intra_users as u')
            ->where('u.is_active', 1)
            ->where('u.discord_id', $tag)
            ->where('u.id', '!=', (int) SessionManager::userId())
            ->whereNotExists(static function ($q) use ($mailbox): void {
                $q->selectRaw('1')->from('intra_mail_mailboxes as mb')->whereColumn('mb.user_id', 'u.id')->where('mb.id', '!=', $mailbox->id);
            })
            ->orderBy('u.username')
            ->pluck('u.username', 'u.id')
            ->mapWithKeys(static fn ($name, $id): array => [(int) $id => (string) $name])
            ->all();
    }

    /** @return array{domain:string, pattern:string, allowed:string, signature:string} */
    private function currentSettings(): array
    {
        $values = Capsule::table('intra_config')->where('category', 'mail')->pluck('config_value', 'config_key')->all();

        return [
            'domain'    => (string) ($values['MAIL_DOMAIN'] ?? MailAddressRules::DEFAULT_DOMAIN),
            'pattern'   => (string) ($values['MAIL_ADDRESS_PATTERN'] ?? 'initial_dot_last'),
            'allowed'   => (string) ($values['MAIL_ALLOWED_DOMAINS'] ?? ''),
            'signature' => SignatureText::toText((string) ($values['MAIL_DEFAULT_SIGNATURE'] ?? '')),
        ];
    }

    /** @param array<string,mixed> $form */
    private function settingsForm(array $form, int $status = 200): Response
    {
        return $this->page('settings/mail', ['form' => $form], $status);
    }

    private function mailboxNotFound(): Response
    {
        Flash::error('Postfach wurde nicht gefunden.');

        return Response::redirect(MailController::basePath() . 'settings/mail/mailboxes');
    }
}
