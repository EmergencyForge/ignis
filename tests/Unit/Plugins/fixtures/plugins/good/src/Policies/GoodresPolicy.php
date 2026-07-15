<?php

declare(strict_types=1);

namespace GoodPluginFixture\Policies;

/**
 * Fixture-Policy für die Gate-Registrierung über das Plugin-Manifest.
 */
final class GoodresPolicy
{
    public static function view(mixed $resource = null): bool
    {
        return true;
    }

    public static function edit(mixed $resource = null): bool
    {
        return false;
    }
}
