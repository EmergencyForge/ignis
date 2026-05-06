<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Requests\CharacterIdentifyRequest;
use App\Http\Response;
use App\Logging\Logger;

/**
 * FiveM-Charakter-Endpoints.
 *
 *   - api/character/identify.php       → identify()
 *   - api/character/get-session-id.php → sessionId()
 *
 * Beide Endpoints werden im Router unter `/api/character/...` registriert
 * (siehe routes/api.php). Auth via ApiKeyMiddleware (für identify) bzw.
 * gar nicht (für sessionId — der Browser ruft das selbst, um seine
 * Session-ID an den FiveM-Server zu reichen).
 *
 * Hinweis zum identify-Flow: Der FiveM-Server schickt eine FREMDE
 * Session-ID (die des Spieler-Browsers) und will Charakter-Daten in
 * jene Session injizieren. Wir müssen also unsere eigene Session
 * schließen, auf die fremde wechseln, schreiben, und wieder zumachen.
 */
final class CharacterController
{
    /**
     * GET /api/character/session-id
     *
     * Gibt die Session-ID des aktuellen Browsers zurück. Der Browser ruft
     * das via JS und reicht die ID an den FiveM-Game-Client weiter, der
     * sie dann beim identify-Call mitschickt.
     */
    public function sessionId(Request $request): Response
    {
        return Response::json(['session_id' => session_id()]);
    }

    /**
     * POST /api/character/identify
     *
     * Body (JSON) — siehe CharacterIdentifyRequest für Regeln:
     *   - intraRP_API_Key: string  (vom ApiKeyMiddleware geprüft, hier nicht mehr sichtbar)
     *   - session_id:      string  (Ziel-Session des Spielers)
     *   - char_name:       string
     *   - char_job:        string
     *   - char_id:         int     (optional)
     *
     * Validation läuft deklarativ via FormRequest — bei Fehler wirft
     * das eine ValidationException, die vom Front-Controller als
     * 422-JSON ausgeliefert wird.
     */
    public function identify(Request $request): Response
    {
        $data = CharacterIdentifyRequest::validate($request);

        $sessionId = (string) $data['session_id'];
        $charName  = (string) $data['char_name'];
        $charJob   = (string) $data['char_job'];

        Logger::info('CharacterIdentify: Request', [
            'char_name'  => $charName,
            'char_job'   => $charJob,
            'session_id' => substr($sessionId, 0, 8) . '...',
        ]);

        // Eigene Session zumachen, damit wir auf die Ziel-Session
        // wechseln können. Beide nutzen denselben Session-Storage.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_id($sessionId);
        session_start();

        \App\Session\SessionManager::loginCharacter(
            !empty($data['char_id']) ? (int) $data['char_id'] : null,
            $charJob,
            $charName,
        );

        session_write_close();

        Logger::info('CharacterIdentify: Charakter-Daten gesetzt', [
            'char_name' => $charName,
            'char_job'  => $charJob,
        ]);

        return Response::json([
            'success'   => true,
            'message'   => 'Charakter-Daten erfolgreich gesetzt',
            'char_name' => $charName,
            'char_job'  => $charJob,
        ]);
    }
}
