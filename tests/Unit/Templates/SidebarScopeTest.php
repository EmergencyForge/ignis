<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Topbar, Sidebar und der Shim navbar.php laufen per `require` im Scope
 * des Layouts bzw. des aufrufenden Templates und teilen dessen Variablen.
 * Jede Zuweisung dort überschreibt eine gleichnamige Variable des
 * Aufrufers, und das fällt erst auf, wenn ein Template zufällig denselben
 * Namen benutzt (in Lex zweimal passiert: $basePath, $isActive).
 *
 * Der Test prüft die Regel, nicht die bekannten Namen: die Komponenten
 * schreiben ausschließlich Variablen mit ihrem Präfix — `top` für die
 * Topbar, `nav` für die Sidebar, `navbar` für den Shim.
 */
final class SidebarScopeTest extends TestCase
{
    private const COMPONENTS = __DIR__ . '/../../../assets/components';

    /**
     * Sammelt jede Variable, der die Datei einen Wert zuweist: normale
     * Zuweisungen, Laufvariablen von foreach (auch destrukturiert) und
     * die Variablen von catch.
     *
     * @return list<string>
     */
    private function assignedVariables(string $source): array
    {
        // Kommentare raus, damit ein erklärender Satz wie „setzt $layout = …"
        // nicht als Zuweisung zählt.
        $source = implode('', array_map(
            static fn ($token): string => is_array($token)
                ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $token[1])
                : $token,
            token_get_all($source),
        ));

        $names = [];

        preg_match_all('~\$([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:\[[^\]]*\])*\s*[.+\-*/]?=(?!=)~', $source, $assignments);
        foreach ($assignments[1] as $name) {
            $names[] = $name;
        }

        preg_match_all('~foreach\s*\([^)]*?\bas\b([^)]*)\)~', $source, $loops);
        foreach ($loops[1] as $vars) {
            preg_match_all('~\$([a-zA-Z_][a-zA-Z0-9_]*)~', $vars, $loopVars);
            foreach ($loopVars[1] as $name) {
                $names[] = $name;
            }
        }

        preg_match_all('~catch\s*\([^)]*\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\)~', $source, $catches);
        foreach ($catches[1] as $name) {
            $names[] = $name;
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function components(): array
    {
        return [
            'Die Topbar'   => ['topbar.php', 'top'],
            'Die Sidebar'  => ['navbar-sidebar.php', 'nav'],
            'Der Shim'     => ['navbar.php', 'navbar'],
        ];
    }

    #[DataProvider('components')]
    public function testComponentOnlyWritesPrefixedVariables(string $file, string $prefix): void
    {
        $source = file_get_contents(self::COMPONENTS . '/' . $file);
        $this->assertIsString($source);

        $assigned = $this->assignedVariables($source);
        $this->assertNotEmpty($assigned, "Keine Zuweisungen in $file gefunden — der Regex passt nicht mehr zur Datei.");

        $unprefixed = array_values(array_filter(
            $assigned,
            static fn (string $name): bool => !str_starts_with($name, $prefix) && $name !== 'SITE_TITLE',
        ));

        $this->assertSame(
            [],
            $unprefixed,
            "$file schreibt Variablen ohne $prefix-Präfix in den Template-Scope: \$"
                . implode(', $', $unprefixed)
                . ' — jedes Template, das denselben Namen vor dem Include belegt, verliert seinen Wert.',
        );
    }
}
