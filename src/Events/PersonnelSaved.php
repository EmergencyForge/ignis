<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Ein Mitarbeiter wurde angelegt oder seine Stammdaten gespeichert
 * (Name, Dienstgrad, Qualifikationen …). Gefeuert von den Schreibpfaden
 * in PersonnelController::store()/update() und
 * Api\PersonnelController::updateProfile().
 *
 * Listener: das Mail-Plugin legt das Postfach an, legt es beim
 * Archiv-Dienstgrad still und zieht den Anzeigenamen nach.
 */
final class PersonnelSaved extends Event
{
    public function __construct(
        public readonly int $personnelId,
    ) {}
}
