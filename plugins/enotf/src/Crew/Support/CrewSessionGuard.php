<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Support;

use App\Session\SessionManager;
use Plugin\Enotf\EnotfSession;

/**
 * CrewSessionGuard: gilt die Crew-Anmeldung dieser Browser-Session noch?
 *
 * Die PHP-Session merkt sich nur Token und Fahrzeug. Ob die Anmeldung
 * noch gilt, steht in intra_enotf_sessions: „alle abmelden“, eine neue
 * Besatzung, ein Defekt oder zu lange Pause beenden die Session dort,
 * und jede Anfrage jedes Tablets sieht das sofort, nicht erst beim
 * nächsten 10-Sekunden-Abgleich.
 *
 * check() räumt bei einer beendeten Anmeldung die Crew-Keys der
 * PHP-Session ab und liefert den Grund als Slug für die Abmelde-Seite
 * (?grund=…). Bei gültiger Anmeldung zieht es die Besatzung aus der DB
 * nach (der Beifahrer behält seine Anmeldung, wenn der Fahrer geht) und
 * vermerkt auf Wunsch die Nutzung.
 */
final class CrewSessionGuard
{
    /** Grund-Slugs der Abmelde-Seite je ended_reason */
    private const GRUND = [
        EnotfSession::ENDED_LOGOUT         => 'beendet',
        EnotfSession::ENDED_DELETED        => 'beendet',
        EnotfSession::ENDED_REPLACED       => 'abgeloest',
        EnotfSession::ENDED_EXPIRED        => 'abgelaufen',
        EnotfSession::ENDED_OUT_OF_SERVICE => 'ausser-dienst',
    ];

    /**
     * Prüft die Crew-Anmeldung gegen die DB.
     *
     * @param bool $touch true für echte Nutzung (Seite, Speichern), false
     *                    für Abgleiche, die eine Session nicht verlängern
     * @return string|null null, wenn keine Crew-Anmeldung besteht oder sie
     *                     gilt, sonst der Grund-Slug (Anmeldung ist dann weg)
     */
    public static function check(bool $touch = true): ?string
    {
        $token   = (string) ($_SESSION['enotf_session_token'] ?? '');
        $vehicle = (string) ($_SESSION['protfzg'] ?? '');
        if ($token === '' && $vehicle === '') {
            return null;
        }

        $service = new EnotfSession();
        $session = $token !== '' ? $service->findByToken($token) : null;

        if ($session === null || (string) $session['vehicle_identifier'] !== $vehicle) {
            return self::end('beendet');
        }

        if ((int) $session['active'] === 0) {
            return self::end(self::GRUND[(string) ($session['ended_reason'] ?? '')] ?? 'beendet');
        }

        if ($session['vehicle_active'] !== null && (int) $session['vehicle_active'] === 0) {
            $service->deactivateAllForVehicle($vehicle, EnotfSession::ENDED_OUT_OF_SERVICE);
            return self::end('ausser-dienst');
        }

        if (EnotfSession::isIdle($session)) {
            $service->end((int) $session['id'], EnotfSession::ENDED_EXPIRED);
            return self::end('abgelaufen');
        }

        if ($touch) {
            $service->touch($session);
        }

        $_SESSION['enotf_position'] = (string) $session['member_position'];
        SessionManager::updateEnotfCrew([
            'fahrername'      => $session['fahrername'],
            'fahrerquali'     => $session['fahrerquali'],
            'beifahrername'   => $session['beifahrername'],
            'beifahrerquali'  => $session['beifahrerquali'],
            'praktikantname'  => $session['praktikantname'],
            'praktikantquali' => $session['praktikantquali'],
        ]);

        return null;
    }

    /**
     * Text der Abmelde-Seite zu einem Grund-Slug, null für unbekannte.
     */
    public static function message(string $grund): ?string
    {
        return match ($grund) {
            'ausser-dienst' => 'Das Fahrzeug ist außer Dienst. Die Anmeldung darauf ist beendet.',
            'abgeloest'     => 'Auf dem Fahrzeug hat sich eine neue Besatzung angemeldet. Deine Anmeldung ist beendet.',
            'abgelaufen'    => 'Die Anmeldung war zu lange ungenutzt und ist abgelaufen.',
            'beendet'       => 'Die Anmeldung auf dem Fahrzeug wurde beendet.',
            default         => null,
        };
    }

    private static function end(string $grund): string
    {
        SessionManager::logoutEnotfCrew();

        return $grund;
    }
}
