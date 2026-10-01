<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\Models\Mailbox;
use Plugin\Mail\Models\Message;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Sendepause bei zwei gleichzeitigen Sendungen aus demselben Postfach
 * (zwei Tabs, zwei Entwürfe). Die Versand-Transaktion muss hier echt
 * sein: in der Test-Transaktion stünde der Lese-Snapshot schon seit
 * setUp() fest. Deshalb ohne Test-Transaktion und mit eigenem Aufräumen.
 */
final class MailSendCooldownParallelTest extends FeatureTestCase
{
    protected bool $useTransactions = false;

    private ?int $roleId = null;
    private ?int $userId = null;
    private ?int $mailboxId = null;
    private ?string $cooldownBefore = null;

    protected function tearDown(): void
    {
        if ($this->mailboxId !== null) {
            Capsule::table('intra_mail_messages')->where('sender_mailbox_id', $this->mailboxId)->delete();
            Capsule::table('intra_mail_mailboxes')->where('id', $this->mailboxId)->delete();
        }
        if ($this->userId !== null) {
            Capsule::table('intra_users')->where('id', $this->userId)->delete();
        }
        if ($this->roleId !== null) {
            Capsule::table('intra_users_roles')->where('id', $this->roleId)->delete();
        }
        if ($this->cooldownBefore !== null) {
            Capsule::table('intra_config')->where('config_key', 'MAIL_SEND_COOLDOWN')->update(['config_value' => $this->cooldownBefore]);
        }
        Mailbox::forget();
        parent::tearDown();
    }

    /** Eigene Verbindung, die neben der Versand-Transaktion committet. */
    private static function otherConnection(): PDO
    {
        $pdo = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';port=' . (int) ($_ENV['DB_PORT'] ?? 3306) . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
            (string) $_ENV['DB_USER'],
            (string) $_ENV['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        // Ohne Fremdschlüssel-Prüfung: die bräuchte die Postfach-Zeile, die
        // die Versand-Transaktion in diesem Moment gesperrt hält.
        $pdo->exec('SET SESSION foreign_key_checks = 0');

        return $pdo;
    }

    #[Test]
    public function eine_mail_die_waehrend_des_wartens_auf_die_sperre_rausging_zaehlt(): void
    {
        $this->cooldownBefore = (string) Capsule::table('intra_config')->where('config_key', 'MAIL_SEND_COOLDOWN')->value('config_value');
        Capsule::table('intra_config')->where('config_key', 'MAIL_SEND_COOLDOWN')->update(['config_value' => '10']);

        $role = FixtureFactory::role();
        $this->roleId = (int) $role->id;
        $user = FixtureFactory::user(['role' => $role->id]);
        $this->userId = (int) $user->id;
        $domain = (string) Capsule::table('intra_config')->where('config_key', 'MAIL_DOMAIN')->value('config_value');
        $address = 'parallel.' . uniqid() . '@' . $domain;
        $this->mailboxId = (int) Capsule::table('intra_mail_mailboxes')->insertGetId([
            'address' => $address, 'display_name' => 'Parallel Schreiber', 'domain' => $domain, 'user_id' => $user->id,
        ]);

        Mailbox::forget();
        $this->actingAs((int) $user->id, ['permissions' => ['mail.use'], 'cirs_username' => $user->username]);
        $fields = ['subject' => 'Zweiter Tab', 'body_json' => (string) json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Text']]]]]), 'to' => [$address]];
        $created = $this->assertJsonResponse($this->post('/mail/drafts', $fields));
        $draftId = (int) $created['messageId'];

        // Der andere Tab committet seine Mail, während dieser Request auf
        // die Postfach-Sperre wartet: hier unmittelbar nach der Sperre.
        $other = self::otherConnection();
        $connection = Capsule::connection();
        $previousEvents = $connection->getEventDispatcher();
        $events = new Dispatcher();
        $connection->setEventDispatcher($events);
        $parallelSent = false;
        $events->listen(QueryExecuted::class, function (QueryExecuted $query) use ($other, &$parallelSent): void {
            if ($parallelSent || !str_contains($query->sql, 'intra_mail_mailboxes') || !str_contains($query->sql, 'for update')) {
                return;
            }
            $parallelSent = true;
            $other->prepare("INSERT INTO intra_mail_messages (sender_mailbox_id, subject, body_json, thread_id, status, sent_at) VALUES (?, 'Erster Tab', '{}', ?, 'sent', ?)")
                ->execute([$this->mailboxId, 'parallel-' . uniqid(), date('Y-m-d H:i:s')]);
        });

        try {
            $response = $this->post('/mail/drafts/' . $draftId . '/send', $fields);
        } finally {
            $connection->setEventDispatcher($previousEvents);
        }

        $this->assertTrue($parallelSent, 'Die Versand-Transaktion hat das Postfach nicht gesperrt.');
        $this->assertStatus(429, $response);
        $this->assertSame('draft', Message::query()->findOrFail($draftId)->status);
    }
}
