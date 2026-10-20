<?php
/**
 * eNOTF Session Refresh
 * Gleicht die Crew-Anmeldung der PHP-Session mit der Datenbank ab: gilt
 * sie noch, kommt die aktuelle Besatzung in die Session, sonst sind die
 * Crew-Keys danach weg. Dieselbe Prüfung wie auf den Crew-Routen
 * (CrewSessionGuard), hier für v1-Seiten ohne diese Middleware.
 *
 * Voraussetzung: Session muss gestartet sein.
 */
if (class_exists(\Plugin\Enotf\Crew\Support\CrewSessionGuard::class)) {
    \Plugin\Enotf\Crew\Support\CrewSessionGuard::check(touch: false);
}
