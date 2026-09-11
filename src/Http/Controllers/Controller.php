<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Helpers\Layout;

/**
 * Base-Klasse für alle HTTP-Controller in intraRP.
 *
 * Bündelt die Auth/Render/Redirect-Helper, die vorher in jedem Controller
 * dupliziert waren. Konkrete Controller erben von dieser Klasse und müssen
 * `requireAuth()`, `ensure()`, `redirect()` und `renderView()` nicht mehr
 * selbst implementieren.
 *
 *
 * Middleware-Pipeline übernommen — bis dahin bleiben sie hier als Inline-
 * Helper für die Stub-basierte Routing-Welt.
 */
abstract class Controller
{
    /**
     * Stellt sicher, dass ein User eingeloggt ist. Sonst Redirect zu login.php
     * mit gespeichertem Redirect-Ziel.
     */
    protected function requireAuth(): void
    {
        if (!\App\Session\SessionManager::isLoggedIn() || !isset($_SESSION['permissions'])) {
            \App\Session\SessionManager::setRedirectFromRequest();
            $this->redirect('login');
        }
    }

    /**
     * Wrapper um Gate::allows: bei Denial wird Flash + Redirect gemacht.
     * Aktionen, die spezifischere Flash-Messages brauchen (z.B. "edit-self"),
     * machen den Gate-Check inline statt diesen Helper zu nutzen.
     */
    protected function ensure(string $ability, mixed $resource = null, string $redirectTo = 'index'): void
    {
        if (Gate::denies($ability, $resource)) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirectTo);
        }
    }

    /**
     * HTTP-Redirect relativ zum BASE_PATH. Wirft eine RedirectException,
     * die der Router direkt am Handler zu Response::redirect() macht
     * (Router::buildHandlerCallable()); nach dem Aufruf läuft im Controller
     * nichts mehr, wie vorher mit `header()` + `exit`. So sehen die Haken
     * des Routers die Weiterleitung (Fragment-Aufrufer bekommen sie als
     * X-Ignis-Location, siehe RouterFactory) und Feature-Tests prüfen
     * sie als Antwort.
     *
     * Auto-Translation: Legacy-deutsche Pfade (`'kalender'`, `'manv/board'`,
     * etc.) werden via `UrlMap::translateRelative()` transparent auf die
     * kanonischen englischen Pfade uebersetzt. Vorteil: bestehende Calls
     * wie `$this->redirect('kalender')` liefern direkt `/calendar` ohne
     * extra 301-Hop, kein Edit pro Call-Site noetig.
     */
    protected function redirect(string $relativePath): never
    {
        $translated = \App\Http\UrlMap::translateRelative($relativePath);
        throw new \App\Http\RedirectException(BASE_PATH . ($translated ?? $relativePath));
    }

    /**
     * Will der Aufrufer nur den Inhalt einer Ansicht (Drawer, Vorschau)?
     * assets/js/ui/drawer-form.js schickt `X-Requested-With: fragment`.
     */
    public static function wantsFragment(): bool
    {
        return Layout::wantsFragment();
    }

    /**
     * Basis-Verzeichnis für renderView(). Plugin-Controller überschreiben
     * das und liefern das templates/-Verzeichnis ihres Plugins.
     */
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 3) . '/templates';
    }

    /**
     * Rendert ein PHP-Template aus templates/. View-Daten werden via extract()
     * in den lokalen Scope geschoben, damit das Template direkt darauf zugreifen
     * kann ($users statt $viewData['users']).
     *
     * Views werden relativ zu viewBasePath() aufgelöst — Controller in
     * Plugins überschreiben die Methode und zeigen auf ihr eigenes
     * templates/-Verzeichnis.
     *
     * Setzt das Template `$layout = 'admin'`, liefert es nur den Inhalt von
     * <main>; die Hülle (templates/layouts/admin.php, immer aus dem Kern,
     * auch für Plugin-Templates) kommt von App\Helpers\Layout. Das Template
     * gibt dazu `$bodyId` und `$SITE_TITLE` mit, optional `$bodyPage` und
     * `$layoutHead`. Ohne `$layout` bleibt alles wie bisher — eNOTF und die
     * Seiten mit eigener Hülle bauen ihr <html> weiter selbst.
     *
     * Fragment statt Seite: mit `X-Requested-With: fragment` (Drawer,
     * assets/js/ui/drawer-form.js) lässt Layout::render() die Hülle weg und
     * liefert nur den Inhalt in <div class="ignis-fragment" data-title>.
     *
     * @param array<string,mixed> $data
     */
    protected function renderView(string $view, array $data = []): void
    {
        $templatePath = rtrim($this->viewBasePath(), '/\\') . '/' . $view . '.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException("View not found: $view ($templatePath)");
        }
        extract($data, EXTR_SKIP);

        // Output-Buffer um die ganze Template-Render-Phase. Wenn das Template
        // mitten im Render einen Throwable wirft, wird der bereits-gerenderte
        // Chrome (Head, Sidebar, Navbar) verworfen statt half-rendered an den
        // Browser zu kleben — der ErrorHandler kann dann seine eigene Page
        // sauber emittieren, ohne sie unter Dashboard-Layout zu schachteln.
        ob_start();
        try {
            require $templatePath;
            $output = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        if (isset($layout) && is_string($layout) && $layout !== '') {
            $output = Layout::render($layout, $output, [
                'SITE_TITLE' => $SITE_TITLE ?? null,
                'bodyId'     => $bodyId ?? null,
                'bodyPage'   => $bodyPage ?? null,
                'layoutHead' => $layoutHead ?? null,
            ]);
        }

        echo $output;
    }
}
