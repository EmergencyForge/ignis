<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Plugins\VersionConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VersionConstraintTest extends TestCase
{
    #[Test]
    #[DataProvider('cases')]
    public function it_matches_versions_against_constraints(string $version, string $constraint, bool $expected): void
    {
        $this->assertSame(
            $expected,
            VersionConstraint::satisfies($version, $constraint),
            "'{$version}' satisfies '{$constraint}' should be " . ($expected ? 'true' : 'false')
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    public static function cases(): array
    {
        return [
            // wildcard / empty
            ['1.0.0', '*', true],
            ['1.0.0', '', true],

            // range (AND of two parts) — the manifest's typical form
            ['1.2.0', '>=1.2 <2.0', true],
            ['1.5.9', '>=1.2 <2.0', true],
            ['1.1.9', '>=1.2 <2.0', false],
            ['2.0.0', '>=1.2 <2.0', false],
            ['2.1.0', '>=1.2 <2.0', false],

            // single operators
            ['1.3.0', '>=1.3.0', true],
            ['1.2.9', '>=1.3.0', false],
            ['1.3.0', '>1.2.0', true],
            ['1.2.0', '>1.2.0', false],
            ['1.0.0', '<=1.0.0', true],
            ['1.0.1', '<=1.0.0', false],
            ['0.9.0', '<1.0.0', true],
            ['1.0.0', '<1.0.0', false],

            // exact
            ['1.0.0', '=1.0.0', true],
            ['1.0.0', '1.0.0', true],
            ['1.0.1', '1.0.0', false],
            ['1.0.0', '!=1.0.0', false],
            ['1.0.1', '!=1.0.0', true],

            // caret: ^1.2 → >=1.2 <2.0
            ['1.2.0', '^1.2', true],
            ['1.9.9', '^1.2', true],
            ['2.0.0', '^1.2', false],
            ['1.1.0', '^1.2', false],

            // tilde: ~1.2.3 → >=1.2.3 <1.3.0
            ['1.2.3', '~1.2.3', true],
            ['1.2.9', '~1.2.3', true],
            ['1.3.0', '~1.2.3', false],
            ['1.2.2', '~1.2.3', false],

            // leading v is tolerated on both sides
            ['v1.5.0', '>=v1.2', true],
        ];
    }
}
