<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * Eine Klasse im Markup, die im Stylesheet nicht existiert, sieht nach
 * Absicht aus und tut nichts. Der Test prüft die Modifier der
 * Komponentenfamilien (ignis-btn--*, ignis-chip--*, ignis-alert--*) und ein
 * paar Bausteine ohne Präfix-Systematik gegen das GEBAUTE CSS unter
 * public/assets/dist — nicht gegen die SCSS-Quellen, weil dort Modifier als
 * `&--icon` verschachtelt stehen und eine Textsuche sie nicht findet. Nur
 * das Kompilat sagt, was im Browser ankommt.
 *
 * eNOTF (plugins/enotf*, assets/components/enotf) ist nicht Teil des
 * Redesigns und wird nicht durchsucht.
 */
final class ComponentClassTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private const BUNDLES = [
        '/public/assets/dist/ui.css',
        '/public/assets/dist/admin.css',
        '/public/assets/dist/style.css',
        '/public/assets/dist/tailwind.css',
        '/public/assets/dist/legacy-utilities.css',
        '/public/assets/dist/personal.css',
    ];

    private const MODIFIER_PREFIXES = [
        'ignis-btn--',
        'ignis-chip--',
        'ignis-alert--',
    ];

    /** Bausteine des Redesigns, die die Listen und die Hülle voraussetzen. */
    private const STANDALONE_CLASSES = [
        'ignis-btn--primary',
        'ignis-btn--secondary',
        'ignis-btn--ghost',
        'ignis-btn--danger',
        'ignis-btn--sm',
        'ignis-btn--icon',
        'ignis-chip--ok',
        'ignis-chip--warn',
        'ignis-chip--danger',
        'ignis-chip--info',
        'ignis-chip--dot',
        'ignis-alert--ok',
        'ignis-alert--warn',
        'ignis-alert--error',
        'ignis-alert--info',
        'ignis-table',
        'ignis-table__sort',
        'ignis-table__num',
        'ignis-row-actions',
        'ignis-mono',
        'ignis-pagination',
        'ignis-list-toolbar',
        'ignis-list-toolbar__field',
        'ignis-list-footer',
        'ignis-snack',
    ];

    private function cssDefines(string $css, string $class): bool
    {
        return preg_match('~\.' . preg_quote($class, '~') . '(?![a-zA-Z0-9_-])~', $css) === 1;
    }

    private function compiledCss(): string
    {
        $css = '';
        foreach (self::BUNDLES as $bundle) {
            $path = self::ROOT . $bundle;
            $this->assertFileExists($path, 'Gebautes CSS fehlt: npm run build ausführen.');
            $css .= (string) file_get_contents($path);
        }

        return $css;
    }

    /**
     * Alle PHP- und JS-Dateien außerhalb von eNOTF, die Markup an den
     * Browser liefern. `src` ist dabei, weil Flash::render() und
     * ListQuery::th() ihr Markup aus PHP ausgeben.
     *
     * @return list<string>
     */
    private function markupFiles(): array
    {
        // Die UI-Module kommen gebaut aus dem Paket (public/assets/js/ui,
        // tests/Unit/Assets/UiPackageTest.php), darum das Kompilat statt der Quelle.
        $roots = ['/templates', '/assets/components', '/assets/js/ui', '/public/assets/js/ui', '/assets/js/modules', '/src', '/index.php', '/dashboard.php'];
        foreach (glob(self::ROOT . '/plugins/*', GLOB_ONLYDIR) ?: [] as $plugin) {
            if (str_starts_with(basename($plugin), 'enotf')) {
                continue;
            }
            $roots[] = substr($plugin, strlen(self::ROOT));
        }

        $files = [];
        foreach ($roots as $root) {
            $path = self::ROOT . $root;
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file instanceof \SplFileInfo || !in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }
                $normalized = str_replace('\\', '/', $file->getPathname());
                if (str_contains($normalized, '/enotf') || str_contains($normalized, '/vendor/') || str_contains($normalized, '/node_modules/')) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    public function testEveryUsedComponentModifierExistsInTheBuiltCss(): void
    {
        $css     = $this->compiledCss();
        $unknown = [];

        foreach ($this->markupFiles() as $file) {
            $markup = (string) file_get_contents($file);
            foreach (self::MODIFIER_PREFIXES as $prefix) {
                preg_match_all('~' . preg_quote($prefix, '~') . '[a-z0-9-]+~', $markup, $matches);
                foreach ($matches[0] as $modifier) {
                    if (!$this->cssDefines($css, $modifier)) {
                        $unknown[] = basename($file) . ': ' . $modifier;
                    }
                }
            }
        }

        $unknown = array_values(array_unique($unknown));
        sort($unknown);

        $this->assertSame(
            [],
            $unknown,
            "Diese Modifier stehen im Markup, aber in keinem gebauten Stylesheet:\n  " . implode("\n  ", $unknown),
        );
    }

    public function testRedesignBuildingBlocksAreDefined(): void
    {
        $css     = $this->compiledCss();
        $missing = [];

        foreach (self::STANDALONE_CLASSES as $class) {
            if (!$this->cssDefines($css, $class)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, "Diese Bausteine fehlen im gebauten CSS:\n  " . implode("\n  ", $missing));
    }
}
