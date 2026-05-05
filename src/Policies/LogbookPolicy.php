<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;
use App\Models\Fahrt;

/**
 * LogbookPolicy — wer darf was mit Fahrtenbuch-Einträgen.
 *
 * Multi-Context-Auth:
 *   - Admin: Permissions::check(['admin', 'logbook.view']) für Liste,
 *            Permissions::check(['admin', 'logbook.manage']) für Create/Delete
 *   - Eigene Einträge (created_by == $userId): editierbar
 *   - eNOTF-Session (set $_SESSION['fahrername']): kann eigene 'enotf'-Einträge editieren
 *   - FireTab-Session (set $_SESSION['einsatz_vehicle_id']): kann eigene 'firetab'-Einträge editieren
 *
 * Die Liste in `index()` ist nur Admin.
 * Create darf jeder mit gültigem Session-Context (Admin/eNOTF/FireTab).
 */
class LogbookPolicy
{
    public static function viewList(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'logbook.view']);
    }

    /**
     * Darf der Aktor einen Eintrag erstellen?
     * Akzeptiert alle drei Session-Kontexte (admin, enotf, firetab).
     */
    public static function create(mixed $context = null): bool
    {
        $isAdmin   = isset($_SESSION['userid']);
        $isEnotf   = isset($_SESSION['fahrername']) && isset($_SESSION['protfzg']);
        $isFiretab = isset($_SESSION['einsatz_vehicle_id']);
        return $isAdmin || $isEnotf || $isFiretab;
    }

    /**
     * Darf der Aktor einen bestimmten Eintrag bearbeiten?
     * Vier mögliche Wege:
     *   1. Admin mit fahrtenbuch.manage Permission
     *   2. Eigentümer (created_by == userId)
     *   3. eNOTF-Session UND Eintrag-Source ist 'enotf' UND Fahrer-Name passt
     *   4. FireTab-Session UND Eintrag-Source ist 'firetab' UND Operator-Name passt
     */
    public static function update(?Fahrt $entry = null): bool
    {
        if ($entry === null) {
            return self::create(); // Form zum Bearbeiten anzeigen ist erlaubt für alle Erstellungs-Berechtigten
        }

        // (1) Admin
        if (isset($_SESSION['userid']) && Permissions::check(['admin', 'logbook.manage'])) {
            return true;
        }

        // (2) Eigentümer
        $userId = (int) ($_SESSION['userid'] ?? 0);
        if ($userId > 0 && (int) $entry->created_by === $userId) {
            return true;
        }

        // (3) eNOTF-Session passt
        if (isset($_SESSION['fahrername']) && $entry->source === Fahrt::SOURCE_ENOTF
            && $entry->fahrer_name === ($_SESSION['fahrername'] ?? '')
        ) {
            return true;
        }

        // (4) FireTab-Session passt
        if (isset($_SESSION['einsatz_vehicle_id']) && $entry->source === Fahrt::SOURCE_FIRETAB
            && $entry->fahrer_name === ($_SESSION['einsatz_operator_name'] ?? '')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Nur Admins mit fahrtenbuch.manage dürfen löschen.
     * eNOTF/FireTab-Kontexte dürfen NICHT löschen (auch eigene nicht).
     */
    public static function delete(?Fahrt $entry = null): bool
    {
        return isset($_SESSION['userid']) && Permissions::check(['admin', 'logbook.manage']);
    }
}
