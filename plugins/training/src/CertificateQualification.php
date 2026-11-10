<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Events\EventDispatcher;
use App\Events\PersonnelSaved;
use App\Personnel\PersonnelQualifications;
use EmergencyForge\Training\Completion\CompletionStep;
use EmergencyForge\Training\Completion\StepResult;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Training\Completion\CompletionTable;
use Plugin\Training\Models\Participation;

/**
 * Die Qualifikation, die der Abschluss zu einer Urkunde eingetragen hat,
 * damit der Widerruf der Urkunde sie mit zurücknehmen kann. Der Kern fragt
 * hier nur nach, wenn das Plugin da ist.
 */
final class CertificateQualification
{
    private function __construct(
        public readonly int $personnelId,
        public readonly string $area,
        public readonly int $previous,
        public readonly string $name,
    ) {
    }

    /**
     * Null, wenn die Urkunde aus keinem Abschluss stammt, der Abschluss keine
     * Stufe gesetzt hat oder die Person inzwischen eine andere Stufe hat.
     */
    public static function forDocument(int $documentId): ?self
    {
        $participationId = (new CompletionTable())->participationFor($documentId);
        $completion      = $participationId !== null ? (new CompletionTable())->find($participationId) : null;
        $p               = $participationId !== null ? Participation::query()->with('course.type')->find($participationId) : null;
        $grant           = Qualifications::grantable($p?->course->type->pass_qualification);
        if ($completion === null || $p === null || $p->personnel_id === null || $grant === null
            || $grant[0] === PersonnelQualifications::AREA_DEPARTMENT
            || $completion->result(CompletionStep::Qualification)?->state !== StepResult::DONE) {
            return null;
        }
        [$area, $value] = $grant;
        $column = $area === PersonnelQualifications::AREA_RD ? 'qualird' : 'qualifw2';
        if ((int) Capsule::table('intra_mitarbeiter')->where('id', $p->personnel_id)->value($column) !== (int) $value) {
            return null;
        }
        // Vor der Ausbildungsstufe zu Lehrgangsbeginn, sonst vor dem Eintrag beim Bestehen.
        $start    = $p->start_qualification_before !== null ? (int) explode(':', $p->start_qualification_before, 2)[1] : null;
        $previous = $start ?? $completion->value(CompletionStep::Qualification, 'before');
        if (!is_int($previous)) {
            return null;
        }

        return new self($p->personnel_id, $area, $previous, (string) Qualifications::label($p->course->type->pass_qualification));
    }

    public function withdraw(string $editor): void
    {
        if ((new PersonnelQualifications())->set($this->personnelId, $this->area, $this->previous, $editor)) {
            app(EventDispatcher::class)->fire(new PersonnelSaved($this->personnelId));
        }
    }
}
