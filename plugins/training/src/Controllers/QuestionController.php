<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use App\Helpers\Flash;
use DomainException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\Question\QuestionType;
use InvalidArgumentException;
use JsonException;
use Plugin\Training\Exchange;
use Plugin\Training\FileStore;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\Topic;
use Plugin\Training\Models\TrainingFile;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\Pool;
use Plugin\Training\QuestionAnalysis;

/** Fragenpool einer Lehrgangsart (`training.exams`). */
final class QuestionController extends BaseController
{
    public function __construct(private readonly FileStore $files = new FileStore())
    {
    }

    /** POST /training/types/{id}/topics */
    public function storeTopic(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        $name = trim((string) ($request->post['name'] ?? ''));
        if ($type === null) {
            return self::notFound();
        }
        if ($name === '') {
            return self::back(self::poolPath($type), 'Bitte einen Namen für das Thema angeben.');
        }
        if (Topic::query()->where('course_type_id', $type->id)->where('name', $name)->exists()) {
            return self::back(self::poolPath($type), 'Dieses Thema gibt es schon.');
        }
        Exchange::createTopic($type, $name);

        return self::back(self::poolPath($type), null, 'Thema angelegt.');
    }

    /** POST /training/topics/{id} */
    public function updateTopic(Request $request, string $id): Response
    {
        $topic = Topic::query()->find((int) $id);
        $name  = mb_substr(trim((string) ($request->post['name'] ?? '')), 0, 150);
        if ($topic === null) {
            return self::notFound();
        }
        if ($name !== '') {
            $topic->name = $name;
            $topic->save();
        }

        return self::back('training/types/' . $topic->course_type_id . '?topic=' . $topic->id . '#fragenpool', $name === '' ? 'Bitte einen Namen angeben.' : null, $name !== '' ? 'Gespeichert.' : null);
    }

    /** POST /training/topics/{id}/delete: nur leer und ohne Regel in der Prüfungsordnung. */
    public function deleteTopic(Request $request, string $id): Response
    {
        $topic = Topic::query()->find((int) $id);
        if ($topic === null) {
            return self::notFound();
        }
        $path = 'training/types/' . $topic->course_type_id . '#fragenpool';
        if (Question::query()->where('topic_id', $topic->id)->whereNull('deleted_at')->exists()) {
            return self::back($path, 'Im Thema stehen noch Fragen. Erst verschieben oder löschen.');
        }
        foreach (Exam::query()->where('course_type_id', $topic->course_type_id)->get() as $exam) {
            foreach ((array) (($exam->blueprint ?? [])['topics'] ?? []) as $rule) {
                if ((int) (((array) $rule)['topic_id'] ?? 0) === $topic->id) {
                    return self::back($path, 'Die Prüfung „' . $exam->title . '“ zieht noch aus diesem Thema.');
                }
            }
        }
        Question::query()->where('topic_id', $topic->id)->update(['topic_id' => null]);
        $topic->delete();

        return self::back($path, null, 'Thema gelöscht.');
    }

    /** GET /training/types/{id}/questions/create */
    public function create(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }
        $question = new Question([
            'course_type_id' => $type->id,
            'type'           => (QuestionType::tryFrom($request->queryString('type')) ?? QuestionType::SingleChoice)->value,
            'topic_id'       => (int) $request->queryString('topic') ?: null,
            'body'           => '',
            'points'         => 1.0,
            'version'        => 1,
            'is_active'      => true,
        ]);

