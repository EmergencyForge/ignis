<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\VersionComparator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VersionComparatorTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function formats(): array
    {
        return [
            'Jahresschema' => ['v2026.0.8', true],
            'ohne v' => ['2026.0.8', true],
            'fünf Stellen' => ['v1.2.3.4.5', true],
            'Vorabversion' => ['v2026.1.0-beta.1', true],
            'Branch-Build' => ['dev-main-abc12345', true],
            'Branch mit Schrägstrich' => ['dev-feature/x-abcdef1', true],
            'sechs Stellen' => ['v1.2.3.4.5.6', false],
            'Wort' => ['latest', false],
            'Leerzeichen am Ende' => ['v2026.0.8 ', false],
            'Branch-Build ohne Hash' => ['dev-main-xyz', false],
            'Pfad' => ['../v1', false],
        ];
    }

    #[Test]
    #[DataProvider('formats')]
    public function validates_the_format(string $version, bool $valid): void
    {
        self::assertSame($valid, VersionComparator::isValidFormat($version));
    }

    /** @return array<string, array{string, string, bool}> */
    public static function comparisons(): array
    {
        return [
            'Jahresschema schlägt 1.x' => ['v2026.0.8', 'v1.2.0', true],
            '1.x nicht neuer als Jahresschema' => ['v1.2.0', 'v2026.0.8', false],
            'zweistellige Patch-Nummer' => ['v2026.0.11', 'v2026.0.8', true],
            'gleich' => ['v2026.0.8', 'v2026.0.8', false],
            'ohne v gegen mit v' => ['2026.0.8', 'v2026.0.8', false],
            'final schlägt beta' => ['v2026.1.0', 'v2026.1.0-beta.1', true],
            'beta der nächsten Minor' => ['v2026.1.0-beta.1', 'v2026.0.11', true],
            'beta.2 schlägt beta.1' => ['v2026.1.0-beta.2', 'v2026.1.0-beta.1', true],
            'rc schlägt beta' => ['v2026.1.0-rc.1', 'v2026.1.0-beta.3', true],
            'Release schlägt Branch-Build' => ['v2026.0.8', 'dev-main-abc12345', true],
            'Branch-Build nie neuer' => ['dev-main-abc12345', 'v2026.0.8', false],
            'Standard ohne version.json' => ['v2026.0.8', 'v0.5.0', true],
            'numerisch statt alphabetisch' => ['v1.10.0', 'v1.9.9', true],
        ];
    }

    #[Test]
    #[DataProvider('comparisons')]
    public function compares_versions(string $candidate, string $installed, bool $newer): void
    {
        self::assertSame($newer, VersionComparator::isNewer($candidate, $installed));
    }

    #[Test]
    public function recognises_prerelease_versions_by_name(): void
    {
        foreach (['v2026.1.0-beta.1', 'v2026.1.0-rc.1', 'v1.0.0-ALPHA', 'dev-main-abc12345'] as $version) {
            self::assertTrue(VersionComparator::isPreRelease($version), $version);
        }
        self::assertFalse(VersionComparator::isPreRelease('v2026.0.8'));
    }

    /** @return array<string, array{string, string, int, string}> */
    public static function urgencies(): array
    {
        return [
            'Wechsel von 1.x ins Jahresschema' => ['v1.2.0', 'v2026.0.8', 0, 'high'],
            'Minor, junge Version' => ['v2026.0.8', 'v2026.1.0', 60, 'low'],
            'Minor, älter als 60 Tage' => ['v2026.0.8', 'v2026.1.0', 61, 'medium'],
            'Patch, junge Version' => ['v2026.0.8', 'v2026.0.11', 30, 'low'],
            'Patch, älter als 30 Tage' => ['v2026.0.8', 'v2026.0.11', 31, 'medium'],
            'Beta der nächsten Minor' => ['v2026.0.11', 'v2026.1.0-beta.1', 0, 'low'],
            'Final nach eigener Beta' => ['v2026.1.0-beta.1', 'v2026.1.0', 400, 'low'],
            'ohne Minor-Stelle' => ['v1.2', 'v2', 400, 'high'],
        ];
    }

    #[Test]
    #[DataProvider('urgencies')]
    public function rates_update_urgency(string $installed, string $latest, int $ageDays, string $urgency): void
    {
        self::assertSame($urgency, VersionComparator::urgency($installed, $latest, $ageDays));
    }
}
