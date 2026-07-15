<?php

declare(strict_types=1);

namespace Plugin\ManvBoard\Policies;

use App\Auth\Permissions;

/**
 * MciPolicy — wer darf was im MANV-Modul.
 *
 * Alle Endpoints teilen den Permission-Check `['admin', 'mci.manage']`.
 * In einer Policy gebündelt, damit eine spätere Differenzierung (z.B.
 * read-only für Beobachter) an einer Stelle passiert.
 */
class MciPolicy
{
    public static function viewList(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'mci.manage']);
    }

    public static function view(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'mci.manage']);
    }

    public static function create(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'mci.manage']);
    }

    public static function update(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'mci.manage']);
    }

    public static function delete(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'mci.manage']);
    }
}
