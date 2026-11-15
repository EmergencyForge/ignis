<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Permissions;
use App\Config\ConfigManager;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Session\SessionManager;
use App\Support\SiteCode;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Theme\CustomCode;
use EmergencyForge\Theme\EditorStyle;
use EmergencyForge\Theme\EditorStyles;
use EmergencyForge\Theme\InvalidCode;
use EmergencyForge\Theme\ScssCompiler;

/**
 * Settings > System > Design: custom SCSS, editor styles and custom JS.
 *
 * JS needs full_admin on top of system.admin: whoever stores a script can
 * act in every session. On an error the page shows the input again with
 * 422 instead of redirecting, so the code is not lost.
 */
final class DesignController extends Controller
{
    private const TABS = ['css', 'styles', 'js'];

    public function __construct(private readonly ConfigManager $config) {}

    /** GET /settings/system/design */
    public function index(): Response
    {
        $this->requireAuth();
        $this->ensure('system.admin');

        $tab = (string) ($_GET['tab'] ?? 'css');

        return $this->page(['tab' => in_array($tab, self::TABS, true) ? $tab : 'css']);
    }

    /** POST /settings/system/design/css */
    public function saveCss(): Response
    {
        $this->requireAuth();
        $this->ensure('system.admin');

        $scss = str_replace("\r\n", "\n", (string) ($_POST['scss'] ?? ''));
        try {
            $css = (new ScssCompiler())->compile($scss);
        } catch (InvalidCode $e) {
            return $this->page(['tab' => 'css', 'scss' => $scss, 'errors' => $e->errors], 422);
        }

        $this->store('css', [SiteCode::SCSS => $scss, SiteCode::CSS => $css], 'Eigenes CSS geändert', 'Das CSS ist gespeichert.');
    }

    /** POST /settings/system/design/styles */
    public function saveStyles(): Response
    {
        $this->requireAuth();
        $this->ensure('system.admin');

        $rows = is_array($_POST['styles'] ?? null) ? array_values($_POST['styles']) : [];
        try {
            $styles = EditorStyles::fromInput($rows);
        } catch (InvalidCode $e) {
            return $this->page(['tab' => 'styles', 'styleRows' => $rows, 'errors' => $e->errors], 422);
        }

        $this->store('styles', [SiteCode::STYLES => $styles->styles === [] ? '' : $styles->toJson()], 'Editor-Stile geändert', 'Die Editor-Stile sind gespeichert.');
    }

    /** POST /settings/system/design/js */
    public function saveJs(): Response
    {
        $this->requireAuth();
        $this->ensure('system.admin');
        if (!self::canEditJs()) {
            $this->forbidden('Eigenes JavaScript darf nur ein Admin+ bearbeiten.', 'settings/system/design?tab=js');
        }

        $head = str_replace("\r\n", "\n", (string) ($_POST['js_head'] ?? ''));
        $body = str_replace("\r\n", "\n", (string) ($_POST['js_body'] ?? ''));
        try {
            $values = [
                SiteCode::JS_HEAD => CustomCode::checkJs($head, 'Kopf der Seite'),
                SiteCode::JS_BODY => CustomCode::checkJs($body, 'Ende der Seite'),
            ];
        } catch (InvalidCode $e) {
            return $this->page(['tab' => 'js', 'jsHead' => $head, 'jsBody' => $body, 'errors' => $e->errors], 422);
        }

        $this->store('js', $values, 'Eigenes JavaScript geändert', 'Das JavaScript ist gespeichert.');
    }

    /** GET /theme/{file}: public, the login page loads the CSS too. */
    public function file(Request $request, string $file): Response
    {
        $found = SiteCode::code()->file($file);
        if ($found === null) {
            return Response::text('Nicht gefunden', 404);
        }

        return new Response(200, $found['body'], [
            'Content-Type'           => $found['contentType'],
            'Cache-Control'          => CustomCode::CACHE_CONTROL,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function canEditJs(): bool
    {
        return Permissions::check('full_admin');
    }

    /**
     * Writes the changed values and logs hashes before and after, not the
     * code itself: it can be 60 KB and would flood the audit view.
     *
     * @param array<string, string> $values
     */
    private function store(string $tab, array $values, string $action, string $message): never
    {
        $userId  = (int) SessionManager::userId();
        $context = [];
        $details = [];
        foreach ($values as $key => $value) {
            $before = SiteCode::value($key);
            if ($before === $value) {
                continue;
            }
            if (!$this->config->set($key, $value === '' ? null : $value, $userId)) {
                Flash::error('Speichern fehlgeschlagen.');
                $this->redirect('settings/system/design?tab=' . $tab);
            }
            $from = $before === '' ? 'leer' : CustomCode::hash($before);
            $to   = $value === '' ? 'leer' : CustomCode::hash($value);
            $context[strtolower($key) . '_before'] = $from;
            $context[strtolower($key) . '_after']  = $to;
            $details[] = $key . ': ' . $from . ' auf ' . $to;
        }

        if ($details === []) {
            Flash::info('Keine Änderungen.');
        } else {
            (new AuditLogger())->log($userId, $action, implode(', ', $details), 'System', 0, $context);
            Flash::success($message . ' Es gilt ab dem nächsten Seitenaufruf.');
        }

        $this->redirect('settings/system/design?tab=' . $tab);
    }

    /** @param array<string, mixed> $input */
    private function page(array $input, int $status = 200): Response
    {
        ob_start();
        $this->renderView('settings/system/design', [
            'tab'       => $input['tab'],
            'errors'    => $input['errors'] ?? [],
            'scss'      => $input['scss'] ?? SiteCode::value(SiteCode::SCSS),
            'styleRows' => $input['styleRows'] ?? array_map(
                static fn (EditorStyle $s): array => ['key' => $s->key, 'label' => $s->label, 'kind' => $s->kind, 'css' => $s->css],
                SiteCode::styles()->styles,
            ),
            'jsHead'    => $input['jsHead'] ?? SiteCode::value(SiteCode::JS_HEAD),
            'jsBody'    => $input['jsBody'] ?? SiteCode::value(SiteCode::JS_BODY),
            'canEditJs' => self::canEditJs(),
        ]);

        return Response::html((string) ob_get_clean(), $status);
    }
}
