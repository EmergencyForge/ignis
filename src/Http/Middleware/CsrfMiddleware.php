<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\ErrorPage;
use App\Security\CsrfProtection;
use EmergencyForge\Http\Middleware\MiddlewareInterface;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Plugin\EnotfV2\Http\Csrf as EnotfCsrf;

/** Globale CSRF-Prüfung; eNOTF-v2 behält seinen eigenen Sitzungstoken. */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var list<string> Ausschließlich Routen mit ApiKeyMiddleware. */
    private const EXEMPT = [
        '/api/character/identify',
        '/api/emd/sync',
        '/api/emd-sync.php',
        '/api/asu/sync',
        '/api/asu-sync.php',
        '/api/telemetry/heartbeat',
        '/api/telemetry-heartbeat.php',
        '/api/emd/status-poll',
    ];

    public function process(Request $request, callable $next): Response
    {
        if (!in_array(strtoupper($request->method), self::WRITE_METHODS, true)) {
            return $next($request);
        }

        if (in_array($request->path, self::EXEMPT, true)) {
            return $next($request);
        }

        $token = $this->extractToken($request);

        if (!(($token !== null && CsrfProtection::validateToken($token)) || $this->validEnotfToken($request))) {
            return ErrorPage::forbidden(
                'Das Formular ist abgelaufen. Lade die Seite neu und versuche es noch einmal.',
                $request->path,
            );
        }

        return $next($request);
    }

    private function validEnotfToken(Request $request): bool
    {
        if (!str_starts_with($request->path, '/enotf-v2/')
            && !str_starts_with($request->path, '/api/enotf-v2/')) {
            return false;
        }
        if (!class_exists(EnotfCsrf::class)) {
            return false;
        }

        // Bestehende Plugin-Formulare und QM senden ihren eigenen Sitzungstoken.
        $token = $request->post[EnotfCsrf::FIELD_NAME] ?? null;
        if (!is_string($token) || $token === '') {
            $token = $request->header(EnotfCsrf::HEADER_NAME);
        }

        return is_string($token) && EnotfCsrf::isValid($token);
    }

    private function extractToken(Request $request): ?string
    {
        $json = $request->json();
        if (is_array($json) && isset($json['csrf_token']) && is_string($json['csrf_token'])) {
            return $json['csrf_token'];
        }

        if (isset($request->post['csrf_token']) && is_string($request->post['csrf_token'])) {
            return $request->post['csrf_token'];
        }

        $header = $request->header('X-CSRF-Token');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return null;
    }
}
