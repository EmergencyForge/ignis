<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use PDO;

/**
 * Personnel-Profile-API für das Admin-Panel. Liefert HTML-Fragmente für
 * AJAX-Paginierung von Kommentaren und System-Logs auf der Profil-Detail-
 * Seite.
 *
 * Besonderheit: Die Responses sind HTML-Fragmente (text/html), nicht JSON.
 * Sie werden vom Frontend via fetch() geholt und per `innerHTML` in die
 * Seite gepatcht. Die Partials selbst rendern per `$_GET['id']` und `$pdo`
 * direkt — wir reichen diese Variablen via `extract()` in den Include-
 * Scope durch.
 */
final class PersonnelProfileController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * GET /api/personnel/profile-comments?id={user_id}
     */
    public function comments(Request $request): Response
    {
        if (!$this->validateIdParam($request)) {
            return new Response(400, '');
        }
        return $this->renderPartial(
            dirname(__DIR__, 3) . '/assets/components/profiles/comments/main.php'
        );
    }

    /**
     * GET /api/personnel/profile-logs?id={user_id}
     */
    public function logs(Request $request): Response
    {
        if (!$this->validateIdParam($request)) {
            return new Response(400, '');
        }
        return $this->renderPartial(
            dirname(__DIR__, 3) . '/assets/components/profiles/logs/main.php'
        );
    }

    private function validateIdParam(Request $request): bool
    {
        $id = $request->query['id'] ?? null;
        return $id !== null && is_numeric($id);
    }

    /**
     * Rendert ein Legacy-Partial, das `$pdo` und `$_GET` im lokalen Scope
     * erwartet. Output-Buffer fängt alles ab und gibt es als HTML-Response
     * zurück.
     */
    private function renderPartial(string $partialPath): Response
    {
        if (!is_file($partialPath)) {
            return new Response(404, 'Partial not found');
        }

        $pdo = $this->pdo;

        ob_start();
        include $partialPath;
        $html = (string) ob_get_clean();

        return new Response(
            status:  200,
            body:    $html,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }
}
