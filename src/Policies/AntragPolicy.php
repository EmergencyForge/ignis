<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;
use App\Models\Antrag;

/**
 * AntragPolicy — Single Source of Truth für "wer darf was mit Anträgen".
 *
 *   viewAny() — Admin-Übersicht aller Anträge ansehen
 *   view()    — Einzelantrag ansehen (eigene IMMER, fremde nur mit application.view)
 *   create()  — Neuen Antrag stellen (jeder eingeloggte User)
 *   decide()  — Status setzen / Antrag bearbeiten (application.edit)
 *
 * Aufruf bevorzugt über den Gate:
 *
 *     Gate::allows('antrag.view', $antrag)
 *     Gate::allows('antrag.decide')
 */
class AntragPolicy
{
    /**
     * Darf der Aktor die Admin-Übersicht aller Anträge sehen?
     */
    public static function viewAny(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'application.edit']);
    }

    /**
     * Darf der Aktor einen einzelnen Antrag ansehen?
     *
     * Logik:
     *   - Mit `application.view` Permission: jeden Antrag
     *   - Sonst: nur eigene Anträge (matched über Discord-Tag)
     *
     * Wenn `$target` null ist (z.B. Permission-Check vor Load), wird auf
     * die globale Permission geprüft.
     */
    public static function view(?Antrag $target = null): bool
    {
        if (Permissions::check(['admin', 'application.view'])) {
            return true;
        }
        if ($target === null) {
            return false;
        }
        $own = $_SESSION['discordtag'] ?? null;
        return $own !== null && $own !== '' && $target->discordid === $own;
    }

    /**
     * Darf der Aktor einen neuen Antrag stellen?
     * Aktuell jeder eingeloggte User — der Login-Check erfolgt im Controller.
     */
    public static function create(mixed $context = null): bool
    {
        return isset($_SESSION['userid']);
    }

    /**
     * Darf der Aktor einen Antrag bearbeiten / Status setzen?
     */
    public static function decide(?Antrag $target = null): bool
    {
        return Permissions::check(['admin', 'application.edit']);
    }
}
