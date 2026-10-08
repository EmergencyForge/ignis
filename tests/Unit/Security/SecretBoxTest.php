<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\SecretBox;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Geheimnisse in der Datenbank: verschlüsselt mit einem Schlüssel aus
 * APP_KEY oder einer selbst angelegten Datei, Klartext von früher kommt
 * unverändert zurück, ein fremder Schlüssel oder ein verfälschter Wert
 * liefert null statt Unsinn.
 */
final class SecretBoxTest extends TestCase
{
    private string $dir;

    /** @var string|false */
    private string|false $envBefore;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/secretbox-' . bin2hex(random_bytes(4));
        $this->envBefore = getenv('APP_KEY');
        putenv('APP_KEY');
        unset($_ENV['APP_KEY']);
        SecretBox::$keyFile = $this->dir . '/private/secret.key';
        SecretBox::forget();
    }

    protected function tearDown(): void
    {
        SecretBox::$keyFile = null;
        SecretBox::forget();
        putenv($this->envBefore !== false ? 'APP_KEY=' . $this->envBefore : 'APP_KEY');
        @unlink($this->dir . '/private/secret.key');
        @rmdir($this->dir . '/private');
        @rmdir($this->dir);
    }

    #[Test]
    public function verschluesselt_und_entschluesselt(): void
    {
        $stored = SecretBox::encrypt('MTIz.geheim.token');

        $this->assertTrue(SecretBox::isEncrypted($stored));
        $this->assertStringNotContainsString('geheim', $stored);
        $this->assertSame('MTIz.geheim.token', SecretBox::decrypt($stored));
        // Jedes Mal ein neuer IV: gleicher Klartext, anderer Wert.
        $this->assertNotSame($stored, SecretBox::encrypt('MTIz.geheim.token'));
    }

    #[Test]
    public function legt_die_schluesseldatei_beim_ersten_mal_an(): void
    {
        $this->assertFileDoesNotExist(SecretBox::$keyFile);

        $stored = SecretBox::encrypt('wert');

        $this->assertFileExists(SecretBox::$keyFile);
        $this->assertSame(32, strlen((string) base64_decode(trim((string) file_get_contents(SecretBox::$keyFile)), true)));
        // Ein neuer Prozess liest denselben Schlüssel aus der Datei.
        SecretBox::forget();
        $this->assertSame('wert', SecretBox::decrypt($stored));
    }

    #[Test]
    public function app_key_geht_vor_der_datei(): void
    {
        putenv('APP_KEY=base64:' . base64_encode(str_repeat('k', 32)));
        $stored = SecretBox::encrypt('wert');

        $this->assertFileDoesNotExist(SecretBox::$keyFile);
        $this->assertSame('wert', SecretBox::decrypt($stored));

        // Mit einem anderen Schlüssel lässt sich der Wert nicht öffnen.
        putenv('APP_KEY=' . base64_encode(str_repeat('x', 32)));
        SecretBox::forget();
        $this->assertNull(SecretBox::decrypt($stored));
    }

    #[Test]
    public function ein_ungueltiger_app_key_wird_gemeldet(): void
    {
        putenv('APP_KEY=zu-kurz');

        $this->expectException(\RuntimeException::class);
        SecretBox::encrypt('wert');
    }

    #[Test]
    public function klartext_von_frueher_und_leere_werte_bleiben(): void
    {
        $this->assertSame('alter-klartext', SecretBox::decrypt('alter-klartext'));
        $this->assertSame('', SecretBox::decrypt(''));
        $this->assertSame('', SecretBox::encrypt(''));
    }

    #[Test]
    public function ein_verfaelschter_wert_liefert_null(): void
    {
        $stored = SecretBox::encrypt('wert');
        $raw = (string) base64_decode(substr($stored, 7), true);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);

        $this->assertNull(SecretBox::decrypt('enc:v1:' . base64_encode($raw)));
        $this->assertNull(SecretBox::decrypt('enc:v1:kein-base64!'));
    }
}
