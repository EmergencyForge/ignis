# intraRP — Routes

Zentrale Definition aller HTTP-Routen für intraRP (Phase 3.1+).

## Dateien

- **web.php** — HTML-Routen (Controller rendern Templates, Browser-Zielgruppe)
- **api.php** — JSON-Routen (Admin-API + FiveM-Server-Endpoints)

Beide Dateien werden vom Front-Controller [`public/index.php`](../public/index.php) geladen und registrieren ihre Routen auf dem `$router`-Objekt.

## Middleware-Übersicht

| Middleware | Zweck | Typisch für |
|---|---|---|
| `AuthMiddleware` | Session-Auth, optional config-gated | HTML-Module, Admin-API |
| `PermissionMiddleware` | `Permissions::check()` mit einer/mehreren Permissions | Module mit Rollen-Gating |
| `CsrfMiddleware` | CSRF-Token für POST/PUT/PATCH/DELETE | State-ändernde Admin-API |
| `ApiKeyMiddleware` | `API_KEY`-Vergleich, localhost-Bypass | FiveM-Server-Endpoints |
| `FiveMCspMiddleware` | CSP-Header bei CitizenFX UA entfernen | eNOTF, Einsatz-Tactical-Map |
| `PinLockscreenMiddleware` | eNOTF PIN-Gate (5-min Timeout) | eNOTF-Protokoll-Routen |
| `MethodMiddleware` | Sekundäre Methoden-Prüfung | selten nötig |

## Config-gated Auth

Einige Module haben deploy-seitig konfigurierbare Auth-Anforderungen. Die `AuthMiddleware` unterstützt das über zwei Parameter:

```php
// Standard: immer Login-Pflicht
new AuthMiddleware()

// Login nur erforderlich, wenn das Flag aktiviert ist
new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH')
new AuthMiddleware('FIRE_INCIDENT_REQUIRE_USER_AUTH')

// Login erforderlich, AUSSER das Flag ist aktiviert (inverted)
new AuthMiddleware('KB_PUBLIC_ACCESS', invert: true)
```

## Handler-Formate

Der Router akzeptiert drei Handler-Formate:

```php
// 1) Closure (inline, für kleine Endpoints)
$router->get('/ping', fn($req) => Response::json(['pong' => true]));

// 2) Controller-Paar (bevorzugt für Module)
$router->get('/users', [UserController::class, 'index']);

// 3) String-Kurzform
$router->get('/users', 'App\\Http\\Controllers\\UserController@index');
```

Bei (2) und (3) wird die Controller-Klasse über den DI-Container aufgelöst — Constructor-Injection von `PDO`, `Logger`, etc. funktioniert automatisch.

## Route-Parameter

FastRoute-Syntax: `{name}` oder `{name:regex}`.

```php
$router->get('/users/{id:\d+}', [UserController::class, 'show']);
```

Parameter werden als zweite+ Argumente an die Controller-Methode gereicht, nach dem `Request`-Objekt:

```php
public function show(Request $request, string $id): Response { ... }
```

Sie sind zusätzlich als Request-Attribute verfügbar (`$request->attribute('id')`), damit Middlewares sie für Policy-Checks o.ä. nutzen können.

## Gruppen

```php
$router->group('/api/users', [new AuthMiddleware(), CsrfMiddleware::class], function ($r) {
    $r->get('/',        [UserApiController::class, 'index']);
    $r->post('/',       [UserApiController::class, 'create']);
    $r->get('/{id:\d+}',[UserApiController::class, 'show']);
});
```

Gruppen-Middlewares werden jeder Route in der Gruppe vorangestellt; zusätzliche Route-spezifische Middlewares werden angehängt. Gruppen können verschachtelt werden.
