<?php

declare(strict_types=1);

namespace Plugin\Mail\Controllers;

use App\Session\SessionManager;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Response;

/**
 * Gemeinsames der Mail-Controller: Ansichten als Antwort statt als Ausgabe
 * (mit Status, etwa 422 bei Formularfehlern, und `Cache-Control: private,
 * no-store`, damit keine Mail-Seite in einem Cache landet) und das
 * Audit-Log. Dort stehen Postfach- und Verteiler-Änderungen, nie Inhalte.
 */
trait RendersPages
{
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    /** @param array<string,mixed> $data */
    private function page(string $view, array $data, int $status = 200): Response
    {
        ob_start();
        try {
            $this->renderView($view, $data);
        } finally {
            $html = (string) ob_get_clean();
        }

        return Response::html($html, $status)->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array<string,mixed> $context */
    private static function audit(string $action, ?string $details, array $context = []): void
    {
        (new AuditLogger())->log((int) (SessionManager::userId() ?? 0), $action, $details, 'Mail', 1, $context);
    }
}
