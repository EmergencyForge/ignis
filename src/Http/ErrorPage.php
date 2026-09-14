<?php

declare(strict_types=1);

namespace App\Http;

use EmergencyForge\Http\Response;

/**
 * Die Fehlerseiten als Antwort.
 *
 * Bis hierher endete eine verweigerte Berechtigung als Hinweis-Blase plus
 * Weiterleitung aufs Dashboard. Für einen geteilten Link ist das die
 * falsche Auskunft: der Aufrufer sieht die Startseite und erfährt nicht,
 * was abgelehnt wurde. Und ein API-Aufrufer bekam eine 302 auf HTML, wo er
 * einen Status erwartet.
 *
 * Deshalb hier beides an einem Ort: die Seite für den Browser, JSON für
 * alles unter `/api/`.
 */
final class ErrorPage
{
    public static function forbidden(
        string $message,
        ?string $path = null,
        ?string $backUrl = null,
        string $backLabel = 'Zurück zum Dashboard',
    ): Response {
        if (self::wantsJson($path)) {
            return Response::json(['success' => false, 'error' => $message], 403);
        }

        $body = self::render('403', [
            'message'   => $message,
            'backUrl'   => $backUrl ?? self::basePath(),
            'backLabel' => $backLabel,
        ]);

        return $body === null
            ? Response::text('Keine Berechtigung: ' . $message, 403)
            : Response::html($body, 403);
    }

    public static function notFound(?string $path = null): Response
    {
        if (self::wantsJson($path)) {
            return Response::json(['success' => false, 'error' => 'not_found'], 404);
        }

        $body = self::render('404', []);

        return $body === null
            ? Response::text('Not Found', 404)
            : Response::html($body, 404);
    }

    /**
     * Rendert ein Fehler-Template. `null`, wenn es fehlt oder beim Rendern
     * etwas wirft — dann bleibt der Plain-Text-Rückfall, damit eine kaputte
     * Installation wenigstens den richtigen Status liefert statt still
     * einen 500er.
     *
     * @param array<string,mixed> $data
     */
    private static function render(string $view, array $data): ?string
    {
        $template = dirname(__DIR__, 2) . '/templates/errors/' . $view . '.php';
        if (!is_file($template)) {
            return null;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $template;

            return (string) ob_get_clean();
        } catch (\Throwable) {
            ob_end_clean();

            return null;
        }
    }

    private static function wantsJson(?string $path): bool
    {
        if ($path !== null && str_starts_with($path, '/api/')) {
            return true;
        }

        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? (string) BASE_PATH : '/';
    }
}
