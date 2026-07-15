<?php

declare(strict_types=1);

/**
 * eNOTF — Web-Routen.
 *
 * Läuft im FiveM-CEF-Browser (iframe) → FiveMCspMiddleware an allen
 * Crew-Routen. User-Auth ist optional und wird über das Config-Flag
 * ENOTF_REQUIRE_USER_AUTH gesteuert.
 *
 * Drei Middleware-Gruppen:
 *   • Public          — keine Auth (nur CSP/iframe-Support)
 *   • Entry / Login   — optionale User-Auth, KEIN PIN-Lockscreen (sonst
 *                       Redirect-Loop auf lockscreen.php selbst)
 *   • Crew-protected  — User-Auth + PIN-Lockscreen + CSP (volle Pipeline)
 *
 * iframe-Cookie-Handling (SameSite=None, Secure) kommt vom SessionManager,
 * der `/enotf/` in REQUEST_URI automatisch erkennt.
 *
 * @var \App\Http\Router $router
 */

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\FiveMCspMiddleware;
use App\Http\Middleware\PinLockscreenMiddleware;
use Plugin\Enotf\Controllers\EnotfAdminController;
use Plugin\Enotf\Controllers\EnotfController;
use Plugin\Enotf\Controllers\EnotfPrintController;
use Plugin\Enotf\Controllers\EnotfProtokollController;
use Plugin\Enotf\Controllers\EnotfSchnittstelleController;
use Plugin\Enotf\Controllers\Settings\EnotfController as SettingsEnotfController;
use Plugin\Enotf\Controllers\Settings\MedikamenteController;
use Plugin\Enotf\Controllers\Settings\PoiController;

$enotfPublic     = [FiveMCspMiddleware::class];
$enotfEntry      = [new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), FiveMCspMiddleware::class];
$enotfCrew       = [new AuthMiddleware('ENOTF_REQUIRE_USER_AUTH'), PinLockscreenMiddleware::class, FiveMCspMiddleware::class];

$router->get('/enotf/',          [EnotfController::class, 'index'], $enotfCrew);
$router->get('/enotf/index',     [EnotfController::class, 'index'], $enotfCrew);

// Login-Flow: KEIN PIN-Middleware (wäre Loop)
$router->get('/enotf/login',      [EnotfController::class, 'loginForm'], $enotfEntry);
$router->post('/enotf/login',     [EnotfController::class, 'login'],     $enotfEntry);

// Logout (Legacy: DB-Write auf GET via `?mode=self|all`)
$router->get('/enotf/loggedout',     [EnotfController::class, 'logout'], $enotfEntry);

// Lockscreen selbst darf NICHT durch PinLockscreenMiddleware — Redirect-Loop
$router->match(['GET', 'POST'], '/enotf/lockscreen',     [EnotfController::class, 'lockscreen'], $enotfEntry);

// Crew-protected (brauchen aktive Crew-Session + PIN-Lockscreen)
$router->match(['GET', 'POST'], '/enotf/overview',     [EnotfController::class, 'overview'], $enotfCrew);

$router->get('/enotf/create',     [EnotfController::class, 'createForm'], $enotfCrew);

$router->get('/enotf/fahrzeuginfo',     [EnotfController::class, 'fahrzeuginfo'], $enotfCrew);

$router->get('/enotf/fahrtenbuch',     [EnotfController::class, 'fahrtenbuch'], $enotfCrew);

// hospital-availability ist public (kein Login, kein PIN)
$router->get('/enotf/hospital-availability',     [EnotfController::class, 'hospitalAvailability'], $enotfPublic);

// ----------------------------------------------------------------------------
//  Admin
//
//  EnotfAdminController prüft intern requireAuth() +
//  Permissions::check(['admin','edivi.view'|'edivi.edit']) → Routes
//  brauchen nur AuthMiddleware. Back-Office-UI, nicht im FiveM-CEF →
//  keine FiveMCspMiddleware nötig.
// ----------------------------------------------------------------------------

$enotfAdminAuth = [new AuthMiddleware()];

