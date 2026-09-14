<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Der CSRF-Token einer Sitzung.
 *
 * Ein Token pro Sitzung, nicht pro Anfrage. Die frühere Fassung würfelte
 * bei jeder erfolgreichen Prüfung neu, und das war der Grund, warum der
 * Schutz nie flächendeckend angezogen werden konnte: eine Seite, die zwei
 * Formulare zeigt, trägt in beiden denselben — nach dem ersten Absenden
 * ist das zweite tot. Dasselbe gilt für zwei offene Tabs und für jeden
 * Autosave neben einem offenen Formular. Der Editor musste deshalb den
 * rotierten Token aus jeder Antwort zurück ins versteckte Feld schreiben.
 *
 * Der Zugewinn dieser Rotation war gering: sie begrenzt das Zeitfenster
 * eines bereits abgeflossenen Tokens, aber wer den Token lesen kann, liest
 * auch den nächsten. Sie kostet dafür Korrektheit an jeder Stelle, an der
 * mehr als eine Anfrage im Spiel ist. Ein Token pro Sitzung ist das, was
 * Laravel, Django und Rails ebenfalls tun.
 *
 * Neu gewürfelt wird bei Anmeldung und Abmeldung — dort, wo auch die
 * Session-ID neu gewürfelt wird, siehe {@see \App\Session\SessionManager}.
 */
class CsrfProtection
{
    private const SESSION_KEY = 'csrf_token';

    /** Der Token der Sitzung; legt beim ersten Aufruf einen an. */
    public static function getToken(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Wirft den Token weg und legt einen neuen an.
     *
     * Gehört zum Wechsel der Identität: nach der Anmeldung soll kein Token
     * mehr gelten, der vor ihr ausgegeben wurde.
     */
    public static function rotate(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    /** Stimmt der übergebene Token mit dem der Sitzung überein? */
    public static function validateToken(string $token): bool
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? '';

        if (!is_string($stored) || $stored === '' || $token === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    /**
     * Prüft den Token aus JSON-Body, POST-Daten oder Header und beendet
     * die Anfrage mit 403, wenn er nicht stimmt.
     *
     * Für Einstiegspunkte, die nicht durch den Router laufen. Alles am
     * Router erledigt {@see \App\Http\Middleware\CsrfMiddleware}.
     *
     * @param array<string,mixed>|null $jsonInput Bereits dekodiertes JSON
     */
    public static function requireValid(?array $jsonInput = null): void
    {
        $token = $jsonInput['csrf_token']
            ?? $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        if (!is_string($token) || !self::validateToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success'    => false,
                'error'      => 'Ungültiger oder fehlender CSRF-Token.',
                'csrf_token' => self::getToken(),
            ]);
            exit;
        }
    }

    /**
     * Der Token für die Antwort.
     *
     * Seit dem Ende der Rotation derselbe wie {@see getToken()}; bleibt,
     * weil Aufrufer ihn so nennen.
     */
    public static function getResponseToken(): string
    {
        return self::getToken();
    }
}
