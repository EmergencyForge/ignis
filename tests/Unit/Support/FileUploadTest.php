<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Exceptions\UploadException;
use App\Support\FileUpload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileUploadTest extends TestCase
{
    private const ERLAUBT = ['image/png' => 'png', 'image/jpeg' => 'jpg'];

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/ignis_upload_' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach ((glob($this->dir . '/*') ?: []) as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function legt_eine_erlaubte_datei_ab(): void
    {
        $r = FileUpload::store($this->png(), $this->dir, 1024 * 1024, self::ERLAUBT);

        $this->assertSame('image/png', $r['mime']);
        $this->assertFileExists($r['pfad']);
        $this->assertStringEndsWith('.png', $r['name']);
    }

    #[Test]
    public function legt_das_verzeichnis_an_wenn_es_fehlt(): void
    {
        $this->assertDirectoryDoesNotExist($this->dir);

        FileUpload::store($this->png(), $this->dir, 1024 * 1024, self::ERLAUBT);

        $this->assertDirectoryExists($this->dir);
    }

    /**
     * Der Dateiname darf nicht vom Client kommen. Sonst bestimmt der
     * Hochladende die Endung und damit, wie der Webserver die Datei ausliefert.
     */
    #[Test]
    public function der_name_vom_client_bestimmt_die_endung_nicht(): void
    {
        $datei         = $this->png();
        $datei['name'] = 'harmlos.php';

        $r = FileUpload::store($datei, $this->dir, 1024 * 1024, self::ERLAUBT);

        $this->assertStringEndsWith('.png', $r['name']);
        $this->assertStringNotContainsString('harmlos', $r['name']);
        $this->assertStringNotContainsString('.php', $r['name']);
    }

    /**
     * Der Typ wird am Inhalt bestimmt, nicht an der Endung — eine als .png
     * benannte PHP-Datei muss durchfallen.
     */
    #[Test]
    public function erkennt_den_typ_am_inhalt_nicht_am_namen(): void
    {
        $this->expectException(UploadException::class);

        FileUpload::store($this->datei('<?php echo 1;', 'bild.png'), $this->dir, 1024 * 1024, self::ERLAUBT);
    }

    #[Test]
    public function lehnt_zu_grosse_dateien_ab(): void
    {
        $this->expectExceptionMessageMatches('~zu groß~');

        FileUpload::store($this->png(), $this->dir, 10, self::ERLAUBT);
    }

    #[Test]
    public function lehnt_nicht_erlaubte_typen_ab(): void
    {
        $this->expectExceptionMessageMatches('~Dateityp~');

        FileUpload::store($this->png(), $this->dir, 1024 * 1024, ['image/gif' => 'gif']);
    }

    #[Test]
    public function meldet_einen_fehlenden_upload(): void
    {
        $this->expectExceptionMessageMatches('~Keine Datei~');

        FileUpload::store(['error' => UPLOAD_ERR_NO_FILE], $this->dir, 1024 * 1024, self::ERLAUBT);
    }

    #[Test]
    public function ein_serverfehler_traegt_status_500(): void
    {
        try {
            // Datei statt Verzeichnis als Ziel: mkdir kann dort nichts anlegen.
            $blockade = $this->dir;
            mkdir(dirname($blockade), 0777, true);
            file_put_contents($blockade, 'keine Ablage');

            FileUpload::store($this->png(), $blockade . '/unten', 1024 * 1024, self::ERLAUBT);
            $this->fail('Es haette eine UploadException kommen muessen');
        } catch (UploadException $e) {
            $this->assertSame(500, $e->status);
        } finally {
            @unlink($this->dir);
        }
    }

    /** @return array<string,mixed> */
    private function png(): array
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        return $this->datei($png, 'bild.png');
    }

    /** @return array<string,mixed> */
    private function datei(string $inhalt, string $name): array
    {
        $tmp = sys_get_temp_dir() . '/ignis_upload_src_' . bin2hex(random_bytes(6));
        file_put_contents($tmp, $inhalt);

        return [
            'name'     => $name,
            'tmp_name' => $tmp,
            'size'     => strlen($inhalt),
            'error'    => UPLOAD_ERR_OK,
        ];
    }
}
