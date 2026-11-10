<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use DomainException;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\Exam\AttemptRules;
use EmergencyForge\Training\Exam\ExamBlueprint;
use EmergencyForge\Training\Exam\ExamKind;
use EmergencyForge\Training\Exam\ExamSettings;
use EmergencyForge\Training\Exam\GradeScale;
use EmergencyForge\Training\Exam\PracticalSheet;
use EmergencyForge\Training\Exam\ReviewMode;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use Plugin\Training\ExamBoard;
use Plugin\Training\Exams;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Exam;
use Plugin\Training\Models\Participation;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\Topic;
use Plugin\Training\Policies\TrainingPolicy;

/**
 * Prüfungsordnung der Lehrgangsart (`training.exams`) und im Lehrgang
 * Freigabe, Zusatzversuch und das Öffnen eines Praxisbogens.
 */
final class ExamController extends BaseController
{
    public function __construct(private readonly Exams $exams = new Exams())
    {
    }

    /** POST /training/types/{id}/exams */
    public function store(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }
        $kind = ExamKind::tryFrom((string) ($request->post['kind'] ?? '')) ?? ExamKind::Theory;

        $exam = new Exam();
        $exam->course_type_id = $type->id;
        $exam->position       = 1 + (int) Exam::query()->where('course_type_id', $type->id)->max('position');
        $exam->kind           = $kind->value;
        $exam->title          = $kind === ExamKind::Theory ? 'Theorieprüfung' : 'Praxisprüfung';
        $exam->required       = true;
        $exam->settings       = (new ExamSettings(attempts: new AttemptRules($kind === ExamKind::Theory ? 30 : null)))->toArray() + ($kind === ExamKind::Theory ? ['count_focus' => true] : ['cases' => [], 'case_mode' => 'choose']);
        $exam->save();

