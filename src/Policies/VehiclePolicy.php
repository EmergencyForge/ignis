<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;

/**
 * VehiclePolicy — Permissions rund um Fahrzeuge + Defekt-Verwaltung.
 *
 *   view()           — Fahrzeug-Listen / Defekt-Übersicht ansehen
 *   manage()         — Fahrzeuge anlegen/ändern, Defekte zuweisen/lösen
 *   createDefect()   — Neuen Defekt melden (auch für eNOTF-Crews)
 *   deleteDefect()   — Defekt hart löschen (admin-only)
 *   manageImport()   — EMD-Fahrzeug-Import verwalten
 *
 * `createDefect()` ist bewusst mit `vehicles.view` als OR-Fallback
 * gebaut, damit jeder der Fahrzeuge sieht auch einen Defekt melden
 * kann. Der eNOTF-User-Sonderfall (Session ohne `userid`, aber mit
 * `fahrername`) wird NICHT hier behandelt — der Controller prüft
 * das separat und umgeht die Policy, weil die Policy auf ein User-
 * Login-Szenario ausgelegt ist.
 */
class VehiclePolicy
{
    public static function view(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'vehicles.view']);
    }

    public static function manage(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'vehicles.manage']);
    }

    public static function createDefect(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'vehicles.manage', 'vehicles.view']);
    }

    public static function deleteDefect(mixed $context = null): bool
    {
        return Permissions::check(['admin']);
    }

    public static function manageImport(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'vehicles.manage']);
    }
}
