<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\ConfigManager;
use EmergencyForge\Theme\CustomCode;
use EmergencyForge\Theme\EditorStyles;
use EmergencyForge\Theme\SafeMode;

/**
 * Custom code from Settings > System > Design for the end of <head> and
 * <body>. What each part does is documented in emergencyforge/theme.
 *
 * Reads the config rows instead of the constants, so a page rendered right
 * after saving (tests, same request) shows the new state.
 */
final class SiteCode
{
    public const SCSS = 'CUSTOM_SCSS';
    public const CSS = 'CUSTOM_CSS';
    public const STYLES = 'EDITOR_STYLES';
    public const JS_HEAD = 'CUSTOM_JS_HEAD';
    public const JS_BODY = 'CUSTOM_JS_BODY';

    public static function value(string $key): string
    {
        try {
            $rows = (new ConfigManager())->getAllConfig();
        } catch (\Throwable) {
            // Without a database (unit tests, outage) pages render without custom code.
            return '';
        }
        foreach ($rows as $row) {
            if ($row['config_key'] === $key) {
                return (string) ($row['config_value'] ?? '');
            }
        }

        return '';
    }

    public static function code(): CustomCode
    {
        return new CustomCode(self::value(self::CSS), self::styles(), self::value(self::JS_HEAD), self::value(self::JS_BODY));
    }

    public static function styles(): EditorStyles
    {
        return EditorStyles::fromJson(self::value(self::STYLES));
    }

    public static function safeMode(): bool
    {
        if (!isset($_SESSION)) {
            $none = [];

            return SafeMode::resolve($_GET, $none);
        }

        return SafeMode::resolve($_GET, $_SESSION);
    }

    /** End of <head>: styles for the editor menu, then CSS and head script. */
    public static function head(): string
    {
        $code = self::code();
        $meta = '<meta name="efe-styles" content="' . htmlspecialchars(json_encode($code->styles->forEditor(), JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES) . '">';

        return $meta . (self::safeMode() ? '' : $code->headTags(self::base()));
    }

    /** End of <body>: the script, or the safe mode banner instead. */
    public static function bodyEnd(): string
    {
        return self::safeMode() ? SafeMode::banner() : self::code()->bodyTags(self::base());
    }

    private static function base(): string
    {
        return (defined('BASE_PATH') ? (string) BASE_PATH : '/') . 'theme/';
    }
}
