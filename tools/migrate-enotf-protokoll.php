<?php

declare(strict_types=1);

/**
 * Bulk-Migrationsskript für enotf/protokoll/.
 */

$projectRoot = dirname(__DIR__);
$sourceRoot  = $projectRoot . '/enotf/protokoll';
$targetRoot  = $projectRoot . '/templates/enotf/protokoll';

if (!is_dir($sourceRoot)) {
    fwrite(STDERR, "Source not found: $sourceRoot\n");
    exit(1);
}

@mkdir($targetRoot, 0755, true);

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));
$count = 0;

foreach ($rii as $file) {
    if ($file->isDir()) continue;
    if ($file->getExtension() !== 'php') continue;
    if (str_contains($file->getFilename(), '.backup')) continue;

    $relPath = ltrim(str_replace($sourceRoot, '', $file->getPathname()), '/\\');
    $relPath = str_replace('\\', '/', $relPath);

    $targetFile = $targetRoot . '/' . $relPath;
    @mkdir(dirname($targetFile), 0755, true);

    $content = file_get_contents($file->getPathname());
    if ($content === false) continue;

    // Skip if content already migrated (no auth/CitizenFX header present)
    if (!str_contains($content, 'CitizenFX')) {
        echo "SKIP (already migrated): $relPath\n";
        continue;
    }

    // Header strippen — alles bis inklusive pin_middleware-Zeile entfernen
    $lines = explode("\n", $content);
    $newLines = ['<?php'];
    $newLines[] = '/**';
    $newLines[] = ' * View: enotf/protokoll/' . $relPath;
    $newLines[] = ' *';
    $newLines[] = ' * @var \\PDO $pdo';
    $newLines[] = ' */';
    $newLines[] = '';

    $skipping = true;
    $useStatementsAdded = false;

    foreach ($lines as $line) {
        if ($skipping) {
            // Erkenne Ende des Headers
            if (str_contains($line, 'pin_middleware.php')) {
                $skipping = false;
                continue;
            }
            // Sammle use-Statements
            if (preg_match('/^use\s+/', trim($line))) {
                $newLines[] = $line;
                $useStatementsAdded = true;
                continue;
            }
            // Skip alle Header-Zeilen
            continue;
        }
        $newLines[] = $line;
    }

    if ($useStatementsAdded) {
        // Leerzeile nach use-Block einfügen wenn nicht schon vorhanden
        // (Header endet immer nach pin_middleware, das nächste ist normalerweise eine use-Zeile oder $daten = ...)
    }

    $newContent = implode("\n", $newLines);

    // Includes anpassen — extra '../' für die zusätzliche templates/-Ebene
    $depth = substr_count($relPath, '/');
    if ($depth === 0) {
        $newContent = preg_replace("|__DIR__ \. '/\\.\\./\\.\\./|", "__DIR__ . '/../../../", $newContent);
        $newContent = preg_replace('|__DIR__ \. "/\\.\\./\\.\\./|', '__DIR__ . "/../../../', $newContent);
    } else {
        $newContent = preg_replace("|__DIR__ \. '/\\.\\./\\.\\./\\.\\./|", "__DIR__ . '/../../../../", $newContent);
        $newContent = preg_replace('|__DIR__ \. "/\\.\\./\\.\\./\\.\\./|', '__DIR__ . "/../../../../', $newContent);
    }

    file_put_contents($targetFile, $newContent);

    // Stub für die ursprüngliche Datei schreiben
    // Stub-Pfad: enotf/protokoll/{...}.php → relativ zu project root.
    // depth 0 (z.B. index.php direkt in protokoll/): braucht 2x ../
    // depth 1 (z.B. abschluss/1.php): braucht 3x ../
    // depth 2: braucht 4x ../
    $depthDots = str_repeat('../', $depth + 2);
    $depthDots = rtrim($depthDots, '/');
    $templatePath = 'enotf/protokoll/' . substr($relPath, 0, -4);

    $stub = <<<STUB
<?php

/**
 * Stub für GET/POST /enotf/protokoll/{$relPath}
 *
 * Logik: src/Http/Controllers/EnotfProtokollController.php::serve()
 *
 * Cookie-Settings für CitizenFX MÜSSEN vor session_start() gesetzt werden.
 */

if (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on') {
    @ini_set('session.cookie_samesite', 'None');
    @ini_set('session.cookie_secure', '1');
}

require_once __DIR__ . '/{$depthDots}/assets/config/config.php';

app(\\App\\Http\\Controllers\\EnotfProtokollController::class)->serve('{$templatePath}');

STUB;

    file_put_contents($file->getPathname(), $stub);

    $count++;
}

echo "Migrated: $count protokoll files\n";
