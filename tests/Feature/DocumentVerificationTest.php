<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\VerifyController;
use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Security\CsrfProtection;
use DateTimeImmutable;
use EmergencyForge\Editor\Layout;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\VerificationCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Prüfcode beim Ausstellen, die öffentliche Seite /verify und der Widerruf.
 */
final class DocumentVerificationTest extends FeatureTestCase
{
    private int $mitarbeiterId = 0;

    /** @var list<string> */
    private array $pdfFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['admin'], 'cirs_username' => $user->username]);
        $this->mitarbeiterId = FixtureFactory::personnel(['fullname' => 'Erika Muster'])->id;
    }

    protected function tearDown(): void
    {
        foreach ($this->pdfFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function template(bool $printCode): EditorTemplate
    {
        $template                     = new EditorTemplate();
        $template->name               = 'Urkunde';
        $template->category           = 'Urkunde';
        $template->print_verification = $printCode;
        $template->is_active          = true;
        $template->content            = [
            'format' => 2,
            'layout' => Layout::defaults(),
            'body'   => ['type' => 'doc', 'content' => [[
                'type'    => 'paragraph',
                'content' => [['type' => 'docVariable', 'attrs' => ['name' => 'mitarbeiter.name']]],
            ]]],
            'header' => ['first' => null, 'rest' => null],
            'footer' => ['first' => null, 'rest' => null],
        ];
        $template->save();

        return $template;
    }

    /** @param array<string,mixed> $post */
    private function postWithToken(string $path, array $post = []): Response
    {
        return $this->post($path, $post + ['csrf_token' => CsrfProtection::getToken()]);
    }

    private function issued(bool $printCode = true): EditorDocument
    {
        $response = $this->postWithToken('/personnel/' . $this->mitarbeiterId . '/documents', [
            'template_id' => (string) $this->template($printCode)->id,
            'title'       => 'Grundlehrgang',
        ]);
        $this->assertSame(1, preg_match('~/documents/(\d+)/edit$~', $response->headers['Location'] ?? '', $m));
        $this->postWithToken('/documents/' . $m[1] . '/issue');

        $document = EditorDocument::findOrFail((int) $m[1]);
        $this->assertTrue($document->isIssued());
        $this->pdfFiles[] = dirname(__DIR__, 2) . '/' . $document->pdf_path;

        return $document;
    }

    private function verify(string $code, string $ip = '203.0.113.7'): Response
    {
        return $this->get('/verify', ['query' => ['code' => $code], 'server' => ['REMOTE_ADDR' => $ip]]);
    }

    private function pdf(string $code, string $ip = '203.0.113.8'): Response
    {
        return $this->get('/verify/pdf', ['query' => ['code' => $code], 'server' => ['REMOTE_ADDR' => $ip]]);
    }

    #[Test]
    public function verify_pdf_liefert_die_gueltige_urkunde(): void
    {
        $document = $this->issued();
        unset($_SESSION['userid'], $_SESSION['permissions']);

        $response = $this->pdf($document->verification_code);

        $this->assertOk($response);
        $this->assertSame('application/pdf', $response->headers['Content-Type'] ?? null);
        $this->assertStringStartsWith('%PDF', $response->body);
        $this->assertStatus(404, $this->pdf('ABCD-EFGH-JK'));
    }

    #[Test]
    public function ausstellen_mit_schalter_vergibt_einen_pruefcode(): void
    {
        $document = $this->issued();

        $this->assertNotNull($document->verification_code);
        $this->assertTrue(VerificationCode::isWellFormed($document->verification_code));
        $this->assertSame(VerificationCode::format($document->verification_code), $document->frozen_values['dokument.pruefcode'] ?? null);
    }

    #[Test]
    public function ausstellen_ohne_schalter_laesst_den_pruefcode_leer(): void
    {
        $document = $this->issued(false);

        $this->assertNull($document->verification_code);
        $this->assertSame('', $document->frozen_values['dokument.pruefcode'] ?? '');
    }

    #[Test]
    public function der_schalter_wird_an_der_vorlage_gespeichert(): void
    {
        $template = $this->template(false);
        $post     = ['name' => 'Urkunde', 'category' => 'Urkunde', 'content' => (string) json_encode($template->content)];

        $this->postWithToken('/settings/documents/editor-templates/' . $template->id, $post + ['print_verification' => '1']);
        $this->assertTrue($template->fresh()->print_verification);

        $this->postWithToken('/settings/documents/editor-templates/' . $template->id, $post + ['print_verification' => '0']);
        $this->assertFalse($template->fresh()->print_verification);
    }

    #[Test]
    public function verify_zeigt_eine_gueltige_urkunde(): void
    {
        $document = $this->issued();

        // Kleinschreibung und Leerzeichen statt Bindestrich fängt normalize() ab.
        $response = $this->verify(strtolower(str_replace('-', ' ', VerificationCode::format($document->verification_code))));

        $this->assertOk($response);
        $this->assertBodyContains('Zu diesem Prüfcode ist eine gültige Urkunde hinterlegt.', $response);
        $this->assertBodyContains('Erika Muster', $response);
        $this->assertBodyContains('unbefristet', $response);
        $this->assertBodyContains('Urkunde ansehen', $response);
        $this->assertSame('noindex', $response->headers['X-Robots-Tag'] ?? null);
    }

    #[Test]
    public function verify_ohne_anmeldung_erreichbar(): void
    {
        $code = $this->issued()->verification_code;
        unset($_SESSION['userid'], $_SESSION['permissions']);

        $this->assertBodyContains('Zu diesem Prüfcode ist eine gültige Urkunde hinterlegt.', $this->verify($code));
    }

    #[Test]
    public function verify_erkennt_tippfehler_und_unbekannte_codes(): void
    {
        $this->assertBodyContains('Zu diesem Prüfcode ist keine Urkunde hinterlegt.', $this->verify(VerificationCode::generate(999999, new DateTimeImmutable(), 'k')));

        // Ein Tippfehler bricht die Prüfsumme und zählt nicht gegen das Limit.
        $response = $this->verify('ABCD-EFGH-JK');
        $this->assertOk($response);
        $this->assertBodyContains('Prüfcode unbekannt.', $response);
    }

    #[Test]
    public function verify_zeigt_beim_abgelaufenen_das_enddatum(): void
    {
        $document                = $this->issued();
        $values                  = $document->frozen_values;
        $values['qualifikation.gueltig_bis'] = '01.01.2020';
        $document->frozen_values = $values;
        $document->save();

        $response = $this->verify($document->verification_code);

        $this->assertBodyContains('ihre Gültigkeit endete am 01.01.2020', $response);
        $this->assertBodyContains('Erika Muster', $response);
        $this->assertSame('application/pdf', $this->pdf($document->verification_code)->headers['Content-Type'] ?? null);
    }

    #[Test]
    public function verify_begrenzt_die_abfragen_je_adresse(): void
    {
        $code = VerificationCode::generate(424242, new DateTimeImmutable(), 'k');
        for ($i = 0; $i < VerifyController::LIMIT_PER_HOUR; $i++) {
            $this->assertOk($this->verify($code, '198.51.100.1'));
        }

        $this->assertStatus(429, $this->verify($code, '198.51.100.1'));
        $this->assertOk($this->verify($code, '198.51.100.2'));
    }

    #[Test]
    public function widerruf_braucht_das_recht(): void
    {
        $document = $this->issued();
        $user     = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['personnel.view'], 'cirs_username' => $user->username]);

        $response = $this->postWithToken('/documents/' . $document->id . '/revoke', ['reason' => 'Betrug']);

        $this->assertForbidden($response);
        $this->assertNull($document->fresh()->revoked_at);
    }

    #[Test]
    public function widerruf_braucht_eine_begruendung(): void
    {
        $document = $this->issued();

        $this->postWithToken('/documents/' . $document->id . '/revoke', ['reason' => '  ']);

        $this->assertNull($document->fresh()->revoked_at);
    }

    #[Test]
    public function widerrufen_zeigt_verify_das_datum_ohne_pdf(): void
    {
        $document = $this->issued();
        $user     = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['training.manage'], 'cirs_username' => $user->username]);

        $this->assertRedirect($this->postWithToken('/documents/' . $document->id . '/revoke', ['reason' => 'Prüfung wiederholt']), '/documents/' . $document->id);

        $fresh = $document->fresh();
        $this->assertSame('Prüfung wiederholt', $fresh->revoke_reason);
        $this->assertSame($user->id, $fresh->revoked_by);

        $response = $this->verify($document->verification_code);
        $this->assertBodyContains('Die Urkunde wurde am ' . date('d.m.Y') . ' widerrufen', $response);
        $this->assertStringNotContainsString('Prüfung wiederholt', $response->body);
        $this->assertStringNotContainsString('Urkunde ansehen', $response->body);
        $this->assertStatus(404, $this->pdf($document->verification_code));
    }
}
