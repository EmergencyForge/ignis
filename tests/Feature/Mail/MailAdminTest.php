<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\MailAddressRules;
use Plugin\Mail\Models\Mailbox;
use Tests\FeatureTestCase;

/**
 * Postfachverwaltung und Mail-Einstellungen (`mail.admin`): Adresse
 * korrigieren mit Reservierung der alten, nicht das eigene Postfach,
 * Domain nur mit `mail.domain.choose`, sperren und entsperren, Audit ohne
 * Inhalte, keine Einsicht in Mails. Einstellungen werden geprüft.
 */
final class MailAdminTest extends FeatureTestCase
{
    use MailFixtures;

    /** @var array{user: \App\Models\User, person: \App\Models\Personnel, mailbox: Mailbox} */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->admin = $this->member('Ada Admin');
        Capsule::table('intra_config')->where('config_key', 'MAIL_ALLOWED_DOMAINS')->update(['config_value' => 'lspd.de']);
    }

    private function asAdmin(bool $chooseDomain = false): void
    {
        $this->loginAs($this->admin['user'], $chooseDomain ? ['mail.use', 'mail.admin', 'mail.domain.choose'] : ['mail.use', 'mail.admin']);
    }

    /** @return list<array{action:string, details:?string, context:?string}> */
    private function auditRows(): array
    {
        return Capsule::table('intra_audit_log')->where('module', 'Mail')->where('user', $this->admin['user']->id)->orderBy('id')
            ->get(['action', 'details', 'context'])->map(static fn ($r): array => (array) $r)->all();
    }

    #[Test]
    public function adresse_aendern_reserviert_die_alte(): void
    {
        $bob = $this->member('Bob Empfang')['mailbox'];
        $this->asAdmin();

        $list = $this->get('/settings/mail/mailboxes');
        $this->assertOk($list);
        $this->assertBodyContains('b.empfang@ignis.ef', $list);
        $this->assertOk($this->get('/settings/mail/mailboxes/' . $bob->id . '/edit'));

        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $bob->id, ['local' => 'bob.e', 'domain' => 'lspd.de']));
        $bob->refresh();
        $this->assertSame('bob.e@ignis.ef', $bob->address, 'Ohne mail.domain.choose bleibt die Domain.');
        $this->assertTrue(MailAddressRules::isTaken('b.empfang@ignis.ef'), 'Die alte Adresse bleibt reserviert.');
        $this->assertFalse(MailAddressRules::isTaken('b.empfang@ignis.ef', $bob->id), '… nur nicht für ihr eigenes Postfach.');

        // Ein anderes Postfach bekommt die alte Adresse nicht.
        $carla = $this->member('Carla Kopie')['mailbox'];
        $taken = $this->post('/settings/mail/mailboxes/' . $carla->id, ['local' => 'b.empfang']);
        $this->assertStatus(422, $taken);
        $this->assertSame('c.kopie@ignis.ef', $carla->refresh()->address);

        // Mit mail.domain.choose wechselt die Domain, aber nur auf erlaubte.
        $this->asAdmin(chooseDomain: true);
        $this->assertStatus(422, $this->post('/settings/mail/mailboxes/' . $bob->id, ['local' => 'bob.e', 'domain' => 'fremd.de']));
        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $bob->id, ['local' => 'bob.e', 'domain' => 'lspd.de']));
        $this->assertSame('bob.e@lspd.de', $bob->refresh()->address);
        $this->assertSame('lspd.de', $bob->domain);

        // Zurück auf die eigene alte Adresse geht.
        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $bob->id, ['local' => 'b.empfang', 'domain' => 'ignis.ef']));
        $this->assertSame('b.empfang@ignis.ef', $bob->refresh()->address);

        $actions = array_column($this->auditRows(), 'action');
        $this->assertSame(['Postfach-Adresse geändert', 'Postfach-Domain geändert', 'Postfach-Domain geändert'], $actions);
    }

    #[Test]
    public function das_eigene_postfach_und_arrays_statt_texten(): void
    {
        $this->asAdmin(chooseDomain: true);
        $own = $this->get('/settings/mail/mailboxes/' . $this->admin['mailbox']->id . '/edit');
        $this->assertBodyContains('Das ist dein eigenes Postfach', $own);

        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $this->admin['mailbox']->id, ['local' => 'chef']));
        $this->assertSame('a.admin@ignis.ef', $this->admin['mailbox']->refresh()->address);

        // Sperren und Entsperren gilt auch nur für fremde Postfächer.
        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $this->admin['mailbox']->id . '/lock'));
        $this->assertFalse($this->admin['mailbox']->refresh()->locked);
        $this->admin['mailbox']->update(['locked' => 1]);
        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $this->admin['mailbox']->id . '/unlock'));
        $this->assertTrue($this->admin['mailbox']->refresh()->locked);
        $this->assertBodyNotContains('/settings/mail/mailboxes/' . $this->admin['mailbox']->id . '/unlock', $this->get('/settings/mail/mailboxes'));
        $this->admin['mailbox']->update(['locked' => 0]);

        $bob = $this->member('Bob Empfang')['mailbox'];
        $this->assertStatus(422, $this->post('/settings/mail/mailboxes/' . $bob->id, ['local' => ['x']]));
        $this->assertStatus(422, $this->post('/settings/mail/mailboxes/' . $bob->id, ['local' => 'Böse Adresse']));
    }

    #[Test]
    public function sperren_und_entsperren_ohne_einsicht_in_mails(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $this->loginAs($alice['user']);
        $this->send(['subject' => 'Geheimer Betreff', 'body_json' => self::doc('Geheimer Text'), 'to' => [$bob['mailbox']->address]]);

        $this->asAdmin();
        $list = $this->get('/settings/mail/mailboxes');
        $this->assertBodyNotContains('Geheimer', $list);

        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $bob['mailbox']->id . '/lock'));
        $this->assertTrue($bob['mailbox']->refresh()->locked);
        $this->assertTrue($bob['mailbox']->active, 'Die Sperre ist getrennt vom Zustand des Mitarbeiters.');

        // Gesperrt: nicht zustellbar und nicht zu öffnen.
        $this->loginAs($alice['user']);
        $blocked = $this->send(['to' => [$bob['mailbox']->address]]);
        $this->assertFalse($blocked['success']);
        $this->assertStringContainsString('nicht zustellbar: ' . $bob['mailbox']->address, (string) $blocked['message']);
        $this->loginAs($bob['user']);
        $this->assertBodyContains('Dein Postfach ist nicht aktiv', $this->get('/mail'));

        $this->asAdmin();
        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $bob['mailbox']->id . '/unlock'));
        $this->assertFalse($bob['mailbox']->refresh()->locked);

        foreach ($this->auditRows() as $row) {
            $this->assertStringNotContainsString('Geheim', (string) $row['details'] . (string) $row['context']);
        }
        $this->assertSame(['Postfach gesperrt', 'Postfach entsperrt'], array_column($this->auditRows(), 'action'));
    }

    #[Test]
    public function ohne_mail_admin_keine_verwaltung(): void
    {
        $this->loginAs($this->admin['user'], ['mail.use']);
        $this->assertRedirect($this->get('/settings/mail/mailboxes'));
        $this->assertRedirect($this->get('/settings/mail'));
        $this->assertRedirect($this->post('/settings/mail/mailboxes/' . $this->admin['mailbox']->id . '/lock'));
        $this->assertFalse($this->admin['mailbox']->refresh()->locked);
    }

    #[Test]
    public function einstellungen_werden_geprueft_und_ohne_signaturtext_protokolliert(): void
    {
        $this->asAdmin();
        $this->assertOk($this->get('/settings/mail'));

        $valid = ['domain' => 'Feuerwehr.test', 'pattern' => 'first_dot_last', 'allowed' => 'lspd.de, rettung.test', 'signature' => "Mit Gruß\nWache 1", 'cooldown' => '15'];
        $this->assertStatus(422, $this->post('/settings/mail', ['domain' => 'kaputt'] + $valid));
        $this->assertStatus(422, $this->post('/settings/mail', ['pattern' => 'nachname'] + $valid));
        $this->assertStatus(422, $this->post('/settings/mail', ['allowed' => 'gut.de, -schlecht'] + $valid));
        $this->assertStatus(422, $this->post('/settings/mail', ['signature' => str_repeat("x\n", 101) . 'x'] + $valid));
        $this->assertStatus(422, $this->post('/settings/mail', ['signature' => "Gru\xC3\x28"] + $valid));
        $this->assertStatus(422, $this->post('/settings/mail', ['domain' => ['x']] + $valid));
        foreach (['-1', '3601', 'zehn', '1.5', ''] as $cooldown) {
            $this->assertStatus(422, $this->post('/settings/mail', ['cooldown' => $cooldown] + $valid));
        }

        $this->assertRedirect($this->post('/settings/mail', $valid), '/settings/mail');
        $config = Capsule::table('intra_config')->where('category', 'mail')->pluck('config_value', 'config_key')->all();
        $this->assertSame('feuerwehr.test', $config['MAIL_DOMAIN']);
        $this->assertSame('first_dot_last', $config['MAIL_ADDRESS_PATTERN']);
        $this->assertSame('lspd.de, rettung.test', $config['MAIL_ALLOWED_DOMAINS']);
        $this->assertStringContainsString('Wache 1', $config['MAIL_DEFAULT_SIGNATURE']);
        $this->assertSame('15', $config['MAIL_SEND_COOLDOWN']);

        $audit = $this->auditRows();
        $this->assertCount(1, $audit);
        $this->assertStringNotContainsString('Wache', (string) $audit[0]['context']);
        $this->assertStringContainsString('MAIL_DEFAULT_SIGNATURE', (string) $audit[0]['details']);

        // Neue Postfächer folgen den neuen Werten.
        $this->assertSame('max.muster@feuerwehr.test', $this->member('Max Muster')['mailbox']->address);

        // Die allgemeine Konfigurationsseite zeigt die Mail-Werte nicht.
        $this->loginAs($this->admin['user'], ['full_admin']);
        $this->assertBodyNotContains('MAIL_DOMAIN', $this->get('/settings/system/config'));
    }
}
