<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\Exam\DrawnQuestion;
use EmergencyForge\Training\Question\QuestionType;

/**
 * Übersetzt Antworten zwischen dem Prüfungsformular (Anzeigepositionen,
 * alles Text) und der gespeicherten Form (Indizes der Frage, wie sie der
 * Grader erwartet). Leere Antworten werden null.
 */
final class Answers
{
    public static function fromForm(DrawnQuestion $drawn, mixed $raw): mixed
    {
        $type = $drawn->question->type;
        $int  = static fn (mixed $v): ?int => is_string($v) && ctype_digit($v) ? (int) $v : (is_int($v) ? $v : null);

        $display = match ($type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse => $int($raw),
            QuestionType::MultipleChoice => is_array($raw) ? array_values(array_unique(array_filter(array_map($int, array_values($raw)), 'is_int'))) : null,
            QuestionType::Ordering       => is_array($raw) ? array_values(array_filter(array_map($int, array_values($raw)), 'is_int')) : null,
            QuestionType::Matching       => is_array($raw) ? self::pairs($raw, $int) : null,
            QuestionType::Number, QuestionType::ShortAnswer => is_string($raw) ? mb_substr(trim($raw), 0, 200) : null,
            QuestionType::FreeText       => is_string($raw) ? mb_substr(trim($raw), 0, 20000) : null,
        };
        if ($display === null || $display === '' || $display === []) {
            return null;
        }
        if ($type === QuestionType::Ordering) {
            $count = count($drawn->order);
            if (count($display) !== $count || array_unique($display) !== $display) {
                return null;
            }
        }

        return $drawn->answerFromDisplay($display);
    }

    /** Gespeicherte Antwort zurück in Anzeigepositionen fürs Formular. */
    public static function toForm(DrawnQuestion $drawn, mixed $stored): mixed
    {
        if ($stored === null || $drawn->order === []) {
            return $stored;
        }
        $position = array_flip($drawn->order);
        $map      = static fn (mixed $index): ?int => is_int($index) ? ($position[$index] ?? null) : null;

        return match ($drawn->question->type) {
            QuestionType::SingleChoice, QuestionType::TrueFalse => $map($stored),
            QuestionType::MultipleChoice, QuestionType::Ordering => is_array($stored) ? array_values(array_filter(array_map($map, $stored), 'is_int')) : null,
            QuestionType::Matching => is_array($stored) ? array_filter(array_map($map, $stored), 'is_int') : null,
            default => $stored,
        };
    }

    /**
     * @param array<mixed, mixed>        $raw
     * @param callable(mixed): ?int      $int
     * @return array<int, int>
     */
    private static function pairs(array $raw, callable $int): array
    {
        $pairs = [];
        foreach ($raw as $left => $right) {
            $left  = $int(is_int($left) ? $left : (string) $left);
            $right = $int($right);
            if ($left !== null && $right !== null) {
                $pairs[$left] = $right;
            }
        }

        return $pairs;
    }
}