        return self::back(self::orderPath($type->id), null, $kind === ExamKind::Theory ? 'Theorieteil angelegt.' : 'Praxisteil angelegt.');
    }

    /** POST /training/exams/{id} */
    public function update(Request $request, string $id): Response
    {
        $exam = Exam::query()->find((int) $id);
        if ($exam === null) {
            return self::notFound();
        }
        $post  = $request->post;
        $path  = self::orderPath($exam->course_type_id);
        $flag  = static fn (string $key): bool => ($post[$key] ?? '') === '1';
        $int   = static fn (string $key, int $min, ?int $empty = null): ?int => trim((string) ($post[$key] ?? '')) === '' ? $empty : max($min, (int) $post[$key]);
        $title = mb_substr(trim((string) ($post['title'] ?? '')), 0, 150);

        try {
            $settings = new ExamSettings(
                (float) str_replace(',', '.', (string) ($post['pass_percent'] ?? '70')),
                new AttemptRules(
                    $exam->isTheory() ? $int('time_limit', 1) : null,
                    (int) $int('max_attempts', 1, 1),
                    (int) $int('cooldown_hours', 0, 0),
                ),
                $flag('grade_scale') ? GradeScale::ihk() : null,
                ReviewMode::tryFrom((string) ($post['review'] ?? '')) ?? ReviewMode::Result,
                $exam->isTheory() ? self::ids($post['knockouts'] ?? []) : [],
                ($post['release'] ?? 'lead') !== 'auto',
                !$exam->isTheory() && $flag('cosign'),
            );
            $extra = $exam->isTheory()
                ? ['count_focus' => $flag('count_focus')]
                : ['cases' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($post['cases'] ?? '')) ?: []), static fn (string $c): bool => $c !== '')), 'case_mode' => ($post['case_mode'] ?? '') === 'draw' ? 'draw' : 'choose'];

            if ($exam->isTheory()) {
                $blueprint = $this->blueprint($exam, $post);
                $sheet     = null;
            } else {
                $blueprint = null;
                $sheet     = self::sheetFrom($post['groups'] ?? []);
            }
        } catch (InvalidArgumentException | DomainException $e) {
            return self::back($path, $e->getMessage());
        }

        $exam->title     = $title !== '' ? $title : $exam->examKind()->label();
        $exam->required  = $flag('required');
        $exam->blueprint = $blueprint;
        $exam->sheet     = $sheet;
        $exam->settings  = $settings->toArray() + $extra;
        $exam->save();

        return self::back($path, null, 'Gespeichert.');
    }

    /** POST /training/exams/{id}/delete: nur ohne Versuche. */
    public function destroy(Request $request, string $id): Response
    {
        $exam = Exam::query()->find((int) $id);
        if ($exam === null) {
            return self::notFound();
        }
        if (Attempt::query()->where('exam_id', $exam->id)->exists()) {
            return self::back(self::orderPath($exam->course_type_id), 'Zu diesem Teil gibt es schon Versuche. Statt zu löschen, auf freiwillig setzen.');
        }
        $exam->delete();

        return self::back(self::orderPath($exam->course_type_id), null, 'Prüfungsteil gelöscht.');
    }

    /** POST /training/exams/{id}/move: eine Stelle hoch oder runter. */
    public function move(Request $request, string $id): Response
    {
        $exam = Exam::query()->find((int) $id);
        if ($exam === null) {
            return self::notFound();
        }
        $parts = ExamBoard::parts($exam->course_type_id);
        $index = array_search($exam->id, array_map(static fn (Exam $e): int => $e->id, $parts), true);
        $swap  = ($request->post['direction'] ?? '') === 'up' ? (int) $index - 1 : (int) $index + 1;
        if ($index !== false && isset($parts[$swap])) {
            [$parts[$index], $parts[$swap]] = [$parts[$swap], $parts[$index]];
            Capsule::connection()->transaction(static function () use ($parts): void {
                foreach ($parts as $position => $part) {
                    Exam::query()->whereKey($part->id)->update(['position' => $position]);
                }
            });
        }

        return self::back(self::orderPath($exam->course_type_id));
    }

    /** POST /training/manage/{id}/exams/{exam}/release: für alle oder mit `participation` für eine Person. */
    public function release(Request $request, string $id, string $exam): Response
    {
        return $this->courseAction($request, $id, $exam, function ($course, Exam $part, ?Participation $participation): string {
            $this->exams->release($course, $part, $participation, self::userId());

            return $participation === null ? 'Für alle Zugelassenen freigegeben.' : 'Für ' . $participation->name() . ' freigegeben.';
        });
    }

    /** POST /training/manage/{id}/exams/{exam}/revoke */
    public function revoke(Request $request, string $id, string $exam): Response
    {
        return $this->courseAction($request, $id, $exam, function ($course, Exam $part, ?Participation $participation): string {
            $this->exams->revoke($course, $part, $participation);

            return 'Freigabe zurückgenommen. Laufende Versuche laufen weiter.';
        });
    }

    /** POST /training/manage/{id}/exams/{exam}/extra: Zusatzversuch mit Begründung. */
    public function extra(Request $request, string $id, string $exam): Response
    {
        return $this->courseAction($request, $id, $exam, function ($course, Exam $part, ?Participation $participation) use ($request): string {
            if ($participation === null) {
                throw new DomainException('Bitte eine Person wählen.');
            }
            $this->exams->grantExtra($participation, $part, trim((string) ($request->post['reason'] ?? '')), self::userId(), self::editor());

            return $participation->name() . ' hat einen Zusatzversuch.';
        });
    }

    /** POST /training/manage/{id}/exams/{exam}/sheet: Praxisbogen für eine Person öffnen. */
    public function sheet(Request $request, string $id, string $exam): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::examine($course)) {
            return self::notFound();
        }
        $part          = Exam::query()->find((int) $exam);
        $participation = Participation::query()->where('course_id', $course->id)->find((int) ($request->post['participation'] ?? 0));
        if ($part === null || $part->course_type_id !== $course->course_type_id || $part->isTheory() || $participation === null) {
            return self::notFound();
        }
        if ($participation->personnel_id === self::personnelId()) {
            return self::back('training/manage/' . $course->id . '#pruefungen', 'Die eigene Prüfung bewertet ein anderer Prüfer.');
        }
        try {
            $attempt = $this->exams->openSheet($course, $participation, $part, isset($request->post['case']) ? (string) $request->post['case'] : null);
        } catch (DomainException $e) {
            return self::back('training/manage/' . $course->id . '#pruefungen', $e->getMessage());
        }

        return self::back('training/attempts/' . $attempt->id . '/sheet');
    }

    /** @param callable(\Plugin\Training\Models\Course, Exam, ?Participation): string $action */
    private function courseAction(Request $request, string $id, string $exam, callable $action): Response
    {
        $course = self::findCourse($id);
        if ($course === null || !TrainingPolicy::lead($course)) {
            return self::notFound();
        }
        $part = Exam::query()->find((int) $exam);
        if ($part === null || $part->course_type_id !== $course->course_type_id) {
            return self::notFound();
        }
        $participation = null;
        if (($request->post['participation'] ?? '') !== '') {
            $participation = Participation::query()->with('personnel')->where('course_id', $course->id)->find((int) $request->post['participation']);
            if ($participation === null) {
                return self::notFound();
            }
            $participation->setRelation('course', $course);
        }
        try {
            $message = $action($course, $part, $participation);
        } catch (DomainException $e) {
            return self::back('training/manage/' . $course->id . '#pruefungen', $e->getMessage());
        }

        return self::back('training/manage/' . $course->id . '#pruefungen', null, $message);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function blueprint(Exam $exam, array $post): array
    {
        $topics = Topic::query()->where('course_type_id', $exam->course_type_id)->pluck('id')->all();
        $pool   = Question::query()->where('course_type_id', $exam->course_type_id)->whereNull('deleted_at')->pluck('id')->all();
        $known  = static fn (array $ids): array => array_values(array_intersect($ids, $pool));

        $rules = [];
        foreach ((array) ($post['topics'] ?? []) as $rule) {
            $rule  = (array) $rule;
            $topic = (int) ($rule['topic_id'] ?? 0);
            if (in_array($topic, $topics, true) && (int) ($rule['count'] ?? 0) > 0) {
                $rules[$topic] = ($rules[$topic] ?? 0) + (int) $rule['count'];
            }
        }
        $variants = [];
        if (($post['use_variants'] ?? '') === '1') {
            foreach ((array) ($post['variants'] ?? []) as $variant) {
                $ids = $known(self::ids($variant));
                if ($ids !== []) {
                    $variants[] = $ids;
                }
            }
            if ($variants === []) {
                throw new DomainException('Mit festen Prüfungsbögen braucht es mindestens einen Bogen mit Fragen.');
            }
        }
        $fixed            = $variants === [] ? $known(self::ids($post['fixed'] ?? [])) : [];
        $shuffleQuestions = ($post['shuffle_questions'] ?? '') === '1';
        $shuffleOptions   = ($post['shuffle_options'] ?? '') === '1';
        if ($variants === [] && $rules === [] && $fixed === []) {
            // Entwurf ohne Fragen; ExamBlueprint lehnt das ab, Exam::examBlueprint() gibt dann null.
            return ['topics' => [], 'fixed' => [], 'variants' => [], 'shuffle_questions' => $shuffleQuestions, 'shuffle_options' => $shuffleOptions];
        }

        return (new ExamBlueprint($variants === [] ? $rules : [], $fixed, $variants, $shuffleQuestions, $shuffleOptions))->toArray();
    }

    /**
     * Gruppen und Kriterien aus dem Formular. Neue Kriterien bekommen eine
     * Kennung, bestehende behalten ihre, damit Bewertungen zuordenbar bleiben.
     *
     * @return list<array<string, mixed>>
     */
    private static function sheetFrom(mixed $groups): array
    {
        $out = [];
        foreach (is_array($groups) ? $groups : [] as $group) {
            $group    = (array) $group;
            $criteria = [];
            foreach ((array) ($group['criteria'] ?? []) as $criterion) {
                $criterion = (array) $criterion;
                $label     = mb_substr(trim((string) ($criterion['label'] ?? '')), 0, 300);
                if ($label === '') {
                    continue;
                }
                $points = trim((string) ($criterion['points'] ?? ''));
                $key    = (string) ($criterion['key'] ?? '');
                $criteria[] = [
                    'key'      => preg_match('/^[a-z0-9]{1,16}$/', $key) === 1 ? $key : 'k' . bin2hex(random_bytes(4)),
                    'label'    => $label,
                    'points'   => $points === '' ? null : (float) str_replace(',', '.', $points),
                    'knockout' => ($criterion['knockout'] ?? '') === '1',
                ];
            }
            $title = mb_substr(trim((string) ($group['title'] ?? '')), 0, 150);
            if ($criteria !== [] || $title !== '') {
                $out[] = ['title' => $title, 'criteria' => $criteria];
            }
        }

        // Ohne Kriterien als Entwurf speichern; starten lässt sich der Teil erst mit Kriterien.
        $hasCriteria = array_filter($out, static fn (array $g): bool => $g['criteria'] !== []) !== [];

        return $hasCriteria ? PracticalSheet::fromArray($out)->toArray() : $out;
    }

    /** @return list<int> */
    private static function ids(mixed $value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map('intval', $list), static fn (int $id): bool => $id > 0)));
    }

    private static function orderPath(int $typeId): string
    {
        return 'training/types/' . $typeId . '#pruefungsordnung';
    }
}
