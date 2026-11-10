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
    /**
     * @param int|string  $granted  Stufen-ID (RD, FW) oder Fachdienst-Nummer (FD)
     * @param int|null    $previous Stufe vor dem Lehrgang, bei FD ohne Belang
     * @param string|null $blocked  warum es sich nicht zurücknehmen lässt
     */
    private function __construct(
        public readonly int $personnelId,
        public readonly string $area,
        public readonly int|string $granted,
        public readonly ?int $previous,
        public readonly string $name,
        public readonly ?string $blocked,
    ) {
    }

    /** Null, wenn die Urkunde aus keinem Abschluss stammt, der eine Qualifikation eingetragen hat. */
    public static function forDocument(int $documentId): ?self
    {
        $participationId = (new CompletionTable())->participationFor($documentId);
        $completion      = $participationId !== null ? (new CompletionTable())->find($participationId) : null;
        $p               = $participationId !== null ? Participation::query()->with('course.type')->find($participationId) : null;
        $key             = $p?->course->type->pass_qualification;
        $grant           = Qualifications::grantable($key);
        if ($completion === null || $p === null || $p->personnel_id === null || $grant === null
            || $completion->result(CompletionStep::Qualification)?->state !== StepResult::DONE) {
            return null;
        }
        [$area, $value] = $grant;
        $name = (string) Qualifications::label($key);

        if ($area === PersonnelQualifications::AREA_DEPARTMENT) {
            // Den Fachdienst hat der Abschluss nur eingetragen, wenn er vorher fehlte.
            return in_array((string) $value, self::departments($p->personnel_id), true)
                ? new self($p->personnel_id, $area, (string) $value, null, $name, null)
                : null;
        }

        $column  = $area === PersonnelQualifications::AREA_RD ? 'qualird' : 'qualifw2';
        $current = (int) Capsule::table('intra_mitarbeiter')->where('id', $p->personnel_id)->value($column);
        // Vor der Ausbildungsstufe zu Lehrgangsbeginn, sonst vor dem Eintrag beim Bestehen.
        $start    = $p->start_qualification_before !== null ? (int) explode(':', $p->start_qualification_before, 2)[1] : null;
        $before   = $start ?? $completion->value(CompletionStep::Qualification, 'before');
        $previous = is_int($before) ? $before : null;
        $blocked  = match (true) {
            $current !== (int) $value => 'Die Person hat inzwischen eine andere Stufe, die bleibt.',
            $previous === null        => 'Die Stufe vor dem Lehrgang ist nicht festgehalten.',
            default                   => null,
        };

        return new self($p->personnel_id, $area, (int) $value, $previous, $name, $blocked);
    }

    public function withdraw(string $editor): void
    {
        $qualifications = new PersonnelQualifications();
        $changed        = match (true) {
            $this->blocked !== null => false,
            $this->area === PersonnelQualifications::AREA_DEPARTMENT => $qualifications->setDepartments(
                $this->personnelId,
                array_values(array_diff(self::departments($this->personnelId), [(string) $this->granted])),
                $editor,
            ),
            default => $qualifications->set($this->personnelId, $this->area, (int) $this->previous, $editor),
        };
        if ($changed) {
            app(EventDispatcher::class)->fire(new PersonnelSaved($this->personnelId));
        }
    }

    /** @return list<string> */
    private static function departments(int $personnelId): array
    {
        $list = json_decode((string) Capsule::table('intra_mitarbeiter')->where('id', $personnelId)->value('fachdienste'), true);

        return is_array($list) ? array_values(array_map('strval', $list)) : [];
    }
}
