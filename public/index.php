<?php

declare(strict_types=1);

/**
 * intraRP Front-Controller (Single Entry Point).
 *
 * Verantwortlich für:
 *   1. Bootstrap laden (Container, Session, ErrorHandler, Config)
 *   2. Routen aus routes/web.php und routes/api.php registrieren
 *   3. Request aus Superglobals bauen, Router dispatchen lassen
 *   4. Response emittieren
 *
 * Die .htaccess leitet alle nicht-existierenden Pfade hierher weiter
 * (Fallback nach !-f/!-d Check). Bestehende Modul-Stubs werden weiterhin
 * direkt von Apache gehandhabt — sie sind echte Files und MultiViews/
 * RewriteRules matchen sie, bevor die Fallback-Regel greift.
 */

use App\Exceptions\AuthorizationException;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

require_once __DIR__ . '/../assets/config/config.php';

$router = app(Router::class);

// Route-Dateien laden. Jede Datei bekommt $router als lokale Variable
// und registriert dort ihre Routen. Reihenfolge: web.php zuerst,
// api.php danach — API-Routen dürfen HTML-Routen shadowen, aber
// nicht umgekehrt (ist eh unmöglich wegen /api/-Prefix).
$routeFiles = [
    __DIR__ . '/../routes/web.php',
    __DIR__ . '/../routes/api.php',
    __DIR__ . '/../routes/api.session.php',
];

foreach ($routeFiles as $file) {
    if (is_file($file)) {
        require $file;
    }
}

// Eingehende Requests mit `.php`-Suffix → clean URL.
// GET/HEAD bekommen einen 301, damit Browser-/Suchmaschinen-Cache
// aufräumt; non-idempotente Methoden werden intern umgeschrieben,
// damit Form-Submits ihren Body nicht durch einen Redirect verlieren.
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$rawPath = parse_url($rawUri, PHP_URL_PATH) ?? '/';
if (preg_match('#\.php$#', $rawPath) && !str_ends_with($rawPath, 'index.php')) {
    $cleanPath = preg_replace('#\.php$#', '', $rawPath);
    $query = parse_url($rawUri, PHP_URL_QUERY);
    $cleanUri = $cleanPath . ($query !== null ? '?' . $query : '');

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET' || $method === 'HEAD') {
        // Permanent-Redirect, damit Browser-/Suchmaschinen-Cache aufräumen.
        Response::redirect($cleanUri, 301)->send();
        exit;
    }
    // Non-idempotente Methoden behalten ihren Body — wir rewriten intern.
    $_SERVER['REQUEST_URI'] = $cleanUri;
    $rawPath = $cleanPath;
}

// API-Versionierung. Canonical ab jetzt: `/api/v1/...`. Eingehende
// Anfragen auf `/api/v1/X` werden für die Route-Dispatch intern auf
// `/api/X` zurückgeschrieben (alle Routen bleiben am alten Pfad
// registriert). Anfragen auf `/api/X` ohne v1-Prefix laufen weiter,
// bekommen aber Deprecation-Header in der Response.
$apiDeprecated = false;
$apiSuccessor  = null;
if (preg_match('#^/api/v1(/.*)?$#', $rawPath, $m)) {
    $rest = $m[1] ?? '';
    if ($rest === '') $rest = '/';
    $cleanPath = '/api' . $rest;
    $query = parse_url($rawUri, PHP_URL_QUERY);
    $_SERVER['REQUEST_URI'] = $cleanPath . ($query !== null ? '?' . $query : '');
} elseif (preg_match('#^/api/(?!_router/)([^/?#].*)?$#', $rawPath, $m)) {
    $apiDeprecated = true;
    $apiSuccessor  = '/api/v1/' . ($m[1] ?? '');
}

try {
    $request  = Request::fromGlobals();
    $response = $router->dispatch($request);

    // Deprecation-Header für unversionierte API-Aufrufe (RFC 8594, RFC 9745).
    // Sunset 6 Monate ab heute, damit externe Clients Zeit zum Migrieren haben.
    if ($apiDeprecated && $apiSuccessor !== null) {
        $sunset = (new DateTimeImmutable('+6 months'))->format(DateTimeInterface::RFC7231);
        $response = new Response(
            status: $response->status,
            body:   $response->body,
            headers: array_merge($response->headers, [
                'Deprecation' => 'true',
                'Sunset'      => $sunset,
                'Link'        => '<' . $apiSuccessor . '>; rel="successor-version"',
            ]),
            emitted: $response->emitted,
        );
    }

    $response->send();

    // Piggyback-Cron — Response ist bereits geflusht, jetzt fällige Jobs
    // abarbeiten. Eligibility-Check + 60s-Lock sind in der Middleware gekapselt.
    if (App\Http\Middleware\CronTickMiddleware::isEligible($request)) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        App\Http\Middleware\CronTickMiddleware::runIfDue(
            $request,
            app(App\Cron\CronScheduler::class)
        );
    }
} catch (ValidationException $e) {
    // Declarative Validation aus einem FormRequest — immer als
    // 422-JSON mit Feld → Fehler-Map.
    Response::json([
        'success' => false,
        'message' => $e->firstError() ?? $e->getMessage(),
        'errors'  => $e->errors(),
    ], 422)->send();
} catch (AuthorizationException $e) {
    // Gate::authorize() aus Controller oder Policy-Middleware.
    // API-Requests bekommen JSON, HTML-Requests einen Flash+Redirect.
    $isApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

    if ($isApi) {
        Response::json([
            'success' => false,
            'message' => 'Keine Berechtigung',
            'ability' => $e->ability(),
        ], 403)->send();
    } else {
        if (class_exists(\App\Helpers\Flash::class)) {
            \App\Helpers\Flash::set('error', 'no-permissions');
        }
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        Response::redirect($base . 'index.php', 302)->send();
    }
} catch (\Throwable $e) {
    // Unerwartete Exceptions landen beim globalen ErrorHandler (Logger),
    // der bereits in config.php via ErrorHandler::register() aktiv ist.
    // Defensive: display_errors lokal ausschalten, falls jemand es in der
    // PHP-Config aktiviert hat — sonst würde PHP vor dem ErrorHandler den
    // Stack-Trace direkt in die HTTP-Response schreiben.
    @ini_set('display_errors', '0');
    throw $e;
}
