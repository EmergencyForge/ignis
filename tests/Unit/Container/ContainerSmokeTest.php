<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use App\Logging\Logger;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ContainerSmokeTest extends TestCase
{
    #[Test]
    public function container_is_available_in_globals(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $GLOBALS['app_container']);
    }

    #[Test]
    public function app_helper_returns_container_when_called_without_arg(): void
    {
        $this->assertSame($this->container, app());
    }

    #[Test]
    public function app_helper_resolves_logger_interface(): void
    {
        $logger = app(LoggerInterface::class);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    #[Test]
    public function logger_facade_and_container_resolve_to_same_instance(): void
    {
        $fromContainer = $this->resolve(LoggerInterface::class);
        $fromFacade    = Logger::getInstance();
        $this->assertSame($fromContainer, $fromFacade);
    }

    #[Test]
    public function app_logger_class_resolves_to_psr_logger(): void
    {
        $appLogger = $this->resolve(Logger::class);
        $psrLogger = $this->resolve(LoggerInterface::class);
        $this->assertSame($psrLogger, $appLogger);
    }
}
