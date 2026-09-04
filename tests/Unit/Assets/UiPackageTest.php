<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * Die UI-Module (Dialog, Drawer, Toasts, Arbeitsbereich …) kommen aus dem
 * gemeinsamen Paket @emergencyforge/ui im Nachbar-Repo WebPackages; der
 * UI-Pass in vite.config.js setzt den Präfix ignis ein und schreibt je
 * Modul eine Datei nach public/assets/js/ui. Unter assets/js/ui liegen nur
 * noch die Produktdateien: shell.js und palette.js (Routen, Sidebar,
 * Suche) und die Kompatibilitätsdateien, die ein Paket-Modul um alte
 * Aliase ergänzen. Eine Kopie eines Paket-Moduls darf dort nicht wieder
 * auftauchen, sonst laufen die Produkte auseinander.
 *
 * Die eNOTF-Seiten (eingefroren) binden die Module einzeln unter
 * assets/js/ui/<name>.js ein; deshalb muss zu jedem eingebundenen Namen
 * die gebaute Datei existieren und die Aliase müssen darin stecken.
 */
final class UiPackageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** Was unter assets/js/ui liegen darf, mit Grund. */
    private const PRODUCT_FILES = [
        'dialog-compat.js'   => 'Paket-Dialog plus window.intraConfirm/intraAlert/intraPrompt',
        'dropdown-compat.js' => 'Paket-Dropdown plus window.eNOTFCustomDropdown',
        'palette.js'         => 'Suche unter dem Suchfeld, ruft /api/system/global-search',
        'shell.js'           => 'Sidebar, Menüs, Posteingang, Schnellaktionen',
    ];

    /** Die Module des Pakets, wie vite.config.js sie baut. */
    private const PACKAGE_MODULES = [
        'accordion', 'alert', 'chip', 'colorpicker', 'combobox', 'datepicker',
        'datetimepicker', 'dialog', 'drawer', 'drawer-form', 'dropdown', 'file',
        'form', 'multi-select', 'snackbar', 'tabs', 'tooltip', 'workbench',
    ];

    public function testOnlyTheProductFilesLiveUnderAssetsJsUi(): void
    {
        $files = array_map('basename', glob(self::ROOT . '/assets/js/ui/*') ?: []);
        sort($files);

        $this->assertSame(
            array_keys(self::PRODUCT_FILES),
            $files,
            "assets/js/ui enthält mehr als die Produktdateien. Paket-Module gehören nach WebPackages/packages/ui/js/src,\n"
            . "Produktzusätze als <modul>-compat.js mit Eintrag in vite.config.js und in diesem Test.",
        );
    }

    public function testEveryPackageModuleIsBuiltWithThePrefix(): void
    {
        foreach (self::PACKAGE_MODULES as $module) {
            $path = self::ROOT . '/public/assets/js/ui/' . $module . '.js';
            $this->assertFileExists($path, 'Gebautes Modul fehlt: npm run build ausführen.');
            $code = (string) file_get_contents($path);
            $this->assertStringNotContainsString('__pfx__', $code, $module . '.js trägt noch den Platzhalter.');
            $this->assertStringNotContainsString('__Pfx__', $code, $module . '.js trägt noch den Platzhalter.');
        }
    }

    public function testTheBuiltModulesCarryTheCompatibilityAliases(): void
    {
        $dialog   = (string) file_get_contents(self::ROOT . '/public/assets/js/ui/dialog.js');
        $dropdown = (string) file_get_contents(self::ROOT . '/public/assets/js/ui/dropdown.js');

        foreach (['intraConfirm', 'intraAlert', 'intraPrompt'] as $alias) {
            $this->assertStringContainsString('window.' . $alias, $dialog);
        }
        $this->assertStringContainsString('window.eNOTFCustomDropdown', $dropdown);
        $this->assertStringContainsString('window.ignisDropdownInit', $dropdown);
    }

    public function testEveryModuleATemplateIncludesIsBuilt(): void
    {
        $missing = [];
        foreach ($this->templates() as $file) {
            preg_match_all('~assets/js/ui/([a-z0-9-]+)\.js~', (string) file_get_contents($file), $m);
            foreach (array_unique($m[1]) as $name) {
                if (!is_file(self::ROOT . '/public/assets/js/ui/' . $name . '.js')) {
                    $missing[] = substr($file, strlen(self::ROOT) + 1) . ': ' . $name . '.js';
                }
            }
        }
        sort($missing);

        $this->assertSame([], $missing, "Eingebundene Module ohne gebaute Datei:\n  " . implode("\n  ", $missing));
    }

    /**
     * @return list<string>
     */
    private function templates(): array
    {
        $files = [];
        foreach (['/templates', '/assets/components', '/plugins'] as $root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . $root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $files[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }
        sort($files);

        return $files;
    }
}
