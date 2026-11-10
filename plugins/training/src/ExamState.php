<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\Exam\GradeScale;
use EmergencyForge\Training\Exam\StartCheck;
use EmergencyForge\Training\Progress\Admission;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\Exam;

/** Stand eines Prüfungsteils für eine Teilnahme, wie ExamBoard ihn rechnet. */
final class ExamState
{
    /** @param list<Attempt> $attempts älteste zuerst */
    public function __construct(
        public readonly Exam $exam,
        public readonly Admission $admission,
        public readonly bool $released,
        public readonly bool $releasedIndividually,
        public readonly array $attempts,
        public readonly int $remaining,
        public readonly StartCheck $startCheck,
    ) {
    }

    public function latest(): ?Attempt
    {
        return $this->attempts === [] ? null : $this->attempts[count($this->attempts) - 1];
    }

    public function open(): ?Attempt
    {
        $latest = $this->latest();

        return $latest !== null && $latest->isOpen() ? $latest : null;
    }

    public function passed(): bool
    {
        foreach ($this->attempts as $attempt) {
            if ($attempt->status === Attempt::DONE && $attempt->passed === true) {
                return true;
            }
        }

        return false;
    }

    /** Versuch, der beim Bestehen gezählt hat, sonst der letzte bewertete. */
    public function result(): ?Attempt
    {
        $done = array_values(array_filter($this->attempts, static fn (Attempt $a): bool => $a->status === Attempt::DONE));
        foreach ($done as $attempt) {
            if ($attempt->passed === true) {
                return $attempt;
            }
        }

        return $done === [] ? null : $done[count($done) - 1];
    }

    /** Wartet ein Versuch auf Bewertung oder Gegenzeichnung? */
    public function pending(): ?Attempt
    {
        $latest = $this->latest();

        return $latest !== null && in_array($latest->status, [Attempt::REVIEW, Attempt::COSIGN], true) ? $latest : null;
    }

    /** Ein neuer Versuch darf jetzt beginnen. */
    public function canStart(): bool
    {
        return $this->admission->admitted && $this->released && $this->latest()?->status !== Attempt::OPEN
            && $this->pending() === null && !$this->passed() && $this->startCheck->allowed;
    }

    /** Warum nicht, als Satz; null wenn es geht. */
    public function blocker(): ?string
    {
        return match (true) {
            $this->passed()             => 'Bestanden.',
            $this->open() !== null      => 'Ein Versuch läuft.',
            $this->pending() !== null   => 'Der letzte Versuch wird noch bewertet.',
            !$this->admission->admitted => $this->admission->reason,
            !$this->released            => 'Die Leitung hat die Prüfung noch nicht freigegeben.',
            !$this->startCheck->allowed => $this->startCheck->reason,
            default                     => null,
        };
    }

    /** Eine Zeile für Tabellen: „Bestanden, 88 % (gut)“, „Versuch 1 läuft“. */
    public function summary(): string
    {
        $result = $this->result();
        if ($this->passed() && $result !== null) {
            return 'Bestanden, ' . self::score($this->exam, $result);
        }
        if (($open = $this->open()) !== null) {
            return 'Versuch ' . $open->number . ' läuft';
        }
        if (($pending = $this->pending()) !== null) {
            return $pending->status === Attempt::REVIEW ? 'Abgegeben, wartet auf Bewertung' : 'Bewertet, wartet auf Gegenzeichnung';
        }
        if ($result !== null) {
            $text = 'Nicht bestanden (' . Attempt::percentLabel((float) $result->percent) . ')';
            if ($this->remaining === 0) {
                return $text . ', keine Versuche mehr';
            }

            return $this->startCheck->allowed ? $text . ', Versuch ' . (count($this->attempts) + 1) . ' offen' : $text . '. ' . $this->startCheck->reason;
        }
        if (!$this->admission->admitted) {
            return 'Gesperrt';
        }

        return $this->released ? 'Offen' : 'Nicht freigegeben';
    }

    /** „88 % (gut)“ */
    public static function score(Exam $exam, Attempt $attempt): string
    {
        $scale = $exam->examSettings()->gradeScale;
        $label = Attempt::percentLabel((float) $attempt->percent);

        return $scale !== null ? $label . ' (' . GradeScale::label($scale->grade((float) $attempt->percent)) . ')' : $label;
    }
}
