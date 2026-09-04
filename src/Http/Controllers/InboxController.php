<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\Flash;
use App\Http\Request;
use App\Notifications\NotificationManager;
use App\Session\SessionManager;
use App\Support\ListQuery;

/**
 * Posteingang: die Benachrichtigungen des angemeldeten Nutzers
 * (App\Notifications\NotificationManager).
 *
 *   GET  /inbox              Seite: alle Einträge, gruppiert nach Tag,
 *                            Filter gelesen/ungelesen und nach Typ, geblättert
 *   GET  /inbox/popover      die letzten Einträge für die Glocke in der
 *                            Topbar, ohne Hülle (shell.js lädt beim Öffnen)
 *   GET  /inbox/{id}/open    setzt den Eintrag auf gelesen und geht zu
 *                            seinem Ziel (ohne Ziel zurück zur Seite)
 *   POST /inbox/read         setzt einen (`id`) oder alle Einträge auf
 *                            gelesen, mit CSRF-Token
 *
 * Alle nur hinter der Anmeldung; welche Typen ein Nutzer sieht,
 * entscheiden die Handler der Registry (NotificationTypeInterface::allowed()).
 */
final class InboxController extends Controller
{
    public const POPOVER_LIMIT = 5;
    public const PAGE_SIZE = 25;

    public function __construct(private readonly NotificationManager $notifications)
    {
    }

    public function index(): void
    {
        $this->requireAuth();
        $userId = (int) SessionManager::userId();

        $list = ListQuery::fromQuery($_GET, ['created' => 'created_at'], 'created', 'desc', self::PAGE_SIZE, ['filter', 'type']);
        $unreadOnly = $list->filter('filter') === 'unread';
        $type = $list->filter('type');
        if ($type !== '' && !isset($this->notifications->visibleTypes()[$type])) {
            $type = '';
        }

        $rows = $list->paginate($this->notifications->builder($userId, $unreadOnly, $type === '' ? null : $type))
            ->map(fn ($n): array => $this->notifications->decorate($n->getAttributes()))
            ->all();

        $this->renderView('inbox/index', [
            'entries'    => $rows,
            'list'       => $list,
            'unreadOnly' => $unreadOnly,
            'type'       => $type,
            'types'      => $this->notifications->visibleTypes(),
            'unread'     => $this->notifications->count($userId),
        ]);
    }

    public function popover(): void
    {
        $this->requireAuth();
        $userId = (int) SessionManager::userId();

        $this->renderView('inbox/_popover', [
            'entries' => $this->notifications->forUser($userId, false, self::POPOVER_LIMIT),
            'unread'  => $this->notifications->count($userId),
            'limit'   => self::POPOVER_LIMIT,
        ]);
    }

    public function open(Request $request, string $id): void
    {
        $this->requireAuth();
        $userId = (int) SessionManager::userId();

        $entry = $this->notifications->find($userId, (int) $id);
        if ($entry === null) {
            Flash::error('Die Benachrichtigung gibt es nicht mehr.');
            $this->redirect('inbox');
        }

        $this->notifications->markRead($userId, (int) $entry['id']);

        $href = $entry['href'];
        if (!is_string($href) || $href === '') {
            $this->redirect('inbox');
        }

        throw new \App\Http\RedirectException($href);
    }

    public function read(): void
    {
        $this->requireAuth();
        $userId = (int) SessionManager::userId();

        $id = (int) ($_POST['id'] ?? 0);
        $changed = $this->notifications->markRead($userId, $id > 0 ? $id : null);

        if ($id > 0) {
            Flash::success($changed > 0 ? 'Als gelesen markiert.' : 'War schon gelesen.');
        } else {
            Flash::success($changed === 0 ? 'Nichts Ungelesenes.' : ($changed === 1 ? 'Eine Benachrichtigung als gelesen markiert.' : $changed . ' Benachrichtigungen als gelesen markiert.'));
        }

        $back = (string) ($_POST['return'] ?? '');
        $this->redirect($back !== '' && preg_match('~^[a-z][a-z0-9/_-]*(\?[^\s]*)?$~i', $back) === 1 ? $back : 'inbox');
    }
}
