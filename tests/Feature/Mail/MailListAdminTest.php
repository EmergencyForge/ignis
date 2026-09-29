<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\Models\MailList;
use Tests\FeatureTestCase;

/**
 * Verteiler-Verwaltung (`mail.lists.manage`): anlegen, bearbeiten,
 * löschen, Adressregeln über Postfächer, Verteiler und frühere Adressen,
 * neue Mitglieder nur aktiv und ungesperrt, Absenderregel, und das
 * Audit-Log mit den Ids der Änderungen statt Namen.
 */
final class MailListAdminTest extends FeatureTestCase
{
    use MailFixtures;

    /** @var array{user: \App\Models\User, person: \App\Models\Personnel, mailbox: \Plugin\Mail\Models\Mailbox} */
    private array $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->manager = $this->member('Mia Manager');
        $this->loginAs($this->manager['user'], ['mail.use', 'mail.lists.manage']);
    }

    /** @return list<array{action:string, context:array<string,mixed>}> */
    private function audit(): array
    {
        return Capsule::table('intra_audit_log')->where('module', 'Mail')->orderBy('id')->get(['action', 'context'])
            ->map(static fn ($r): array => ['action' => (string) $r->action, 'context' => (array) json_decode((string) $r->context, true)])->all();
    }

    #[Test]
    public function statischer_verteiler_von_anlage_bis_loeschen(): void
    {
        $a = $this->member('Anna Aktiv')['mailbox'];
        $b = $this->member('Bert Zwei')['mailbox'];
        $locked = $this->member('Lars Gesperrt')['mailbox'];
        $locked->update(['locked' => 1]);

        $this->assertOk($this->get('/mail/lists'));
        $this->assertBodyContains('Noch keine Verteiler', $this->get('/mail/lists'));
        $this->assertOk($this->get('/mail/lists/create'));

        $this->assertRedirect($this->post('/mail/lists', [
            'name' => 'Wache 1', 'local' => 'wache1', 'domain' => 'ignis.ef', 'kind' => 'static', 'senders' => 'all',
            'members' => [(string) $a->id, (string) $locked->id],
        ]), '/mail/lists');

        $list = MailList::query()->where('address', 'wache1@ignis.ef')->firstOrFail();
        $members = Capsule::table('intra_mail_list_members')->where('list_id', $list->id)->pluck('mailbox_id')->map('intval')->all();
        $this->assertSame([$a->id], $members, 'Gesperrte kommen nicht neu dazu.');

        // Bisherige Mitglieder bleiben, auch wenn sie inzwischen gesperrt sind.
        $a->update(['locked' => 1]);
        $this->assertRedirect($this->post('/mail/lists/' . $list->id, [
            'name' => 'Wache 1', 'local' => 'wache1', 'domain' => 'ignis.ef', 'kind' => 'static', 'senders' => 'managers',
            'members' => [(string) $a->id, (string) $b->id],
        ]));
        $members = Capsule::table('intra_mail_list_members')->where('list_id', $list->id)->orderBy('mailbox_id')->pluck('mailbox_id')->map('intval')->all();
        $this->assertSame([$a->id, $b->id], $members);
        $this->assertSame('managers', $list->refresh()->senders);

        $index = $this->get('/mail/lists');
        $this->assertBodyContains('wache1@ignis.ef', $index);
        $this->assertBodyContains('Nur Verwaltung', $index);

        $this->assertRedirect($this->post('/mail/lists/' . $list->id . '/delete'));
        $this->assertNull(MailList::query()->find($list->id));

        $audit = $this->audit();
        $this->assertSame(['Verteiler angelegt', 'Verteiler bearbeitet', 'Verteiler gelöscht'], array_column($audit, 'action'));
        $this->assertSame([$a->id], $audit[0]['context']['members_added']);
        $this->assertSame([$b->id], $audit[1]['context']['members_added']);
        $this->assertSame(['all', 'managers'], $audit[1]['context']['senders_changed']);
        $this->assertSame([$a->id, $b->id], $audit[2]['context']['members_removed']);
        $this->assertStringNotContainsString('Anna', (string) json_encode($audit));
    }

    #[Test]
    public function dynamischer_verteiler_braucht_eine_regel_und_protokolliert_ihre_ids(): void
    {
        $rank = $this->rank();
        $rd   = $this->rdQuali();

        $base = ['name' => 'Alle RD', 'local' => 'rd', 'domain' => 'ignis.ef', 'kind' => 'dynamic', 'senders' => 'all'];
        $this->assertStatus(422, $this->post('/mail/lists', $base));
        $this->assertRedirect($this->post('/mail/lists', $base + ['rank_ids' => [(string) $rank->id], 'rd_quali_ids' => [(string) $rd->id, '999999']]));

        $list = MailList::query()->where('address', 'rd@ignis.ef')->firstOrFail();
        $this->assertSame([(int) $rank->id], $list->rule['rank_ids']);
        $this->assertSame([(int) $rd->id], $list->rule['rd_quali_ids'], 'Erfundene Ids fallen weg.');
        $this->assertOk($this->get('/mail/lists/' . $list->id . '/edit'));

        $this->assertRedirect($this->post('/mail/lists/' . $list->id, $base + ['rd_quali_ids' => [(string) $rd->id]]));
        $audit = $this->audit();
        $this->assertSame([(int) $rank->id], $audit[1]['context']['rank_ids_removed']);
    }

    #[Test]
    public function das_formular_zeigt_nur_den_bereich_der_gewaehlten_art(): void
    {
        $static  = '~<div[^>]*data-list-kind="static"(?![^>]*hidden)[^>]*>~';
        $dynamic = '~<fieldset class="ignis-field[^"]*"[^>]*data-list-kind="dynamic"(?![^>]*hidden)[^>]*>~';

        $create = $this->get('/mail/lists/create');
        $this->assertMatchesRegularExpression($static, $create->body);
        $this->assertMatchesRegularExpression('~<fieldset class="ignis-field[^"]*"[^>]*data-list-kind="dynamic"[^>]*hidden~', $create->body);
        $this->assertDoesNotMatchRegularExpression($dynamic, $create->body);

        // Fehler bei „Dynamisch“: das Formular kommt mit der Regel zurück.
        $invalid = $this->post('/mail/lists', ['name' => 'Alle RD', 'local' => 'rd', 'domain' => 'ignis.ef', 'kind' => 'dynamic', 'senders' => 'all']);
        $this->assertStatus(422, $invalid);
        $this->assertMatchesRegularExpression($dynamic, $invalid->body);
        $this->assertDoesNotMatchRegularExpression($static, $invalid->body);

        $list = MailList::query()->create(['address' => 'rang@ignis.ef', 'name' => 'Rang', 'kind' => 'dynamic', 'rule' => ['rank_ids' => [(int) $this->rank()->id]], 'senders' => 'all']);
        $this->assertMatchesRegularExpression($dynamic, $this->get('/mail/lists/' . $list->id . '/edit')->body);
    }

    #[Test]
    public function adresse_ist_ueber_postfaecher_verteiler_und_historie_eindeutig(): void
    {
        $bob = $this->member('Bob Empfang')['mailbox'];
        Capsule::table('intra_mail_address_history')->insert(['address' => 'alt@ignis.ef', 'mailbox_id' => $bob->id]);
        $form = ['name' => 'X', 'domain' => 'ignis.ef', 'kind' => 'static', 'senders' => 'all'];

        $this->assertStatus(422, $this->post('/mail/lists', ['local' => 'b.empfang'] + $form));
        $this->assertStatus(422, $this->post('/mail/lists', ['local' => 'alt'] + $form));
        $this->assertStatus(422, $this->post('/mail/lists', ['local' => 'ok', 'domain' => 'fremd.de'] + $form));
        $this->assertStatus(422, $this->post('/mail/lists', ['local' => 'ok', 'senders' => 'jeder'] + $form));
        $this->assertStatus(422, $this->post('/mail/lists', ['local' => ['ok']] + $form));
        $this->assertSame(0, MailList::query()->count());
    }

    #[Test]
    public function ohne_recht_keine_verwaltung(): void
    {
        $this->loginAs($this->manager['user'], ['mail.use']);
        $this->assertRedirect($this->get('/mail/lists'));
        $this->assertRedirect($this->post('/mail/lists', ['name' => 'X', 'local' => 'x', 'domain' => 'ignis.ef', 'kind' => 'static', 'senders' => 'all']));
        $this->assertSame(0, MailList::query()->count());
    }
}
