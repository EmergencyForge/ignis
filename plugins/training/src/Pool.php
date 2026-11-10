<?php

declare(strict_types=1);

namespace Plugin\Training;

use EmergencyForge\Training\Exam\QuestionSource;
use EmergencyForge\Training\Question\ChoiceSpec;
use EmergencyForge\Training\Question\MatchSpec;
use EmergencyForge\Training\Question\NumberSpec;
use EmergencyForge\Training\Question\OrderSpec;
use EmergencyForge\Training\Question\Question as PoolQuestion;
use EmergencyForge\Training\Question\QuestionType;
use EmergencyForge\Training\Question\ShortAnswerSpec;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Plugin\Training\Models\AttemptAnswer;
use Plugin\Training\Models\Question;

/**
 * Fragenpool einer Lehrgangsart: Port für den ExamDrawer, Speichern mit
 * Versionen, Antwortangaben aus Formular und CSV.
 */
final class Pool implements QuestionSource
{
    public function __construct(private readonly int $courseTypeId)
    {
    }

    public function byTopic(int $topicId): array
    {
        return array_map(static fn (Question $q): PoolQuestion => $q->toDomain(), $this->active('topic_id', [$topicId]));
    }

    public function byIds(array $ids): array
    {
        $found = [];
        foreach ($this->active('id', $ids) as $question) {
            $found[$question->id] = $question->toDomain();
        }

        return $found;
    }

    /**
     * @param list<int> $values
     * @return list<Question>
     */
    private function active(string $column, array $values): array
    {
        /** @var list<Question> $rows */
        $rows = Question::query()->where('course_type_id', $this->courseTypeId)->where('is_active', true)
            ->whereNull('deleted_at')->whereIn($column, $values ?: [0])->orderBy('id')->get()->all();

        return $rows;
    }

    /**
     * Speichert Inhalt in die Frage. Kam die aktuelle Fassung schon in
     * einem Versuch vor, wird sie vorher als eigene Zeile abgelegt und die
     * Frage zählt eine Version weiter. Die ID bleibt, damit
     * Prüfungsordnungen weiter auf sie zeigen.
     *
     * @param array{type: string, body: string, points: float, spec: ?array<string, mixed>, explanation: ?string, topic_id: ?int, is_active: bool, image_path?: ?string} $data
     * @return bool ob eine neue Version entstanden ist
     */
    public static function save(Question $question, array $data, ?int $userId): bool
    {
        return Capsule::connection()->transaction(static function () use ($question, $data, $userId): bool {
            $versioned = false;
            if ($question->exists && self::contentChanges($question, $data) && self::usedInAttempts($question)) {
                $old = $question->replicate();
                $old->deleted_at = Carbon::now();
                $old->is_active  = false;
                $old->save();
                $question->previous_id = $old->id;
                $question->version++;
                $versioned = true;
            }
            foreach ($data as $key => $value) {
                $question->{$key} = $value;
            }
            if (!$question->exists) {
                $question->created_by = $userId;
            }
            $question->save();

            return $versioned;
        });
    }

    /** Kam die aktuelle Fassung in einem Versuch vor? */
    public static function usedInAttempts(Question $question): bool
    {
        return AttemptAnswer::query()->where('question_id', $question->id)->where('question_version', $question->version)->exists();
    }

    /**
     * Wie oft je Frage (über alle Versionen) gestellt.
     *
     * @param list<int> $ids
     * @return array<int, int>
     */
    public static function usage(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map('intval', AttemptAnswer::query()->whereIn('question_id', $ids)->selectRaw('question_id, COUNT(*) AS c')->groupBy('question_id')->pluck('c', 'question_id')->all());
    }

