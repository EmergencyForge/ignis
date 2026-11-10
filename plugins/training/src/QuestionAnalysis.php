<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\Exam\DrawnExam;
use EmergencyForge\Training\Exam\QuestionStats;
use EmergencyForge\Training\Question\ChoiceSpec;
use EmergencyForge\Training\Question\GradedAnswer;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\AttemptAnswer;
use Plugin\Training\Models\Question;

/**
 * Fragenauswertung aus allen abgegebenen Versuchen. Anteil richtig und
 * Markierung gelten über alle Versionen einer Frage; die Verteilung der
 * Antworten nur für die aktuelle, weil eine neue Version andere Antworten
 * an denselben Stellen haben kann.
 */
final class QuestionAnalysis
{
    /**
     * @param list<Question> $questions
     * @return array<int, QuestionStats> Fragen-ID => Auswertung, nur Fragen mit Antworten
     */
    public static function of(array $questions): array
    {
        $current = [];
        foreach ($questions as $question) {
            $current[$question->id] = $question;
        }
        if ($current === []) {
            return [];
        }

        $answers = AttemptAnswer::query()
            ->whereIn('question_id', array_keys($current))
            ->whereIn('attempt_id', Attempt::query()->whereNotNull('submitted_at')->select('id'))
            ->get(['attempt_id', 'position', 'question_id', 'question_version', 'answer', 'auto_points', 'manual_points', 'needs_review']);

        /** @var array<int, DrawnExam> $drawn */
        $drawn = [];
        /** @var list<Attempt> $attempts */
        $attempts = Attempt::query()->whereIn('id', $answers->pluck('attempt_id')->unique()->all())->get(['id', 'drawn'])->all();
        foreach ($attempts as $attempt) {
            $drawn[$attempt->id] = $attempt->drawnExam();
        }

        $stats = [];
        /** @var AttemptAnswer $answer */
        foreach ($answers as $answer) {
            $max = $drawn[$answer->attempt_id]->questions[$answer->position]->question->points ?? null;
            if ($max === null) {
                continue;
            }
            $question = $current[$answer->question_id];
            $chosen   = $answer->question_version === $question->version && $question->questionType()->specClass() === ChoiceSpec::class
                ? array_values(array_filter((array) $answer->answer, 'is_int'))
                : [];
            ($stats[$answer->question_id] ??= new QuestionStats())->add(
                new GradedAnswer($answer->question_id, $answer->points(), $max, $answer->needs_review && $answer->manual_points === null),
                $chosen,
            );
        }

        return $stats;
    }
}