$router->get('/enotf/admin',          [EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/',         [EnotfAdminController::class, 'listAction'], $enotfAdminAuth);
$router->get('/enotf/admin/list',     [EnotfAdminController::class, 'listAction'], $enotfAdminAuth);

$router->get('/enotf/admin/delete',     [EnotfAdminController::class, 'destroy'], $enotfAdminAuth);

$router->get('/enotf/admin/qm-actions-modal',     [EnotfAdminController::class, 'qmActionsModal'], $enotfAdminAuth);

$router->get('/enotf/admin/qm-log-modal',     [EnotfAdminController::class, 'qmLogModal'], $enotfAdminAuth);

// bulk-delete-empty: 308-Redirect auf /api/enotf/bulk-delete-empty (Ziel-Route
// kommt aus routes.api.php dieses Plugins)
$enotfApiRedirect = function (string $target): \Closure {
    return function (\App\Http\Request $request) use ($target): \App\Http\Response {
        $qs   = $request->server['QUERY_STRING'] ?? '';
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $url  = rtrim($base, '/') . $target . ($qs !== '' ? '?' . $qs : '');
        return \App\Http\Response::redirect($url, 308);
    };
};
$router->match(['GET', 'POST'], '/enotf/admin/bulk-delete-empty.php', $enotfApiRedirect('/api/enotf/bulk-delete-empty'));

// Zielverwaltung — auf POI-System konsolidiert. Legacy-URLs leiten
// dauerhaft auf `/settings/pois` um, bis externe Bookmarks aktualisiert
// sind. Controller + Template gibt's noch im Repo, sind aber nicht mehr
// erreichbar.
$zielverwaltungRedirect = static function (\App\Http\Request $request) {
    $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
    return \App\Http\Response::redirect($base . 'settings/pois', 301);
};
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung',           $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/',          $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/create',    $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/update',    $zielverwaltungRedirect);
$router->match(['GET', 'POST'], '/enotf/admin/zielverwaltung/delete',    $zielverwaltungRedirect);

// ----------------------------------------------------------------------------
//  Print + Schnittstelle
//
//  Print:         Crew-facing, voller Middleware-Stack inkl. PIN-Lockscreen.
//                 EnotfPrintController::show() macht zusätzlich einen eigenen
//                 PIN-Check — Belt-and-Suspenders, beide Policies sind
//                 deckungsgleich.
//  Schnittstelle: Public (Klinik-Access ohne User-Login möglich) → nur
//                 FiveMCspMiddleware. `voranmeldung` prüft PIN je nach
//                 Config selbst.
// ----------------------------------------------------------------------------

// Print — ENR kommt entweder als Query (/enotf/print/index.php?enr=…)
// oder als Clean-URL-Segment (/enotf/print/{enr}).
$router->get('/enotf/print',            [EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/',           [EnotfPrintController::class, 'show'], $enotfCrew);
$router->get('/enotf/print/index',      [EnotfPrintController::class, 'show'], $enotfCrew);
// Clean-URL: Parameter über $_GET reichen, damit show() weiterhin ?enr= liest.
$router->get('/enotf/print/{enr:[\w._-]+}', function (\App\Http\Request $request, string $enr) {
    $_GET['enr'] = $enr;
    app(EnotfPrintController::class)->show();
    return \App\Http\Response::empty();
}, $enotfCrew);

// Schnittstelle — public
$router->get('/enotf/schnittstelle',           [EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/',          [EnotfSchnittstelleController::class, 'index'], $enotfPublic);
$router->get('/enotf/schnittstelle/index',     [EnotfSchnittstelleController::class, 'index'], $enotfPublic);

$router->match(['GET', 'POST'], '/enotf/schnittstelle/klinikcode',     [EnotfSchnittstelleController::class, 'klinikcode'], $enotfPublic);

$router->match(['GET', 'POST'], '/enotf/schnittstelle/voranmeldung',     [EnotfSchnittstelleController::class, 'voranmeldung'], $enotfPublic);

$router->get('/enotf/schnittstelle/hospital-availability',     [EnotfSchnittstelleController::class, 'hospitalAvailability'], $enotfPublic);

// api-prereg: 308 auf /api/enotf/prereg
$router->match(['GET', 'POST'], '/enotf/schnittstelle/api-prereg.php', $enotfApiRedirect('/api/enotf/prereg'));

// ----------------------------------------------------------------------------
//  Protokoll-Pages
//
//  EnotfProtokollController::serve(string $templatePath) rendert jede
//  Protokoll-Page. Der Template-Pfad spiegelt die URL-Struktur:
//  URL `/enotf/protokoll/abschluss/3_1.php` → Template
//  `enotf/protokoll/abschluss/3_1`.
//
//  Zwei URL-Formen werden unterstützt:
//    1. Direct-Path:  /enotf/protokoll/<section>/<page>.php?enr=X
//    2. Clean-URL:    /enotf/p/{enr}/<section>/<page>
//
//  Segment 3 ist mehrdeutig — für die Sections `erstbefund` und `massnahmen`
//  ist es ein Unter-Verzeichnis (→ index.php), für alle anderen ein
//  Leaf-Template (→ <page>.php). Der Resolver prüft das per FS-Check.
// ----------------------------------------------------------------------------

// Helper: resolviert Path-Segmente auf einen Template-Pfad.
$protokollResolveTemplate = static function (?string $section, ?string $subsection, ?string $page): string {
    $templateRoot = __DIR__ . '/templates';
    $base         = 'enotf/protokoll';

    if ($section === null) {
        return $base . '/index';
    }
    if ($subsection === null) {
        // `/enotf/protokoll/index.php` und `/enotf/protokoll/` (via Clean-URL
        // mit nur ENR) resolven beide auf das Protokoll-Root-Template —
        // "index" ist hier KEIN Sektions-Ordner-Name.
        if ($section === 'index') {
            return $base . '/index';
        }
        // Ambiguität: {section} kann Unter-Verzeichnis (mit index.php) ODER
        // direktes Leaf-Template sein (z.B. `protokollart.php`). Früher hat
        // Apache MultiViews das `/index.php` weggefallen lassen, wenn's keinen
        // passenden Ordner gab — der Router muss das jetzt selbst per FS-Check
        // erkennen.
        $candidateDirIndex = $templateRoot . '/' . $base . '/' . $section . '/index.php';
        if (!is_file($candidateDirIndex)) {
            $candidateLeaf = $templateRoot . '/' . $base . '/' . $section . '.php';
            if (is_file($candidateLeaf)) {
                return $base . '/' . $section;
            }
        }
        return $base . '/' . $section . '/index';
    }
    if ($page === null) {
        // Ambiguität: subsection kann Leaf-Page ODER Subdir-Name sein.
        // FS-Check: existiert `/section/subsection/index.php`?
        $candidateIndex = $templateRoot . '/' . $base . '/' . $section . '/' . $subsection . '/index.php';
        if (is_file($candidateIndex)) {
            return $base . '/' . $section . '/' . $subsection . '/index';
        }
        return $base . '/' . $section . '/' . $subsection;
    }
    return $base . '/' . $section . '/' . $subsection . '/' . $page;
};

// Direct-Path-Handler: URL matched `/enotf/protokoll/<irgendwas>` — ohne `.php`
$protokollDirectHandler = function (\App\Http\Request $request) use ($protokollResolveTemplate): \App\Http\Response {
    $path   = $request->path;
    $suffix = '';
    if (preg_match('#^/enotf/protokoll/?(.*)$#', $path, $m)) {
        $suffix = rtrim($m[1], '/');
        $suffix = (string) preg_replace('/\.php$/', '', $suffix);
    }

    // Apache-MultiViews-Parität: `/foo/index.php` und `/foo/` zeigen auf
    // denselben Template-Pfad. Trailing `/index` oder `/index/…` strippen.
    $suffix = (string) preg_replace('#(?:^|/)index$#', '', $suffix);
    $suffix = trim($suffix, '/');

    if ($suffix === '') {
        $templatePath = 'enotf/protokoll/index';
    } else {
        $segments = explode('/', $suffix);
        $templatePath = $protokollResolveTemplate(
            $segments[0] ?? null,
            $segments[1] ?? null,
            $segments[2] ?? null
        );
        // 4-Segmente-Fall (selten, z.B. tiefste diagnose-Struktur)
        if (isset($segments[3])) {
            $templatePath .= '/' . $segments[3];
        }
    }

    app(EnotfProtokollController::class)->serve($templatePath);
    return \App\Http\Response::empty();
};

$router->match(['GET', 'POST'], '/enotf/protokoll',                    $protokollDirectHandler, $enotfCrew);
$router->match(['GET', 'POST'], '/enotf/protokoll/',                   $protokollDirectHandler, $enotfCrew);
$router->match(['GET', 'POST'], '/enotf/protokoll/{path:[\w./_-]+}',   $protokollDirectHandler, $enotfCrew);

// Clean-URL-Routen `/enotf/p/{enr}/...` — replicieren die Root-htaccess-Rewrites
$router->match(['GET', 'POST'], '/enotf/p/{enr:[\w._-]+}', function (\App\Http\Request $request, string $enr) use ($protokollResolveTemplate): \App\Http\Response {
    $_GET['enr'] = $enr;
    app(EnotfProtokollController::class)->serve($protokollResolveTemplate(null, null, null));
    return \App\Http\Response::empty();
}, $enotfCrew);

$router->match(['GET', 'POST'], '/enotf/p/{enr:[\w._-]+}/{section:[\w-]+}', function (\App\Http\Request $request, string $enr, string $section) use ($protokollResolveTemplate): \App\Http\Response {
    $_GET['enr'] = $enr;
    app(EnotfProtokollController::class)->serve($protokollResolveTemplate($section, null, null));
    return \App\Http\Response::empty();
}, $enotfCrew);

$router->match(['GET', 'POST'], '/enotf/p/{enr:[\w._-]+}/{section:[\w-]+}/{subsection:[\w_-]+}', function (\App\Http\Request $request, string $enr, string $section, string $subsection) use ($protokollResolveTemplate): \App\Http\Response {
    $_GET['enr'] = $enr;
    app(EnotfProtokollController::class)->serve($protokollResolveTemplate($section, $subsection, null));
    return \App\Http\Response::empty();
}, $enotfCrew);

$router->match(['GET', 'POST'], '/enotf/p/{enr:[\w._-]+}/{section:[\w-]+}/{subsection:[\w-]+}/{page:[\w_-]+}', function (\App\Http\Request $request, string $enr, string $section, string $subsection, string $page) use ($protokollResolveTemplate): \App\Http\Response {
    $_GET['enr'] = $enr;
    app(EnotfProtokollController::class)->serve($protokollResolveTemplate($section, $subsection, $page));
    return \App\Http\Response::empty();
}, $enotfCrew);

// ----------------------------------------------------------------------------
//  Settings — Schnellzugriff/Kategorien, Medikamente, POIs
// ----------------------------------------------------------------------------

$enotfSettingsAuth = [new AuthMiddleware()];

// eNOTF-Settings (Schnellzugriff + Kategorien)
$router->get('/settings/enotf/index',     [SettingsEnotfController::class, 'index'],   $enotfSettingsAuth);
$router->post('/settings/enotf/create',     [SettingsEnotfController::class, 'store'],   $enotfSettingsAuth);
$router->post('/settings/enotf/update',     [SettingsEnotfController::class, 'update'],  $enotfSettingsAuth);
$router->post('/settings/enotf/delete',     [SettingsEnotfController::class, 'destroy'], $enotfSettingsAuth);
$router->get('/settings/enotf/kategorien/index',     [SettingsEnotfController::class, 'categoriesIndex'],  $enotfSettingsAuth);
$router->post('/settings/enotf/kategorien/create',     [SettingsEnotfController::class, 'categoryStore'],   $enotfSettingsAuth);
$router->post('/settings/enotf/kategorien/update',     [SettingsEnotfController::class, 'categoryUpdate'],  $enotfSettingsAuth);
$router->post('/settings/enotf/kategorien/delete',     [SettingsEnotfController::class, 'categoryDestroy'], $enotfSettingsAuth);

// Medikamente-Settings
$router->get('/settings/medications/index',     [MedikamenteController::class, 'index'],   $enotfSettingsAuth);
$router->post('/settings/medications/create',     [MedikamenteController::class, 'store'],   $enotfSettingsAuth);
$router->post('/settings/medications/update',     [MedikamenteController::class, 'update'],  $enotfSettingsAuth);
$router->post('/settings/medications/delete',     [MedikamenteController::class, 'destroy'], $enotfSettingsAuth);

// POI-Settings (Zielverwaltung, Fachabteilungen, Zugangscodes)
$router->get('/settings/pois/index',     [PoiController::class, 'index'],   $enotfSettingsAuth);
$router->post('/settings/pois/create',     [PoiController::class, 'store'],   $enotfSettingsAuth);
$router->post('/settings/pois/update',     [PoiController::class, 'update'],  $enotfSettingsAuth);
$router->post('/settings/pois/delete',     [PoiController::class, 'destroy'], $enotfSettingsAuth);
$router->get('/settings/pois/access-codes',     [PoiController::class, 'accessCodes'], $enotfSettingsAuth);
$router->get('/settings/pois/departments',     [PoiController::class, 'departmentsIndex'], $enotfSettingsAuth);
$router->post('/settings/pois/departments-create',     [PoiController::class, 'departmentStore'],   $enotfSettingsAuth);
$router->post('/settings/pois/departments-update',     [PoiController::class, 'departmentUpdate'],  $enotfSettingsAuth);
$router->post('/settings/pois/departments-delete',     [PoiController::class, 'departmentDestroy'], $enotfSettingsAuth);
$router->post('/settings/pois/departments-reset-availability',     [PoiController::class, 'departmentResetAvailability'], $enotfSettingsAuth);
$router->match(['GET', 'POST'], '/settings/pois/departments-update-sort.php', $enotfApiRedirect('/api/pois/departments-sort'));
