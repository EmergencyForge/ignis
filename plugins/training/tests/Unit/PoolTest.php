<?php

declare(strict_types=1);

namespace Plugin\Training\Tests\Unit;

use EmergencyForge\Training\Exam\DrawnQuestion;
use EmergencyForge\Training\Question\Question;
use EmergencyForge\Training\Question\QuestionType;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\Answers;
use Plugin\Training\Pool;
use Tests\TestCase;

final class PoolTest extends TestCase
{
    #[Test]
    public function csv_answers_survive_a_round_trip(): void
    {
        $cases = [
            'single_choice'   => '*60-80 | 120-140',
            'multiple_choice' => '*Puls | *Atmung | Haarfarbe | %anteilig',
            'true_false'      => 'Wahr | *Falsch',
            'matching'        => 'Herz=Kreislauf | Lunge=Atmung',
            'ordering'        => 'Eigenschutz | Ansprechen | Atmung prüfen | %je_position',
            'number'          => '14 | 2 | /min',
            'short_answer'    => 'Adrenalin | Epinephrin | %genau',
        ];
        foreach ($cases as $type => $csv) {
            $kind = QuestionType::from($type);
            $this->assertSame($csv, Pool::specToCsv($kind, Pool::specFromCsv($kind, $csv)), $type);
        }
        $this->assertNull(Pool::specFromCsv(QuestionType::FreeText, 'egal'));
    }

    #[Test]
    public function csv_rejects_answers_without_a_solution(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Pool::specFromCsv(QuestionType::TrueFalse, 'Wahr | Falsch');
    }

    #[Test]
    public function german_labels_name_the_type(): void
    {
        $this->assertSame(QuestionType::Number, Pool::typeFrom('number'));
        $this->assertSame(QuestionType::Number, Pool::typeFrom(QuestionType::Number->label()));
        $this->assertNull(Pool::typeFrom('Rätsel'));
    }

    #[Test]
    public function form_answers_map_display_positions_back_to_options(): void
    {
        $question = Question::fromArray(['id' => 1, 'type' => 'multiple_choice', 'body' => 'Vitalzeichen?', 'points' => 2, 'spec' => ['options' => ['Puls', 'Atmung', 'Haarfarbe'], 'correct' => [0, 1], 'partial' => false]]);
        $drawn    = new DrawnQuestion($question, [2, 0, 1]);

        // Angezeigt wird Haarfarbe, Puls, Atmung: Position 1 und 2 sind Puls und Atmung.
        $stored = Answers::fromForm($drawn, ['1', '2']);
        $this->assertSame([0, 1], $stored);
        $this->assertSame([1, 2], Answers::toForm($drawn, $stored));
        $this->assertNull(Answers::fromForm($drawn, []));
        $this->assertNull(Answers::fromForm($drawn, 'kein Array'));
    }
}
