<?php

declare(strict_types=1);

namespace Tests\Integration\KnowledgeBase;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\KnowledgeBase\Console\KbImagesCleanupCommand;
use Tests\IntegrationTestCase;

/**
 * kb:images:cleanup löscht nur alte Bilder ohne Verweis aus einem
 * Eintrag. Läuft gegen ein eigenes Temp-Verzeichnis statt storage/.
 */
final class KbImagesCleanupTest extends IntegrationTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/kb-images-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->dir) ?: [] as $name) {
            $path = $this->dir . '/' . $name;
            if ($name !== '.' && $name !== '..') {
                is_dir($path) ? rmdir($path) : unlink($path);
            }
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function image(string $name, int $mtime): string
    {
        $name .= '.png';
        touch($this->dir . '/' . $name, $mtime);
        return $name;
    }

    private function entry(string $column, string $file, bool $archived = false): void
    {
        Capsule::table('intra_kb_entries')->insert([
            'type'        => 'medication',
            'title'       => 'Bildtest ' . $file,
            $column       => '<p><img src="/irgendwo/storage/kb-images/' . $file . '" alt=""></p>',
            'is_archived' => $archived ? 1 : 0,
        ]);
    }

    #[Test]
    public function loescht_nur_alte_bilder_ohne_verweis(): void
    {
        $now = time();
        $alt = $now - 25 * 3600;

        $verwaist   = $this->image(str_repeat('a', 32), $alt);
        $frisch     = $this->image(str_repeat('b', 32), $now - 3600);
        $imInhalt   = $this->image(str_repeat('c', 32), $alt);
        $imFeld     = $this->image(str_repeat('d', 32), $alt);
        $imArchiv   = $this->image(str_repeat('e', 32), $alt);
        $fremd      = 'notizen.png';
        $versalien  = str_repeat('F', 32) . '.png';
        $ordner     = str_repeat('0', 32) . '.png';
        touch($this->dir . '/' . $fremd, $alt);
        touch($this->dir . '/' . $versalien, $alt);
        mkdir($this->dir . '/' . $ordner);
        touch($this->dir . '/' . $ordner, $alt);

        $this->entry('content', $imInhalt);
        $this->entry('med_dosierung', $imFeld);
        $this->entry('content', $imArchiv, archived: true);

        $removed = (new KbImagesCleanupCommand())->cleanup($this->dir, $now);

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($this->dir . '/' . $verwaist);
        foreach ([$frisch, $imInhalt, $imFeld, $imArchiv, $fremd, $versalien] as $bleibt) {
            $this->assertFileExists($this->dir . '/' . $bleibt);
        }
        $this->assertDirectoryExists($this->dir . '/' . $ordner);
    }

    #[Test]
    public function ohne_verzeichnis_passiert_nichts(): void
    {
        $this->assertSame(0, (new KbImagesCleanupCommand())->cleanup($this->dir . '/fehlt', time()));
    }
}
