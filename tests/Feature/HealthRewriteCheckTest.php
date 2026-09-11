<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * /api/health meldet, ob die Anfrage über public/index.php kam und ob
 * das Docroot auf public/ zeigt. Das Dashboard liest daraus seinen
 * Hosting-Hinweis.
 */
final class HealthRewriteCheckTest extends FeatureTestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Test]
    public function meldet_docroot_auf_public(): void
    {
        $response = $this->get('/api/health', ['server' => [
            'SCRIPT_FILENAME' => $this->root() . '/public/index.php',
            'DOCUMENT_ROOT'   => $this->root() . '/public',
        ]]);

        $body = $this->assertJsonResponse($response);
        $this->assertSame('ok',     $body['checks']['rewrite']['status'] ?? null);
        $this->assertTrue($body['checks']['rewrite']['front_controller'] ?? null);
        $this->assertSame('public', $body['checks']['rewrite']['document_root'] ?? null);
    }

    #[Test]
    public function erkennt_die_durchreichung_ueber_die_root_htaccess(): void
    {
        $response = $this->get('/api/health', ['server' => [
            'SCRIPT_FILENAME' => $this->root() . '/public/index.php',
            'DOCUMENT_ROOT'   => $this->root(),
        ]]);

        $body = $this->assertJsonResponse($response);
        $this->assertSame('ok',       $body['checks']['rewrite']['status'] ?? null);
        $this->assertSame('fallback', $body['checks']['rewrite']['document_root'] ?? null);
    }

    #[Test]
    public function ohne_front_controller_ist_der_check_degraded(): void
    {
        $body = $this->assertJsonResponse($this->get('/api/health'));

        $this->assertSame('degraded', $body['checks']['rewrite']['status'] ?? null);
        $this->assertSame('unknown',  $body['checks']['rewrite']['document_root'] ?? null);
    }
}
