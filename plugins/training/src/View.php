<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\CourseStatus;
use EmergencyForge\Training\ParticipationStatus;
use EmergencyForge\Training\Progress\PageCoverage;
use EmergencyForge\Training\UnitKind;
use Plugin\Training\Models\Attendance;
use Plugin\Training\Models\Session;
use Plugin\Training\Models\Unit;

/** Beschriftungen und Chip-Farben, die mehrere Ansichten teilen. */
final class View
{
    public static function courseChip(CourseStatus $status): string
    {
        return match ($status) {
            CourseStatus::Planned        => 'ignis-chip--secondary',
            CourseStatus::EnrollmentOpen => 'ignis-chip--info',
            CourseStatus::Running        => 'ignis-chip--info',
            CourseStatus::ExamPhase      => 'ignis-chip--warn',
            CourseStatus::Completed      => 'ignis-chip--ok',
            CourseStatus::Cancelled      => 'ignis-chip--danger',
        };
    }

    public static function participationChip(ParticipationStatus $status): string
    {
        return match ($status) {
            ParticipationStatus::Enrolled, ParticipationStatus::InExam => 'ignis-chip--warn',
            ParticipationStatus::Admitted, ParticipationStatus::InTraining => 'ignis-chip--info',
            ParticipationStatus::Passed  => 'ignis-chip--ok',
            ParticipationStatus::Failed, ParticipationStatus::Rejected => 'ignis-chip--danger',
            default                      => 'ignis-chip--secondary',
        };
    }

    /**
     * „PDF · Seite 12 von 40 gesehen“, „Termin · Mo 17.11., 18:00 Uhr · anwesend“
     *
     * @param array{state: string, pages: ?array<string, mixed>, attendance: ?string}|null $state
     */
    public static function unitMeta(Unit $unit, ?array $state, ?Session $session): string
    {
        $parts = [];
        switch ($unit->type()) {
            case UnitKind::File:
                $file = $unit->file;
                if ($file === null) {
                    return 'Datei fehlt';
                }
                $parts[] = $file->label();
                $pages   = $state['pages'] ?? null;
                if ($file->isPdf() && is_array($pages) && (int) ($pages['total'] ?? 0) > 0) {
                    $parts[] = ($state['state'] ?? '') === 'done'
                        ? $pages['total'] . ' Seiten'
                        : sprintf('Seite %d von %d gesehen', PageCoverage::fromRanges(is_array($pages['seen'] ?? null) ? $pages['seen'] : [])->seen((int) $pages['total']), (int) $pages['total']);
                } elseif (!$file->isPdf() && !$file->isImage()) {
                    $parts[] = 'zum Herunterladen, ' . $file->sizeLabel();
                }
                break;
            case UnitKind::Link:
                $parts[] = 'Link';
                $parts[] = 'öffnet in neuem Tab';
                break;
            case UnitKind::Session:
                $parts[] = 'Termin';
                $parts[] = $session !== null ? $session->when() . ' Uhr' : 'noch nicht geplant';
                if (($state['attendance'] ?? null) !== null) {
                    $parts[] = mb_strtolower(Attendance::STATES[$state['attendance']] ?? '');
                }
                break;
            default:
                $parts[] = $unit->type()->label();
        }

        return implode(' · ', array_filter($parts));
    }
}