    /** @param array<string, mixed> $data */
    private static function contentChanges(Question $question, array $data): bool
    {
        // Thema und Aktiv ändern nichts an dem, was im Versuch stand.
        foreach (['type', 'body', 'points', 'spec', 'explanation', 'image_path'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] != $question->{$key}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft die Angaben gegen die Regeln der Fragart. Wirft mit einer
     * Meldung für den Nutzer.
     *
     * @param array<string, mixed>|null $spec
     */
    public static function validate(QuestionType $type, string $body, float $points, ?array $spec): void
    {
        PoolQuestion::fromArray(['id' => 0, 'type' => $type->value, 'body' => $body, 'points' => $points, 'spec' => $spec]);
    }

    /**
     * Antwortangaben aus dem Editor.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>|null
     */
    public static function specFromForm(QuestionType $type, array $post): ?array
    {
        $rows = static function (string $key) use ($post): array {
            $value = $post[$key] ?? [];
            if (!is_array($value)) {
                return [];
            }
            ksort($value);

            return $value;
        };
        $flag = static fn (string $key): bool => ($post[$key] ?? '') === '1';

        switch ($type) {
            case QuestionType::SingleChoice:
            case QuestionType::MultipleChoice:
                $options = [];
                $correct = [];
                $marked  = array_map('strval', is_array($post['correct'] ?? null) ? $post['correct'] : (isset($post['correct']) ? [$post['correct']] : []));
                foreach ($rows('options') as $key => $text) {
                    $text = trim((string) $text);
                    if ($text === '') {
                        continue;
                    }
                    if (in_array((string) $key, $marked, true)) {
                        $correct[] = count($options);
                    }
                    $options[] = $text;
                }

                return (new ChoiceSpec($options, $correct, $type === QuestionType::MultipleChoice && $flag('partial')))->toArray();
            case QuestionType::TrueFalse:
                return ChoiceSpec::trueFalse(($post['statement'] ?? 'true') !== 'false')->toArray();
            case QuestionType::Matching:
                $pairs = [];
                foreach ($rows('pairs') as $pair) {
                    $left  = trim((string) (is_array($pair) ? ($pair[0] ?? '') : ''));
                    $right = trim((string) (is_array($pair) ? ($pair[1] ?? '') : ''));
                    if ($left !== '' || $right !== '') {
                        $pairs[] = [$left, $right];
                    }
                }

                return (new MatchSpec($pairs))->toArray();
            case QuestionType::Ordering:
                $items = array_values(array_filter(array_map(static fn ($i): string => trim((string) $i), $rows('items')), static fn (string $i): bool => $i !== ''));

                return (new OrderSpec($items, $flag('per_position')))->toArray();
            case QuestionType::Number:
                $value = NumberSpec::parse($post['value'] ?? null);
                if ($value === null) {
                    throw new InvalidArgumentException('Bitte die richtige Zahl angeben.');
                }
                $tolerance = trim((string) ($post['tolerance'] ?? '')) === '' ? 0.0 : NumberSpec::parse($post['tolerance']);
                if ($tolerance === null) {
                    throw new InvalidArgumentException('Die Toleranz ist keine Zahl.');
                }

                return (new NumberSpec($value, $tolerance, mb_substr(trim((string) ($post['unit'] ?? '')), 0, 20)))->toArray();
            case QuestionType::ShortAnswer:
                $accepted = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($post['accepted'] ?? '')) ?: []), static fn (string $a): bool => $a !== ''));

