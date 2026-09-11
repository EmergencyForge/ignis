<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\Flash;
use App\Helpers\Theme;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Security\CsrfProtection;
use App\Session\SessionManager;

/**
 * Einstellungen des eigenen Kontos. Vorerst nur der Darstellungsmodus;
 * das Benutzermenü in der Navbar postet hierher.
 */
final class ProfileController extends Controller
{
    /**
     * POST /profile/theme — Darstellungsmodus speichern. Wirkt sofort über
     * die Session, dauerhaft über die Spalte intra_users.theme. Zurück geht
     * es dorthin, wo der Wechsel ausgelöst wurde.
     */
    public function theme(Request $request): Response
    {
        $back = $this->backTarget($request);

        if (!CsrfProtection::validateToken((string) ($request->post['csrf_token'] ?? ''))) {
            Flash::error('Die Anfrage war ungültig. Bitte versuche es noch einmal.');
            return Response::redirect($back);
        }

        $theme = (string) ($request->post['theme'] ?? '');
        if (!in_array($theme, Theme::MODES, true)) {
            Flash::error('Unbekannter Darstellungsmodus.');
            return Response::redirect($back);
        }

        $userId = SessionManager::userId();
        $user   = $userId !== null ? User::query()->find($userId) : null;
        if ($user !== null) {
            $user->theme = $theme;
            $user->save();
        }
        SessionManager::set('theme', $theme);

        return Response::redirect($back);
    }

    /**
     * Ziel nach dem Umschalten: der Referer, aber nur, wenn er zu dieser
     * Installation gehört. Ein fremder Host darf nicht bestimmen, wo der
     * Nutzer landet.
     */
    private function backTarget(Request $request): string
    {
        $base    = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $home    = $base . 'index';
        $referer = (string) ($request->server['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return $home;
        }

        $refererHost = (string) parse_url($referer, PHP_URL_HOST);
        $ownHost     = (string) ($request->server['HTTP_HOST'] ?? '');
        if ($refererHost === '' || $ownHost === '' || strcasecmp($refererHost, $ownHost) !== 0) {
            return $home;
        }

        $path = (string) parse_url($referer, PHP_URL_PATH);
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $home;
        }

        $query = (string) parse_url($referer, PHP_URL_QUERY);

        return $path . ($query !== '' ? '?' . $query : '');
    }
}
