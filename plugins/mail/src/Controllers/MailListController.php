<?php

declare(strict_types=1);

namespace Plugin\Mail\Controllers;

use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Models\AmbSkill;
use App\Models\FdSkill;
use App\Models\Rank;
use App\Models\Role;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Plugin\Mail\MailAddressRules;
use Plugin\Mail\MailDirectory;
use Plugin\Mail\Models\ListMember;
use Plugin\Mail\Models\Mailbox;
use Plugin\Mail\Models\MailList;

/**
 * Verteiler unter /mail/lists (`mail.lists.manage`).
 *
 * - statisch: feste Mitglieder aus Postfächern. Neu dazu kommen nur
 *   aktive, nicht gesperrte Postfächer; wer schon Mitglied ist, bleibt es
 *   (zugestellt wird einem gesperrten Mitglied trotzdem nichts).
 * - dynamisch: eine Regel aus Rolle, Dienstgrad, RD- und FW-Qualifikation
 *   und Fachdienst (ODER-verknüpft, MailDirectory), aufgelöst beim Senden.
 *
 * `senders` legt fest, wer an den Verteiler schreiben darf: alle mit
 * Mail-Zugang oder nur die Verteiler-Verwaltung (MailController prüft das
 * beim Senden, das Adressbuch blendet solche Verteiler sonst aus).
 *
 * Das Audit-Log bekommt Adresse, Name, Art, Absenderregel und die Ids
 * hinzugekommener und entfernter Mitglieder und Regel-Kriterien — keine
 * Namen der Mitglieder, keine Mail-Inhalte.
 */
final class MailListController extends Controller
{
    use RendersPages;

    private const KINDS = ['static', 'dynamic'];

    public function __construct(private readonly MailAddressRules $rules) {}

    /** GET /mail/lists */
    public function index(Request $request): Response
    {
        $lists  = MailList::query()->orderBy('name')->get();
        $counts = Capsule::table('intra_mail_list_members')->selectRaw('list_id, COUNT(*) as c')->groupBy('list_id')->pluck('c', 'list_id')->all();
        $names  = $this->criteria();

        $summaries = [];
        foreach ($lists as $list) {
            $count = (int) ($counts[$list->id] ?? 0);
            $summaries[$list->id] = $list->kind === 'dynamic'
                ? self::ruleSummary($list->rule ?? [], $names)
                : $count . ($count === 1 ? ' Mitglied' : ' Mitglieder');
        }

        return $this->page('mail/lists/index', ['lists' => $lists->all(), 'summaries' => $summaries]);
    }

    /** GET /mail/lists/create */
    public function create(Request $request): Response
    {
        return $this->form(null, [
            'name' => '', 'local' => '', 'domain' => $this->rules->defaultDomain(), 'kind' => 'static', 'senders' => MailList::SENDERS_ALL,
            'members' => [], 'role_ids' => [], 'rank_ids' => [], 'rd_quali_ids' => [], 'fw_quali_ids' => [], 'fachdienst_ids' => [],
        ]);
    }

    /** POST /mail/lists */
    public function store(Request $request): Response
    {
        return $this->save(new MailList(), $request->post);
    }

    /** GET /mail/lists/{id}/edit */
    public function edit(Request $request, string $id): Response
    {
        $list = MailList::query()->find((int) $id);
        if ($list === null) {
            return $this->notFound();
        }
        [$local, $domain] = explode('@', $list->address, 2) + [1 => ''];
        $form = [
            'name' => $list->name, 'local' => $local, 'domain' => $domain, 'kind' => $list->kind, 'senders' => $list->senders,
            'members' => $this->memberIds($list),
        ];
        foreach (MailDirectory::RULE_KEYS as $key) {
            $form[$key] = array_map('intval', (array) (($list->rule ?? [])[$key] ?? []));
        }

        return $this->form($list, $form);
    }

    /** POST /mail/lists/{id} */
    public function update(Request $request, string $id): Response
    {
        $list = MailList::query()->find((int) $id);

        return $list === null ? $this->notFound() : $this->save($list, $request->post);
    }

    /** POST /mail/lists/{id}/delete */
    public function destroy(Request $request, string $id): Response
    {
        $list = MailList::query()->find((int) $id);
        if ($list === null) {
            return $this->notFound();
        }

        $changes = self::changes($this->memberIds($list), [], $list->rule ?? [], []);
        $list->delete(); // Mitglieder fallen per Fremdschlüssel mit

        self::audit('Verteiler gelöscht', $list->address, ['list_id' => $list->id, 'address' => $list->address, 'name' => $list->name, 'kind' => $list->kind] + $changes);
        Flash::success('Verteiler „' . $list->name . '“ wurde gelöscht.');

        return Response::redirect(MailController::basePath() . 'mail/lists');
    }

