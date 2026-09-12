<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ein `var(--token)` ohne Fallback muss auf einen definierten Token zeigen.
 *
 * Sonst faellt die ganze Deklaration aus — still. Keine Konsolenmeldung, kein
 * Build-Fehler, die Schrift ist einfach die geerbte. So standen Logs- und
 * Telemetrie-Seiten auf Consolas statt Geist Mono und die Federation-Felder
 * auf der Sans-Schrift, obwohl dort Instanz-IDs und Schluessel stehen.
 *
 * Mit Fallback ist ein unbekannter Token in Ordnung: dann ist der Fallback die
 * Absicht, und genau so schuetzt sich zum Beispiel die Fehlerseite, die ihre
 * Tokens selbst mitbringt.
 */
final class CssTokenTest extends TestCase
{
    #[Test]
    public function jeder_var_aufruf_ohne_fallback_zeigt_auf_einen_definierten_token(): void
    {
        $root      = str_replace('\\', '/', dirname(__DIR__, 2));
        $definiert = $this->definierteTokens($root);
        $fehler    = [];

        foreach ($this->dateien($root, ['templates', 'plugins', 'assets/components'], 'php') as $datei) {
            $inhalt = (string) file_get_contents($datei);
            if (!preg_match_all('~var\((--[A-Za-z0-9_-]+)\s*([,)])~', $inhalt, $treffer, PREG_SET_ORDER)) {
                continue;
            }

            // Eine Vorlage darf ihre Tokens selbst mitbringen — die Fehlerseite
            // tut das, weil sie ohne die Stylesheet-Kette rendern koennen muss.
            // Das zaehlt aber nur fuer sie selbst: Custom Properties gelten im
            // Dokument, das sie setzt, nicht projektweit.
            $lokal = [];
            if (preg_match_all('~(--[A-Za-z0-9_-]+)\s*:~', $inhalt, $m)) {
                $lokal = array_flip($m[1]);
            }

            foreach ($treffer as [, $token, $abschluss]) {
                if ($abschluss === ',' || isset($definiert[$token]) || isset($lokal[$token])) {
                    continue;
                }
                $fehler[] = sprintf(
                    '%s: var(%s) ohne Fallback, aber nirgends definiert',
                    ltrim(str_replace($root, '', str_replace('\\', '/', $datei)), '/'),
                    $token
                );
            }
        }

        $this->assertSame([], array_values(array_unique($fehler)), implode("\n", array_unique($fehler)));
    }

    /**
     * Tokens aus den geteilten Stylesheets — die, die jede Seite ueber ihren
     * Head bekommt. Bewusst ohne templates/ und plugins/: was eine einzelne
     * Vorlage in ihrem eigenen <style> setzt, gilt nicht fuer die anderen,
     * und genau diese Verwechslung war der Fehler (--font-mono existierte nur
     * in der Fehlerseite und wurde anderswo benutzt).
     *
     * @return array<string, true>
     */
    private function definierteTokens(string $root): array
    {
        $definiert = [];
        $quellen   = ['assets/css', 'public/assets/dist'];

        foreach ($this->dateien($root, $quellen, 'css|scss') as $datei) {
            if (preg_match_all('~(--[A-Za-z0-9_-]+)\s*:~', (string) file_get_contents($datei), $m)) {
                foreach ($m[1] as $token) {
                    $definiert[$token] = true;
                }
            }
        }

        return $definiert;
    }

    /**
     * @param  list<string>  $dirs
     * @return list<string>
     */
    private function dateien(string $root, array $dirs, string $endungen): array
    {
        $dateien = [];
        foreach ($dirs as $dir) {
            $pfad = $root . '/' . $dir;
            if (!is_dir($pfad)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($pfad, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $eintrag) {
                if ($eintrag instanceof \SplFileInfo
                    && preg_match('~\.(' . $endungen . ')$~', $eintrag->getFilename())
                ) {
                    $dateien[] = $eintrag->getPathname();
                }
            }
        }

        return $dateien;
    }
}
