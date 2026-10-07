<?php

declare(strict_types=1);

namespace Plugin\Calendar\Listeners;

use Plugin\Calendar\AbsenceSyncService;
use Plugin\Forms\Events\FormDeleted;

/**
 * Entfernt den gespiegelten Abwesenheitstermin eines gelöschten Antrags.
 */
final class RemoveAbsenceOnFormDeleted
{
    public function handle(FormDeleted $event): void
    {
        AbsenceSyncService::removeForAntrag($event->formId);
    }
}
