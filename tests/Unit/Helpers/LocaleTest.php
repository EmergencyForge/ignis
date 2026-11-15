<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Localization\Locale;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Welche Sprache gilt: APP_LOCALES schaltet frei, APP_LOCALE ist der
 * Rückfall, unbekannte Tags zählen nicht.
 */
final class LocaleTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $before = [];

    protected function setUp(): void
    {
        $this->before = ['env' => $_ENV, 'session' => $_SESSION ?? null];
        unset($_ENV['APP_LOCALE'], $_ENV['APP_LOCALES']);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_ENV = $this->before['env'];
        $_SESSION = $this->before['session'] ?? [];
    }

    #[Test]
    public function ohne_konfiguration_nur_deutsch(): void
    {
        $this->assertSame('de', Locale::default());
        $this->assertSame(['de'], Locale::available());
        $this->assertSame('de', Locale::current());
    }

    #[Test]
    public function unbekannte_tags_fallen_weg(): void
    {
        $_ENV['APP_LOCALE'] = 'fr';
        $_ENV['APP_LOCALES'] = ' de , en-GB,fr,"><script>, en-GB';

        $this->assertSame('de', Locale::default());
        $this->assertSame(['de', 'en-GB'], Locale::available());
    }

    #[Test]
    public function session_gilt_nur_wenn_freigeschaltet(): void
    {
        $_ENV['APP_LOCALES'] = 'de,en-GB';
        $_SESSION['locale'] = 'en-GB';
        $this->assertSame('en-GB', Locale::current());

        $_ENV['APP_LOCALES'] = 'de';
        $this->assertSame('de', Locale::current());

        $_SESSION['locale'] = '"><x';
        $this->assertSame('de', Locale::htmlLang());
    }

    #[Test]
    public function englisch_kommt_aus_en_json_mit_deutsch_als_rueckfall(): void
    {
        $this->assertSame('This language is not enabled.', Locale::translator('en-GB')->t('ignis.profile.locale_unknown'));
        $this->assertSame('Diese Sprache ist nicht freigeschaltet.', Locale::translator('de')->t('ignis.profile.locale_unknown'));
        $this->assertSame('ignis.gibt.es_nicht', Locale::translator('en-GB')->t('ignis.gibt.es_nicht'));
    }
}
