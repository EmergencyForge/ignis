<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\ErrorPage;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Die Fehlerseiten kommen aus dem UI-Paket (Fehlerseite in auth()): die
 * Hülle der Anmeldung als eine Spalte, darüber die Ziffern des Fehlers.
 * Sie laufen ohne Anmeldung und ohne head.php, deshalb stehen Stylesheets
 * und Module in templates/errors/_shell.php selbst.
 */
final class ErrorPageTest extends FeatureTestCase
{
    #[Test]
    public function die_404_zeigt_ziffern_und_die_adresse_escaped(): void
    {
        $response = $this->get('/gibt-es-nicht/%3Cscript%3Ealert(1)%3C/script%3E');

        $this->assertStatus(404, $response);
        $body = $response->body;

        $this->assertStringContainsString('<body data-ui-skin="core" data-page="error-404">', $body);
        $this->assertStringContainsString('<div class="twplus-login twplus-login--page">', $body);
        $this->assertStringContainsString('<div class="twplus-login__brand">', $body);
        // Das Licht steht vor den Ziffern, die helle Lage hängt per ~ daran.
        $this->assertMatchesRegularExpression(
            '~<div class="ignis-error-stage__ember"></div>\s*'
            . '<span class="ignis-error-stage__digit" data-digit="4"></span>\s*'
            . '<span class="ignis-error-stage__digit" data-digit="0"></span>\s*'
            . '<span class="ignis-error-stage__digit" data-digit="4"></span>\s*</div>~',
            $body,
        );
        $this->assertStringContainsString('<span class="ignis-sr-only">Fehler 404: </span>Seite nicht gefunden</h1>', $body);
        $this->assertStringContainsString('<code>/gibt-es-nicht/&lt;script&gt;alert(1)&lt;/script&gt;</code>', $body);
        $this->assertStringNotContainsString('<script>alert', $body);
        $this->assertStringContainsString('Zur Startseite', $body);
        $this->assertStringContainsString('data-ignis-history-back hidden', $body);
        $this->assertStringContainsString('assets/js/ui/error-stage.js', $body);
        // Ohne preferences.js folgten Licht und Knopf einer eigenen SYSTEM_COLOR nicht.
        $this->assertStringContainsString('assets/js/ui/preferences.js', $body);
        $this->assertStringContainsString('assets/dist/ui.css', $body);
    }

    #[Test]
    public function eine_sehr_lange_adresse_wird_gekuerzt(): void
    {
        $body = $this->get('/' . str_repeat('a', 300))->body;

        $this->assertStringContainsString('<code>/' . str_repeat('a', 118) . '…</code>', $body);
        $this->assertStringNotContainsString(str_repeat('a', 120), $body);
    }

    #[Test]
    public function ohne_adresse_fehlt_die_adresszeile(): void
    {
        $this->assertStringNotContainsString('twplus-login__path', ErrorPage::notFound()->body);
    }

    #[Test]
    public function ohne_sitzung_kommt_die_seite_trotzdem(): void
    {
        $session = $_SESSION;
        unset($_SESSION);
        try {
            $response = ErrorPage::notFound('/weg');
        } finally {
            $_SESSION = $session;
        }

        $this->assertStatus(404, $response);
        $this->assertStringContainsString('<code>/weg</code>', $response->body);
    }

    #[Test]
    public function die_403_traegt_ihre_ziffern_und_die_vorgabe(): void
    {
        $response = ErrorPage::forbidden('Fahrzeuge darfst du nicht sehen.', '/settings/vehicles');
        $body = $response->body;

        $this->assertStatus(403, $response);
        $this->assertMatchesRegularExpression('~data-digit="4"></span>\s*<span class="ignis-error-stage__digit" data-digit="0"></span>\s*<span class="ignis-error-stage__digit" data-digit="3">~', $body);
        $this->assertStringContainsString('<span class="ignis-sr-only">Fehler 403: </span>Dafür fehlt dir die Berechtigung</h1>', $body);
        $this->assertStringContainsString('<p class="twplus-login__lead">Fahrzeuge darfst du nicht sehen.</p>', $body);
        $this->assertStringNotContainsString('twplus-login__path', $body);
    }

    #[Test]
    public function api_pfade_bekommen_weiter_json(): void
    {
        $response = $this->get('/api/gibt-es-nicht');

        $this->assertStatus(404, $response);
        $this->assertStringContainsString('application/json', $response->headers['Content-Type'] ?? '');
        $this->assertSame(['success' => false, 'error' => 'not_found'], json_decode($response->body, true));
    }
}
