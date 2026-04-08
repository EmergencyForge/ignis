<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;
use App\Models\User;

/**
 * UserPolicy — Single Source of Truth für "wer darf was mit System-Benutzern".
 *
 * Methoden geben true/false zurück. Die "wer ist der Aktor"-Information wird
 * (vorerst) aus $_SESSION gelesen — in Phase 4+ wird das durch echte
 * Constructor-Injection eines AuthContext ersetzt.
 *
 * Aufruf bevorzugt über den Gate:
 *
 *     Gate::allows('user.update', $targetUser)
 *
 * Welche Permission-Strings im Hintergrund geprüft werden, bleibt ein Detail
 * der Policy — Aufrufer müssen das nicht wissen.
 */
class UserPolicy
{
    /**
     * Darf der aktuelle Aktor die Benutzerliste ansehen?
     */
    public static function viewList(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'users.view']);
    }

    /**
     * Darf der Aktor einen bestimmten User in der Edit-Maske öffnen?
     * Permissions: admin oder users.edit. Bei Ziel-User zusätzlich Priority-Check.
     */
    public static function update(?User $target = null): bool
    {
        if (!Permissions::check(['admin', 'users.edit'])) {
            return false;
        }
        if ($target === null) {
            return true;
        }
        return self::canModify($target);
    }

    /**
     * Darf der Aktor einen User endgültig löschen?
     */
    public static function delete(?User $target = null): bool
    {
        if (!Permissions::check(['admin', 'users.delete'])) {
            return false;
        }
        if ($target === null) {
            return true;
        }
        return self::canModify($target);
    }

    /**
     * Darf der Aktor einen User aktivieren/deaktivieren?
     * Hat aktuell dieselben Regeln wie delete (users.delete Permission).
     */
    public static function toggleActive(?User $target = null): bool
    {
        return self::delete($target);
    }

    /**
     * Darf der Aktor den Audit-Log einsehen?
     */
    public static function viewAuditLog(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'audit.view']);
    }

    /**
     * Darf der Aktor Registrierungs-Codes / Einladungslinks erstellen?
     */
    public static function createRegistrationCode(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'users.create']);
    }

    /**
     * Interner Helper: ist der Aktor "stark genug" um den Ziel-User zu modifizieren?
     *
     *   - Selbst-Modifikation ist NICHT erlaubt
     *   - Ziel darf kein full_admin sein
     *   - Ziel-Rolle muss eine niedrigere Priorität haben (=höhere Zahl)
     */
    private static function canModify(User $target): bool
    {
        if ((int) $target->id === (int) ($_SESSION['userid'] ?? 0)) {
            return false;
        }
        if ($target->full_admin) {
            return false;
        }
        $targetPriority = (int) ($target->userRole?->priority ?? 0);
        $ownPriority    = (int) ($_SESSION['role_priority'] ?? 0);
        return $targetPriority > $ownPriority;
    }
}
