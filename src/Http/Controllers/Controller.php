<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Helpers\Flash;
use PDO;

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
 *
 * Konkrete Controller bekommen $pdo via Constructor-Injection. PHP-DI macht
 * das Autowiring automatisch, wenn sie diesen Constructor erben.
 */
abstract class Controller
{
    public function __construct(
        protected PDO $pdo,
    ) {}

    /**
     * Stellt sicher, dass ein User eingeloggt ist. Sonst Redirect zu login.php
     * mit gespeichertem Redirect-Ziel.
     */
    protected function requireAuth(): void
    {
        if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }
    }

    /**
     * Wrapper um Gate::allows: bei Denial wird Flash + Redirect gemacht.
     * Aktionen, die spezifischere Flash-Messages brauchen (z.B. "edit-self"),
     * machen den Gate-Check inline statt diesen Helper zu nutzen.
     */
    protected function ensure(string $ability, mixed $resource = null, string $redirectTo = 'index.php'): void
    {
        if (Gate::denies($ability, $resource)) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirectTo);
        }
    }

    /**
     * HTTP-Redirect relativ zum BASE_PATH und harter exit.
     * `never`-Return signalisiert dem Type-Checker, dass nach diesem Aufruf
     * nichts mehr läuft.
     */
    protected function redirect(string $relativePath): never
    {
        header('Location: ' . BASE_PATH . $relativePath);
        exit;
    }

    /**
     * Rendert ein PHP-Template aus templates/. View-Daten werden via extract()
     * in den lokalen Scope geschoben, damit das Template direkt darauf zugreifen
     * kann ($users statt $viewData['users']).
     *
     * Stellt zusätzlich `$pdo` im Template-Scope bereit, weil die Partials
     * (navbar.php, global-announcements.php, footer.php, ...) die Variable
     * als lokale Referenz erwarten.
     *
     * @param array<string,mixed> $data
     */
    protected function renderView(string $view, array $data = []): void
    {
        $templatePath = dirname(__DIR__, 3) . '/templates/' . $view . '.php';
        if (!is_file($templatePath)) {
            throw new \RuntimeException("View not found: $view ($templatePath)");
        }
        // Legacy-Compat: bestehende Partials erwarten ein lokales $pdo
        $pdo = $this->pdo;

        extract($data, EXTR_SKIP);
        require $templatePath;
    }
}
