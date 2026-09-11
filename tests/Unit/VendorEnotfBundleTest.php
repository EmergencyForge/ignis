<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Die eNOTF-v1-Templates rufen `new bootstrap.Modal(...)` auf. Der Global
 * dafür kommt aus vendor-enotf.js — aber nur, wenn das Bundle ihn selbst
 * setzt: `import 'bootstrap'` registriert die Data-API und sonst nichts,
 * window.bootstrap setzt allein der UMD-Bundle-Build.
 *
 * Das Ausbleiben ist still. Kein Build-Fehler, keine Konsolenmeldung beim
 * Laden — erst der Klick, der ein Modal öffnen soll, läuft ins Leere. Genau
 * so starb das Konfliktmodal, und mit ihm fünfzehn weitere Aufrufstellen.
 */
final class VendorEnotfBundleTest extends TestCase
{
    #[Test]
    public function die_quelle_haengt_bootstrap_an_window(): void
    {
        $src = $this->read('assets/js/vendor-enotf.js');

        $this->assertMatchesRegularExpression(
            '~import \* as bootstrap from [\'"]bootstrap[\'"]~',
            $src,
            'Ohne Namensimport gibt es nichts, was an window gehängt werden könnte.'
        );
        $this->assertMatchesRegularExpression(
            '~window\.bootstrap\s*=\s*bootstrap~',
            $src
        );
    }

    #[Test]
    public function das_gebaute_bundle_traegt_den_global(): void
    {
        $dist = $this->read('public/assets/dist/vendor-enotf.js');

        $this->assertStringContainsString(
            'window.bootstrap=',
            $dist,
            'Das Bundle im Repo ist älter als die Quelle — npm run build fehlt.'
        );
    }

    private function read(string $relative): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
