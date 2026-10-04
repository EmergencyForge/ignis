<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * DB_PORT gilt für die Legacy-$pdo, den Container-Fallback und
 * tools/db-migrate.php genauso wie für Eloquent und Phinx. Vorher landeten
 * diese drei bei einer Datenbank auf einem anderen Port immer auf 3306.
 */
final class MysqlDsnTest extends TestCase
{
    private const KEYS = ['DB_HOST', 'DB_PORT', 'DB_NAME'];

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::KEYS as $key) {
            $this->saved[$key] = $_ENV[$key] ?? null;
        }
        $_ENV['DB_HOST'] = 'db.example';
        $_ENV['DB_NAME'] = 'ignis';
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        parent::tearDown();
    }

    #[Test]
    public function nimmt_den_port_aus_db_port(): void
    {
        $_ENV['DB_PORT'] = '3307';

        $this->assertSame('mysql:host=db.example;port=3307;dbname=ignis;charset=utf8mb4', mysql_dsn());
    }

    #[Test]
    public function ohne_db_port_gilt_3306(): void
    {
        unset($_ENV['DB_PORT']);
        $this->assertSame('mysql:host=db.example;port=3306;dbname=ignis;charset=utf8', mysql_dsn('utf8'));
    }

    /** Ein leeres DB_PORT= in der .env ist kein Port 0. */
    #[Test]
    public function leerer_oder_unsinniger_port_faellt_auf_3306_zurueck(): void
    {
        foreach (['', 'abc', '0', '-1'] as $value) {
            $_ENV['DB_PORT'] = $value;
            $this->assertStringContainsString(';port=3306;', mysql_dsn(), "DB_PORT='{$value}'");
        }
    }
}