                return (new ShortAnswerSpec($accepted, $flag('tolerant')))->toArray();
            case QuestionType::FreeText:
                return null;
        }
    }

    /**
     * Antwortspalte der CSV: Teile mit `|` getrennt, richtige Antworten mit
     * führendem `*`. Zuordnung `links=rechts`, Reihenfolge in richtiger
     * Folge, Zahl `Wert|Toleranz|Einheit`, Kurzantwort die akzeptierten
     * Antworten, Wahr/Falsch `*Wahr|Falsch`.
     *
     * @return array<string, mixed>|null
     */
    public static function specFromCsv(QuestionType $type, string $answers): ?array
    {
        $parts = array_values(array_filter(array_map('trim', explode('|', $answers)), static fn (string $p): bool => $p !== ''));

        switch ($type) {
            case QuestionType::SingleChoice:
            case QuestionType::MultipleChoice:
                $options = [];
                $correct = [];
                $partial = false;
                foreach ($parts as $part) {
                    if ($part === '%anteilig') {
                        $partial = true;
                        continue;
                    }
                    if (str_starts_with($part, '*')) {
                        $correct[] = count($options);
                        $part      = ltrim(substr($part, 1));
                    }
                    $options[] = $part;
                }

                return (new ChoiceSpec($options, $correct, $partial && $type === QuestionType::MultipleChoice))->toArray();
            case QuestionType::TrueFalse:
                $true = null;
                foreach ($parts as $part) {
                    if (str_starts_with($part, '*')) {
                        $true = mb_strtolower(ltrim(substr($part, 1))) !== 'falsch';
                    }
                }
                if ($true === null) {
                    throw new InvalidArgumentException('Bei Wahr/Falsch fehlt die richtige Antwort (*Wahr oder *Falsch).');
                }

                return ChoiceSpec::trueFalse($true)->toArray();
            case QuestionType::Matching:
                $pairs = [];
                foreach ($parts as $part) {
                    [$left, $right] = array_map('trim', explode('=', $part, 2)) + [1 => ''];
                    $pairs[] = [$left, $right];
                }

                return (new MatchSpec($pairs))->toArray();
            case QuestionType::Ordering:
                $perPosition = in_array('%je_position', $parts, true);

                return (new OrderSpec(array_values(array_diff($parts, ['%je_position'])), $perPosition))->toArray();
            case QuestionType::Number:
                $value = NumberSpec::parse($parts[0] ?? null);
                if ($value === null) {
                    throw new InvalidArgumentException('Bei Zahl fehlt der Wert (Wert|Toleranz|Einheit).');
                }

                return (new NumberSpec($value, NumberSpec::parse($parts[1] ?? '0') ?? 0.0, (string) ($parts[2] ?? '')))->toArray();
            case QuestionType::ShortAnswer:
                $strict = in_array('%genau', $parts, true);

                return (new ShortAnswerSpec(array_values(array_diff($parts, ['%genau'])), !$strict))->toArray();
            case QuestionType::FreeText:
                return null;
        }
    }

    /**
     * Gegenstück zu specFromCsv() für den Export.
     *
     * @param array<string, mixed>|null $spec
     */
    public static function specToCsv(QuestionType $type, ?array $spec): string
    {
        $spec ??= [];
        $num  = static fn (float $v): string => str_replace('.', ',', (string) $v);

        $parts = match ($type) {
            QuestionType::SingleChoice, QuestionType::MultipleChoice => array_merge(
                array_map(static fn (int $i, string $o): string => (in_array($i, (array) ($spec['correct'] ?? []), true) ? '*' : '') . $o, array_keys((array) ($spec['options'] ?? [])), array_values((array) ($spec['options'] ?? []))),
                !empty($spec['partial']) ? ['%anteilig'] : [],
            ),
            QuestionType::TrueFalse      => (int) (((array) ($spec['correct'] ?? []))[0] ?? 0) === 1 ? ['Wahr', '*Falsch'] : ['*Wahr', 'Falsch'],
            QuestionType::Matching       => array_map(static fn ($p): string => ((array) $p)[0] . '=' . ((array) $p)[1], (array) ($spec['pairs'] ?? [])),
            QuestionType::Ordering       => array_merge(array_map('strval', (array) ($spec['items'] ?? [])), !empty($spec['per_position']) ? ['%je_position'] : []),
            QuestionType::Number         => [$num((float) ($spec['value'] ?? 0)), $num((float) ($spec['tolerance'] ?? 0)), (string) ($spec['unit'] ?? '')],
            QuestionType::ShortAnswer    => array_merge(array_map('strval', (array) ($spec['accepted'] ?? [])), ($spec['tolerant'] ?? true) ? [] : ['%genau']),
            QuestionType::FreeText       => [],
        };

        return implode(' | ', array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    /** Fragart aus Wert oder deutscher Bezeichnung. */
    public static function typeFrom(string $value): ?QuestionType
    {
        $value = trim($value);
        foreach (QuestionType::cases() as $case) {
            if ($case->value === $value || mb_strtolower($case->label()) === mb_strtolower($value)) {
                return $case;
            }
        }

        return null;
    }
}
