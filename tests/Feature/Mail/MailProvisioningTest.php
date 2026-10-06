<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Events\EventDispatcher;
use App\Events\PersonnelSaved;
use App\Models\Personnel;
use App\Models\PersonnelTitle;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\MailboxProvisioner;
use Plugin\Mail\Models\Mailbox;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Postfächer folgen den Mitarbeitern (Plugin\Mail\MailboxProvisioner):
 * angelegt beim Anlegen, stillgelegt im Archiv-Dienstgrad und beim
 * Löschen, die Sperre der Administration bleibt, freigewordene und
 * Verteiler-Adressen gelten als vergeben.
 */
final class MailProvisioningTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    private function mailboxOf(int $mitarbeiterId): ?Mailbox
    {
        return Mailbox::query()->where('mitarbeiter_id', $mitarbeiterId)->first();
    }

    #[Test]
    public function anlegen_ueber_das_formular_legt_das_postfach_an(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);

        $response = $this->post('/personnel/create', [
            'fullname'   => 'Max von der Heide',
            'gebdatum'   => '1990-01-01',
            'dienstgrad' => (string) $this->rank()->id,
            'geschlecht' => '0',
            'discordtag' => (string) random_int(100000000000000000, 999999999999999999),
            'telefonnr'  => '',
            'dienstnr'   => 'MX-' . uniqid(),
            'einstdatum' => '2024-01-01',
            'charakterid' => 'CHAR-1',
        ]);
        $this->assertRedirect($response, '/personnel/profile');

        $id = (int) Personnel::query()->where('fullname', 'Max von der Heide')->value('id');
        $mailbox = $this->mailboxOf($id);
        $this->assertNotNull($mailbox);
        $this->assertSame('m.von-der-heide@ignis.ef', $mailbox->address);
        $this->assertSame('Max von der Heide', $mailbox->display_name);
        $this->assertSame('ignis.ef', $mailbox->domain);
        $this->assertTrue($mailbox->active);
    }

    #[Test]
    public function titel_im_anzeigenamen_aber_nicht_in_der_adresse(): void
    {
        $dr = (int) PersonnelTitle::query()->where('name', 'Dr.')->value('id');
        $mailbox = $this->provision($this->mitarbeiter('Max Muster', ['titel_id' => $dr]));

        $this->assertSame('m.muster@ignis.ef', $mailbox->address);
        $this->assertSame('Dr. Max Muster', $mailbox->display_name);
    }

    #[Test]
    public function muster_vorname_punkt_nachname_und_dopplungen(): void
    {
        Capsule::table('intra_config')->where('config_key', 'MAIL_ADDRESS_PATTERN')->update(['config_value' => 'first_dot_last']);
        $a = $this->provision($this->mitarbeiter('Jörg Müller'));
        $b = $this->provision($this->mitarbeiter('Jörg Müller'));
        $this->assertSame('joerg.mueller@ignis.ef', $a->address);
        $this->assertSame('joerg.mueller2@ignis.ef', $b->address);

        Capsule::table('intra_config')->where('config_key', 'MAIL_ADDRESS_PATTERN')->update(['config_value' => 'initial_dot_last']);
        $c = $this->provision($this->mitarbeiter('Anna Schmidt'));
        $d = $this->provision($this->mitarbeiter('Anja Schmidt'));
        $this->assertSame('a.schmidt@ignis.ef', $c->address);
        $this->assertSame('an.schmidt@ignis.ef', $d->address);
    }

    #[Test]
    public function verteiler_und_fruehere_adressen_gelten_als_vergeben(): void
    {
        Capsule::table('intra_mail_lists')->insert(['address' => 'k.lang@ignis.ef', 'name' => 'Liste', 'kind' => 'static']);
        $owner = $this->provision($this->mitarbeiter('Karl Kurz'));
        Capsule::table('intra_mail_address_history')->insert(['address' => 'k.lang2@ignis.ef', 'mailbox_id' => $owner->id]);

        $mailbox = $this->provision($this->mitarbeiter('Klaus Lang'));
        $this->assertSame('kl.lang@ignis.ef', $mailbox->address);

        Capsule::table('intra_config')->where('config_key', 'MAIL_ADDRESS_PATTERN')->update(['config_value' => 'first_dot_last']);
        Capsule::table('intra_mail_address_history')->insert(['address' => 'otto.berg@ignis.ef', 'mailbox_id' => $owner->id]);
        $this->assertSame('otto.berg2@ignis.ef', $this->provision($this->mitarbeiter('Otto Berg'))->address);
    }

    #[Test]
    public function archiv_dienstgrad_legt_still_und_rueckkehr_reaktiviert(): void
    {
        $person  = $this->mitarbeiter('Eva Ende');
        $mailbox = $this->provision($person);

        $person->dienstgrad = $this->rank(archive: true)->id;
        $person->save();
        app(EventDispatcher::class)->fire(new PersonnelSaved((int) $person->id));
        $this->assertFalse($mailbox->refresh()->active);

        $person->dienstgrad = $this->rank()->id;
        $person->fullname   = 'Eva Neu';
        $person->save();
        app(EventDispatcher::class)->fire(new PersonnelSaved((int) $person->id));
        $mailbox->refresh();
        $this->assertTrue($mailbox->active);
        $this->assertSame('Eva Neu', $mailbox->display_name);
        $this->assertSame('e.ende@ignis.ef', $mailbox->address, 'Die Adresse bleibt, nur der Name folgt.');
    }

    #[Test]
    public function eine_sperre_uebersteht_jedes_speichern(): void
    {
        $person  = $this->mitarbeiter('Lena Lock');
        $mailbox = $this->provision($person);
        $mailbox->locked = true;
        $mailbox->save();

        app(EventDispatcher::class)->fire(new PersonnelSaved((int) $person->id));
        app(MailboxProvisioner::class)->sync((int) $person->id);

        $this->assertTrue($mailbox->refresh()->locked);
    }

    #[Test]
    public function loeschen_legt_still_und_das_postfach_bleibt(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
        $person  = $this->mitarbeiter('Gerd Weg');
        $mailbox = $this->provision($person);

        $this->assertRedirect($this->post('/personnel/delete', ['id' => (string) $person->id]));

        $mailbox->refresh();
        $this->assertNull($mailbox->mitarbeiter_id);
        $this->assertFalse($mailbox->active);
        $this->assertSame('g.weg@ignis.ef', $mailbox->address);
    }

    #[Test]
    public function backfill_legt_an_ist_idempotent_und_legt_verwaiste_still(): void
    {
        $person = $this->mitarbeiter('Bernd Bestand');
        $orphan = Mailbox::query()->create(['mitarbeiter_id' => null, 'address' => 'weg@ignis.ef', 'display_name' => 'Weg', 'domain' => 'ignis.ef', 'active' => 1, 'locked' => 0]);

        $tester = $this->commandTester('mail:backfill');
        $this->assertSame(0, $tester->execute([]));
        $this->assertSame('b.bestand@ignis.ef', $this->mailboxOf((int) $person->id)?->address);
        $this->assertFalse(Mailbox::query()->findOrFail($orphan->id)->active);

        $count = Mailbox::query()->count();
        $this->assertSame(0, $this->commandTester('mail:backfill')->execute([]));
        $this->assertSame($count, Mailbox::query()->count());
    }

    #[Test]
    public function backfill_und_aufraeumen_laufen_naechtlich(): void
    {
        $jobs = Capsule::table('intra_cron_jobs')->whereIn('identifier', ['mail.backfill', 'mail.cleanup'])
            ->where('active', 1)->where('handler_type', 'console')->pluck('handler', 'identifier')->all();

        ksort($jobs);

        $this->assertSame(['mail.backfill' => 'mail:backfill', 'mail.cleanup' => 'mail:cleanup'], $jobs);
    }
}
