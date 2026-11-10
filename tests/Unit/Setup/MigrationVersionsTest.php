<?php

declare(strict_types=1);

namespace Tests\Unit\Setup;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Zwei PRs mit derselben Migrationsnummer sind einzeln grün, nach dem Merge
 * bricht Phinx aber jede Migration ab. Plugins zählen mit, Phinx lädt alle
 * Pfade gemeinsam.
 */
final class MigrationVersionsTest extends TestCase
{
    #[Test]
    public function jede_migrationsnummer_gibt_es_nur_einmal(): void
    {
        $root  = dirname(__DIR__, 3);
        $files = array_merge(
            glob($root . '/database/migrations/*.php') ?: [],
            glob($root . '/plugins/*/migrations/*.php') ?: [],
        );
        $seen = [];
        foreach ($files as $file) {
            $seen[(string) strtok(basename($file), '_')][] = substr($file, strlen($root) + 1);
        }

        $duplicates = array_filter($seen, static fn (array $paths): bool => count($paths) > 1);

        $this->assertNotEmpty($files);
        $this->assertSame([], $duplicates);
    }
}
