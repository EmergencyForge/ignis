<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * NotificationPolicy — wer darf was mit Benachrichtigungen.
 *
 * Benachrichtigungen sind streng user-eigen: jeder eingeloggte User sieht
 * NUR seine eigenen, und der NotificationManager filtert ohnehin per
 * userId. Es gibt keine Admin-Sichtbarkeit auf fremde Benachrichtigungen.
 *
 * Daher reichen die Permission-Checks "ist eingeloggt" aus — die Resource-
 * spezifische Filterung passiert auf Query-Ebene im Manager.
 */
class NotificationPolicy
{
    public static function viewAny(mixed $context = null): bool
    {
        return isset($_SESSION['userid']);
    }

    public static function markRead(mixed $context = null): bool
    {
        return isset($_SESSION['userid']);
    }

    public static function delete(mixed $context = null): bool
    {
        return isset($_SESSION['userid']);
    }
}
