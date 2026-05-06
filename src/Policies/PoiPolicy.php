<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;

/**
 * PoiPolicy — Permissions für die POI-Verwaltung (Krankenhäuser, Ziele,
 * Departments, Access-Codes).
 *
 *   view()    — POI-Listen / Departments / Access-Codes ansehen
 *   manage()  — POIs anlegen/ändern/löschen, Departments + Codes pflegen
 *
 * Das `view`-Recht reicht für Read-only-Zugriff (z. B. Krankenhaus-
 * Schnittstelle, Voranmeldungs-Formulare). `manage` ist nur für die
 * Settings-/Admin-UI nötig.
 */
class PoiPolicy
{
    public static function view(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'pois.view']);
    }

    public static function manage(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'pois.manage']);
    }
}
