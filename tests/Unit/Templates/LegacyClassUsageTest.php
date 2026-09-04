<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Bootstrap ist seit dem Modernisierungssprint nicht mehr geladen; Klassen
 * wie `btn btn-primary`, `form-select` oder `table table-striped` tun im
 * Markup nichts mehr oder hängen an Resten in admin.scss, die mit dem
 * Redesign verschwinden. Der Test findet jede Ansicht außerhalb von eNOTF,
 * die solche Klassen noch trägt, und verlangt, dass sie auf der Liste unten
 * steht. Die Liste ist der Rest, nicht die Regel: wer eine Ansicht auf die
 * ignis-Bausteine umstellt (ignis-btn, ignis-input, ignis-table,
 * ignis-chip, ignis-alert, ignis-input-group), streicht sie hier.
 *
 * Geprüft werden die Klassen im Attribut `class="…"`, auch in JS-Strings
 * innerhalb der Templates; eNOTF (plugins/enotf*, assets/components/enotf)
 * ist nicht Teil des Redesigns.
 */
final class LegacyClassUsageTest extends TestCase
{
    /** Klassen, die allein schon Bootstrap sind. */
    private const FORBIDDEN_TOKENS = [
        'badge',
        'filter-btn',
        'form-control',
        'form-control-plaintext',
        'form-control-sm',
        'form-select',
        'form-select-sm',
        'form-check',
        'form-check-input',
        'btn-group',
        'btn-group-sm',
        'input-group',
        'input-group-sm',
        'input-group-text',
    ];

    /** Grundklasse, die zusammen mit einem Modifier dieses Präfixes Bootstrap ist (`btn btn-primary`). */
    private const FORBIDDEN_PAIRS = [
        'btn'   => 'btn-',
        'table' => 'table-',
        'alert' => 'alert-',
    ];

    /**
     * Ansichten, die noch alte Klassen tragen (Stand der Redesign-Runde).
     * Pfade relativ zur Repo-Wurzel.
     */
    private const STILL_LEGACY = [
        'plugins/firetab/templates/firetab/create.php',
        'plugins/firetab/templates/firetab/login-vehicle.php',
        'plugins/firetab/templates/firetab/tabs/asu_trupps.php',
        'plugins/firetab/templates/firetab/tabs/fahrzeuge.php',
        'plugins/firetab/templates/firetab/tabs/lagekarte.php',
        'plugins/firetab/templates/firetab/tabs/log.php',
        'plugins/firetab/templates/firetab/tabs/stammdaten.php',
    ];

    /** @return list<string> Verzeichnisse und Dateien relativ zur Repo-Wurzel */
    private function roots(): array
    {
        $roots = ['templates', 'assets/components', 'index.php', 'dashboard.php'];
        foreach (glob(dirname(__DIR__, 3) . '/plugins/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (str_starts_with(basename($dir), 'enotf')) {
                continue;
            }
            $roots[] = 'plugins/' . basename($dir) . '/templates';
        }

        return $roots;
    }

    /**
     * Die Bootstrap-Klassen einer Datei, leer wenn sie sauber ist.
     *
     * @return list<string>
     */
    public static function legacyClasses(string $source): array
    {
        preg_match_all('~class="([^"]*)"~', $source, $matches);
        $found = [];
        foreach ($matches[1] as $attribute) {
            $tokens = preg_split('~\s+~', trim($attribute)) ?: [];
            foreach ($tokens as $token) {
                if (in_array($token, self::FORBIDDEN_TOKENS, true)) {
                    $found[] = $token;
                }
            }
            foreach (self::FORBIDDEN_PAIRS as $base => $prefix) {
                if (!in_array($base, $tokens, true)) {
                    continue;
                }
                foreach ($tokens as $token) {
                    if (str_starts_with($token, $prefix)) {
                        $found[] = $base . ' ' . $token;
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    public function testOnlyTheListedViewsStillCarryBootstrapClasses(): void
    {
        $base  = dirname(__DIR__, 3);
        $found = [];

        foreach ($this->roots() as $root) {
            $path = $base . '/' . $root;
            if (is_file($path)) {
                $files = [$path];
            } elseif (is_dir($path)) {
                $files = [];
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                        $files[] = $file->getPathname();
                    }
                }
            } else {
                continue;
            }

            foreach ($files as $file) {
                $rel = str_replace('\\', '/', substr($file, strlen($base) + 1));
                if (str_contains($rel, '/enotf')) {
                    continue;
                }
                $classes = self::legacyClasses((string) file_get_contents($file));
                if ($classes !== []) {
                    $found[$rel] = $classes;
                }
            }
        }

        ksort($found);

        $unexpected = array_diff_key($found, array_flip(self::STILL_LEGACY));
        $lines = [];
        foreach ($unexpected as $rel => $classes) {
            $lines[] = $rel . ': ' . implode(', ', $classes);
        }
        $this->assertSame(
            [],
            $lines,
            "Bootstrap-Klassen außerhalb der bekannten Reste (auf ignis-Bausteine umstellen):\n  " . implode("\n  ", $lines),
        );

        $cleaned = array_diff(self::STILL_LEGACY, array_keys($found));
        $this->assertSame(
            [],
            array_values($cleaned),
            "Diese Ansichten sind inzwischen sauber und gehören von der Liste gestrichen:\n  " . implode("\n  ", $cleaned),
        );
    }

    public function testCalendarAndDefectsAreClean(): void
    {
        $base = dirname(__DIR__, 3);
        foreach (['templates/calendar', 'templates/settings/vehicles/defects'] as $dir) {
            foreach (glob($base . '/' . $dir . '/*.php') ?: [] as $file) {
                $this->assertSame([], self::legacyClasses((string) file_get_contents($file)), basename($file));
            }
        }
    }
}
