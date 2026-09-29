<?php

declare(strict_types=1);

namespace Plugin\Mail;

/**
 * Die Standard-Signatur (`MAIL_DEFAULT_SIGNATURE`) ist im Formular
 * Klartext, eine Zeile je Absatz, in der Konfiguration Editor-JSON.
 *
 * Grenzen: höchstens MAX_LINES Zeilen und MAX_LENGTH Zeichen, und der
 * Text muss gültiges UTF-8 sein. Ungültige Bytes werden nicht still
 * repariert, sondern als Fehler gemeldet (json_encode würde sonst einen
 * leeren Wert schreiben).
 */
final class SignatureText
{
    public const MAX_LINES  = 100;
    public const MAX_LENGTH = 4000;

    /** `null` heißt in Ordnung, sonst die Meldung fürs Formular. */
    public static function validate(string $text): ?string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return 'Die Signatur enthält ungültige Zeichen.';
        }
        if (mb_strlen($text, 'UTF-8') > self::MAX_LENGTH) {
            return 'Die Signatur ist zu lang (höchstens ' . self::MAX_LENGTH . ' Zeichen).';
        }
        if (count(preg_split('/\R/u', trim($text)) ?: []) > self::MAX_LINES) {
            return 'Die Signatur hat zu viele Zeilen (höchstens ' . self::MAX_LINES . ').';
        }

        return null;
    }

    /** Klartext → Editor-JSON als String; leerer Text → leerer String (keine Standard-Signatur). */
    public static function toJson(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        $content = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            $line = trim($line);
            $content[] = $line === ''
                ? ['type' => 'paragraph']
                : ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $line]]];
        }

        return (string) json_encode(['type' => 'doc', 'content' => $content], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Gespeicherter Wert → Klartext. Kein gültiges Dokument: der Wert selbst. */
    public static function toText(string $stored): string
    {
        if (trim($stored) === '') {
            return '';
        }

        $doc = json_decode($stored, true);
        if (!is_array($doc) || ($doc['type'] ?? null) !== 'doc') {
            return trim($stored);
        }

        $lines = [];
        foreach ((array) ($doc['content'] ?? []) as $node) {
            $lines[] = is_array($node) ? self::nodeText($node) : '';
        }

        return trim(implode("\n", $lines));
    }

    /** @param array<string,mixed> $node */
    private static function nodeText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }
        if (($node['type'] ?? null) === 'hardBreak') {
            return "\n";
        }

        $text = '';
        foreach ((array) ($node['content'] ?? []) as $child) {
            $text .= is_array($child) ? self::nodeText($child) : '';
        }

        return $text;
    }
}
