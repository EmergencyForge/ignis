<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\FileResponse;
use App\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileResponseTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ignis-fileresponse-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/assets/sub', 0777, true);
        file_put_contents($this->dir . '/assets/plugin.css', 'body{}');
        file_put_contents($this->dir . '/assets/sub/icon.svg', '<svg/>');
        file_put_contents($this->dir . '/assets/secret.php', '<?php echo 1;');
        file_put_contents($this->dir . '/manifest.php', '<?php return [];');
    }

    protected function tearDown(): void
    {
        foreach (['assets/plugin.css', 'assets/sub/icon.svg', 'assets/secret.php', 'manifest.php'] as $file) {
            @unlink($this->dir . '/' . $file);
        }
        @rmdir($this->dir . '/assets/sub');
        @rmdir($this->dir . '/assets');
        @rmdir($this->dir);
    }

    /** @param array<string,string> $server */
    private function request(array $server = []): Request
    {
        return new Request(method: 'GET', path: '/', server: $server);
    }

    #[Test]
    public function liefert_erlaubte_datei_mit_typ_und_cache_headern(): void
    {
        $response = FileResponse::fromDirectory($this->request(), $this->dir . '/assets', 'plugin.css', ['css']);

        $this->assertNotNull($response);
        $this->assertSame(200, $response->status);
        $this->assertSame('body{}', $response->body);
        $this->assertSame('text/css; charset=utf-8', $response->headers['Content-Type']);
        $this->assertSame('6', $response->headers['Content-Length']);
        $this->assertStringStartsWith('public, max-age=', $response->headers['Cache-Control']);
        $this->assertArrayHasKey('Last-Modified', $response->headers);
    }

    #[Test]
    public function unterordner_sind_erlaubt(): void
    {
        $response = FileResponse::fromDirectory($this->request(), $this->dir . '/assets', 'sub/icon.svg', ['svg']);

        $this->assertNotNull($response);
        $this->assertSame('image/svg+xml', $response->headers['Content-Type']);
    }

    #[Test]
    public function php_dateien_werden_auch_dann_abgewiesen_wenn_sie_existieren(): void
    {
        $this->assertNull(FileResponse::fromDirectory($this->request(), $this->dir . '/assets', 'secret.php', ['css', 'js']));
    }

    #[Test]
    public function pfade_mit_punkt_punkt_oder_absolut_werden_abgewiesen(): void
    {
        $base = $this->dir . '/assets';
        $this->assertNull(FileResponse::fromDirectory($this->request(), $base, '../manifest.php', ['php']));
        $this->assertNull(FileResponse::fromDirectory($this->request(), $base, 'sub/../../manifest.php', ['php']));
        $this->assertNull(FileResponse::fromDirectory($this->request(), $base, '/etc/passwd', ['css']));
        $this->assertNull(FileResponse::fromDirectory($this->request(), $base, 'sub\\..\\plugin.css', ['css']));
        $this->assertNull(FileResponse::fromDirectory($this->request(), $base, "plugin.css\0.css", ['css']));
    }

    #[Test]
    public function fehlende_datei_ergibt_null(): void
    {
        $this->assertNull(FileResponse::fromDirectory($this->request(), $this->dir . '/assets', 'nope.css', ['css']));
    }

    #[Test]
    public function antwortet_304_wenn_der_browser_die_datei_schon_hat(): void
    {
        $mtime = (int) filemtime($this->dir . '/assets/plugin.css');
        $since = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        $response = FileResponse::fromDirectory(
            $this->request(['HTTP_IF_MODIFIED_SINCE' => $since]),
            $this->dir . '/assets',
            'plugin.css',
            ['css'],
        );

        $this->assertNotNull($response);
        $this->assertSame(304, $response->status);
        $this->assertSame('', $response->body);
    }
}
