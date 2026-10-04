<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\KnowledgeBase\Console\KbImagesCleanupCommand;
use Tests\TestCase;

/**
 * Welche Bilder ein Eintrag verwendet, steht als Dateiname hinter
 * `kb-images/` im gespeicherten HTML. Der Pfad davor spielt keine Rolle.
 */
class KbImagesReferenceTest extends TestCase
{
    private const A = '0123456789abcdef0123456789abcdef.png';
    private const B = 'fedcba9876543210fedcba9876543210.webp';

    #[Test]
    public function findet_den_dateinamen_unabhaengig_vom_base_path(): void
    {
        $values = [
            'content'       => '<p><img src="/storage/kb-images/' . self::A . '" alt=""></p>',
            'med_dosierung' => '<img src="/ignis/storage/kb-images/' . self::B . '"><img src="https://x.example/storage/kb-images/' . self::A . '">',
        ];

        $this->assertSame([self::A => true, self::B => true], KbImagesCleanupCommand::referencedFiles($values));
    }

    #[Test]
    public function fremde_namen_und_andere_werte_zaehlen_nicht(): void
    {
        $values = [
            'id'      => 7,
            'leer'    => null,
            'titel'   => 'storage/kb-images ohne Datei',
            'kurz'    => '<img src="/storage/kb-images/0123abcd.png">',
            'gross'   => '<img src="/storage/kb-images/0123456789ABCDEF0123456789ABCDEF.png">',
            'endung'  => '<img src="/storage/kb-images/0123456789abcdef0123456789abcdef.svg">',
            'ordner'  => '<img src="/storage/uploads/' . self::A . '">',
            'zurueck' => '<img src="/storage/kb-images/../' . self::A . '">',
        ];

        $this->assertSame([], KbImagesCleanupCommand::referencedFiles($values));
    }
}
