<?php

namespace App\Security;

/**
 * CSRF-Schutz für AJAX-Requests.
 *
 * Generiert und validiert Tokens, die in $_SESSION gespeichert werden.
 * Tokens werden nach erfolgreicher Validierung rotiert.
 */
class CsrfProtection
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Gibt den aktuellen CSRF-Token zurück (erzeugt einen neuen, falls keiner existiert).
     */
    public static function getToken(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Validiert einen übergebenen Token gegen den gespeicherten.
     * Rotiert den Token nach erfolgreicher Prüfung.
     */
    public static function validateToken(string $token): bool
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? '';
        if ($stored === '' || $token === '') {
            return false;
        }

        if (!hash_equals($stored, $token)) {
            return false;
        }

        // Token nach Verwendung rotieren
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return true;
    }

    /**
     * Prüft den CSRF-Token aus einem JSON-Request-Body oder POST-Daten.
     * Sendet 403 und beendet bei Fehler.
     *
     * @param array|null $jsonInput Bereits dekodiertes JSON-Input (optional)
     */
    public static function requireValid(?array $jsonInput = null): void
    {
        $token = $jsonInput['csrf_token']
            ?? $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (!self::validateToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Ungültiger oder fehlender CSRF-Token.',
                'csrf_token' => self::getToken(),
            ]);
            exit;
        }
    }

    /**
     * Gibt den aktuellen Token für die Response zurück (nach Rotation).
     */
    public static function getResponseToken(): string
    {
        return self::getToken();
    }
}
