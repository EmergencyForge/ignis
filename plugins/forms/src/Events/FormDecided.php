<?php

declare(strict_types=1);

namespace Plugin\Forms\Events;

use App\Events\Event;
use Plugin\Forms\Models\Form;

/**
 * Ein Antrag wurde bearbeitet: Status, Bearbeiter und Bemerkung sind
 * gespeichert. Gefeuert aus FormsController::decide().
 *
 * Listener:
 *   - Kalender: spiegelt einen genehmigten Abwesenheitsantrag als Termin,
 *     sonst entfernt er ihn wieder.
 */
final class FormDecided extends Event
{
    public function __construct(
        public readonly Form $form,
        public readonly int $status,
    ) {}
}
