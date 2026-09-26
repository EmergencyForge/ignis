<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * Prüft am gebauten ui.css, dass der gemeinsame Look im Skin-Block ankommt
 * und die eigenen Namen von ignis dort auf seine Rollen zeigen. Der Minifier
 * darf doppelte Deklarationen zusammenlegen, deshalb zählt je Eigenschaft
 * der letzte Wert im Block.
 */
final class LookAdoptionTest extends TestCase
{
    private const CSS = __DIR__ . '/../../../public/assets/dist/ui.css';

    /** @return array<string, string> */
    private function skinBlock(string $selector): array
    {
        $css = (string) file_get_contents(self::CSS);
        $values = [];
        $pattern = '/(?:^|})\s*' . preg_quote($selector, '/') . '\s*\{([^{}]*)\}/';
        preg_match_all($pattern, $css, $blocks);
        foreach ($blocks[1] as $block) {
            preg_match_all('/(--[\w-]+)\s*:\s*([^;]+)/', $block, $pairs, PREG_SET_ORDER);
            foreach ($pairs as [, $name, $value]) {
                $values[$name] = trim($value);
            }
        }
        return $values;
    }

    public function testTheSkinUsesTheSharedLook(): void
    {
        $skin = $this->skinBlock('body[data-ui-skin=core]') + $this->skinBlock('body[data-ui-skin="core"]');
        self::assertSame('2px', $skin['--radius-2'] ?? null);
        self::assertSame('3px', $skin['--radius-3'] ?? null);
        self::assertStringStartsWith('oklch(', $skin['--canvas'] ?? '');
    }

    public function testIgnisNamesPointAtTheRoles(): void
    {
        $skin = $this->skinBlock('body[data-ui-skin=core]') + $this->skinBlock('body[data-ui-skin="core"]');
        $expected = [
            '--accent-hue' => '40',
            '--accent-shift' => '20deg',
            '--bg' => 'var(--canvas)',
            '--text' => 'var(--content-text)',
            '--text-3' => 'var(--tertiary-text)',
            '--border' => 'var(--border-subtle)',
            '--radius-md' => 'var(--radius-2)',
            '--shadow' => 'var(--shadow-card)',
            '--spring' => 'var(--motion-spring)',
        ];
        foreach ($expected as $name => $value) {
            self::assertSame($value, $skin[$name] ?? null, $name);
        }
    }

    public function testEmptyStatesAreBuilt(): void
    {
        self::assertStringContainsString('.ignis-empty', (string) file_get_contents(self::CSS));
    }

    public function testTheSidebarFloatsOnDesktop(): void
    {
        $css = (string) file_get_contents(self::CSS);
        // Der Minifier (lightningcss) schreibt "min-width: 901px" als
        // "width>=901px" um; das Muster lässt beide Schreibweisen zu.
        self::assertMatchesRegularExpression('/@media\s*\([^)]*901px[^)]*\)\s*\{[^@]*\.ignis-sidebar\s*\{[^}]*margin:\s*0 0 var\(--sidebar-inset\) var\(--sidebar-inset\)/', $css);
        self::assertMatchesRegularExpression('/body\.ignis-app\s*\{[^}]*grid-template-columns:\s*calc\(var\(--sidebar-w\) \+ var\(--sidebar-inset, ?0px\)\)/', $css);
    }
}
