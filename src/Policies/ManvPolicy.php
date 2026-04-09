<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;

/**
 * ManvPolicy — wer darf was im MANV-Modul.
 *
 * Im Legacy-Code nutzen ALLE manv/*.php Files denselben Permission-Check
 * `['admin', 'manv.manage']`. Wir bündeln das in eine Policy, damit eine
 * spätere Differenzierung (z.B. read-only für Schaulustige) leichter wird.
 */
class ManvPolicy
{
    public static function viewList(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'manv.manage']);
    }

    public static function view(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'manv.manage']);
    }

    public static function create(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'manv.manage']);
    }

    public static function update(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'manv.manage']);
    }

    public static function delete(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'manv.manage']);
    }
}
