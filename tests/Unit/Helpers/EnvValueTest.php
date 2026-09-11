<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * env_value() ist die einzige Stelle, an der die DB-Zugangsdaten gesucht
 * werden — Web-Bootstrap, Eloquent-Capsule, Console und tools/db-migrate.php
 * hängen alle daran. Als der Fallback nur in assets/config/database.php
 * existierte, lief die Migration auf einer SetEnv-Maschine durch, während
 * Eloquent mit leerem Datenbanknamen startete. Deshalb hier festgehalten:
 * alle drei Quellen zählen, und zwar in dieser Reihenfolge.
 */
final class EnvValueTest extends TestCase
{
    private const KEY = 'IGNIS_ENV_VALUE_TEST';

    protected function tearDown(): void
    {
        unset($_ENV[self::KEY], $_SERVER[self::KEY]);
        putenv(self::KEY);

        parent::tearDown();
    }

    #[Test]
    public function nicht_gesetzt_ergibt_null(): void
    {
        $this->assertNull(env_value(self::KEY));
    }

    #[Test]
    public function liest_aus_env(): void
    {
        $_ENV[self::KEY] = 'aus-env';

        $this->assertSame('aus-env', env_value(self::KEY));
    }

    /**
     * Der Fall, der ignis auf einer Apache-Instanz mit SetEnv umgebracht hat:
     * ohne E in variables_order bleibt $_ENV leer, der Wert steht nur hier.
     */
    #[Test]
    public function liest_aus_server_wenn_env_leer_bleibt(): void
    {
        $_SERVER[self::KEY] = 'aus-server';

        $this->assertSame('aus-server', env_value(self::KEY));
    }

    #[Test]
    public function liest_aus_getenv_als_letzte_quelle(): void
    {
        putenv(self::KEY . '=aus-getenv');

        $this->assertSame('aus-getenv', env_value(self::KEY));
    }

    #[Test]
    public function env_hat_vorrang_vor_den_spaeteren_quellen(): void
    {
        $_ENV[self::KEY]    = 'aus-env';
        $_SERVER[self::KEY] = 'aus-server';
        putenv(self::KEY . '=aus-getenv');

        $this->assertSame('aus-env', env_value(self::KEY));
    }

    #[Test]
    public function server_hat_vorrang_vor_getenv(): void
    {
        $_SERVER[self::KEY] = 'aus-server';
        putenv(self::KEY . '=aus-getenv');

        $this->assertSame('aus-server', env_value(self::KEY));
    }

    /**
     * Ein leeres DB_PASS ist eine gültige Konfiguration. Wer hier auf empty()
     * prüft statt auf null, meldet auf so einer Instanz "nicht konfiguriert".
     */
    #[Test]
    public function leerer_string_ist_ein_wert_und_kein_fehlender_schluessel(): void
    {
        $_ENV[self::KEY] = '';

        $this->assertSame('', env_value(self::KEY));
        $this->assertNotNull(env_value(self::KEY));
    }
}
