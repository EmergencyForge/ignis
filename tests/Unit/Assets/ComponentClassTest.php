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
        'ignis-alert--danger',
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
        'ignis-segmented',
    ];

    /**
     * Vokabular vor dem Redesign (docs/specs/2026-09-28-gemeinsames-css.md).
     * Das Stylesheet kennt diese Klassen nicht mehr, im Quellcode haben sie
     * nichts zu suchen.
     */
    private const OLD_VOCABULARY = [
        'ignis-btn--accent',
        'ignis-btn--success',
        'ignis-btn--info',
        'ignis-btn--warning',
        'ignis-btn--soft-primary',
        'ignis-btn--soft-danger',
        'ignis-btn--soft-warning',
        'ignis-btn--soft-success',
        'ignis-btn--outline-primary',
        'ignis-btn--outline-danger',
        'ignis-btn--outline-warning',
        'ignis-btn--outline-success',
        'ignis-btn--outline-secondary',
        'ignis-btn--outline-info',
        'ignis-alert--success',
        'ignis-alert--warning',
        'ignis-alert--error',
        'ignis-chip--success',
        'ignis-chip--warning',
        'ignis-chip--error',
        'ignis-chip--note',
        'ignis-snack--success',
        'ignis-snack--warning',
        'ignis-snack--error',
        'ignis-filter-links',
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

        // Paket-Befund: WebPackages ui/datetimepicker.js setzt noch das alte
        // soft-primary. Behebt das Paket es (secondary), fliegt der Eintrag raus.
        $unknown = array_values(array_diff(array_unique($unknown), ['datetimepicker.js: ignis-btn--soft-primary']));
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

    /**
     * Die Migration auf das neue Vokabular (docs/specs/2026-09-28-gemeinsames-css.md)
     * ist abgeschlossen, die Aliase aus ef.base-legacy-aliases() sind raus.
     * Quellcode, der die alten Namen neu einführt, fällt hier durch.
     *
     * public/assets/js/ui ist gebautes Paket-Modul (WebPackages), kein
     * ignis-Quellcode — ein altes Klassenliteral dort (z.B. datetimepicker.js'
     * "Übernehmen"-Knopf) ist ein Paket-Befund, den dieses Repo nicht beheben
     * kann; der Knopf fällt auf die Grundform von ignis-btn zurück.
     */
    public function testNoOldVocabularyClassNamesInSource(): void
    {
        $hits = [];

        foreach ($this->markupFiles() as $file) {
            if (str_contains(str_replace('\\', '/', $file), '/public/assets/js/ui/')) {
                continue;
            }
            $markup = (string) file_get_contents($file);
            foreach (self::OLD_VOCABULARY as $class) {
                if (preg_match('~[\s"\']' . preg_quote($class, '~') . '(?![a-zA-Z0-9_-])~', $markup)) {
                    $hits[] = basename($file) . ': ' . $class;
                }
            }
        }

        $hits = array_values(array_unique($hits));
        sort($hits);

        $this->assertSame([], $hits, "Diese Dateien tragen noch altes Vokabular:\n  " . implode("\n  ", $hits));
    }

    public function testTheLegacyAliasesStayRemoved(): void
    {
        $this->assertStringNotContainsString('base-legacy-aliases', (string) file_get_contents(self::ROOT . '/assets/css/ui.scss'));
    }

}
