<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Auth\Permissions;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Das Recht `admin` umfasst alle Mail-Rechte (mail.use, mail.lists.manage,
 * mail.domain.choose, mail.admin), ohne dass die Rolle sie einzeln trägt.
 * Fremde Mails liest auch ein Admin nicht.
 */
final class MailAdminRoleTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    #[Test]
    public function die_admin_rolle_hat_alle_mail_rechte(): void
    {
        $role  = FixtureFactory::role(['permissions' => ['admin'], 'admin' => true]);
        $admin = $this->member('Adam Admin', ['role' => $role->id]);
        $bob   = $this->member('Bob Empfang');
        $this->loginAs($admin['user'], Permissions::retrieveFromDatabase((int) $admin['user']->id));
        $this->assertSame(['admin'], $_SESSION['permissions']);

        $this->assertOk($this->get('/mail'));
        $this->assertTrue($this->send(['to' => [$bob['mailbox']->address]])['success']);

        $this->assertOk($this->get('/mail/lists'));
        $this->assertRedirect($this->post('/mail/lists', [
            'name' => 'Wache 1', 'local' => 'wache1', 'domain' => 'ignis.ef', 'kind' => 'static', 'senders' => 'managers',
            'members' => [(string) $bob['mailbox']->id],
        ]), '/mail/lists');
        $this->assertTrue($this->send(['to' => ['wache1@ignis.ef']])['success'], 'Admin schreibt auch an eingeschränkte Verteiler.');

        $this->assertOk($this->get('/settings/mail'));
        $this->assertOk($this->get('/settings/mail/mailboxes'));
        $this->assertBodyContains('name="domain"', $this->get('/settings/mail/mailboxes/' . $bob['mailbox']->id . '/edit'));

        // Bobs Posteingang bleibt Bobs.
        $this->loginAs($bob['user']);
        $incoming = (int) $this->send(['to' => [$bob['mailbox']->address]])['messageId'];
        $this->loginAs($admin['user'], ['admin']);
        $this->assertNotFound($this->get('/mail/inbox/' . $incoming));
    }
}
