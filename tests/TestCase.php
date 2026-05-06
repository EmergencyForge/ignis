<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Container\ContainerInterface;

/**
 * Base TestCase für Tests, die auf den DI-Container zugreifen wollen.
 *
 * Bestehende Tests, die direkt von PHPUnit\Framework\TestCase erben, sind
 * unbeeinträchtigt. Diese Klasse ist optional — nutze sie wenn du den
 * Container, app()-Helper oder Container-basiertes Setup brauchst.
 */
abstract class TestCase extends BaseTestCase
{
    protected ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $GLOBALS['app_container'];
    }

    /**
     * Resolve a service from the container.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    protected function resolve(string $abstract): object
    {
        return $this->container->get($abstract);
    }
}
