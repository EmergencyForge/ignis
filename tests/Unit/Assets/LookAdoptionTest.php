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
        self::assertSame('10px', $skin['--radius-2'] ?? null);
        self::assertSame('16px', $skin['--radius-3'] ?? null);
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

    /**
     * Die alte Hülle (navbar.php) läuft auf eNOTF-Adminseiten ohne Skin. Dort
     * brauchen Wortmarke und Toast die Rollen des Looks als Standard auf :root.
     */
    public function testRolesUsedOutsideTheSkinHaveRootDefaults(): void
    {
        $root = $this->skinBlock(':root');
        $expected = [
            '--content-text' => 'var(--text)',
            '--accent-fill' => 'var(--accent)',
            '--motion-ease' => 'var(--ease)',
            '--motion-spring' => 'var(--spring)',
            '--sidebar-inset' => '0px',
        ];
        foreach ($expected as $name => $value) {
            self::assertSame($value, $root[$name] ?? null, $name);
        }
    }

    /**
     * Was auf :root aus --text, --fill-3 oder --ok abgeleitet ist, steht dort
     * mit den alten Werten fest. Im Skin muss es neu gerechnet werden.
     */
    public function testDerivedTokensFollowTheNewPalette(): void
    {
        $skin = $this->skinBlock('body[data-ui-skin=core]') + $this->skinBlock('body[data-ui-skin="core"]');
        $expected = [
            '--input-text' => 'var(--text)',
            '--input-placeholder' => 'var(--text-3)',
            '--btn-secondary-bg' => 'var(--fill-3)',
            '--btn-success-bg' => 'var(--ok)',
            '--btn-danger-bg' => 'var(--danger)',
            '--btn-warning-bg' => 'var(--warn)',
            '--comment-positive-stripe' => 'var(--ok)',
        ];
        foreach ($expected as $name => $value) {
            self::assertSame($value, $skin[$name] ?? null, $name);
        }
        self::assertStringContainsString('var(--ok)', $skin['--btn-success-hover'] ?? '');
        self::assertStringContainsString('var(--ok)', $skin['--comment-positive-bg'] ?? '');
    }

    public function testLightSkinKeepsTheDarkAccentText(): void
    {
        $light = $this->skinBlock('[data-theme=light] body[data-ui-skin=core]') + $this->skinBlock('[data-theme="light"] body[data-ui-skin="core"]');
        self::assertMatchesRegularExpression('/^color-mix\(in srgb,\s*var\(--accent-base\) 75%,\s*(black|#000)\)$/', $light['--accent-text'] ?? '');
        self::assertMatchesRegularExpression('/^color-mix\(in srgb,\s*var\(--accent-base\) 75%,\s*(black|#000)\)$/', $light['--accent-focus'] ?? '');
    }

    public function testTailwindRadiiAndShadowsComeFromTheLook(): void
    {
        $css = (string) file_get_contents(dirname(self::CSS) . '/tailwind.css');
        self::assertMatchesRegularExpression('/\.rounded-md\{border-radius:var\(--radius-2\)\}/', $css);
        self::assertMatchesRegularExpression('/\.rounded-xl\{border-radius:var\(--radius-3\)\}/', $css);
        self::assertMatchesRegularExpression('/\.shadow-strong\{[^}]*var\(--shadow-pop\)/', $css);
        self::assertMatchesRegularExpression('/\.shadow-soft\{[^}]*var\(--shadow-card\)/', $css);
    }

    /**
     * Der neutrale Chip des Skins hat (0,2,1). Eine Sichtungskategorie mit
     * nur einer Klasse verlöre dagegen und wäre grau.
     */
    public function testTriageChipsKeepTheirToneInTheSkin(): void
    {
        $css = (string) file_get_contents(self::CSS);
        self::assertMatchesRegularExpression(
            '/body\[data-ui-skin="?core"?\] \.ignis-chip:is\(\.ignis-chip--sk1,[^)]*\.ignis-chip--tot\)\{background:var\(--tone-soft\);border-color:var\(--tone-line\);color:var\(--tone-text\)\}/',
            $css,
        );
        self::assertStringContainsString('.ignis-chip--sk1{--tone-soft:var(--danger-soft);--tone-line:var(--danger-line);--tone-text:var(--danger-text)}', $css);
        self::assertStringContainsString('.ignis-chip--sk4{--tone-soft:var(--info-soft);', $css);
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

    public function testTheWordmarkIsMaskedInTheAccentGradient(): void
    {
        $css = (string) file_get_contents(self::CSS);
        self::assertMatchesRegularExpression(
            '/\.ignis-wordmark\s*\{[^}]*background-image:\s*linear-gradient\([^}]*var\(--accent-fill\)[^}]*mask:[^}]*\}/',
            $css,
        );
    }

    /**
     * .ignis-main ist ein Block, sonst wirkt margin-top:auto am Footer
     * nicht. Der Footer bleibt außerdem in der Inhaltsspalte von
     * .twplus-page statt über die volle Breite zu laufen.
     */
    public function testTheFooterSitsAtTheBottomOfTheContentColumn(): void
    {
        $css = (string) file_get_contents(self::CSS);

        preg_match('/\.ignis-main\{([^}]*)\}/', $css, $main);
        self::assertNotEmpty($main, '.ignis-main fehlt im gebauten CSS.');
        self::assertStringContainsString('display:flex', $main[1]);
        self::assertStringContainsString('flex-direction:column', $main[1]);

        preg_match('/\.ignis-main>\.footer\{([^}]*)\}/', $css, $footer);
        self::assertNotEmpty($footer, '.ignis-main>.footer fehlt im gebauten CSS.');
        self::assertStringContainsString('max-width:88rem', $footer[1]);
        // lightningcss schreibt "transparent" als "#0000" um.
        self::assertMatchesRegularExpression('/background-color:\s*(transparent|#0000)\s*!important/', $footer[1]);
    }

    /**
     * .ignis-card (Paket) setzt overflow: hidden; im Formular-Drawer
     * schneidet das sonst den Fokusring der Felder ab, weil
     * .ignis-card__body dort padding: 0 hat.
     */
    public function testDrawerFormCardStaysVisibleForTheFocusRing(): void
    {
        $css = (string) file_get_contents(self::CSS);
        self::assertMatchesRegularExpression(
            '/\.ignis-drawer--form\s+\.ignis-fragment\s+\.ignis-form-card\s*\{[^}]*overflow:\s*visible[^}]*\}/',
            $css,
        );
    }
}
