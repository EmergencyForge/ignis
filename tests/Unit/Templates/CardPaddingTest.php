<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Seit ui 0.7.0 polstert sich nur .ignis-card selbst, ihr __header und
 * __body haben kein eigenes Polster mehr. In einer twplus-section-card oder
 * twplus-table-card ohne ignis-card kleben Titel und Felder am Rand.
 */
final class CardPaddingTest extends TestCase
{
    public function testIgnisCardPartsSitInAnIgnisCard(): void
    {
        $root = dirname(__DIR__, 3);
        $hits = [];
        foreach (['templates', 'plugins', 'assets/components'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $src = (string) file_get_contents($file->getPathname());
                if (preg_match('~class="(?![^"]*\bignis-card\b)[^"]*\btwplus-(?:section|table)-card\b[^"]*"[^>]*>\s*<div class="ignis-card__(?:header|body)\b~', $src) === 1) {
                    $hits[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        self::assertSame([], $hits, 'ignis-card__header/__body brauchen eine .ignis-card als Hülle');
    }
}
