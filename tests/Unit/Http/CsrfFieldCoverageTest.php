<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Jedes schreibende Formular schickt den CSRF-Token mit.
 *
 * Die Middleware ist die eine Hälfte des Schutzes, das versteckte Feld die
 * andere. Wer ein Formular hinzufügt und `csrf_field()` vergisst, baut
 * keine Lücke — er baut einen Knopf, der eine 403-Seite zeigt. Das fällt
 * im Betrieb auf, aber erst dort, und nur dem, der ihn drückt.
 *
 * Gesucht wird im Quelltext, nicht im gerenderten HTML: ein Formular in
 * einer selten besuchten Ecke käme sonst in keinem Test vorbei.
 */
final class CsrfFieldCoverageTest extends TestCase
{
    /** Vorlagen, die keinen Token brauchen. */
    private const AUSNAHMEN = [
        // Die Fehlerseiten laufen ohne Sitzung und ohne Formular.
        'templates/errors/_shell.php',
    ];

    #[Test]
    public function jedes_post_formular_traegt_das_versteckte_feld(): void
    {
        $ohne = [];

        foreach ($this->templates() as $pfad => $inhalt) {
            if (in_array($pfad, self::AUSNAHMEN, true)) {
                continue;
            }

            foreach ($this->postFormulare($inhalt) as $nr => $rumpf) {
                $feldname = str_starts_with($pfad, 'plugins/enotf-v2/') ? '(?:csrf_token|_csrf)' : 'csrf_token';
                if (preg_match('~csrf_field\(\)|name\s*=\s*["\']' . $feldname . '["\']~i', $rumpf) !== 1) {
                    $ohne[] = $pfad . ' (Formular ' . ($nr + 1) . ')';
                }
            }
        }

        $this->assertSame([], $ohne, "Formulare ohne CSRF-Token:\n  " . implode("\n  ", $ohne));
    }

    /**
     * Die Rümpfe aller `<form method="POST">` einer Vorlage.
     *
     * Für die Erkennung werden PHP-Blöcke durch Leerzeichen gleicher Länge
     * ersetzt — sonst beendet das `>` in `<?= BASE_PATH ?>` den Form-Tag
     * vorzeitig und der Rumpf beginnt an der falschen Stelle. Die Offsets
     * bleiben dadurch für das Original gültig.
     *
     * @return list<string>
     */
    private function postFormulare(string $inhalt): array
    {
        $maskiert = $this->ohnePhpBloecke($inhalt);

        preg_match_all('~<form\b[^>]*>~i', $maskiert, $treffer, PREG_OFFSET_CAPTURE);

        $rumpfe = [];
        foreach ($treffer[0] as [$tag, $offset]) {
            if (preg_match('~method\s*=\s*["\']post~i', $tag) !== 1) {
                continue;
            }
            if (preg_match('~onsubmit\s*=\s*["\']\s*return\s+false\s*;?\s*["\']~i', $tag) === 1) {
                continue;
            }

            $start = $offset + strlen($tag);
            $ende  = stripos($maskiert, '</form', $start);
            $rumpfe[] = substr($inhalt, $start, ($ende === false ? strlen($inhalt) : $ende) - $start);
        }

        return $rumpfe;
    }

    /** Ersetzt PHP-Blöcke durch Leerzeichen gleicher Länge. */
    private function ohnePhpBloecke(string $original): string
    {
        return preg_replace_callback(
            '~<\?.*?\?>~s',
            static fn (array $m): string => str_repeat(' ', strlen($m[0])),
            $original,
        ) ?? $original;
    }

    /** @return array<string,string> Pfad (mit /) => Inhalt */
    private function templates(): array
    {
        $wurzel = dirname(__DIR__, 3);
        $verzeichnisse = [$wurzel . '/templates', ...(glob($wurzel . '/plugins/*/templates', GLOB_ONLYDIR) ?: [])];
        $out = [];
        foreach ($verzeichnisse as $verzeichnis) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($verzeichnis, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iter as $datei) {
                /** @var \SplFileInfo $datei */
                if ($datei->getExtension() !== 'php') {
                    continue;
                }
                $relativ = str_replace('\\', '/', substr($datei->getPathname(), strlen($wurzel) + 1));
                $out[$relativ] = (string) file_get_contents($datei->getPathname());
            }
        }

        return $out;
    }
}
