<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use App\Helpers\Flash;
use App\Models\EditorTemplate;
use App\Models\Role;
use App\Models\User;
use EmergencyForge\Training\Completion\CompletionPlan;
use EmergencyForge\Training\Completion\CompletionStep;
use Plugin\Training\Completion\Settings;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\EnrollmentMode;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Training\Models\Course;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\Prerequisite;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\Topic;
use Plugin\Training\Policies\TrainingPolicy;
use Plugin\Training\Pool;
use Plugin\Training\ExamBoard;
use Plugin\Training\Qualifications;
use Plugin\Training\QuestionAnalysis;

/** Lehrgangsarten unter /training/types (`training.manage`). */
final class TypeController extends BaseController
{
    /** GET /training/types */
    public function index(Request $request): Response
    {
        $types   = CourseType::query()->orderByDesc('is_active')->orderBy('name')->get()->all();
        $courses = Course::query()->selectRaw('course_type_id, COUNT(*) AS c')->groupBy('course_type_id')->pluck('c', 'course_type_id')->all();
        $modules = Module::query()->whereNotNull('course_type_id')->selectRaw('course_type_id, COUNT(*) AS c')->groupBy('course_type_id')->pluck('c', 'course_type_id')->all();

        return $this->page('types/index', ['types' => $types, 'courses' => $courses, 'modules' => $modules]);
    }

    /** GET /training/types/create */
    public function create(Request $request): Response
    {
        return $this->page('types/create', ['form' => self::defaults(), 'options' => self::options()]);
    }

    /** POST /training/types */
    public function store(Request $request): Response
    {
        $type  = new CourseType();
        $error = $this->fill($type, $request->post);
        if ($error !== null) {
            Flash::error($error);

            return $this->page('types/create', ['form' => self::formFrom($request->post), 'options' => self::options()], 422);
        }

        return self::back('training/types/' . $type->id . '#lehrplan', null, 'Lehrgangsart angelegt. Jetzt den Lehrplan füllen.');
    }

    /** GET /training/types/{id} */
    public function show(Request $request, string $id): Response
    {
        $type = CourseType::query()->with(['modules.units.file', 'prerequisites'])->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }

