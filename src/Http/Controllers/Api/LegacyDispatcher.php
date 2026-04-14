<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use PDO;

/**
 * LegacyDispatcher — dünner Wrapper um die noch nicht vollständig portierten
 * Legacy-API-Endpoints unter `src/LegacyApi/`.
 *
 * URL-Ebene komplett in den Router verlegt (alle Endpoints laufen durch
 * `Middleware-Pipeline → Controller`), aber die inneren Business-Logiken
 * der Endpoints sind bewusst **noch nicht** Zeile-für-Zeile nach PHP-
 * Controller portiert — das wäre ein separates, sehr großes Vorhaben.
 *
 * Stattdessen:
 *
 *   1. Jeder alte `api/<sub>/foo.php` ist als-ist nach
 *      `src/LegacyApi/<sub>/foo.php` gewandert.
 *   2. Seine `__DIR__`-basierten Includes wurden per sed auf den neuen
 *      Pfad angepasst (eine Level-Stufe mehr).
 *   3. Router-Routen zeigen auf `[LegacyDispatcher::class, 'run']` mit
 *      dem relativen Legacy-Pfad als Closure-Argument.
 *   4. `run()` includet die Datei und macht `$pdo` + `$request` im
 *      Script-Scope verfügbar.
 *
 * Die Legacy-Scripts dürfen weiterhin direkt `echo`en und `header(...)`
 * setzen — das Response-Objekt signalisiert der Pipeline via `emitted=true`,
 * dass kein zusätzlicher Body gesendet werden soll.
 *
 * Middleware (`AuthMiddleware`, `ApiKeyMiddleware`, `PermissionMiddleware`)
 * läuft VOR dem Dispatcher — die Auth-Checks innerhalb der Legacy-Files
 * sind dadurch redundant, aber harmlos (dead code, wird in einem späteren
 * Refactor rausgenommen, wenn der File zum echten Controller umgeschrieben
 * wird).
 */
final class LegacyDispatcher
{
    private const LEGACY_BASE = __DIR__ . '/../../../LegacyApi';

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Lädt eine Legacy-API-Datei und führt sie im Request-Scope aus.
     *
     * @param  string  $legacyPath  Relativer Pfad unter src/LegacyApi/, z.B. "documents/save.php"
     */
    public function run(Request $request, string $legacyPath): Response
    {
        // Defense-in-depth gegen Path-Traversal. Der $legacyPath kommt
        // normalerweise aus routes/api.legacy.php (hartkodiert), nicht aus
        // User-Input. Trotzdem mehrfach gehärtet, falls sich in Zukunft
        // jemals dynamische Pfade einschleichen:
        //   1. keine `..`-Sequenzen
        //   2. kein führender Slash / Backslash
        //   3. kein Null-Byte
        //   4. nur .php-Extension erlaubt
        //   5. realpath() muss INNERHALB von LEGACY_BASE bleiben
        if (
            str_contains($legacyPath, '..')
            || str_contains($legacyPath, "\0")
            || str_starts_with($legacyPath, '/')
            || str_starts_with($legacyPath, '\\')
            || !str_ends_with($legacyPath, '.php')
        ) {
            Logger::error('LegacyDispatcher: ungültiger Pfad', ['path' => $legacyPath]);
            return Response::json(['success' => false, 'error' => 'Ungültiger Endpoint'], 400);
        }

        $file = str_replace('\\', '/', self::LEGACY_BASE . '/' . $legacyPath);

        if (!is_file($file)) {
            Logger::error('LegacyDispatcher: Datei nicht gefunden', ['path' => $legacyPath, 'resolved' => $file]);
            return Response::json(['success' => false, 'error' => 'Endpoint nicht verfügbar'], 404);
        }

        // Realpath-Check: der aufgelöste absolute Pfad MUSS mit LEGACY_BASE
        // beginnen, sonst hat jemand z.B. via Symlink-Trick rausgebrochen.
        $realFile = realpath($file);
        $realBase = realpath(self::LEGACY_BASE);
        if ($realFile === false || $realBase === false || !str_starts_with($realFile, $realBase)) {
            Logger::error('LegacyDispatcher: realpath außerhalb LEGACY_BASE', [
                'path'     => $legacyPath,
                'resolved' => $realFile ?: 'false',
            ]);
            return Response::json(['success' => false, 'error' => 'Ungültiger Endpoint'], 400);
        }

        // $pdo und $request im Include-Scope verfügbar machen —
        // Legacy-Files erwarten ein lokales $pdo (vorher durch
        // `require database.php` gesetzt) und einige nutzen $_GET/$_POST
        // direkt statt $request.
        $pdo = $this->pdo;

        require $realFile;

        // Legacy-Files schreiben direkt per `echo` in die Response.
        // Pipeline soll nichts mehr hinzufügen.
        return Response::empty();
    }
}