    // ── Speichern ─────────────────────────────────────────────────

    /** @param array<string,mixed> $post */
    private function save(MailList $list, array $post): Response
    {
        $text = static fn (string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';

        $form = [
            'name'    => $text('name'),
            'local'   => $text('local'),
            'domain'  => $text('domain'),
            'kind'    => $text('kind'),
            'senders' => $text('senders'),
            'members' => self::ids($post['members'] ?? []),
        ];
        foreach (MailDirectory::RULE_KEYS as $key) {
            $form[$key] = self::ids($post[$key] ?? []);
        }

        $error = null;
        if ($form['name'] === '' || mb_strlen($form['name']) > 150) {
            $error = 'Bitte einen Namen angeben (höchstens 150 Zeichen).';
        } elseif (!in_array($form['kind'], self::KINDS, true)) {
            $error = 'Die Art muss statisch oder dynamisch sein.';
        } elseif (!in_array($form['senders'], [MailList::SENDERS_ALL, MailList::SENDERS_MANAGERS], true)) {
            $error = 'Bitte festlegen, wer an den Verteiler schreiben darf.';
        }

        $address = MailAddressRules::compose($form['local'], $form['domain']);
        $keep    = $list->exists ? substr((string) strrchr($list->address, '@'), 1) : null;
        $error ??= $this->rules->validate($address, null, $list->exists ? $list->id : null, $keep);

        $rule = [];
        foreach ($this->criteriaQueries() as $key => $query) {
            $rule[$key] = array_values(array_map('intval', $query->whereIn('id', $form[$key])->pluck('id')->all()));
        }
        if ($error === null && $form['kind'] === 'dynamic' && array_merge(...array_values($rule)) === []) {
            $error = 'Ein dynamischer Verteiler braucht mindestens eine Rolle, einen Dienstgrad, eine Qualifikation oder einen Fachdienst.';
        }
        if ($error !== null) {
            Flash::error($error);

            return $this->form($list->exists ? $list : null, $form, 422);
        }

        $before  = $list->exists ? $this->memberIds($list) : [];
        $members = $form['kind'] === 'static' ? $this->allowedMembers($form['members'], $before) : [];
        $isNew   = !$list->exists;
        $ruleBefore    = $list->exists ? ($list->rule ?? []) : [];
        $sendersBefore = $list->exists ? $list->senders : null;

        try {
            Capsule::connection()->transaction(function () use ($list, $form, $address, $rule, $members): void {
                $list->name    = $form['name'];
                $list->address = $address;
                $list->kind    = $form['kind'];
                $list->senders = $form['senders'];
                // Die Regel gilt nur dynamisch; ein Wechsel schleppt nichts Altes mit.
                $list->rule = $form['kind'] === 'dynamic' ? $rule : null;
                $list->setAttribute('updated_at', date('Y-m-d H:i:s'));
                $list->save();

                ListMember::query()->where('list_id', $list->id)->whereNotIn('mailbox_id', $members === [] ? [0] : $members)->delete();
                $present = $this->memberIds($list);
                foreach (array_diff($members, $present) as $mailboxId) {
                    ListMember::query()->create(['list_id' => $list->id, 'mailbox_id' => $mailboxId]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            Flash::error('Die Adresse ' . $address . ' ist bereits vergeben.');

            return $this->form($isNew ? null : $list, $form, 422);
        }

        $context = ['list_id' => $list->id, 'address' => $list->address, 'name' => $list->name, 'kind' => $list->kind, 'senders' => $list->senders]
            + self::changes($before, $members, $ruleBefore, $list->rule ?? []);
        if ($sendersBefore !== null && $sendersBefore !== $list->senders) {
            $context['senders_changed'] = [$sendersBefore, $list->senders];
        }
        self::audit($isNew ? 'Verteiler angelegt' : 'Verteiler bearbeitet', $list->address, $context);
        Flash::success('Verteiler „' . $list->name . '“ wurde ' . ($isNew ? 'angelegt.' : 'gespeichert.'));

        return Response::redirect(MailController::basePath() . 'mail/lists');
    }

    /**
     * Neu dazu nur aktive, nicht gesperrte Postfächer; bisherige Mitglieder
     * bleiben, auch wenn ihr Postfach inzwischen gesperrt ist.
     *
     * @param list<int> $ids
     * @param list<int> $before
     * @return list<int>
     */
    private function allowedMembers(array $ids, array $before): array
    {
        $usable = array_map('intval', Mailbox::query()->whereKey($ids)->where('active', true)->where('locked', false)->pluck('id')->all());

        return array_values(array_filter($ids, static fn (int $id): bool => in_array($id, $before, true) || in_array($id, $usable, true)));
    }

    /** @return list<int> */
    private function memberIds(MailList $list): array
    {
        return array_values(array_map('intval', ListMember::query()->where('list_id', $list->id)->pluck('mailbox_id')->all()));
    }

    /**
     * Ids aus einem Formularfeld; alles, was keine Zahl ist, fällt weg.
     *
     * @return list<int>
     */
    private static function ids(mixed $raw): array
    {
        $ids = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if (is_string($value) && preg_match('/^[1-9]\d{0,9}$/', $value) === 1) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Unterschied zweier Stände, nur Ids: `members_added`, `rank_ids_removed` …
     *
     * @param list<int> $membersBefore
     * @param list<int> $membersAfter
     * @param array<string,mixed> $ruleBefore
     * @param array<string,mixed> $ruleAfter
     * @return array<string,list<int>>
     */
    private static function changes(array $membersBefore, array $membersAfter, array $ruleBefore, array $ruleAfter): array
    {
        $pairs = ['members' => [$membersBefore, $membersAfter]];
        foreach (MailDirectory::RULE_KEYS as $key) {
            $pairs[$key] = [array_map('intval', (array) ($ruleBefore[$key] ?? [])), array_map('intval', (array) ($ruleAfter[$key] ?? []))];
        }

        $changes = [];
        foreach ($pairs as $key => [$before, $after]) {
            if (($added = array_values(array_diff($after, $before))) !== []) {
                $changes[$key . '_added'] = $added;
            }
            if (($removed = array_values(array_diff($before, $after))) !== []) {
                $changes[$key . '_removed'] = $removed;
            }
        }

        return $changes;
    }

    // ── Anzeige ───────────────────────────────────────────────────

    /** @return array<string, QueryBuilder> Kriterium => Abfrage mit `id` und `name`, sortiert */
    private function criteriaQueries(): array
    {
        $byPriority = static fn (string $model): QueryBuilder => $model::query()->toBase()->orderBy('priority')->select(['id', 'name']);

        return [
            'role_ids'       => $byPriority(Role::class),
            'rank_ids'       => $byPriority(Rank::class),
            'rd_quali_ids'   => $byPriority(AmbSkill::class),
            'fw_quali_ids'   => $byPriority(FdSkill::class),
            'fachdienst_ids' => Capsule::table('intra_mitarbeiter_fdquali')->orderBy('sgnr')->select(['id', 'sgname as name']),
        ];
    }

    /** @return array<string, array<int,string>> Kriterium => Id => Name */
    private function criteria(): array
    {
        $names = [];
        foreach ($this->criteriaQueries() as $key => $query) {
            $names[$key] = array_map('strval', $query->pluck('name', 'id')->all());
        }

        return $names;
    }

    /** @param array<string,mixed> $form */
    private function form(?MailList $list, array $form, int $status = 200): Response
    {
        $members = array_map('intval', (array) $form['members']);
        $stored  = $list !== null ? $this->memberIds($list) : [];
        $mailboxes = Mailbox::query()
            ->where(static fn ($q) => $q->where('active', true)->where('locked', false))
            ->orWhereIn('id', array_merge($stored, $members === [] ? [0] : $members))
            ->orderBy('display_name')
            ->get(['id', 'address', 'display_name', 'active', 'locked']);

        $domains = $this->rules->allowedDomains();
        if ($form['domain'] !== '' && !in_array($form['domain'], $domains, true) && $list !== null) {
            array_unshift($domains, (string) $form['domain']); // bisherige Domain bleibt wählbar
        }

        return $this->page('mail/lists/form', [
            'list'      => $list,
            'form'      => $form,
            'domains'   => $domains,
            'mailboxes' => $mailboxes->all(),
            'criteria'  => $this->criteria(),
        ], $status);
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string, array<int,string>> $names
     */
    private static function ruleSummary(array $rule, array $names): string
    {
        $labels = ['role_ids' => 'Rolle', 'rank_ids' => 'Dienstgrad', 'rd_quali_ids' => 'RD', 'fw_quali_ids' => 'FW', 'fachdienst_ids' => 'Fachdienst'];
        $parts  = [];
        foreach ($labels as $key => $label) {
            $values = array_map(static fn ($id): string => $names[$key][(int) $id] ?? 'gelöscht', (array) ($rule[$key] ?? []));
            if ($values !== []) {
                $parts[] = $label . ': ' . implode(', ', $values);
            }
        }

        return $parts === [] ? 'Keine Regel' : implode(' · ', $parts);
    }

    private function notFound(): Response
    {
        Flash::error('Verteiler wurde nicht gefunden.');

        return Response::redirect(MailController::basePath() . 'mail/lists');
    }
}
