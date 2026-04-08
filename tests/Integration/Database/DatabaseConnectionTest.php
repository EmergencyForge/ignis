<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

class DatabaseConnectionTest extends IntegrationTestCase
{
    #[Test]
    public function pdo_is_resolvable_from_container(): void
    {
        $this->assertInstanceOf(PDO::class, $this->pdo);
    }

    #[Test]
    public function pdo_can_query_database(): void
    {
        $version = $this->pdo->query('SELECT VERSION()')->fetchColumn();
        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', (string) $version);
    }

    #[Test]
    public function pdo_uses_exception_error_mode(): void
    {
        $this->assertSame(
            PDO::ERRMODE_EXCEPTION,
            $this->pdo->getAttribute(PDO::ATTR_ERRMODE)
        );
    }
}
