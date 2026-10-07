<?php

declare(strict_types=1);

namespace Plugin\Forms\Events;

use App\Events\Event;

/**
 * Ein Antrag wurde gelöscht, egal ob über den Controller, die Konsole oder
 * einen Test. Gefeuert aus dem `deleted`-Hook von Form.
 *
 * Listener:
 *   - Kalender: entfernt den gespiegelten Abwesenheitstermin.
 */
final class FormDeleted extends Event
{
    public function __construct(
        public readonly int $formId,
    ) {}
}
