<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Policies;

use App\Auth\Permissions;

/**
 * KnowledgebasePolicy — Permissions fürs Wissens-Datenbank-Modul.
 *
 *   view()  — Inhalte ansehen (read-only)
 *   edit()  — Kategorien/Tags/Einträge anlegen, ändern, löschen
 *
 * Das View-Recht wird bewusst NICHT per Permission gegated: die
 * Knowledgebase kann via `KB_PUBLIC_ACCESS`-Config-Flag für alle
 * eingeloggten User oder sogar anonym (je nach Middleware-Config)
 * freigeschaltet sein. Das Route-Level-Middleware regelt das —
 * die Policy fokussiert sich auf Schreibrechte.
 */
class KnowledgebasePolicy
{
    public static function view(mixed $context = null): bool
    {
        // Read-Access ist Route-Level gated (AuthMiddleware + KB_PUBLIC_ACCESS)
        return true;
    }

    public static function edit(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'kb.edit']);
    }
}