        return $this->editorPage($type, $question);
    }

    /** POST /training/types/{id}/questions */
    public function store(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }

        return $this->save($type, new Question(['course_type_id' => $type->id, 'version' => 1]), $request);
    }

    /** GET /training/questions/{id}/edit */
    public function edit(Request $request, string $id): Response
    {
        $question = self::question($id);

        return $question === null ? self::notFound() : $this->editorPage(CourseType::query()->findOrFail($question->course_type_id), $question);
    }

    /** POST /training/questions/{id} */
    public function update(Request $request, string $id): Response
    {
        $question = self::question($id);
        $type     = $question !== null ? CourseType::query()->find($question->course_type_id) : null;

        return $question === null || $type === null ? self::notFound() : $this->save($type, $question, $request);
    }

    /** POST /training/questions/{id}/delete: weich, abgegebene Versuche zeigen sie weiter. */
    public function destroy(Request $request, string $id): Response
    {
        $question = self::question($id);
        if ($question === null) {
            return self::notFound();
        }
        $path = 'training/types/' . $question->course_type_id . '#fragenpool';
        $exam = self::examUsing($question);
        if ($exam !== null) {
            return self::back($path, 'Die Prüfung „' . $exam->title . '“ setzt diese Frage fest ein. Erst dort entfernen.');
        }
        $question->deleted_at = \Illuminate\Support\Carbon::now();
        $question->is_active  = false;
        $question->save();

        return self::back($path, null, 'Frage gelöscht.');
    }

    /** POST /training/questions/{id}/active */
    public function active(Request $request, string $id): Response
    {
        $question = self::question($id);
        if ($question === null) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }
        $question->is_active = ($request->post['active'] ?? '') === '1';
        // Wer einen KI-Entwurf anschaltet, gibt ihn frei.
        $question->ai_draft = $question->ai_draft && !$question->is_active;
        $question->save();

        return self::json(['active' => $question->is_active]);
    }

    /** GET /training/questions/{id}/image */
    public function image(Request $request, string $id): Response
    {
        $question = self::question($id);
        $file     = $question?->image_path !== null ? TrainingFile::query()->find((int) $question->image_path) : null;

        return $file === null ? self::notFound() : self::deliver($file, false);
    }

    /** GET /training/types/{id}/questions/export?format=csv|json */
    public function export(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }
        $name = 'fragenpool-' . strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $type->short));
        if ($request->queryString('format') === 'json') {
            return self::download($name . '.json', 'application/json', (string) json_encode(Exchange::json($type, false), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return self::download($name . '.csv', 'text/csv; charset=utf-8', Exchange::csv($type));
    }

    /** POST /training/types/{id}/questions/import: CSV oder JSON, alles oder nichts. */
    public function import(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }
        $path    = self::poolPath($type);
        $content = self::upload($request);
        if ($content === null) {
            return self::back($path, 'Bitte eine CSV- oder JSON-Datei auswählen.');
        }
        try {
            $data  = self::decode($content);
            $count = $data !== null ? count(Exchange::importQuestions($type, $data, self::userId() ?: null)) : Exchange::importCsv($type, $content, self::userId() ?: null);
        } catch (DomainException $e) {
            return self::back($path, $e->getMessage());
        }

        return self::back($path, null, $count === 1 ? 'Eine Frage importiert.' : $count . ' Fragen importiert.');
    }

    /** GET /training/types/{id}/export: Lehrgangsart mit Lehrplan, Pool und Prüfungsordnung. */
    public function exportType(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null || !TrainingPolicy::manage()) {
            return self::notFound();
        }

        return self::download('lehrgangsart-' . strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $type->short)) . '.json', 'application/json',
            (string) json_encode(Exchange::json($type), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** POST /training/types/import */
    public function importType(Request $request): Response
    {
        if (!TrainingPolicy::manage()) {
            return self::notFound();
        }
        $content = self::upload($request);
        try {
            $data = $content !== null ? self::decode($content) : null;
            if ($data === null) {
                throw new DomainException('Bitte eine JSON-Datei mit einer Lehrgangsart auswählen.');
            }
            $type = Exchange::importCourseType($data, self::userId() ?: null);
        } catch (DomainException $e) {
            return self::back('training/types', $e->getMessage());
        }

        return self::back('training/types/' . $type->id, null, 'Lehrgangsart importiert. Sie ist noch nicht aktiv; Einheiten mit Dateien fehlen und müssen neu hochgeladen werden.');
    }

    private function editorPage(CourseType $type, Question $question, int $status = 200): Response
    {
        $usage = $question->exists ? (Pool::usage([$question->id])[$question->id] ?? 0) : 0;

        return $this->page('types/question', [
            'type'     => $type,
            'question' => $question,
            'topics'   => Topic::query()->where('course_type_id', $type->id)->orderBy('position')->orderBy('name')->get()->all(),
            'usage'    => $usage,
            'stats'    => $question->exists ? (QuestionAnalysis::of([$question])[$question->id] ?? null) : null,
            'versions' => $question->exists && Pool::usedInAttempts($question),
        ], $status);
    }

    private function save(CourseType $type, Question $question, Request $request): Response
    {
        $post  = $request->post;
        $kind  = QuestionType::tryFrom((string) ($post['type'] ?? '')) ?? QuestionType::SingleChoice;
        $body  = trim((string) ($post['body'] ?? ''));
        $topic = (int) ($post['topic_id'] ?? 0);
        $data  = [
            'type'        => $kind->value,
            'body'        => $body,
            'points'      => max(0.5, round((float) str_replace(',', '.', (string) ($post['points'] ?? '1')), 2)),
            'explanation' => trim((string) ($post['explanation'] ?? '')) ?: null,
            'topic_id'    => $topic > 0 && Topic::query()->where('course_type_id', $type->id)->whereKey($topic)->exists() ? $topic : null,
            'is_active'   => ($post['is_active'] ?? '') === '1',
            'spec'        => null,
        ];

        try {
            $data['spec'] = Pool::specFromForm($kind, $post);
            Pool::validate($kind, $body, $data['points'], $data['spec']);
            $image = $this->storeImage($request, $question);
            if ($image !== false) {
                $data['image_path'] = $image;
            }
        } catch (InvalidArgumentException | DomainException $e) {
            Flash::error($e->getMessage());
            foreach ($data as $key => $value) {
                $question->{$key} = $value;
            }

            return $this->editorPage($type, $question, 422);
        }

        $question->ai_draft = $question->ai_draft && !$data['is_active'];
        $versioned = Pool::save($question, $data, self::userId() ?: null);

        return self::back(
            ($post['next'] ?? '') === 'new' ? 'training/types/' . $type->id . '/questions/create?type=' . $kind->value . '&topic=' . ($data['topic_id'] ?? '') : self::poolPath($type, $data['topic_id']),
            null,
            $versioned ? 'Gespeichert als Version ' . $question->version . '. Abgegebene Versuche behalten die alte Fassung.' : 'Gespeichert.',
        );
    }

    /**
     * Neues Bild, entferntes Bild (null) oder unverändert (false). Das alte
     * Bild bleibt liegen, weil frühere Versionen und Versuche darauf zeigen.
     */
    private function storeImage(Request $request, Question $question): string|null|false
    {
        $upload = $request->files['image'] ?? null;
        if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $this->files->store($upload, self::userId() ?: null);
            if (!$file->isImage()) {
                $this->files->delete($file);
                throw new DomainException('Bitte ein Bild hochladen (PNG, JPG oder WebP).');
            }

            return (string) $file->id;
        }

        return ($request->post['remove_image'] ?? '') === '1' && $question->image_path !== null ? null : false;
    }

    private static function question(string $id): ?Question
    {
        return Question::query()->whereNull('deleted_at')->find((int) $id);
    }

    private static function examUsing(Question $question): ?Exam
    {
        foreach (Exam::query()->where('course_type_id', $question->course_type_id)->get() as $exam) {
            $blueprint = $exam->blueprint ?? [];
            $ids       = array_merge((array) ($blueprint['fixed'] ?? []), ...array_map(static fn ($v): array => (array) $v, array_values((array) ($blueprint['variants'] ?? []))));
            if (in_array($question->id, array_map('intval', $ids), true)) {
                return $exam;
            }
        }

        return null;
    }

    private static function poolPath(CourseType $type, ?int $topic = null): string
    {
        return 'training/types/' . $type->id . ($topic !== null ? '?topic=' . $topic : '') . '#fragenpool';
    }

    private static function upload(Request $request): ?string
    {
        $upload = $request->files['file'] ?? null;
        $tmp    = is_array($upload) && ($upload['error'] ?? null) === UPLOAD_ERR_OK && is_string($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : null;
        if ($tmp === null || !is_file($tmp) || filesize($tmp) > 5 * 1048576) {
            return null;
        }
        $content = file_get_contents($tmp);

        return $content === false || $content === '' ? null : $content;
    }

    /** @return array<string, mixed>|null null, wenn es kein JSON ist */
    private static function decode(string $content): ?array
    {
        $trimmed = ltrim((string) preg_replace('/^\xEF\xBB\xBF/', '', $content));
        if (!str_starts_with($trimmed, '{')) {
            return null;
        }
        try {
            $data = json_decode($trimmed, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('Die JSON-Datei ist beschädigt.');
        }

        return is_array($data) ? $data : null;
    }

    private static function download(string $name, string $type, string $body): Response
    {
        return new Response(200, $body, [
            'Content-Type'        => $type,
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
