<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Policies;

use App\Auth\Permissions;

/**
 * EnotfV2Policy — Authorization für eNOTF v2.
 *
 * Übernimmt die dreischichtige Auth-Logik der v1-EnotfPolicy 1:1 und
 * arbeitet bewusst auf DENSELBEN Session-Keys und Config-Konstanten —
 * EIN Crew-Login (und eine PIN-Verifikation) gilt damit gleichzeitig
 * für v1 und v2:
 *
 * 1. **User-Auth-Gate** (`ENOTF_REQUIRE_USER_AUTH`): Wenn aktiv, MUSS ein
 *    User-Login (`$_SESSION['userid']`) vorhanden sein. Klinikzugriff via
 *    Code (`$_SESSION['klinik_access_*']`, TTL 2h) bypassed das Gate.
 *
 * 2. **PIN-Lockscreen** (`ENOTF_USE_PIN`, `ENOTF_PIN`): 5-Minuten-
 *    Inaktivitäts-Timeout über `pin_verified`/`pin_last_activity`.
 *    Admins/edivi.view-User sind exempt. Durchgesetzt wird das über die
 *    geteilte PinLockscreenMiddleware (auf den v2-Routen mit dem eigenen
 *    Lockscreen /enotf-v2/lockscreen als Ziel — die PIN-Session ist
 *    dieselbe wie in v1).
 *
 * 3. **Crew-Login**: `$_SESSION['fahrername']` + `$_SESSION['protfzg']`,
 *    gesetzt via SessionManager::loginEnotfCrew().
 *
 * Die Logik ist eine eigenständige Kopie statt einer Ableitung von
 * Plugin\Enotf\Policies\EnotfPolicy — v2 soll auth-seitig nicht brechen,
 * wenn v1 intern umgebaut wird. Wer hier etwas ändert, muss die
 * Session-Keys/Konstanten synchron zu v1 halten.
 */
class EnotfV2Policy
{
    public const KLINIK_ACCESS_TTL = 7200; // 2 Stunden
    public const PIN_TIMEOUT       = 300;  // 5 Minuten

    /**
     * User-Auth-Gate: muss ein User-Login vorhanden sein, um eNOTF zu nutzen?
     */
    public static function requiresUserAuth(mixed $context = null): bool
    {
        return defined('ENOTF_REQUIRE_USER_AUTH') && ENOTF_REQUIRE_USER_AUTH === true;
    }

    /**
     * User-Auth-Gate passiert? True bei eingeloggtem User ODER gültigem
     * Klinik-Access (oder wenn das Gate deaktiviert ist).
     */
    public static function passedUserAuthGate(mixed $context = null): bool
    {
        if (!self::requiresUserAuth()) {
            return true;
        }

        $userAuth = isset($_SESSION['userid']) && !empty($_SESSION['userid']);
        return $userAuth || self::hasKlinikAccess();
    }

    /**
     * Klinikzugriff aktiv? (Einmal-Code-Login, 2h gültig)
     */
    public static function hasKlinikAccess(mixed $context = null): bool
    {
        if (!isset($_SESSION['klinik_access_enr'], $_SESSION['klinik_access_time'])) {
            return false;
        }

        return (time() - (int) $_SESSION['klinik_access_time']) < self::KLINIK_ACCESS_TTL;
    }

    /**
     * PIN-Feature aktiv?
     */
    public static function pinEnabled(mixed $context = null): bool
    {
        return defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true;
    }

    /**
     * Vom PIN-Lockscreen ausgenommen? (Admins / edivi.view)
     */
    public static function pinExempt(mixed $context = null): bool
    {
        if (!self::pinEnabled()) {
            return true;
        }
        return Permissions::check(['edivi.view']);
    }

    /**
     * PIN gültig erfasst und nicht abgelaufen?
     */
    public static function pinVerified(mixed $context = null): bool
    {
        if (!self::pinEnabled()) {
            return true;
        }

        if (self::pinExempt() || self::hasKlinikAccess()) {
            return true;
        }

        $verified = isset($_SESSION['pin_verified']) && $_SESSION['pin_verified'] === true;
        if (!$verified) {
            return false;
        }

        $lastActivity = $_SESSION['pin_last_activity'] ?? null;
        if ($lastActivity === null) {
            return false;
        }

        return (time() - (int) $lastActivity) <= self::PIN_TIMEOUT;
    }

    /**
     * PIN tatsächlich eingegeben und noch frisch?
     *
     * Anders als pinVerified() OHNE die Feature-/Exempt-Defaults: wenn
     * nie ein PIN erfasst wurde, ist das hier false — auch bei
     * deaktiviertem PIN-Feature oder Exempt-Usern. Für Checks gedacht,
     * die eine POSITIVE Geräte-Verifikation brauchen (z. B. die
     * Session-Verwaltung auf der Login-Seite), nicht fürs Lockscreen-Gate.
     */
    public static function pinEntered(mixed $context = null): bool
    {
        if (($_SESSION['pin_verified'] ?? null) !== true) {
            return false;
        }

        $lastActivity = $_SESSION['pin_last_activity'] ?? null;

        return $lastActivity !== null && (time() - (int) $lastActivity) <= self::PIN_TIMEOUT;
    }

    /**
     * Hat der aktuelle Aktor eine eNOTF-Crew-Session (Fahrzeug-Login)?
     */
    public static function hasCrewSession(mixed $context = null): bool
    {
        return isset($_SESSION['fahrername'], $_SESSION['protfzg'])
            && !empty($_SESSION['fahrername'])
            && !empty($_SESSION['protfzg']);
    }

    /**
     * Char-Lock aktiv? (Charname muss Login-Name matchen)
     */
    public static function charLockEnabled(mixed $context = null): bool
    {
        return defined('ENOTF_CHAR_LOCK') && ENOTF_CHAR_LOCK === true;
    }

    /**
     * Job-Filter aktiv? (Fahrzeugauswahl filtert nach char_job)
     */
    public static function jobFilterEnabled(mixed $context = null): bool
    {
        return defined('ENOTF_JOB_FILTER') && ENOTF_JOB_FILTER === true;
    }

    /**
     * Bulk-Delete leerer Protokolle (Admin-Operation).
     */
    public static function bulkDelete(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'edivi.edit']);
    }

    /**
     * Admin-View des Protokolllistings (lesend).
     */
    public static function viewAdminList(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'edivi.view']);
    }

    /**
     * Bearbeiten von Protokollen im Admin-Kontext.
     */
    public static function editProtocol(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'edivi.edit']);
    }

    /**
     * Generisches „kann das Modul ansehen" für eingeloggte User
     * (Read-only-UIs, globale Suche).
     */
    public static function viewModule(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'enotf.view', 'edivi.view']);
    }
}
