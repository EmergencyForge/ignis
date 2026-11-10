<?php

declare(strict_types=1);

namespace Plugin\Training;

use DomainException;
use EmergencyForge\Training\Exam\ExamKind;
use EmergencyForge\Training\Question\QuestionType;
use EmergencyForge\Training\UnitKind;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use Plugin\Training\Controllers\TrainingController;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\Topic;
use Plugin\Training\Models\Unit;

/**
 * Fragenpool als CSV, ganze Lehrgangsarten als JSON, damit Server sich
 * Lehrgänge teilen können.
 *
 * CSV: eine Zeile je Frage, Spalten Thema, Art, Frage, Antworten, Punkte,
 * Erklärung; Aufbau der Antwortspalte siehe Pool::specFromCsv().
 *
 * JSON verweist zwischen Fragen und Prüfungsordnung über `ref` (die ID im
 * Quellsystem) und über Themennamen. Dateien reisen nicht mit;
 * Einheiten mit Datei fallen beim Import weg.
 */
final class Exchange
{
    public const FORMAT = 'emergencyforge-training';

    private const CSV_HEADER = ['Thema', 'Art', 'Frage', 'Antworten', 'Punkte', 'Erklärung'];

    public static function csv(CourseType $type): string
    {
        $out = fopen('php://temp', 'w+');
        if ($out === false) {
            throw new \RuntimeException('Kein Puffer für den Export.');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::CSV_HEADER, ';', '"', '');
        $topics = Topic::query()->where('course_type_id', $type->id)->pluck('name', 'id')->all();
        foreach (self::questions($type) as $question) {
            $kind = $question->questionType();
            fputcsv($out, [
                $topics[$question->topic_id] ?? '',
                $kind->label(),
                $question->body,
                Pool::specToCsv($kind, $question->spec),
                Exams::number($question->points),
                (string) $question->explanation,
            ], ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Liest alle Zeilen und legt die Fragen nur an, wenn keine Zeile einen
     * Fehler hat.
     *
     * @return int angelegte Fragen
     */
    public static function importCsv(CourseType $type, string $content, ?int $userId): int
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $first   = strtok($content, "\n") ?: '';
        $sep     = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $handle  = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new \RuntimeException('Kein Puffer für den Import.');
        }
        fwrite($handle, $content);
        rewind($handle);

        $rows   = [];
        $errors = [];
        $line   = 0;
        while (($cells = fgetcsv($handle, null, $sep, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || ($line === 1 && mb_strtolower(trim((string) ($cells[0] ?? ''))) === 'thema')) {
                continue;
            }
            $cells = array_map(static fn ($c): string => trim((string) $c), $cells) + array_fill(0, 6, '');
            [$topic, $kindLabel, $body, $answers, $points, $explanation] = $cells;
            $kind = Pool::typeFrom($kindLabel);
            try {
                if ($kind === null) {
                    throw new InvalidArgumentException('Unbekannte Art „' . $kindLabel . '“.');
                }
                $pointsValue = $points === '' ? 1.0 : (float) str_replace(',', '.', $points);
                $spec        = Pool::specFromCsv($kind, $answers);
                Pool::validate($kind, $body, $pointsValue, $spec);
                $rows[] = ['topic' => $topic, 'type' => $kind->value, 'body' => $body, 'points' => $pointsValue, 'spec' => $spec, 'explanation' => $explanation !== '' ? $explanation : null, 'is_active' => true];
            } catch (InvalidArgumentException $e) {
                $errors[] = 'Zeile ' . $line . ': ' . $e->getMessage();
            }
        }
        fclose($handle);

        if ($errors !== []) {
            throw new DomainException(implode(' ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' Und ' . (count($errors) - 5) . ' weitere.' : ''));
        }
        if ($rows === []) {
            throw new DomainException('Die Datei enthält keine Fragen.');
        }

        Capsule::connection()->transaction(static function () use ($type, $rows, $userId): void {
            $topics = self::topicIds($type);
            foreach ($rows as $row) {
                $row['topic_id'] = $row['topic'] !== '' ? ($topics[mb_strtolower($row['topic'])] ??= self::createTopic($type, $row['topic'])) : null;
                unset($row['topic']);
                $question = new Question(['course_type_id' => $type->id]);
                Pool::save($question, $row, $userId);
            }
        });

        return count($rows);
    }

    /**
     * Lehrgangsart mit Lehrplan, Fragenpool und Prüfungsordnung.
     *
     * @return array<string, mixed>
     */
    public static function json(CourseType $type, bool $withCourseType = true): array
    {
        /** @var list<Topic> $topics */
        $topics    = Topic::query()->where('course_type_id', $type->id)->orderBy('position')->orderBy('id')->get()->all();
        $topicName = [];
        foreach ($topics as $topic) {
            $topicName[$topic->id] = $topic->name;
        }
        $data = [
            'format'    => self::FORMAT,
            'version'   => 1,
            'topics'    => array_map(static fn (Topic $t): array => ['name' => $t->name], $topics),
            'questions' => array_map(static fn (Question $q): array => [
                'ref'         => $q->id,
                'topic'       => $topicName[$q->topic_id] ?? null,
                'type'        => $q->type,
                'body'        => $q->body,
                'points'      => $q->points,
                'spec'        => $q->spec,
                'explanation' => $q->explanation,
                'active'      => $q->is_active,
            ], self::questions($type)),
        ];
        if (!$withCourseType) {
            return $data;
        }

        $data['course_type'] = [
            'name' => $type->name, 'short' => $type->short, 'description' => $type->description, 'area' => $type->area,
            'enrollment_mode' => $type->enrollment_mode, 'sequential' => $type->sequential, 'validity_months' => $type->validity_months,
        ];
        $data['modules'] = array_map(static fn (Module $m): array => [
            'title' => $m->title,
            'units' => array_values(array_map(static fn (Unit $u): array => [
                'kind' => $u->kind, 'title' => $u->title, 'required' => $u->required, 'content' => $u->content, 'url' => $u->url,
            ], array_filter($m->units->all(), static fn (Unit $u): bool => in_array($u->type(), [UnitKind::Text, UnitKind::Link, UnitKind::Session], true)))),
        ], $type->modules()->with('units')->get()->all());
        $data['exams'] = array_map(static function (Exam $exam) use ($topicName): array {
            $blueprint = $exam->blueprint ?? [];
            foreach ((array) ($blueprint['topics'] ?? []) as $i => $rule) {
                $blueprint['topics'][$i] = ['topic' => $topicName[(int) ($rule['topic_id'] ?? 0)] ?? null, 'count' => (int) ($rule['count'] ?? 0)];
            }

            return ['kind' => $exam->kind, 'title' => $exam->title, 'required' => $exam->required, 'blueprint' => $blueprint, 'sheet' => $exam->sheet, 'settings' => $exam->settings];
        }, ExamBoard::parts($type->id));

        return $data;
    }

    /**
     * Fragen (und Themen) aus JSON in einen bestehenden Pool.
     *
     * @param array<string, mixed> $data
     * @return array<int, int> ref => neue Fragen-ID
     */
    public static function importQuestions(CourseType $type, array $data, ?int $userId): array
    {
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw new DomainException('Das ist keine Ausbildungs-Datei aus ignis oder Lex.');
        }
        $rows   = [];
        $errors = [];
        foreach ((array) ($data['questions'] ?? []) as $i => $question) {
            $question = (array) $question;
            $kind     = QuestionType::tryFrom((string) ($question['type'] ?? ''));
            try {
                if ($kind === null) {
                    throw new InvalidArgumentException('Unbekannte Fragart.');
                }
                $spec   = is_array($question['spec'] ?? null) ? $question['spec'] : null;
                $points = (float) ($question['points'] ?? 1);
                $body   = trim((string) ($question['body'] ?? ''));
                Pool::validate($kind, $body, $points, $spec);
                $rows[] = [
                    'ref'  => (int) ($question['ref'] ?? 0),
                    'topic' => trim((string) ($question['topic'] ?? '')),
                    'data' => ['type' => $kind->value, 'body' => $body, 'points' => $points, 'spec' => $spec, 'explanation' => isset($question['explanation']) ? (string) $question['explanation'] : null, 'is_active' => (bool) ($question['active'] ?? true)],
                ];
            } catch (InvalidArgumentException $e) {
                $errors[] = 'Frage ' . ((int) $i + 1) . ': ' . $e->getMessage();
            }
        }
        if ($errors !== []) {
            throw new DomainException(implode(' ', array_slice($errors, 0, 5)));
        }

        return Capsule::connection()->transaction(static function () use ($type, $data, $rows, $userId): array {
            $topics = self::topicIds($type);
            foreach ((array) ($data['topics'] ?? []) as $topic) {
                $name = trim((string) (((array) $topic)['name'] ?? ''));
                if ($name !== '') {
                    $topics[mb_strtolower($name)] ??= self::createTopic($type, $name);
                }
            }
            $ids = [];
            foreach ($rows as $row) {
                $question = new Question(['course_type_id' => $type->id]);
                Pool::save($question, $row['data'] + ['topic_id' => $row['topic'] !== '' ? ($topics[mb_strtolower($row['topic'])] ??= self::createTopic($type, $row['topic'])) : null], $userId);
                $ids[$row['ref']] = $question->id;
            }

            return $ids;
        });
    }

    /**
     * Neue, inaktive Lehrgangsart aus einer JSON-Datei.
     *
     * @param array<string, mixed> $data
     */
    public static function importCourseType(array $data, ?int $userId): CourseType
    {
        $meta = (array) ($data['course_type'] ?? []);
        $name = trim((string) ($meta['name'] ?? ''));
        if (($data['format'] ?? null) !== self::FORMAT || $name === '') {
            throw new DomainException('Das ist keine Lehrgangsart aus ignis oder Lex.');
        }

        return Capsule::connection()->transaction(static function () use ($data, $meta, $name, $userId): CourseType {
            $type = new CourseType();
            $type->name            = mb_substr($name, 0, 150);
            $type->short           = mb_substr(trim((string) ($meta['short'] ?? '')) ?: mb_substr($name, 0, 3), 0, 20);
            $type->description     = isset($meta['description']) ? (string) $meta['description'] : null;
            $type->area            = isset(CourseType::AREAS[(string) ($meta['area'] ?? '')]) ? (string) $meta['area'] : 'other';
            $type->enrollment_mode = in_array($meta['enrollment_mode'] ?? null, ['open', 'confirm', 'lead'], true) ? (string) $meta['enrollment_mode'] : 'open';
            $type->sequential      = (bool) ($meta['sequential'] ?? false);
            $type->validity_months = isset($meta['validity_months']) ? max(1, (int) $meta['validity_months']) : null;
            $type->is_active       = false;
            $type->created_by      = $userId;
            $type->save();

            foreach (array_values((array) ($data['modules'] ?? [])) as $position => $module) {
                $module = (array) $module;
                $row = new Module();
                $row->course_type_id = $type->id;
                $row->position       = $position;
                $row->title          = mb_substr(trim((string) ($module['title'] ?? '')) ?: 'Modul', 0, 150);
                $row->save();
                foreach (array_values((array) ($module['units'] ?? [])) as $unitPosition => $unit) {
                    $unit = (array) $unit;
                    $kind = UnitKind::tryFrom((string) ($unit['kind'] ?? ''));
                    if (!in_array($kind, [UnitKind::Text, UnitKind::Link, UnitKind::Session], true)) {
                        continue;
                    }
                    $newUnit = new Unit();
                    $newUnit->module_id = $row->id;
                    $newUnit->position  = $unitPosition;
                    $newUnit->kind      = $kind->value;
                    $newUnit->title     = mb_substr(trim((string) ($unit['title'] ?? '')) ?: $kind->label(), 0, 200);
                    $newUnit->required  = (bool) ($unit['required'] ?? true);
                    $newUnit->content   = is_array($unit['content'] ?? null) ? $unit['content'] : null;
                    $newUnit->url       = $kind === UnitKind::Link ? TrainingController::safeUrl($unit['url'] ?? null) : null;
                    $newUnit->save();
                }
            }

            $refs   = self::importQuestions($type, $data, $userId);
            $topics = self::topicIds($type);
            $map    = static fn (mixed $ref): ?int => $refs[(int) $ref] ?? null;
            foreach (array_values((array) ($data['exams'] ?? [])) as $position => $exam) {
                $exam = (array) $exam;
                $kind = ExamKind::tryFrom((string) ($exam['kind'] ?? '')) ?? ExamKind::Theory;
                $blueprint = (array) ($exam['blueprint'] ?? []);
                $rules = [];
                foreach ((array) ($blueprint['topics'] ?? []) as $rule) {
                    $topicId = $topics[mb_strtolower(trim((string) (((array) $rule)['topic'] ?? '')))] ?? null;
                    if ($topicId !== null) {
                        $rules[] = ['topic_id' => $topicId, 'count' => max(1, (int) (((array) $rule)['count'] ?? 1))];
                    }
                }
                $blueprint['topics']   = $rules;
                $blueprint['fixed']    = array_values(array_filter(array_map($map, (array) ($blueprint['fixed'] ?? []))));
                $blueprint['variants'] = array_values(array_filter(array_map(static fn ($v): array => array_values(array_filter(array_map($map, (array) $v))), (array) ($blueprint['variants'] ?? []))));
                $settings = (array) ($exam['settings'] ?? []);
                $settings['knockouts'] = array_values(array_filter(array_map($map, (array) ($settings['knockouts'] ?? []))));

                $row = new Exam();
                $row->course_type_id = $type->id;
                $row->position       = $position;
                $row->kind           = $kind->value;
                $row->title          = mb_substr(trim((string) ($exam['title'] ?? '')) ?: $kind->label(), 0, 150);
                $row->required       = (bool) ($exam['required'] ?? true);
                $row->blueprint      = $kind === ExamKind::Theory ? $blueprint : null;
                $row->sheet          = $kind === ExamKind::Practical && is_array($exam['sheet'] ?? null) ? array_values($exam['sheet']) : null;
                $row->settings       = $settings;
                $row->save();
            }

            return $type;
        });
    }

    /** @return list<Question> */
    private static function questions(CourseType $type): array
    {
        /** @var list<Question> $questions */
        $questions = Question::query()->where('course_type_id', $type->id)->whereNull('deleted_at')->orderBy('topic_id')->orderBy('id')->get()->all();

        return $questions;
    }

    /** @return array<string, int> kleingeschriebener Name => ID */
    private static function topicIds(CourseType $type): array
    {
        $ids = [];
        foreach (Topic::query()->where('course_type_id', $type->id)->get() as $topic) {
            $ids[mb_strtolower($topic->name)] = $topic->id;
        }

        return $ids;
    }

    public static function createTopic(CourseType $type, string $name): int
    {
        $topic = new Topic();
        $topic->course_type_id = $type->id;
        $topic->name           = mb_substr($name, 0, 150);
        $topic->position       = 1 + (int) Topic::query()->where('course_type_id', $type->id)->max('position');
        $topic->save();

        return $topic->id;
    }
}
