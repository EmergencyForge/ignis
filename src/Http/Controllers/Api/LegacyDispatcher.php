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
 * Hintergrund: Beim großen "api/*"-Cutover in Phase 3.1 haben wir die
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
        // Pfad-Traversal-Schutz — legacyPath darf keine `..`-Hops enthalten
        if (str_contains($legacyPath, '..') || str_starts_with($legacyPath, '/')) {
            Logger::error('LegacyDispatcher: ungültiger Pfad', ['path' => $legacyPath]);
            return Response::json(['success' => false, 'error' => 'Ungültiger Endpoint'], 400);
        }

        $file = self::LEGACY_BASE . '/' . $legacyPath;
        $file = str_replace('\\', '/', $file);

        if (!is_file($file)) {
            Logger::error('LegacyDispatcher: Datei nicht gefunden', ['path' => $legacyPath, 'resolved' => $file]);
            return Response::json(['success' => false, 'error' => 'Endpoint nicht verfügbar'], 404);
        }

        // $pdo und $request im Include-Scope verfügbar machen —
        // Legacy-Files erwarten ein lokales $pdo (vorher durch
        // `require database.php` gesetzt) und einige nutzen $_GET/$_POST
        // direkt statt $request.
        $pdo = $this->pdo;

        require $file;

        // Legacy-Files schreiben direkt per `echo` in die Response.
        // Pipeline soll nichts mehr hinzufügen.
        return Response::empty();
    }
}
