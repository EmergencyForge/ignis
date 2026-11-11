<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\CourseStatus;
use EmergencyForge\Training\ParticipationStatus;
use EmergencyForge\Training\Progress\PageCoverage;
use EmergencyForge\Training\Question\ChoiceSpec;
use EmergencyForge\Training\Question\MatchSpec;
use EmergencyForge\Training\Question\NumberSpec;
use EmergencyForge\Training\Question\OrderSpec;
use EmergencyForge\Training\Question\Question;
use EmergencyForge\Training\Question\ShortAnswerSpec;
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
                if ($file->viewsAsPdf() && is_array($pages) && (int) ($pages['total'] ?? 0) > 0) {
                    $parts[] = ($state['state'] ?? '') === 'done'
                        ? $pages['total'] . ' Seiten'
                        : sprintf('Seite %d von %d gesehen', PageCoverage::fromRanges(is_array($pages['seen'] ?? null) ? $pages['seen'] : [])->seen((int) $pages['total']), (int) $pages['total']);
                } elseif (!$file->viewsAsPdf() && !$file->isImage()) {
                    $parts[] = 'zum Herunterladen, ' . $file->sizeLabel();
                }
                break;
            case UnitKind::Link:
                $parts[] = 'Link';
                $parts[] = 'öffnet in neuem Tab';
                break;
            case UnitKind::Session:
            case UnitKind::Presentation:
                $parts[] = $unit->type()->label();
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

    /** Gespeicherte Antwort (Indizes der Frage) als lesbarer Text. */
    public static function answerText(Question $question, mixed $answer): string
    {
        if ($answer === null || $answer === '' || $answer === []) {
            return 'keine Antwort';
        }
        $spec = $question->spec;

        return match (true) {
            $spec instanceof ChoiceSpec => implode(', ', array_map(static fn ($i): string => $spec->options[(int) $i] ?? '?', (array) $answer)),
            $spec instanceof MatchSpec  => implode('; ', array_map(static fn ($l, $r): string => ($spec->left()[(int) $l] ?? '?') . ' = ' . ($spec->right()[(int) $r] ?? '?'), array_keys((array) $answer), array_values((array) $answer))),
            $spec instanceof OrderSpec  => implode(' → ', array_map(static fn ($i): string => $spec->items[(int) $i] ?? '?', (array) $answer)),
            $spec instanceof NumberSpec => trim((string) $answer . ' ' . $spec->unit),
            default                     => is_scalar($answer) ? (string) $answer : '',
        };
    }

    /** Die richtige Lösung als Text; null bei Freitext. */
    public static function solutionText(Question $question): ?string
    {
        $spec = $question->spec;

        return match (true) {
            $spec instanceof ChoiceSpec      => implode(', ', array_map(static fn (int $i): string => $spec->options[$i] ?? '?', $spec->correct)),
            $spec instanceof MatchSpec       => implode('; ', array_map(static fn (array $p): string => $p[0] . ' = ' . $p[1], $spec->pairs)),
            $spec instanceof OrderSpec       => implode(' → ', $spec->items),
            $spec instanceof NumberSpec      => Exams::number($spec->value) . ($spec->tolerance > 0 ? ' (± ' . Exams::number($spec->tolerance) . ')' : '') . ($spec->unit !== '' ? ' ' . $spec->unit : ''),
            $spec instanceof ShortAnswerSpec => implode(' oder ', $spec->accepted),
            default                          => null,
        };
    }
}
