<?php

declare(strict_types=1);

namespace App\Localization;

use EmergencyForge\I18n\Translator;

/**
 * Aktive Sprache und Übersetzer.
 *
 * Werte sind BCP-47-Tags (de, en-GB), so stehen sie in intra_users.locale,
 * in der Session, in APP_LOCALE/APP_LOCALES und in <html lang>. Der Katalog
 * zu en-GB heißt lang/en.json, den Weg dahin sucht der Translator selbst.
 *
 * Wer nichts gewählt hat, bekommt APP_LOCALE, auch ohne Login. Die
 * Sprache des Browsers zählt bewusst nicht.
 */
final class Locale
{
    /** Bekannte Sprachen, benannt in der Sprache selbst. */
    public const NAMES = [
        'de'    => 'Deutsch',
        'en-GB' => 'English',
    ];

    /** @var array<string, Translator> */
    private static array $translators = [];

    /** Sprache der Installation: APP_LOCALE, sonst de. */
    public static function default(): string
    {
        $locale = trim((string) env_value('APP_LOCALE'));

        return isset(self::NAMES[$locale]) ? $locale : 'de';
    }

    /**
     * Wählbare Sprachen aus APP_LOCALES (kommagetrennt), unbekannte Tags
     * fallen weg. Ohne gültigen Eintrag nur die Installationssprache.
     *
     * @return list<string>
     */
    public static function available(): array
    {
        $listed = array_map('trim', explode(',', (string) env_value('APP_LOCALES')));
        $known = array_values(array_unique(array_filter($listed, static fn (string $tag): bool => isset(self::NAMES[$tag]))));

        return $known !== [] ? $known : [self::default()];
    }

    public static function isAvailable(string $locale): bool
    {
        return in_array($locale, self::available(), true);
    }

    /** Die Wahl aus der Session, wenn sie noch freigeschaltet ist, sonst die Installationssprache. */
    public static function current(): string
    {
        $chosen = $_SESSION['locale'] ?? null;

        return is_string($chosen) && self::isAvailable($chosen) ? $chosen : self::default();
    }

    /** Für <html lang="…"> in Seiten, die ihr <html> selbst bauen. */
    public static function htmlLang(): string
    {
        return htmlspecialchars(self::current(), ENT_QUOTES, 'UTF-8');
    }

    public static function translator(?string $locale = null): Translator
    {
        $locale ??= self::current();

        return self::$translators[$locale] ??= Translator::fromDirectories($locale, [dirname(__DIR__, 2) . '/lang']);
    }
}
