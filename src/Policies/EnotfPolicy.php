<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;

/**
 * EnotfPolicy — Authorization für das eNOTF-Modul.
 *
 * eNOTF hat eine eigene Auth-Schicht (Crew-Login auf Fahrzeug), die parallel
 * zur normalen User-Auth läuft. Drei Schichten:
 *
 * 1. **User-Auth-Gate** (`ENOTF_REQUIRE_USER_AUTH`): Wenn aktiv, MUSS ein
 *    User-Login (`$_SESSION['userid']`) vorhanden sein, BEVOR die eNOTF-Pages
 *    zugänglich sind. Klinikzugriff via Code (`klinik_access_*`) bypassed das.
 *
 * 2. **PIN-Lockscreen** (`ENOTF_USE_PIN`): Wenn aktiv, muss eine PIN eingegeben
 *    werden. Admins/edivi.view-User sind exempt. 5-Minuten-Timeout.
 *
 * 3. **Crew-Login** (`fahrername` + `protfzg` in Session): Voraussetzung für
 *    overview/protokoll/etc. Wird via login.php gesetzt, via loggedout.php
 *    gelöscht.
 *
 * Char-Lock (`ENOTF_CHAR_LOCK`) und Job-Filter (`ENOTF_JOB_FILTER`) sind
 * Verhaltens-Toggles, die in der LoginForm/Login-Action ausgewertet werden,
 * nicht hier.
 */
class EnotfPolicy
{
    public const KLINIK_ACCESS_TTL = 7200; // 2 Stunden
    public const PIN_TIMEOUT       = 300;  // 5 Minuten

    /**
     * User-Auth-Gate: muss ein User-Login vorhanden sein, um eNOTF zu nutzen?
     * Steuert die Frage "darf jemand das eNOTF-Modul überhaupt aufrufen".
     */
    public static function requiresUserAuth(mixed $context = null): bool
    {
        return defined('ENOTF_REQUIRE_USER_AUTH') && ENOTF_REQUIRE_USER_AUTH === true;
    }

    /**
     * Hat der User das User-Auth-Gate passiert?
     * True wenn entweder eingeloggter User oder gültiger Klinik-Access.
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
     * Klinikzugriff aktiv? (über externen Klinik-Code-Login, 2h gültig)
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
     * Ist der aktuelle User vom PIN-Lockscreen ausgenommen?
     * Admins und edivi.view-User können den PIN-Schritt überspringen.
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
}
