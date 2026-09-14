<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\CsrfMiddleware;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Der CSRF-Schutz hängt global am Router.
 *
 * Vorher musste ihn jede Route einzeln anmelden, und elf von
 * vierundsechzig schreibenden taten das — die Rollenverwaltung nicht, der
 * Auslöser des Systemupdates nicht. Die Lücke war keine falsche Zeile,
 * sondern eine vergessene: genau die Sorte Fehler, die ein Test finden
 * muss, weil beim Lesen nichts auffällt.
 *
 * Deshalb prüfen die Tests hier nicht Route für Route, sondern die beiden
 * Stellen, an denen der Schutz überhaupt verloren gehen kann: der Haken am
 * Router und die Ausnahmeliste.
 */
final class CsrfTest extends FeatureTestCase
{
    private function login(): void
    {
        $this->actingAs(FixtureFactory::user(['full_admin' => true])->id, ['permissions' => []]);
    }

    #[Test]
    public function ohne_token_kommt_die_403_seite(): void
    {
        $this->login();

        // request() statt post(): post() legt den Token bei.
        $response = $this->request('POST', '/settings/personnel/ranks/create', [
            'post' => ['name' => 'Brandmeister', 'name_m' => 'Brandmeister', 'name_w' => 'Brandmeisterin'],
        ]);

        $this->assertStatus(403, $response);
        $this->assertStringContainsString('abgelaufen', $response->body);
    }

    #[Test]
    public function ein_fremder_token_reicht_nicht(): void
    {
        $this->login();

        $response = $this->request('POST', '/users/roles/create', [
            'post' => ['name' => 'Neu', 'csrf_token' => str_repeat('a', 64)],
        ]);

        $this->assertStatus(403, $response);
    }

    #[Test]
    public function der_header_zaehlt_genauso_wie_das_feld(): void
    {
        $this->login();

        // So kommen die fetch-Aufrufe an, siehe csrf_head() in src/helpers.php.
        $response = $this->request('POST', '/profile/theme', [
            'post'    => ['theme' => 'light'],
            'headers' => ['X-CSRF-Token' => $this->csrfToken()],
        ]);

        $this->assertNotSame(403, $response->status);
    }

    #[Test]
    public function lesende_anfragen_bleiben_unberuehrt(): void
    {
        $this->login();

        $this->assertNotSame(403, $this->get('/settings/personnel/ranks/index')->status);
    }

    #[Test]
    public function der_token_gilt_fuer_mehr_als_eine_anfrage(): void
    {
        // Eine Seite mit zwei Formularen traegt in beiden denselben Token.
        // Solange er nach jeder Pruefung rotierte, war das zweite Formular
        // nach dem Absenden des ersten tot — der Grund, warum der Schutz
        // nie flaechendeckend angezogen werden konnte.
        $this->login();
        $token = $this->csrfToken();

        $erste  = $this->request('POST', '/profile/theme', ['post' => ['theme' => 'light', 'csrf_token' => $token]]);
        $zweite = $this->request('POST', '/profile/theme', ['post' => ['theme' => 'dark', 'csrf_token' => $token]]);

        $this->assertNotSame(403, $erste->status);
        $this->assertNotSame(403, $zweite->status);
    }

    #[Test]
    public function die_ausnahmeliste_bleibt_bei_den_maschinen_endpunkten(): void
    {
        // Kein Test der Implementierung, sondern eine Bremse: wer hier
        // etwas einträgt, nimmt eine Route aus dem Schutz. Das soll auffallen.
        $exempt = (new ReflectionClass(CsrfMiddleware::class))->getConstant('EXEMPT');

        $this->assertSame([
            '/api/character/identify',
            '/api/emd/sync',
            '/api/emd-sync.php',
            '/api/asu/sync',
            '/api/asu-sync.php',
            '/api/telemetry/heartbeat',
            '/api/telemetry-heartbeat.php',
            '/api/emd/status-poll',
        ], $exempt);
    }
}
