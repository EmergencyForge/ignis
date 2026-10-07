<?php

declare(strict_types=1);

namespace App\Calendar\Listeners;

use App\Calendar\AbsenceSyncService;
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
