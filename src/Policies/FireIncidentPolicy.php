<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;
use App\Models\FireIncident;

/**
 * FireIncidentPolicy — wer darf was im einsatz/-Modul (Feuerwehr-Einsätze).
 *
 * Multi-Context-Auth (analog zu fahrtenbuch):
 *
 *   - **Admin** mit `fire.incident.qm` Permission: Vollzugriff (QM-Übersicht,
 *     Status setzen, finalisieren, archivieren)
 *   - **FireTab-Session**: User der via einsatz/login-fahrzeug.php auf einem
 *     Fahrzeug eingeloggt ist (`$_SESSION['einsatz_vehicle_id']`). Darf
 *     Einsätze für sein Fahrzeug erstellen, ansehen, bearbeiten — aber nicht
 *     finalisieren oder QM-Status setzen.
 *
 * Optional zusätzlich: `FIRE_INCIDENT_REQUIRE_USER_AUTH` ConfigManager-Wert.
 * Wenn aktiv, muss vor dem FireTab-Login auch ein System-User-Login da sein.
 */
class FireIncidentPolicy
{
    /**
     * Kann der aktuelle Aktor das Einsatz-Modul überhaupt aufrufen?
     * Pre-Login-Check: ggf. wird zusätzlich ein User-Login verlangt.
     */
    public static function accessModule(mixed $context = null): bool
    {
        // Wenn FIRE_INCIDENT_REQUIRE_USER_AUTH aktiv ist, MUSS ein User-Login da sein
        if (defined('FIRE_INCIDENT_REQUIRE_USER_AUTH') && FIRE_INCIDENT_REQUIRE_USER_AUTH === true) {
            return isset($_SESSION['userid']);
        }
        return true;
    }

    /**
     * Hat der Aktor eine FireTab-Session (Fahrzeug-Login)?
     * Voraussetzung für list/view/create/update.
     */
    public static function hasFireTabSession(mixed $context = null): bool
    {
        return isset($_SESSION['einsatz_vehicle_id'])
            && isset($_SESSION['einsatz_operator_id']);
    }

    /**
     * Darf der Aktor die Einsatzliste (eigenes Fahrzeug) sehen?
     * = Module-Access + FireTab-Session.
     */
    public static function viewList(mixed $context = null): bool
    {
        return self::accessModule() && self::hasFireTabSession();
    }

    /**
     * Darf der Aktor einen einzelnen Einsatz ansehen?
     */
    public static function view(?FireIncident $incident = null): bool
    {
        return self::viewList();
    }

    /**
     * Darf der Aktor einen neuen Einsatz erstellen?
     */
    public static function create(mixed $context = null): bool
    {
        return self::viewList();
    }

    /**
     * Darf der Aktor einen Einsatz bearbeiten (Stammdaten, Lagemeldungen, etc.)?
     * Standard-Regel: jeder mit FireTab-Session, solange der Einsatz nicht
     * finalisiert ist. Admin mit fire.incident.qm darf auch finalisierte
     * editieren.
     */
    public static function update(?FireIncident $incident = null): bool
    {
        if (!self::accessModule()) {
            return false;
        }

        $isAdmin = isset($_SESSION['userid']) && Permissions::check(['admin', 'fire.incident.qm']);

        if ($incident !== null && $incident->finalized && !$isAdmin) {
            return false;
        }

        return $isAdmin || self::hasFireTabSession();
    }

    /**
     * QM-Funktionen (finalisieren, Status setzen, archivieren).
     * Nur Admin mit fire.incident.qm.
     */
    public static function manageQm(?FireIncident $incident = null): bool
    {
        return isset($_SESSION['userid'])
            && Permissions::check(['admin', 'fire.incident.qm']);
    }

    /**
     * Admin-Liste aller Einsätze (für QM-Übersicht).
     */
    public static function viewAdminList(mixed $context = null): bool
    {
        return self::manageQm();
    }
}
