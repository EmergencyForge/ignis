<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Die früheren Redirect-Skripte unter assets/functions/ sind gelöscht;
 * ihre URLs beantwortet jetzt der Router mit demselben 308, damit alte
 * JS-Aufrufe und Links weiter ankommen.
 */
final class LegacyRedirectsTest extends FeatureTestCase
{
    #[Test]
    public function post_auf_eine_alte_api_bleibt_ein_308_mit_methode(): void
    {
        // 308 statt 302, weil ein 302 die Methode auf GET kippen darf
        // und der Aufrufer dann mit leerem Rumpf ankaeme.
        $response = $this->post('/assets/functions/save_fields', ['x' => '1']);

        $this->assertStatus(308, $response);
        $this->assertStringEndsWith('/api/enotf/save-fields', $response->headers['Location'] ?? '');
    }

    #[Test]
    public function query_string_wird_mitgenommen(): void
    {
        $response = $this->get('/assets/functions/system/theme-api', [
            'server' => ['QUERY_STRING' => 'theme=dark'],
        ]);

        $this->assertStatus(308, $response);
        $this->assertStringEndsWith('/api/system/theme?theme=dark', $response->headers['Location'] ?? '');
    }

    #[Test]
    public function settings_altpfade_ohne_php_suffix_leiten_um(): void
    {
        $response = $this->get('/settings/vehicles/defects/handler');

        $this->assertStatus(308, $response);
        $this->assertStringEndsWith('/api/vehicles/defects-handler', $response->headers['Location'] ?? '');
    }

    #[Test]
    public function docredir_zeigt_auf_den_dokument_viewer(): void
    {
        $response = $this->get('/assets/functions/docredir', ['query' => ['docid' => 'abc-7']]);

        $this->assertStatus(308, $response);
        $this->assertStringEndsWith('personnel/document-view?docid=abc-7', $response->headers['Location'] ?? '');
    }
}
