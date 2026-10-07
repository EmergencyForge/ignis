<?php

declare(strict_types=1);

namespace Plugin\Mail\Controllers;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Models\Personnel;
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
use Plugin\Mail\Models\MailboxMember;
use Plugin\Mail\SignatureTemplate;

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
 *
 * Gruppenpostfächer legt nur die Verwaltung an, mit Name und Adresse von
 * Hand und Mitgliedern statt eines Kontos. Mitglied werden heißt mitlesen:
 * sich selbst nimmt niemand auf, das macht eine andere Person mit
 * Postfachverwaltung, wie beim eigenen Postfach. Austreten darf jeder. Ins
 * Audit-Log kommen IDs, nie Inhalte. Ein Gruppenpostfach wird gesperrt,
 * nicht gelöscht: es steht als Absender in den Postfächern der Empfänger.
 */
final class MailAdminController extends Controller
{
    use RendersPages;

    private const PATTERNS = ['initial_dot_last', 'first_dot_last'];
    public const MAX_SEND_COOLDOWN = 3600;

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
        ], 'name', 'asc', 50, filterKeys: ['kind']);

        $query = Capsule::table('intra_mail_mailboxes as mb')
            ->leftJoin('intra_mitarbeiter as m', 'm.id', '=', 'mb.mitarbeiter_id')
            ->select('mb.id', 'mb.kind', 'mb.address', 'mb.display_name', 'mb.domain', 'mb.active', 'mb.locked', 'mb.mitarbeiter_id', 'mb.user_id', 'm.fullname as owner')
            ->selectSub(Capsule::table('intra_mail_mailbox_members as mm')->selectRaw('COUNT(*)')->whereColumn('mm.mailbox_id', 'mb.id'), 'members');
        $kind = $list->filter('kind');
        if (array_key_exists($kind, Mailbox::KIND_LABELS)) {
            $query->where('mb.kind', $kind);
        }
        if ($list->q !== '') {
            $like = $list->like();
            $query->where(static fn ($w) => $w->where('mb.address', 'like', $like)->orWhere('mb.display_name', 'like', $like));
        }

        return $this->page('settings/mailboxes', ['rows' => $list->paginate($query)->all(), 'list' => $list, 'ownId' => $this->ownMailboxId()]);
    }

    /** GET /settings/mail/mailboxes/groups/create */
    public function createGroup(Request $request): Response
    {
        return $this->groupForm(['name' => '', 'local' => '', 'domain' => $this->rules->defaultDomain()]);
    }

    /** POST /settings/mail/mailboxes/groups */
    public function storeGroup(Request $request): Response
    {
        $text   = static fn (mixed $v): ?string => is_string($v) ? trim($v) : null;
        $name   = $text($request->post['name'] ?? '');
        $local  = $text($request->post['local'] ?? '');
        $raw    = $text($request->post['domain'] ?? '');
        // Ohne `mail.domain.choose` gilt die Standard-Domain, ein mitgeschickter Wert zählt nicht.
        $domain = Permissions::check(['admin', 'mail.domain.choose']) && $raw !== null ? MailAddressRules::normalize($raw) : $this->rules->defaultDomain();
        $form   = ['name' => $name ?? '', 'local' => $local ?? '', 'domain' => $domain];

        $address = MailAddressRules::compose($form['local'], $domain);
        $error   = $name === null || $local === null
            ? 'Ungültige Eingabe.'
            : (self::groupNameError($form['name']) ?? $this->rules->validate($address));
        if ($error !== null) {
            Flash::error($error);

            return $this->groupForm($form, 422);
        }

        $mailbox = new Mailbox();
        $mailbox->kind         = Mailbox::KIND_GROUP;
        $mailbox->address      = $address;
        $mailbox->display_name = $form['name'];
        $mailbox->domain       = $domain;
        $mailbox->active       = true;
        $mailbox->locked       = false;
        try {
            $mailbox->save();
        } catch (UniqueConstraintViolationException) {
            Flash::error('Die Adresse ' . $address . ' ist bereits vergeben.');

            return $this->groupForm($form, 422);
        }

        self::audit('Gruppenpostfach angelegt', $address, ['mailbox_id' => $mailbox->id, 'address' => $address]);
        Flash::success('Gruppenpostfach ' . $address . ' ist angelegt. Jetzt fehlen noch die Mitglieder.');

        return Response::redirect(MailController::basePath() . 'settings/mail/mailboxes/' . $mailbox->id . '/edit');
    }

    /** POST /settings/mail/mailboxes/{id}/name: Namen eines Gruppenpostfachs ändern. */
    public function renameGroup(Request $request, string $id): Response
    {
        $mailbox = $this->group((int) $id);
        if ($mailbox === null) {
            return $this->mailboxNotFound();
        }
        $back = Response::redirect(MailController::basePath() . 'settings/mail/mailboxes/' . $mailbox->id . '/edit');
        $raw  = $request->post['name'] ?? null;
        $name = is_string($raw) ? trim($raw) : '';
        $error = is_string($raw) ? self::groupNameError($name) : 'Ungültige Eingabe.';
        if ($error !== null) {
            Flash::error($error);

            return $back;
        }
        if ($name === $mailbox->display_name) {
            Flash::info('Keine Änderungen.');

            return $back;
        }

        $before = $mailbox->display_name;
        $mailbox->display_name = $name;
        $mailbox->setAttribute('updated_at', date('Y-m-d H:i:s'));
        $mailbox->save();

        self::audit('Gruppenpostfach umbenannt', $before . ' → ' . $name, ['mailbox_id' => $mailbox->id, 'von' => $before, 'auf' => $name]);
        Flash::success('Das Gruppenpostfach heißt jetzt „' . $name . '“.');

        return $back;
    }

    /** POST /settings/mail/mailboxes/{id}/members: ein aktives Konto aufnehmen, nie das eigene. */
    public function addMember(Request $request, string $id): Response
    {
        $mailbox = $this->group((int) $id);
        if ($mailbox === null) {
            return $this->mailboxNotFound();
        }
        $back = Response::redirect(MailController::basePath() . 'settings/mail/mailboxes/' . $mailbox->id . '/edit');
        $raw  = $request->post['user_id'] ?? null;
        $userId = is_string($raw) && preg_match('/^[1-9]\d{0,9}$/', $raw) === 1 ? (int) $raw : null;
        $name   = $userId !== null ? self::accountName($userId, activeOnly: true) : null;

        $error = match (true) {
            $userId === null || $name === null     => 'Bitte ein aktives Konto wählen.',
            $userId === SessionManager::userId()   => 'Sich selbst nimmt niemand in ein Gruppenpostfach auf: das macht eine andere Person mit Postfachverwaltung.',
            $mailbox->hasMember($userId)           => $name . ' ist bereits Mitglied.',
            default                                => null,
        };
        if ($error !== null) {
            Flash::error($error);

            return $back;
        }

        try {
            MailboxMember::query()->create(['mailbox_id' => $mailbox->id, 'user_id' => $userId]);
        } catch (UniqueConstraintViolationException) {
            Flash::info($name . ' ist bereits Mitglied.');

            return $back;
        }
        Mailbox::forget();

        self::audit('Gruppenpostfach: Mitglied aufgenommen', $mailbox->address . ': Konto #' . $userId, ['mailbox_id' => $mailbox->id, 'user_id' => $userId]);
        Flash::success($name . ' liest jetzt ' . $mailbox->address . ' mit.');

        return $back;
    }

    /** POST /settings/mail/mailboxes/{id}/members/{userId}/delete */
    public function removeMember(Request $request, string $id, string $userId): Response
    {
        $mailbox = $this->group((int) $id);
        if ($mailbox === null) {
            return $this->mailboxNotFound();
        }
        $back    = Response::redirect(MailController::basePath() . 'settings/mail/mailboxes/' . $mailbox->id . '/edit');
        $removed = MailboxMember::query()->where('mailbox_id', $mailbox->id)->where('user_id', (int) $userId)->delete();
        if ($removed === 0) {
            Flash::info('Dieses Konto ist kein Mitglied.');

            return $back;
        }
        Mailbox::forget();

        self::audit('Gruppenpostfach: Mitglied entfernt', $mailbox->address . ': Konto #' . (int) $userId, ['mailbox_id' => $mailbox->id, 'user_id' => (int) $userId]);
        Flash::success((self::accountName((int) $userId) ?? 'Das Konto') . ' liest ' . $mailbox->address . ' nicht mehr mit.');

        return $back;
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
     * POST /settings/mail/mailboxes/{id}/account („Konto zuordnen“): das
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
        if ($mailbox->isGroup()) {
            Flash::error('Ein Gruppenpostfach hat kein eigenes Konto. Wer es liest, steht bei den Mitgliedern.');

            return Response::redirect($back);
        }

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
            'Postfach #' . $mailbox->id . ': Konto #' . ($before ?? '-') . ' → #' . ($target ?? '-'),
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
     * POST /settings/mail: prüft jeden Wert; die Werte stehen in intra_config
     * als nicht editierbar, damit die allgemeine Konfigurationsseite sie nicht
     * ungeprüft überschreibt.
     */
    public function saveSettings(Request $request): Response
    {
        $post = $request->post;
        $text = static fn (string $key): ?string => is_string($post[$key] ?? null) ? $post[$key] : null;

        // Die Signatur kommt als Editor-JSON aus dem versteckten Feld.
        $signature = SignatureTemplate::parse($post['signature'] ?? null);
        $form = [
            'domain'    => MailAddressRules::normalize($text('domain') ?? ''),
            'pattern'   => $text('pattern') ?? '',
            'allowed'   => trim($text('allowed') ?? ''),
            'signature' => is_array($signature) ? $signature : $this->currentSettings()['signature'],
            'cooldown'  => trim($text('cooldown') ?? ''),
        ];

        $error = null;
        if (in_array(null, [$text('domain'), $text('pattern'), $text('allowed'), $text('cooldown')], true)) {
            $error = 'Ungültige Eingabe.';
        } elseif (preg_match('/^\d{1,4}$/', $form['cooldown']) !== 1 || (int) $form['cooldown'] > self::MAX_SEND_COOLDOWN) {
            $error = 'Die Sendepause muss eine ganze Zahl von 0 bis ' . self::MAX_SEND_COOLDOWN . ' Sekunden sein.';
        } elseif (!MailAddressRules::isDomain($form['domain'])) {
            $error = 'Die Standard-Domain ist keine gültige Domain (z. B. ignis.ef).';
        } elseif (!in_array($form['pattern'], self::PATTERNS, true)) {
            $error = 'Bitte ein Adressmuster wählen.';
        } else {
            $invalid = array_filter(preg_split('/[\s,;]+/', $form['allowed']) ?: [], static fn (string $d): bool => $d !== '' && !MailAddressRules::isDomain(MailAddressRules::normalize($d)));
            $error = $invalid !== []
                ? 'Keine gültige Domain: ' . implode(', ', $invalid) . '.'
                : (is_string($signature) ? $signature : null);
        }
        if ($error !== null || !is_array($signature)) {
            Flash::error($error ?? 'Ungültige Eingabe.');

            return $this->settingsForm($form, 422);
        }

        $values = [
            'MAIL_DOMAIN'            => $form['domain'],
            'MAIL_ADDRESS_PATTERN'   => $form['pattern'],
            'MAIL_ALLOWED_DOMAINS'   => implode(', ', array_values(array_diff(MailAddressRules::parseDomains($form['allowed']), [$form['domain']]))),
            'MAIL_DEFAULT_SIGNATURE' => SignatureTemplate::toStored($signature),
            'MAIL_SEND_COOLDOWN'     => (string) (int) $form['cooldown'],
        ];
        $before  = Capsule::table('intra_config')->whereIn('config_key', array_keys($values))->pluck('config_value', 'config_key')->all();
        $changed = [];
        foreach ($values as $key => $value) {
            $old = (string) ($before[$key] ?? '');
            if ($old === $value) {
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
            Flash::success('Mail-Einstellungen gespeichert. Domain und Muster gelten für neue Postfächer.');
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
            : ($mailbox->active || $mailbox->isGroup()
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
            'members'    => $mailbox->isGroup() ? $this->members($mailbox) : [],
            'memberCandidates' => $mailbox->isGroup() ? $this->memberCandidates($mailbox) : [],
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
     * Konten, denen das Postfach gehören darf: aktiv, mit dem Mitarbeiter
     * verknüpft (`aktenid`) oder mit seiner Discord-ID, ohne eigenes
     * Postfach und nicht das Konto, das gerade verwaltet.
     *
     * @return array<int,string> Id => Benutzername
     */
    private function eligibleAccounts(Mailbox $mailbox): array
    {
        if ($mailbox->mitarbeiter_id === null) {
            return [];
        }
        $tag = trim((string) Capsule::table('intra_mitarbeiter')->where('id', $mailbox->mitarbeiter_id)->value('discordtag'));

        return Capsule::table('intra_users as u')
            ->where('u.is_active', 1)
            ->where(static function ($q) use ($mailbox, $tag): void {
                $q->where('u.aktenid', $mailbox->mitarbeiter_id);
                if ($tag !== '') {
                    $q->orWhere('u.discord_id', $tag);
                }
            })
            ->where('u.id', '!=', (int) SessionManager::userId())
            ->whereNotExists(static function ($q) use ($mailbox): void {
                $q->selectRaw('1')->from('intra_mail_mailboxes as mb')->whereColumn('mb.user_id', 'u.id')->where('mb.id', '!=', $mailbox->id);
            })
            ->orderBy('u.username')
            ->pluck('u.username', 'u.id')
            ->mapWithKeys(static fn ($name, $id): array => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * Ohne gespeicherte Standard-Signatur steht die eingebaute Vorlage im
     * Editor, damit sichtbar ist, was gilt.
     *
     * @return array{domain:string, pattern:string, allowed:string, signature:array<string,mixed>, cooldown:string}
     */
    private function currentSettings(): array
    {
        $values = Capsule::table('intra_config')->where('category', 'mail')->pluck('config_value', 'config_key')->all();

        return [
            'domain'    => (string) ($values['MAIL_DOMAIN'] ?? MailAddressRules::DEFAULT_DOMAIN),
            'pattern'   => (string) ($values['MAIL_ADDRESS_PATTERN'] ?? 'initial_dot_last'),
            'allowed'   => (string) ($values['MAIL_ALLOWED_DOMAINS'] ?? ''),
            'signature' => SignatureTemplate::decode((string) ($values['MAIL_DEFAULT_SIGNATURE'] ?? '')) ?? SignatureTemplate::builtIn(),
            'cooldown'  => (string) ($values['MAIL_SEND_COOLDOWN'] ?? '10'),
        ];
    }

    /** @param array<string,mixed> $form */
    private function settingsForm(array $form, int $status = 200): Response
    {
        return $this->page('settings/mail', ['form' => $form, 'previewValues' => $this->previewValues()], $status);
    }

    /**
     * Die Angaben des angemeldeten Kontos für die Vorschau der
     * Standard-Signatur: sein Mitarbeiter und sein Postfach, soweit es sie gibt.
     *
     * @return array<string,list<array<string,mixed>>>
     */
    private function previewValues(): array
    {
        $userId  = SessionManager::userId();
        $mailbox = $userId !== null ? Mailbox::ownedBy($userId) : null;
        $personId = $mailbox->mitarbeiter_id ?? ($userId !== null ? Mailbox::mitarbeiterIdForUser($userId) : null);

        return SignatureTemplate::values(
            $personId !== null ? Personnel::query()->find($personId) : null,
            $mailbox->address ?? '',
            $mailbox->display_name ?? '',
        );
    }

    private function group(int $id): ?Mailbox
    {
        $mailbox = Mailbox::query()->find($id);

        return $mailbox !== null && $mailbox->isGroup() ? $mailbox : null;
    }

    private static function groupNameError(string $name): ?string
    {
        return match (true) {
            $name === ''           => 'Bitte gib dem Gruppenpostfach einen Namen.',
            mb_strlen($name) > 150 => 'Der Name darf höchstens 150 Zeichen lang sein.',
            default                => null,
        };
    }

    /** Anzeigename eines Kontos (vollständiger Name, sonst Benutzername), null wenn es fehlt. */
    private static function accountName(int $userId, bool $activeOnly = false): ?string
    {
        $user = Capsule::table('intra_users')->where('id', $userId)
            ->when($activeOnly, static fn ($q) => $q->where('is_active', 1))
            ->first(['fullname', 'username']);

        return $user === null ? null : (string) (($user->fullname ?? '') !== '' ? $user->fullname : $user->username);
    }

    /**
     * Mitglieder eines Gruppenpostfachs, auch inaktive Konten.
     *
     * @return list<array{id:int, name:string, active:bool}>
     */
    private function members(Mailbox $mailbox): array
    {
        return Capsule::table('intra_mail_mailbox_members as mm')
            ->join('intra_users as u', 'u.id', '=', 'mm.user_id')
            ->where('mm.mailbox_id', $mailbox->id)
            ->orderByRaw("COALESCE(NULLIF(u.fullname, ''), u.username)")
            ->get(['u.id', 'u.fullname', 'u.username', 'u.is_active'])
            ->map(static fn ($u): array => ['id' => (int) $u->id, 'name' => (string) (($u->fullname ?? '') !== '' ? $u->fullname : $u->username), 'active' => (int) $u->is_active === 1])
            ->all();
    }

    /**
     * Konten, die „Mitglied aufnehmen“ anbietet: aktiv, noch nicht Mitglied,
     * nicht das eigene.
     *
     * @return array<int,string> Id => Name
     */
    private function memberCandidates(Mailbox $mailbox): array
    {
        return Capsule::table('intra_users as u')
            ->where('u.is_active', 1)
            ->where('u.id', '!=', (int) SessionManager::userId())
            ->whereNotExists(static function ($q) use ($mailbox): void {
                $q->selectRaw('1')->from('intra_mail_mailbox_members as mm')->whereColumn('mm.user_id', 'u.id')->where('mm.mailbox_id', $mailbox->id);
            })
            ->orderByRaw("COALESCE(NULLIF(u.fullname, ''), u.username)")
            ->get(['u.id', 'u.fullname', 'u.username'])
            ->mapWithKeys(static fn ($u): array => [(int) $u->id => (string) (($u->fullname ?? '') !== '' ? $u->fullname : $u->username)])
            ->all();
    }

    /** @param array{name:string, local:string, domain:string} $form */
    private function groupForm(array $form, int $status = 200): Response
    {
        return $this->page('settings/mailbox-group', [
            'form'      => $form,
            'domains'   => $this->rules->allowedDomains(),
            'canChoose' => Permissions::check(['admin', 'mail.domain.choose']),
        ], $status);
    }

    private function mailboxNotFound(): Response
    {
        Flash::error('Postfach wurde nicht gefunden.');

        return Response::redirect(MailController::basePath() . 'settings/mail/mailboxes');
    }
}
