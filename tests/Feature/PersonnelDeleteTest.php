<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\PersonnelLog;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Mail\MailFixtures;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Mitarbeiter und Profil-Kommentare löschen nur per POST mit CSRF-Token.
 *
 * Vorher lief beides per GET: CsrfMiddleware prüft nur schreibende
 * Methoden, ein `<img src=".../personnel/delete?id=…">` auf einer beliebigen
 * Seite löschte also den Mitarbeiter, sobald ein berechtigter Admin sie
 * öffnete (seit dem Mail-Plugin legt das auch sein Postfach still).
 */
final class PersonnelDeleteTest extends FeatureTestCase
{
    use MailFixtures;

    private function login(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    private function comment(Personnel $person): PersonnelLog
    {
        $log = new PersonnelLog();
        $log->profilid  = $person->id;
        $log->type      = 1;
        $log->content   = 'Notiz';
        $log->paneluser = 'Test';
        $log->datetime  = date('Y-m-d H:i:s');
        $log->save();

        return $log;
    }

    #[Test]
    public function get_loescht_keinen_mitarbeiter(): void
    {
        $this->login();
        $person = $this->mitarbeiter('Bleibt Da');

        $this->get('/personnel/delete', ['query' => ['id' => (string) $person->id]]);

        $this->assertNotNull(Personnel::query()->find($person->id));
    }

    #[Test]
    public function post_ohne_token_loescht_keinen_mitarbeiter(): void
    {
        $this->login();
        $person = $this->mitarbeiter('Bleibt Auch');

        // request() statt post(): post() legt den Token bei.
        $response = $this->request('POST', '/personnel/delete', ['post' => ['id' => (string) $person->id]]);

        $this->assertStatus(403, $response);
        $this->assertNotNull(Personnel::query()->find($person->id));
    }

    #[Test]
    public function post_mit_token_loescht_den_mitarbeiter(): void
    {
        $this->login();
        $person = $this->mitarbeiter('Geht Weg');

        $this->assertRedirect($this->post('/personnel/delete', ['id' => (string) $person->id]));

        $this->assertNull(Personnel::query()->find($person->id));
    }

    #[Test]
    public function kommentar_loescht_nur_per_post_mit_token(): void
    {
        $this->login();
        $person = $this->mitarbeiter('Mit Notiz');
        $log    = $this->comment($person);

        $this->get('/personnel/comment-delete', ['query' => ['id' => (string) $log->logid, 'pid' => (string) $person->id]]);
        $this->assertNotNull(PersonnelLog::query()->find($log->logid));

        $response = $this->request('POST', '/personnel/comment-delete', ['post' => ['id' => (string) $log->logid]]);
        $this->assertStatus(403, $response);
        $this->assertNotNull(PersonnelLog::query()->find($log->logid));

        $this->assertRedirect($this->post('/personnel/comment-delete', ['id' => (string) $log->logid]));
        $this->assertNull(PersonnelLog::query()->find($log->logid));
    }
}
