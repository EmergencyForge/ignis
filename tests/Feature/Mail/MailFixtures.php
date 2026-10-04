<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Models\AmbSkill;
use App\Models\FdSkill;
use App\Models\Personnel;
use App\Models\Rank;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Mail\MailboxProvisioner;
use Plugin\Mail\Models\Mailbox;
use Tests\FixtureFactory;

/**
 * Gemeinsame Test-Daten des Mailmoduls: Dienstgrade, Qualifikationen,
 * Mitarbeiter mit Konto und Postfach.
 */
trait MailFixtures
{
    private ?Rank $mailRank = null;
    private ?AmbSkill $mailRd = null;
    private ?FdSkill $mailFw = null;

    protected function setUpMail(): void
    {
        Mailbox::forget();
        Capsule::table('intra_config')->where('config_key', 'MAIL_DOMAIN')->update(['config_value' => 'ignis.ef']);
        Capsule::table('intra_config')->where('config_key', 'MAIL_ADDRESS_PATTERN')->update(['config_value' => 'initial_dot_last']);
        Capsule::table('intra_config')->where('config_key', 'MAIL_ALLOWED_DOMAINS')->update(['config_value' => '']);
        Capsule::table('intra_config')->where('config_key', 'MAIL_DEFAULT_SIGNATURE')->update(['config_value' => '']);
        // Die Sendepause prüft MailSendCooldownTest; andere Tests senden schnell hintereinander.
        Capsule::table('intra_config')->where('config_key', 'MAIL_SEND_COOLDOWN')->update(['config_value' => '0']);
    }

    protected function rank(bool $archive = false): Rank
    {
        if (!$archive && $this->mailRank !== null) {
            return $this->mailRank;
        }
        $rank = new Rank();
        $rank->name     = 'Rang_' . uniqid();
        $rank->name_m   = $rank->name;
        $rank->name_w   = $rank->name;
        $rank->priority = 10;
        $rank->archive  = $archive;
        $rank->save();

        return $archive ? $rank : $this->mailRank = $rank;
    }

    protected function rdQuali(): AmbSkill
    {
        $rd = new AmbSkill();
        $rd->name     = 'RD_' . uniqid();
        $rd->name_m   = $rd->name;
        $rd->name_w   = $rd->name;
        $rd->priority = 0;
        $rd->none     = false;
        $rd->save();

        return $rd;
    }

    protected function fwQuali(): FdSkill
    {
        $fw = new FdSkill();
        $fw->name      = 'FW_' . uniqid();
        $fw->shortname = 'FW';
        $fw->name_m    = $fw->name;
        $fw->name_w    = $fw->name;
        $fw->priority  = 0;
        $fw->none      = false;
        $fw->save();

        return $fw;
    }

    /** Legt einen Fachdienst (Sachgebiet) an und liefert seine Id. */
    protected function fachdienst(int $sgnr, string $name): int
    {
        return (int) Capsule::table('intra_mitarbeiter_fdquali')->insertGetId(['sgnr' => $sgnr, 'sgname' => $name, 'disabled' => 0]);
    }

    /** @param array<string,mixed> $overrides */
    protected function mitarbeiter(string $fullname, array $overrides = []): Personnel
    {
        $this->mailRd ??= $this->rdQuali();
        $this->mailFw ??= $this->fwQuali();

        $person = new Personnel();
        $person->fullname   = $fullname;
        $person->dienstnr   = 'M-' . uniqid();
        $person->gebdatum   = new \DateTime('1990-01-01');
        $person->geschlecht = 0;
        $person->einstdatum = new \DateTime('2024-01-01');
        $person->dienstgrad = $this->rank()->id;
        $person->qualird    = $this->mailRd->id;
        $person->qualifw2   = $this->mailFw->id;
        foreach ($overrides as $key => $value) {
            $person->{$key} = $value;
        }
        $person->save();
        // Wie nach PersonnelSaved: über die Discord-ID verknüpfen, wenn sie eindeutig passt.
        \App\Personnel\AccountLink::autoLinkByDiscord($person->discordtag);

        return $person;
    }

    protected function provision(Personnel $person): Mailbox
    {
        $mailbox = app(MailboxProvisioner::class)->sync((int) $person->id);
        $this->assertNotNull($mailbox, 'Postfach für ' . $person->fullname . ' fehlt.');

        return $mailbox;
    }

    /**
     * Konto mit Mitarbeiter und Postfach.
     *
     * @param array<string,mixed> $userOverrides
     * @return array{user: User, person: Personnel, mailbox: Mailbox}
     */
    protected function member(string $fullname, array $userOverrides = []): array
    {
        $user   = FixtureFactory::user($userOverrides);
        $person = $this->mitarbeiter($fullname, ['discordtag' => (string) $user->discord_id]);

        return ['user' => $user, 'person' => $person, 'mailbox' => $this->provision($person)];
    }

    /** @param list<string> $permissions */
    protected function loginAs(User $user, array $permissions = ['mail.use']): void
    {
        Mailbox::forget();
        $this->actingAs($user->id, ['permissions' => $permissions, 'cirs_username' => $user->username, 'discordtag' => (string) $user->discord_id]);
    }

    /**
     * Legt über die JSON-Route einen Entwurf an und liefert seine ID.
     *
     * @param array<string,mixed> $fields
     */
    protected function draft(array $fields = []): int
    {
        $response = $this->post('/mail/drafts', $fields + [
            'subject'   => 'Betreff',
            'body_json' => self::doc('Hallo'),
        ]);
        $data = $this->assertJsonResponse($response);
        $this->assertTrue($data['success'] ?? false, (string) ($data['message'] ?? ''));

        return (int) $data['messageId'];
    }

    /**
     * Entwurf anlegen und senden; liefert die Antwort des Sendens.
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    protected function send(array $fields): array
    {
        $id = $this->draft();

        return $this->assertJsonResponse($this->post('/mail/drafts/' . $id . '/send', $fields + [
            'subject'   => 'Betreff',
            'body_json' => self::doc('Hallo'),
        ]));
    }

    protected static function doc(string $text): string
    {
        return (string) json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]]);
    }

    /** @return list<array{mailbox_id:int, role:string, folder:string}> */
    protected function deliveries(int $messageId): array
    {
        return Capsule::table('intra_mail_deliveries')->where('message_id', $messageId)->orderBy('mailbox_id')->orderBy('role')
            ->get(['mailbox_id', 'role', 'folder'])
            ->map(static fn ($r): array => ['mailbox_id' => (int) $r->mailbox_id, 'role' => (string) $r->role, 'folder' => (string) $r->folder])
            ->all();
    }
}
