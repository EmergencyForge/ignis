<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\PersonnelController;
use EmergencyForge\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regressionstest fuer den FileUpload-Refactor: der DB-Fehler-Zweig in
 * uploadPfp() griff auf die nicht mehr existierende Variable $targetPath
 * zu (PHP-Warning "Undefined variable"). Ohne erreichbare Test-DB scheitert
 * der Personnel::update()-Aufruf hier zuverlaessig, was genau diesen Zweig
 * ausloest.
 */
class PersonnelControllerUploadPfpTest extends TestCase
{
    #[Test]
    public function db_failure_path_does_not_warn_about_an_undefined_variable(): void
    {
        $controller = $this->resolve(PersonnelController::class);

        $tmp = tempnam(sys_get_temp_dir(), 'pfp');
        // 1x1 PNG, damit finfo den Typ als image/png erkennt.
        file_put_contents($tmp, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        $request = new Request(
            method: 'POST',
            path: '/api/personnel/upload-pfp',
            post: ['id' => '999999'],
            files: ['pfp' => [
                'name'     => 'foto.png',
                'type'     => 'image/png',
                'tmp_name' => $tmp,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmp),
            ]],
        );

        $response = $controller->uploadPfp($request);

        // Ohne erreichbare DB landet die Anfrage im catch-Zweig; der Test
        // will nur sicherstellen, dass der Zweig selbst sauber durchlaeuft
        // (kein Fatal/Warning durch eine undefinierte Variable).
        $this->assertContains($response->status, [200, 500]);
        $body = json_decode($response->body, true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('success', $body);
    }
}
