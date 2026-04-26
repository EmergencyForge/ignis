<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;

/**
 * SystemPolicy — generischer Admin-Gate für System-/Settings-Bereiche
 * ohne eigene Resource-Semantik (Logs, System-Konfig, Federation,
 * Cron-Jobs, Antrag-Settings etc.).
 *
 * Resource-spezifische Settings-Bereiche (POIs, Vehicles, Personnel,
 * Documents, Knowledgebase) bleiben weiterhin in ihren eigenen
 * Policies — die haben Per-Action-Semantik (view vs. manage).
 */
class SystemPolicy
{
    /**
     * Volle Admin-Berechtigung. Mappt 1:1 auf das `admin`-Permission-
     * Flag, das nur Full-Admin-Rollen oder Rollen mit explizitem
     * `admin`-Permission tragen.
     */
    public static function admin(mixed $context = null): bool
    {
        return Permissions::check('admin');
    }
}
