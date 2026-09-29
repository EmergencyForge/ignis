<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Ein Mitarbeiter wurde gelöscht (PersonnelController::destroy(), hart,
 * die Zeile ist weg). Listener dürfen ihn deshalb nicht mehr laden, nur
 * mit der ID aufräumen.
 *
 * Listener: das Mail-Plugin legt das Postfach still.
 */
final class PersonnelDeleted extends Event
{
    public function __construct(
        public readonly int $personnelId,
    ) {}
}
