<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Support\NavigationCounters;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Einbindung in die Hülle: Sidebar-Eintrag „Mail“ mit Zähler, Glocke
 * (Benachrichtigungstyp `mail`) und die Suchgruppe „Mails“, die nur
 * eigene, nicht gelöschte Kopien findet und BCC nie verrät.
 */
final class MailIntegrationTest extends FeatureTestCase
{
    use MailFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        NavigationCounters::reset();
    }

    protected function tearDown(): void
    {
        NavigationCounters::reset();
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function search(string $q): array
    {
        $response = $this->get('/api/system/global-search', ['query' => ['q' => $q]]);
        $this->assertSame('private, no-store', $response->headers['Cache-Control'] ?? null);
        foreach ($this->assertJsonResponse($response)['results'] as $group) {
            if ($group['key'] === 'mails') {
                return $group;
            }
        }

        return ['items' => []];
    }

    #[Test]
    public function die_glocke_folgt_der_mail_und_braucht_ein_offenes_postfach(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $carla = $this->member('Carla Kopie');
        $this->loginAs($alice['user']);
        $id = (int) $this->send(['subject' => 'Wohin?', 'to' => [$bob['mailbox']->address, $carla['mailbox']->address]])['messageId'];
        $bell = static fn (int $userId): int => (int) Capsule::table('intra_notifications')->where('user_id', $userId)->where('type', 'mail')->count();
        $type = new \Plugin\Mail\Notifications\MailType();

        // Der Link führt dorthin, wo die Mail gerade liegt.
        $this->loginAs($bob['user']);
        // Er nennt das Postfach, damit er auch aus einem Gruppenpostfach richtig öffnet.
        $inBox = '?postfach=' . $bob['mailbox']->id;
        $this->assertSame('/mail/inbox/' . $id . $inBox, $type->link(['link' => '/mail/inbox/' . $id]));
        $this->post('/mail/messages/' . $id . '/move', ['folder' => 'archive']);
        $this->assertSame('/mail/archive/' . $id . $inBox, $type->link(['link' => '/mail/inbox/' . $id]));

        // Papierkorb nimmt den Eintrag mit, die anderen behalten ihren.
        $this->assertSame(1, $bell($bob['user']->id));
        $this->post('/mail/messages/' . $id . '/move', ['folder' => 'trash']);
        $this->assertSame(0, $bell($bob['user']->id));
        $this->assertSame(1, $bell($carla['user']->id));

        // Endgültig löschen ebenso.
        $this->loginAs($carla['user']);
        $this->post('/mail/messages/' . $id . '/delete');
        $this->assertSame(0, $bell($carla['user']->id));

        // Gesperrtes Postfach: keine Mail-Einträge in der Glocke.
        $this->assertTrue($type->allowed());
        $carla['mailbox']->update(['locked' => 1]);
        $this->loginAs($carla['user']);
        $this->assertFalse($type->allowed());
    }

    #[Test]
    public function sidebar_zaehlt_ungelesene_und_die_glocke_meldet_sie(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $this->loginAs($alice['user']);
        $id = (int) $this->send(['subject' => 'Einsatzbesprechung', 'to' => [$bob['mailbox']->address]])['messageId'];

        $this->loginAs($bob['user'], ['mail.use']);
        NavigationCounters::reset();
        $page = $this->get('/mail');
        $this->assertMatchesRegularExpression('~href="/mail"[^>]*>\s*<i class="fa-solid fa-envelope"[^>]*></i>\s*<span class="ignis-sidebar__label">Mail</span>\s*<span class="ignis-sidebar__count">1</span>~', $page->body);

        $inbox = $this->get('/inbox');
        $this->assertOk($inbox);
        $this->assertBodyContains('Neue Mail von Alice Absender', $inbox);
        $this->assertSame('/mail/inbox/' . $id, Capsule::table('intra_notifications')->where('user_id', $bob['user']->id)->where('type', 'mail')->value('link'));

        $this->post('/mail/messages/' . $id . '/read');
        NavigationCounters::reset();
        $this->assertSame(0, (int) Capsule::table('intra_notifications')->where('user_id', $bob['user']->id)->where('type', 'mail')->where('is_read', 0)->count(), 'Lesen der Mail erledigt den Glocken-Eintrag.');
        $this->assertDoesNotMatchRegularExpression('~ignis-sidebar__label">Mail</span>\s*<span class="ignis-sidebar__count">~', $this->get('/mail')->body);

        // Ohne mail.use kein Eintrag und kein Glockeneintrag.
        $this->loginAs($bob['user'], ['calendar.view']);
        $this->assertBodyNotContains('ignis-sidebar__label">Mail<', $this->get('/inbox'));
    }

    #[Test]
    public function suche_findet_nur_eigene_mails_und_verraet_kein_bcc(): void
    {
        $alice = $this->member('Alice Absender');
        $bob   = $this->member('Bob Empfang');
        $dora  = $this->member('Dora Blind');
        $eve   = $this->member('Eve Fremd');

        $this->loginAs($alice['user']);
        $id = (int) $this->send([
            'subject'   => 'Funkprobe Dienstag',
            'body_json' => self::doc('Treffpunkt Fahrzeughalle'),
            'to'        => [$bob['mailbox']->address],
            'bcc'       => [$dora['mailbox']->address],
        ])['messageId'];

        $this->loginAs($bob['user']);
        $hits = $this->search('Funkprobe')['items'];
        $this->assertCount(1, $hits);
        $this->assertSame('Funkprobe Dienstag', $hits[0]['label']);
        $this->assertSame('/mail/inbox/' . $id . '?postfach=' . $bob['mailbox']->id, $hits[0]['href']);
        $this->assertCount(1, $this->search('Fahrzeughalle')['items'], 'Treffer im Text.');
        $this->assertCount(1, $this->search('Alice')['items'], 'Treffer am Absender.');
        $this->assertSame([], $this->search('<p>')['items'], 'Tags im HTML sind kein Treffer.');
        $this->assertSame([], $this->search('Dora')['items'], 'BCC wird nicht durchsucht.');
        $this->assertSame([], $this->search('%')['items']);

        $this->loginAs($eve['user'], ['full_admin']);
        $this->assertSame([], $this->search('Funkprobe')['items'], 'Fremde Mails nie, auch nicht für Admins.');

        $this->loginAs($bob['user']);
        $this->post('/mail/messages/' . $id . '/delete');
        $this->assertSame([], $this->search('Funkprobe')['items'], 'Gelöschte Kopien nicht.');
    }
}
