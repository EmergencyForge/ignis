<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\ErrorPage;
use App\Personnel\AccountLink;
use App\Session\SessionManager;
use EmergencyForge\Http\Response;
use Plugin\Training\FileStore;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\TrainingFile;

abstract class BaseController extends Controller
{
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    /** @param array<string, mixed> $data */
    protected function page(string $view, array $data, int $status = 200): Response
    {
        ob_start();
        try {
            $response = $this->renderView($view, $data);
        } finally {
            $html = (string) ob_get_clean();
        }

        return $response ?? Response::html($html, $status)->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array<string, mixed> $data */
    protected static function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status)->withHeader('Cache-Control', 'private, no-store');
    }

    protected static function notFound(): Response
    {
        return ErrorPage::notFound();
    }

    protected static function back(string $path, ?string $error = null, ?string $success = null): Response
    {
        if ($error !== null) {
            Flash::error($error);
        }
        if ($success !== null) {
            Flash::success($success);
        }

        return Response::redirect(self::base() . ltrim($path, '/'));
    }

    protected static function base(): string
    {
        return defined('BASE_PATH') ? (string) BASE_PATH : '/';
    }

    protected static function userId(): int
    {
        return (int) ($_SESSION['userid'] ?? 0);
    }

    protected static function editor(): string
    {
        return (string) (SessionManager::username() ?? 'System');
    }

    protected static function personnelId(): ?int
    {
        return AccountLink::currentId();
    }

    protected static function findCourse(string $id): ?Course
    {
        return Course::query()->with('type')->find((int) $id);
    }

    /**
     * Datei inline oder als Download. Kein Zwischenspeicher, damit eine
     * Unterlage nach dem Entzug der Rechte nicht aus einem Cache kommt.
     */
    protected static function deliver(TrainingFile $file, bool $download): Response
    {
        // Angezeigt wird die umgewandelte PDF, heruntergeladen immer das Original.
        $converted = !$download && !$file->isPdf() && $file->viewsAsPdf();
        $store     = new FileStore();
        $path      = $converted ? $store->pdfPath($file) : $store->path($file);
        $body      = $path !== null ? file_get_contents($path) : false;
        if ($body === false) {
            return Response::text('Die Datei wurde nicht gefunden.', 404)->withHeader('Cache-Control', 'private, no-store');
        }
        $inline = !$download && ($file->viewsAsPdf() || $file->isImage());

        return new Response(200, $body, [
            'Content-Type'           => $converted ? 'application/pdf' : $file->mime,
            'Content-Length'         => (string) strlen($body),
            'Content-Disposition'    => self::disposition($inline ? 'inline' : 'attachment', $converted ? pathinfo($file->original_name, PATHINFO_FILENAME) . '.pdf' : $file->original_name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    private static function disposition(string $type, string $name): string
    {
        $ascii = str_replace(['"', '\\'], '_', (string) preg_replace('/[^\x20-\x7E]/u', '_', $name));

        return $type . '; filename="' . ($ascii !== '' ? $ascii : 'unterlage') . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }
}
