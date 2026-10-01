<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\MailDirectory;
use Plugin\Mail\Models\MailList;
use EmergencyForge\Mail\ListRef;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Verteiler und Adressbuch: statische und dynamische Mitglieder (Rolle,
 * Dienstgrad, RD-/FW-Qualifikation, Fachdienst), gesperrte Postfächer bekommen nichts,
 * `senders = managers` nimmt nur Post von der Verteiler-Verwaltung, und
 * das Adressbuch zeigt nur, was der Nutzer anschreiben darf.
 */
final class MailDirectoryTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    /** @param array<string,mixed> $rule */
    private function list(string $address, string $kind = 'static', array $rule = [], string $senders = MailList::SENDERS_ALL): MailList
    {
        return MailList::query()->create([
            'address' => $address,
            'name'    => ucfirst(strtok($address, '@') ?: 'Liste'),
            'kind'    => $kind,
            'rule'    => $kind === 'dynamic' ? $rule : null,
            'senders' => $senders,
        ]);
    }

    /** @return list<string> */
    private function members(MailList $list): array
    {
        $addresses = array_map(static fn ($ref): string => $ref->address, (new MailDirectory())->listMembers(new ListRef($list->id, $list->address)));
        sort($addresses);

        return $addresses;
    }

    #[Test]
    public function statische_mitglieder_ohne_gesperrte_und_inaktive(): void
    {
        $a = $this->member('Anna Aktiv')['mailbox'];
        $b = $this->member('Bert Gesperrt')['mailbox'];
        $c = $this->member('Cleo Inaktiv')['mailbox'];
        $b->update(['locked' => 1]);
        $c->update(['active' => 0]);

        $list = $this->list('team@ignis.ef');
        foreach ([$a, $b, $c] as $mailbox) {
            Capsule::table('intra_mail_list_members')->insert(['list_id' => $list->id, 'mailbox_id' => $mailbox->id]);
        }

        $this->assertSame([$a->address], $this->members($list));
    }

    #[Test]
    public function dynamische_regel_nach_rolle_dienstgrad_und_qualifikation(): void
    {
        $role   = FixtureFactory::role();
        $rank   = $this->rank(archive: false);
        $other  = new \App\Models\Rank(['name' => 'Anders', 'name_m' => 'Anders', 'name_w' => 'Anders', 'priority' => 5, 'archive' => 0]);
        $other->save();
        $rd = $this->rdQuali();
        $fw = $this->fwQuali();

        $byRole = $this->member('Rolf Rolle', ['role' => $role->id]);
        $byRole['person']->update(['dienstgrad' => $other->id]);
        $byRank = $this->mitarbeiter('Dina Dienstgrad');
        $byRd   = $this->mitarbeiter('Rita Rd', ['dienstgrad' => $other->id, 'qualird' => $rd->id]);
        $byFw   = $this->mitarbeiter('Falk Fw', ['dienstgrad' => $other->id, 'qualifw2' => $fw->id]);
        $none   = $this->mitarbeiter('Nils Nichts', ['dienstgrad' => $other->id]);
        $locked = $this->mitarbeiter('Lars Gesperrt');
        foreach ([$byRank, $byRd, $byFw, $none, $locked] as $person) {
            $this->provision($person);
        }
        \Plugin\Mail\Models\Mailbox::query()->where('mitarbeiter_id', $locked->id)->update(['locked' => 1]);

        $list = $this->list('dyn@ignis.ef', 'dynamic', [
            'role_ids' => [$role->id], 'rank_ids' => [$rank->id], 'rd_quali_ids' => [$rd->id], 'fw_quali_ids' => [$fw->id],
        ]);

        $this->assertSame(['d.dienstgrad@ignis.ef', 'f.fw@ignis.ef', 'r.rd@ignis.ef', 'r.rolle@ignis.ef'], $this->members($list));
        $this->assertSame([], $this->members($this->list('leer@ignis.ef', 'dynamic', [])));
    }

    #[Test]
    public function dynamische_regel_nach_fachdienst(): void
    {
        $atemschutz = $this->fachdienst(901, 'Atemschutz');
        $this->fachdienst(902, 'Gefahrgut');

        $mit     = $this->mitarbeiter('Anja Atemschutz', ['fachdienste' => '["901","210"]']);
        $zahlen  = $this->mitarbeiter('Alois Altdaten', ['fachdienste' => '[901]']);
        $anderer = $this->mitarbeiter('Gero Gefahrgut', ['fachdienste' => '["902"]']);
        $kaputt  = $this->mitarbeiter('Kai Kaputt', ['fachdienste' => 'kein json']);
        $ohne    = $this->mitarbeiter('Olaf Ohne');
        foreach ([$mit, $zahlen, $anderer, $kaputt, $ohne] as $person) {
            $this->provision($person);
        }

        $list = $this->list('atemschutz@ignis.ef', 'dynamic', ['fachdienst_ids' => [$atemschutz]]);

        $this->assertSame(['a.altdaten@ignis.ef', 'a.atemschutz@ignis.ef'], $this->members($list));
        // Ein Fachdienst, den niemand hat, heißt: niemand, nicht alle.
        $keiner = $this->fachdienst(904, 'Taucher');
        $this->assertSame([], $this->members($this->list('taucher@ignis.ef', 'dynamic', ['fachdienst_ids' => [$keiner]])));
    }

    #[Test]
    public function verteiler_nur_fuer_die_verwaltung(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $restricted = $this->list('leitung@ignis.ef', senders: MailList::SENDERS_MANAGERS);
        $restricted->update(['name' => 'Geheime Runde']);
        Capsule::table('intra_mail_list_members')->insert(['list_id' => $restricted->id, 'mailbox_id' => $bob['mailbox']->id]);

        // Normale Nutzer: abgelehnt, egal ob An, CC oder BCC.
        $this->loginAs($alice['user']);
        foreach (['to', 'cc', 'bcc'] as $field) {
            $response = $this->post('/mail/drafts/' . $this->draft() . '/send', [$field => ['leitung@ignis.ef']]);
            $this->assertStatus(422, $response);
            // Nur die Adresse, nie der Name eines Verteilers, den man nicht sieht.
            $message = (string) ($this->assertJsonResponse($response)['message'] ?? '');
            $this->assertStringContainsString('leitung@ignis.ef', $message);
            $this->assertStringNotContainsString('Geheime Runde', $message);
        }

        // Allen antworten: der gespeicherte Empfängerkopf mit dem Verteiler
        // wird beim Senden genauso geprüft.
        $replyAll = $this->draft(['to' => [$bob['mailbox']->address, 'leitung@ignis.ef']]);
        $this->assertStatus(422, $this->post('/mail/drafts/' . $replyAll . '/send', []));

        // Die Verteiler-Verwaltung darf.
        $this->loginAs($alice['user'], ['mail.use', 'mail.lists.manage']);
        $sent = $this->assertJsonResponse($this->post('/mail/drafts/' . $replyAll . '/send', []));
        $this->assertTrue($sent['success']);
        $this->assertContains(['mailbox_id' => $bob['mailbox']->id, 'role' => 'to', 'folder' => 'inbox'], $this->deliveries($replyAll));

        // Wer antwortet, sieht den Verteiler als bloße Adresse.
        $this->loginAs($bob['user']);
        $compose = $this->get('/mail/compose/reply-all/' . $replyAll);
        $this->assertBodyContains('leitung@ignis.ef', $compose);
        $this->assertBodyNotContains('Geheime Runde', $compose);
        $this->loginAs($alice['user'], ['mail.use', 'mail.lists.manage']);
        $this->assertBodyContains('Geheime Runde', $this->get('/mail/compose/reply-all/' . $replyAll));
    }

    #[Test]
    public function adressbuch_zeigt_nur_was_man_anschreiben_darf(): void
    {
        $alice = $this->member('Alice Absender');
        $this->member('Bernd Sucher');
        $gone = $this->member('Berta Gesperrt')['mailbox'];
        $gone->update(['locked' => 1]);
        $this->list('bereitschaft@ignis.ef');
        $this->list('berater@ignis.ef', senders: MailList::SENDERS_MANAGERS);

        $this->loginAs($alice['user']);
        $response = $this->get('/mail/addressbook', ['query' => ['q' => 'be']]);
        $this->assertSame('private, no-store', $response->headers['Cache-Control'] ?? null);
        $addresses = array_column($this->assertJsonResponse($response)['results'], 'address');
        $this->assertContains('b.sucher@ignis.ef', $addresses);
        $this->assertContains('bereitschaft@ignis.ef', $addresses);
        $this->assertNotContains($gone->address, $addresses);
        $this->assertNotContains('berater@ignis.ef', $addresses);

        // LIKE-Platzhalter sind Text, kein Joker.
        $this->assertSame([], $this->assertJsonResponse($this->get('/mail/addressbook', ['query' => ['q' => '%']]))['results']);

        $this->loginAs($alice['user'], ['mail.use', 'mail.lists.manage']);
        $addresses = array_column($this->assertJsonResponse($this->get('/mail/addressbook', ['query' => ['q' => 'be']]))['results'], 'address');
        $this->assertContains('berater@ignis.ef', $addresses);
    }
}
