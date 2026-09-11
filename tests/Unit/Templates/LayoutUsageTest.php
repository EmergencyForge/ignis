<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Ansichten bauen ihre Seitenhülle nicht mehr selbst, sondern setzen
 * `$layout = 'admin'` und liefern nur den Inhalt von <main>
 * (templates/layouts/admin.php, Controller::renderView()).
 *
 * Der Test prüft die Regel, nicht die 51 umgestellten Dateien: jede Ansicht
 * unter templates/ und in den Plugin-Templates außerhalb von eNOTF, die
 * ein eigenes `<!DOCTYPE html>` trägt, muss auf der Liste der bewusst
 * eigenen Hüllen stehen. Eine neue Seite, die die Hülle kopiert, fällt
 * hier durch; eine Seite von der Liste, die umgestellt wird, muss von der
 * Liste gestrichen werden, sonst fällt sie auch durch.
 *
 * eNOTF (plugins/enotf, plugins/enotf-v2) hat seine eigene Hülle und ist
 * nicht Teil des Redesigns; seine Admin-Seiten bekommen Topbar und Sidebar
 * über den Shim assets/components/navbar.php.
 */
final class LayoutUsageTest extends TestCase
{
    /**
     * Ansichten mit eigener Hülle, relativ zur Repo-Wurzel:
     * die Fehlerseite ohne Sidebar, zwei Vollbildansichten (Dokument zum
     * Drucken, der Vorlagen-Editor) und die fireTab-App, die wie eNOTF
     * als eigenständige Anwendung mit Fahrzeug-Login läuft.
     */
    private const OWN_SHELL = [
        'templates/errors/404.php',
        'templates/personnel/document-view.php',
        'templates/settings/documents/visual-editor.php',
        'plugins/firetab/templates/firetab/asu.php',
        'plugins/firetab/templates/firetab/create.php',
        'plugins/firetab/templates/firetab/list.php',
        'plugins/firetab/templates/firetab/logbook.php',
        'plugins/firetab/templates/firetab/login-vehicle.php',
        'plugins/firetab/templates/firetab/status-reports.php',
        'plugins/firetab/templates/firetab/view.php',
    ];

    /** @return list<string> Template-Verzeichnisse relativ zur Repo-Wurzel */
    private function templateRoots(): array
    {
        $roots = ['templates'];
        foreach (glob(dirname(__DIR__, 3) . '/plugins/*/templates', GLOB_ONLYDIR) ?: [] as $dir) {
            $plugin = basename(dirname($dir));
            if (str_starts_with($plugin, 'enotf')) {
                continue;
            }
            $roots[] = 'plugins/' . $plugin . '/templates';
        }

        return $roots;
    }

    public function testViewsUseTheLayoutUnlessListed(): void
    {
        $repo = dirname(__DIR__, 3);
        $offenders = [];
        $missingBodyId = [];
        $missingTitle = [];
        $stale = self::OWN_SHELL;

        foreach ($this->templateRoots() as $rootRelative) {
            $root = $repo . '/' . $rootRelative;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                $inRoot = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if (str_starts_with($inRoot, 'layouts/') || str_starts_with($inRoot, 'partials/')) {
                    continue;
                }
                $relative = $rootRelative . '/' . $inRoot;
                $src = (string) file_get_contents($file->getPathname());

                if (str_contains($src, '<!DOCTYPE html>')) {
                    if (in_array($relative, self::OWN_SHELL, true)) {
                        $stale = array_diff($stale, [$relative]);
                    } else {
                        $offenders[] = $relative;
                    }
                    continue;
                }

                if (preg_match('~\$layout\s*=\s*\'admin\'~', $src) === 1) {
                    if (preg_match('~\$bodyId\s*=~', $src) !== 1) {
                        $missingBodyId[] = $relative;
                    }
                    if (preg_match('~\$SITE_TITLE\s*=~', $src) !== 1) {
                        $missingTitle[] = $relative;
                    }
                }
            }
        }

        $this->assertSame([], $offenders,
            "Diese Ansichten bauen ihre Seitenhülle selbst statt \$layout = 'admin' zu setzen:\n  " . implode("\n  ", $offenders));
        $this->assertSame([], $missingBodyId,
            "Diese Ansichten nutzen das Layout ohne \$bodyId:\n  " . implode("\n  ", $missingBodyId));
        $this->assertSame([], $missingTitle,
            "Diese Ansichten nutzen das Layout ohne \$SITE_TITLE:\n  " . implode("\n  ", $missingTitle));
        $this->assertSame([], array_values($stale),
            "Diese Einträge in OWN_SHELL bauen keine eigene Hülle mehr, bitte streichen:\n  " . implode("\n  ", $stale));
    }

    public function testLayoutRendersTheViewContentInsideMain(): void
    {
        $layout = (string) file_get_contents(dirname(__DIR__, 3) . '/templates/layouts/admin.php');

        $this->assertStringContainsString('<main class="ignis-main">', $layout);
        $this->assertStringContainsString('<?= $layoutContent ?>', $layout);
        $this->assertStringContainsString('class="ignis-app"', $layout);
        $this->assertStringContainsString('topbar.php', $layout);
        $this->assertStringContainsString('navbar-sidebar.php', $layout);
        $this->assertStringContainsString('_base/admin/head.php', $layout);
        $this->assertStringContainsString("localStorage.getItem('ignis.sidebar')", $layout);
    }

    /**
     * Die Root-Skripte laufen nicht über renderView(); sie legen die Hülle
     * selbst um ihren gepufferten Inhalt.
     */
    public function testRootPagesRenderThroughTheLayout(): void
    {
        foreach (['index.php', 'dashboard.php'] as $script) {
            $src = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $script);
            $this->assertStringNotContainsString('<!DOCTYPE html>', $src, "$script baut seine Hülle selbst.");
            $this->assertStringContainsString("Layout::render('admin'", $src, "$script rendert nicht durch die Hülle.");
        }
    }
}
