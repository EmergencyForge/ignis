<?php

declare(strict_types=1);

namespace App\Calendar\Listeners;

use App\Calendar\AbsenceSyncService;
use Plugin\Forms\Events\FormDecided;
use Plugin\Forms\Models\Form;

/**
 * Spiegelt einen genehmigten Abwesenheitsantrag als Termin im Kalender und
 * nimmt ihn bei jedem anderen Status wieder heraus. Welche Antragstypen als
 * Abwesenheit gelten, entscheidet AbsenceSyncService::isAbsenceAntrag().
 */
final class SyncAbsenceOnFormDecided
{
    public function handle(FormDecided $event): void
    {
        $antrag = $event->form;
        if (!AbsenceSyncService::isAbsenceAntrag($antrag)) {
            return;
        }

        if ($event->status === Form::STATUS_ACCEPTED) {
            AbsenceSyncService::syncFromAntrag(
                $antrag,
                (string) ($antrag->getFieldValue('von_datum') ?? ''),
                (string) ($antrag->getFieldValue('bis_datum') ?? ''),
            );
            return;
        }

        AbsenceSyncService::removeForAntrag((int) $antrag->id);
    }
}
