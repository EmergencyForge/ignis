<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * footer.php lief mit einem harten "/assets/..."-Pfad zum Logo; unter einem
 * BASE_PATH-Unterpfad (z. B. /intra/) zeigte das ins Leere. Der Pfad muss
 * aus BASE_PATH gebildet werden.
 */
final class FooterLogoPathTest extends TestCase
{
    public function testFooterBuildsTheLogoPathFromBasePath(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../assets/components/footer.php');

        $this->assertStringNotContainsString('src="/assets', $source);
        $this->assertStringContainsString('BASE_PATH', $source);
    }
}