        return $this->page('types/show', [
            'type'    => $type,
            'form'    => [
                'name' => $type->name, 'short' => $type->short, 'description' => (string) $type->description, 'area' => $type->area,
                'start_qualification' => (string) $type->start_qualification, 'pass_qualification' => (string) $type->pass_qualification,
                'validity_months' => $type->validity_months ?? '', 'enrollment_mode' => $type->enrollment_mode,
                'sequential' => $type->sequential, 'is_active' => $type->is_active, 'prerequisites' => $type->prerequisiteKeys(),
            ],
            'options' => self::options(),
            'courses' => Course::query()->where('course_type_id', $type->id)->count(),
        ] + (TrainingPolicy::manage() ? self::completionData($type) : [])
          + (TrainingPolicy::exams() ? self::examData($type, (int) $request->queryString('topic')) : []));
    }

    /**
     * Daten für den Reiter Abschluss, unter einem Schlüssel, weil die
     * Partials davor eigene Variablen wie `$settings` setzen.
     *
     * @return array{completion: array<string, mixed>}
     */
    private static function completionData(CourseType $type): array
    {
        $users = [];
        foreach (User::query()->where('is_active', true)->orderBy('fullname')->orderBy('username')->get(['id', 'fullname', 'username']) as $user) {
            $users[(int) $user->id] = (string) ($user->fullname ?: $user->username);
        }

        return ['completion' => [
            'plan'      => CompletionPlan::fromArray($type->completion_plan),
            'settings'  => Settings::of($type),
            'templates' => EditorTemplate::query()->where('is_active', true)->orderBy('category')->orderBy('name')->get(['id', 'name', 'category'])->all(),
            'users'     => $users,
            'roles'     => Role::query()->orderBy('priority')->pluck('name', 'id')->all(),
        ]];
    }

    /** @return array<string, mixed> Daten für Fragenpool und Prüfungsordnung */
    private static function examData(CourseType $type, int $topic): array
    {
        /** @var list<Question> $questions */
        $questions = Question::query()->where('course_type_id', $type->id)->whereNull('deleted_at')->orderBy('topic_id')->orderBy('id')->get()->all();
        $counts    = [];
        $active    = [];
        foreach ($questions as $question) {
            $counts[(int) $question->topic_id] = ($counts[(int) $question->topic_id] ?? 0) + 1;
            if ($question->is_active) {
                $active[(int) $question->topic_id] = ($active[(int) $question->topic_id] ?? 0) + 1;
            }
        }

        return [
            'topics'    => Topic::query()->where('course_type_id', $type->id)->orderBy('position')->orderBy('name')->get()->all(),
            'topic'     => $topic,
            'questions' => $questions,
            'counts'    => $counts,
            'active'    => $active,
            'usage'     => Pool::usage(array_map(static fn (Question $q): int => $q->id, $questions)),
            'stats'     => QuestionAnalysis::of($questions),
            'parts'     => ExamBoard::parts($type->id),
        ];
    }

    /** POST /training/types/{id} */
    public function update(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }
        $error = $this->fill($type, $request->post);

        return self::back('training/types/' . $type->id, $error, $error === null ? 'Gespeichert.' : null);
    }

    /** POST /training/types/{id}/completion: Reiter Abschluss. */
    public function completion(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }
        $post  = $request->post;
        $text  = static fn (string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
        $steps = is_array($post['steps'] ?? null) ? $post['steps'] : [];
        $plan  = [];
        foreach (CompletionStep::cases() as $step) {
            $plan[$step->value] = $step === CompletionStep::Result || ($steps[$step->value] ?? '') === '1';
        }

        $signer   = $text('signer_mode');
        $signerId = (int) $text('signer_user_id');
        $template = $text('certificate_template_id');
        $channel  = $text('announce_channel');
        $webhook  = $text('announce_webhook');
        $target   = $text('follow_up_target');
        $days     = $text('follow_up_days');
        [$kind, $targetId] = array_pad(explode(':', $target, 2), 2, '');
        $targetOk = match ($kind) {
            'role'  => Role::query()->whereKey((int) $targetId)->exists(),
            'user'  => User::query()->whereKey((int) $targetId)->exists(),
            default => false,
        };

        $error = match (true) {
            !isset(Settings::SIGNERS[$signer])                                                       => 'Unbekannte Unterschrift.',
            $signer === Settings::SIGNER_FIXED && !User::query()->whereKey($signerId)->exists()      => 'Bitte die Person wählen, die unterschreibt.',
            $template !== '' && !EditorTemplate::query()->whereKey((int) $template)->exists()        => 'Diese Vorlage gibt es nicht.',
            $plan['certificate'] && $template === ''                                                 => 'Bitte eine Vorlage für die Urkunde wählen.',
            !isset(Settings::CHANNELS[$channel])                                                     => 'Unbekannter Ort für die Bekanntgabe.',
            $webhook !== '' && preg_match(Settings::WEBHOOK_PATTERN, $webhook) !== 1                 => 'Die Webhook-URL muss ein Discord-Webhook sein (https://discord.com/api/webhooks/…).',
            $plan['announce'] && $channel === 'discord' && $webhook === ''                           => 'Für Discord fehlt die Webhook-URL.',
            $plan['announce'] && $text('announce_text') === ''                                       => 'Bitte einen Text für die Bekanntgabe angeben.',
            $target !== '' && !$targetOk                                                             => 'Unbekannte Rolle oder Person für die Folgeaufgabe.',
            $plan['follow_up'] && ($target === '' || $text('follow_up_title') === '')                => 'Die Folgeaufgabe braucht eine Rolle oder Person und einen Titel.',
            $days !== '' && (!ctype_digit($days) || (int) $days > 365)                              => 'Frist in Tagen von 0 bis 365.',
            default                                                                                  => null,
        };
        if ($error !== null) {
            return self::back('training/types/' . $type->id . '#abschluss', $error);
        }

        $type->completion_plan         = $plan;
        $type->certificate_template_id = $template !== '' ? (int) $template : null;
        $type->signer_mode             = $signer;
        $type->signer_user_id          = $signer === Settings::SIGNER_FIXED ? $signerId : null;
        $type->completion_settings     = [
            'announce_channel' => $channel,
            'announce_webhook' => $webhook,
            'announce_text'    => mb_substr($text('announce_text'), 0, 500),
            'follow_up_target' => $target,
            'follow_up_title'  => mb_substr($text('follow_up_title'), 0, 200),
            'follow_up_days'   => $days !== '' ? (int) $days : 0,
        ];
        $type->save();

        return self::back('training/types/' . $type->id . '#abschluss', null, 'Gespeichert.');
    }

    /** POST /training/types/{id}/sequential: Schalter im Reiter Lehrplan. */
    public function sequential(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::json(['error' => 'Nicht gefunden.'], 404);
        }
        $type->sequential = ($request->post['sequential'] ?? '') === '1';
        $type->save();

        return self::json(['sequential' => $type->sequential]);
    }

    /**
     * POST /training/types/{id}/copy: Stammdaten, Voraussetzungen und
     * Lehrplan. Dateien teilen sich Original und Kopie.
     */
    public function copy(Request $request, string $id): Response
    {
        $type = CourseType::query()->with(['modules.units', 'prerequisites'])->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }

        $copy = Capsule::connection()->transaction(function () use ($type): CourseType {
            $copy             = $type->replicate();
            $copy->name       = mb_substr($type->name . ' (Kopie)', 0, 150);
            $copy->is_active  = false;
            $copy->created_by = self::userId() ?: null;
            $copy->save();
            foreach ($type->prerequisites as $prerequisite) {
                Prerequisite::query()->create(['course_type_id' => $copy->id, 'qualification' => $prerequisite->qualification]);
            }
            foreach ($type->modules as $module) {
                $newModule = $module->replicate();
                $newModule->course_type_id = $copy->id;
                $newModule->save();
                foreach ($module->units as $unit) {
                    $newUnit = $unit->replicate();
                    $newUnit->module_id = $newModule->id;
                    $newUnit->save();
                }
            }

            return $copy;
        });

        return self::back('training/types/' . $copy->id, null, 'Kopie angelegt. Sie ist noch nicht aktiv.');
    }

    /** POST /training/types/{id}/delete: nur ohne Lehrgänge. */
    public function destroy(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }
        if (Course::query()->where('course_type_id', $type->id)->exists()) {
            return self::back('training/types/' . $type->id, 'Es gibt Lehrgänge dieser Art. Setze sie stattdessen auf inaktiv.');
        }
        $type->delete();

        return self::back('training/types', null, 'Lehrgangsart gelöscht.');
    }

    /** @param array<string, mixed> $post */
    private function fill(CourseType $type, array $post): ?string
    {
        $form    = self::formFrom($post);
        $targets = Qualifications::targets();
        $known   = array_merge(...array_values(Qualifications::options() ?: [[]]));

        $error = match (true) {
            $form['name'] === '' || mb_strlen($form['name']) > 150                       => 'Bitte einen Namen angeben (bis 150 Zeichen).',
            $form['short'] === '' || mb_strlen($form['short']) > 20                      => 'Bitte ein Kürzel angeben (bis 20 Zeichen).',
            !isset(CourseType::AREAS[$form['area']])                                     => 'Unbekannter Bereich.',
            $form['start_qualification'] !== '' && !isset($targets[$form['start_qualification']]) => 'Diese Qualifikation lässt sich nicht per Lehrgang vergeben.',
            $form['pass_qualification'] !== '' && !isset($targets[$form['pass_qualification']])   => 'Diese Qualifikation lässt sich nicht per Lehrgang vergeben.',
            EnrollmentMode::tryFrom($form['enrollment_mode']) === null                    => 'Unbekannte Art der Anmeldung.',
            $form['validity_months'] !== '' && (!ctype_digit($form['validity_months']) || (int) $form['validity_months'] < 1) => 'Gültigkeit in Monaten ab 1, leer heißt unbegrenzt.',
            array_diff($form['prerequisites'], array_keys($known)) !== []                => 'Unbekannte Voraussetzung.',
            default                                                                      => null,
        };
        if ($error !== null) {
            return $error;
        }

        Capsule::connection()->transaction(function () use ($type, $form): void {
            $isNew                     = !$type->exists;
            $type->name                = $form['name'];
            $type->short               = $form['short'];
            $type->description         = $form['description'] !== '' ? $form['description'] : null;
            $type->area                = $form['area'];
            $type->start_qualification = $form['start_qualification'] ?: null;
            $type->pass_qualification  = $form['pass_qualification'] ?: null;
            $type->validity_months     = $form['validity_months'] !== '' ? (int) $form['validity_months'] : null;
            $type->enrollment_mode     = $form['enrollment_mode'];
            $type->is_active           = $form['is_active'];
            if ($isNew) {
                $type->sequential = $form['sequential'];
                $type->created_by = self::userId() ?: null;
            }
            $type->save();

            Prerequisite::query()->where('course_type_id', $type->id)->delete();
            foreach ($form['prerequisites'] as $key) {
                Prerequisite::query()->create(['course_type_id' => $type->id, 'qualification' => $key]);
            }
        });

        return null;
    }

    /**
     * @param array<string, mixed> $post
     * @return array{name: string, short: string, description: string, area: string, start_qualification: string, pass_qualification: string, validity_months: string, enrollment_mode: string, sequential: bool, is_active: bool, prerequisites: list<string>}
     */
    private static function formFrom(array $post): array
    {
        $text = static fn (string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
        $list = is_array($post['prerequisites'] ?? null) ? $post['prerequisites'] : [];

        return [
            'name'                => $text('name'),
            'short'               => $text('short'),
            'description'         => $text('description'),
            'area'                => $text('area'),
            'start_qualification' => $text('start_qualification'),
            'pass_qualification'  => $text('pass_qualification'),
            'validity_months'     => $text('validity_months'),
            'enrollment_mode'     => $text('enrollment_mode'),
            'sequential'          => ($post['sequential'] ?? '') === '1',
            'is_active'           => ($post['is_active'] ?? '') === '1',
            'prerequisites'       => array_values(array_unique(array_filter(array_map('strval', $list)))),
        ];
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        return [
            'name' => '', 'short' => '', 'description' => '', 'area' => 'rd', 'start_qualification' => '', 'pass_qualification' => '',
            'validity_months' => '', 'enrollment_mode' => EnrollmentMode::Open->value, 'sequential' => false, 'is_active' => true, 'prerequisites' => [],
        ];
    }

    /** @return array{targets: array<string, string>, qualifications: array<string, array<string, string>>} */
    private static function options(): array
    {
        return ['targets' => Qualifications::targets(), 'qualifications' => Qualifications::options()];
    }
}
