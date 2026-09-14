<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\ErrorPage;
use App\Http\ForbiddenException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Eine verweigerte Berechtigung endete früher als Hinweis-Blase plus
 * Weiterleitung aufs Dashboard. Hier steht, was stattdessen ankommt: eine
 * Seite, die den Grund nennt und zurückführt — und für API-Pfade eine
 * JSON-Antwort, weil ein 302 auf HTML dort niemandem hilft.
 */
final class ErrorPageTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);
        parent::tearDown();
    }

    #[Test]
    public function die_403_seite_nennt_grund_und_rueckweg(): void
    {
        $response = ErrorPage::forbidden('Fahrzeuge darfst du nicht sehen.', '/settings/vehicles', '/dashboard', 'Zur Übersicht');

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('text/html', $response->headers['Content-Type'] ?? '');
        $this->assertStringContainsString('Fahrzeuge darfst du nicht sehen.', $response->body);
        $this->assertStringContainsString('href="/dashboard"', $response->body);
        $this->assertStringContainsString('Zur Übersicht', $response->body);
    }

    #[Test]
    public function api_pfade_bekommen_json_statt_einer_seite(): void
    {
        $response = ErrorPage::forbidden('Nichts für dich.', '/api/vehicles/list');

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('application/json', $response->headers['Content-Type'] ?? '');
        $this->assertSame(
            ['success' => false, 'error' => 'Nichts für dich.'],
            json_decode($response->body, true),
        );
    }

    #[Test]
    public function ein_json_aufrufer_bekommt_json_auch_ohne_api_pfad(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $response = ErrorPage::notFound('/irgendwas');

        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('application/json', $response->headers['Content-Type'] ?? '');
    }

    #[Test]
    public function die_404_seite_bleibt_html(): void
    {
        $response = ErrorPage::notFound('/gibt-es-nicht');

        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('Seite nicht gefunden', $response->body);
    }

    #[Test]
    public function die_exception_traegt_dieselbe_antwort(): void
    {
        $response = (new ForbiddenException('Kein Zutritt.', '/zurueck', 'Zurück', '/geheim'))->toResponse();

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('Kein Zutritt.', $response->body);
        $this->assertStringContainsString('href="/zurueck"', $response->body);
    }
}
