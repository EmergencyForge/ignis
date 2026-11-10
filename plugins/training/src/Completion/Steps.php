<?php

declare(strict_types=1);

namespace Plugin\Training\Completion;

use App\Documents\Editor\DocumentId;
use App\Documents\Editor\DocumentIssuer;
use App\Documents\Editor\DocumentNotIssued;
use App\Documents\Editor\VariableCatalog;
use App\Integrations\DiscordWebhook;
use App\Models\AmbSkill;
use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Models\FdSkill;
use App\Models\Personnel;
use App\Personnel\PersonalLogManager;
use App\Personnel\PersonnelQualifications;
use EmergencyForge\Editor\PackageGuard;
use EmergencyForge\Training\CertificateVariables;
use EmergencyForge\Training\Completion\Completion;
use EmergencyForge\Training\Completion\CompletionStep;
use EmergencyForge\Training\Completion\StepResult;
use EmergencyForge\Training\Exam\GradeScale;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use Plugin\Training\ExamBoard;
use Plugin\Training\Models\Attempt;
use Plugin\Training\Models\Participation;
use Plugin\Training\Notify;
use Plugin\Training\Qualifications;
use RuntimeException;

/**
 * Die Schritte der Abschluss-Kette, wie ignis sie ausführt. Läuft ohne
 * Sitzung (Cron), deshalb kommt alles aus der Teilnahme: Aussteller, Leitung,
 * Empfänger. Eingetragen wird als „System“.
 */
final class Steps
{
    private const EDITOR = 'System';

    /** @var array<int, Participation> */
    private array $participations = [];

    public function __construct(
        private readonly CompletionTable $table,
        private readonly PersonnelQualifications $qualifications = new PersonnelQualifications(),
    ) {
    }

    /** @return array<string, \EmergencyForge\Training\Completion\CompletionHandler> */
    public function handlers(): array
    {
        return [
            CompletionStep::Result->value        => new Step($this->result(...)),
            CompletionStep::Qualification->value => new Step($this->qualification(...)),
            CompletionStep::Certificate->value   => new Step($this->certificate(...)),
            CompletionStep::Journal->value       => new Step($this->journal(...)),
            CompletionStep::Notify->value        => new Step($this->notify(...)),
            CompletionStep::Announce->value      => new Step($this->announce(...)),
            CompletionStep::FollowUp->value      => new Step($this->followUp(...)),
        ];
    }

    /** Prozent im Schnitt der Pflicht-Teile, Note aus dem ersten Teil mit Notenschlüssel, Ablauf aus der Gültigkeit. */
    private function result(Completion $completion): StepResult
    {
        $p = $this->participation($completion);
        $p->finished_at ??= Carbon::now();
        if ($completion->passed) {
            $percents = [];
            $grade    = null;
            foreach ($this->results($p) as [$part, $attempt]) {
                $percents[] = (float) $attempt->percent;
                $scale      = $part->examSettings()->gradeScale;
                if ($grade === null && $scale instanceof GradeScale) {
                    $grade = GradeScale::label($scale->grade((float) $attempt->percent));
                }
            }
            $p->result_percent = $percents !== [] ? round(array_sum($percents) / count($percents), 2) : null;
            $p->grade          = $grade;
            $months            = $p->course->type->validity_months;
            $p->expires_on     = $months !== null ? $p->finished_at->copy()->addMonthsNoOverflow($months)->startOfDay() : null;
        }
        $p->save();

        return StepResult::done(self::resultLabel($p), ['percent' => $p->result_percent !== null ? (float) $p->result_percent : null, 'grade' => $p->grade]);
    }

    private function qualification(Completion $completion): StepResult
    {
        $p     = $this->participation($completion);
        $key   = $p->course->type->pass_qualification;
        $grant = Qualifications::grantable($key);
        if ($grant === null) {
            return StepResult::skipped('Keine Qualifikation bei Bestehen festgelegt.');
        }
        if ($p->personnel_id === null) {
            return StepResult::skipped('Der Mitarbeiter wurde gelöscht.');
        }
        [$area, $value] = $grant;
        $name = (string) $this->qualificationName($p);
        if (!$this->qualifications->grant($p->personnel_id, $area, $value, $p->course->title . ' bestanden', self::EDITOR)) {
            return StepResult::skipped('Gleiche oder höhere Qualifikation vorhanden, nicht geändert.');
        }
        $until = $p->expires_on !== null ? ' bis ' . $p->expires_on->format('d.m.Y') : '';

        return StepResult::done($name . $until, ['name' => $name]);
    }

    /**
     * Legt den Entwurf an und stellt ihn aus. Bleibt ein Pflichtfeld der
     * Vorlage offen, das keine Variable füllt, bleibt es beim Entwurf und
     * die Leitung bekommt Bescheid. Leere Variablen halten nicht auf: was
     * fehlt, entscheidet die Vorlage.
     */
    private function certificate(Completion $completion): StepResult
    {
        $p        = $this->participation($completion);
        $template = EditorTemplate::query()->find((int) $p->course->type->certificate_template_id);
        if ($template === null) {
            return StepResult::skipped('Keine Vorlage gewählt.');
        }
        if ($p->personnel === null) {
            return StepResult::skipped('Der Mitarbeiter wurde gelöscht.');
        }
        [$signerPersonnel, $signerUser] = $this->signer($p);

        $documentId = $this->table->documentId($p->id);
        $document   = $documentId !== null ? EditorDocument::query()->with(['mitarbeiter', 'templateVersion'])->find($documentId) : null;
        if ($document === null) {
            $document = DocumentId::create(static fn (string $docid): EditorDocument => EditorDocument::create($template->newDocumentAttributes() + [
                'docid'          => $docid,
                'mitarbeiter_id' => $p->personnel_id,
                'title'          => trim(str_replace('(Lehrgang)', '', $template->name)) . ' ' . $p->course->title,
                'status'         => EditorDocument::STATUS_DRAFT,
                'created_by'     => $signerUser,
            ]));
            $this->table->setDocument($p->id, (int) $document->id);
            $document->load(['mitarbeiter', 'templateVersion']);
        }
        $data = ['document_id' => (int) $document->id, 'docid' => (string) $document->docid];
        if ($document->isIssued()) {
            return StepResult::done('Urkunde ' . $document->docid . ' ausgestellt', $data);
        }

        $variables = $this->variables($p);
        $frozen    = array_merge(VariableCatalog::resolve([
            'mitarbeiter' => $document->mitarbeiter,
            'docid'       => $document->docid,
            'aussteller'  => $signerPersonnel !== null ? Personnel::query()->find($signerPersonnel) : null,
        ]), $variables);
        $open = [];
        foreach ((new PackageGuard())->openItems($document->content, $frozen, $document->templateVersion?->content) as $item) {
            if ($item['type'] !== 'variable') {
                $open[] = '„' . $item['label'] . '“';
            }
        }
        if ($open !== []) {
            Notify::users(array_filter([$p->course->lead_user_id]), 'Urkunde für ' . $p->name() . ' braucht noch Angaben', 'documents/' . $document->id . '/edit', $p->course->title);

            return StepResult::done('Entwurf: ' . (count($open) === 1 ? 'Feld ' . $open[0] . ' fehlt' : 'Felder ' . implode(', ', $open) . ' fehlen'), $data + ['draft' => true]);
        }

        try {
            app(DocumentIssuer::class)->issue($document, $signerPersonnel, $signerUser, $variables, true);
        } catch (DocumentNotIssued $e) {
            if (!$e->alreadyIssued) {
                return StepResult::failed($e->getMessage());
            }
        }

        return StepResult::done('Urkunde ' . $document->docid . ' ausgestellt', $data);
    }

    private function journal(Completion $completion): StepResult
    {
        $p = $this->participation($completion);
        if ($p->personnel_id === null) {
            return StepResult::skipped('Der Mitarbeiter wurde gelöscht.');
        }
        $base   = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        $course = sprintf('<a href="%s">%s</a>', htmlspecialchars($base . 'training/courses/' . $p->course_id), htmlspecialchars($p->course->title));
        if (!$completion->passed) {
            $html = $course . ' nicht bestanden.';
        } else {
            $result = self::resultLabel($p);
            $html   = $course . ' bestanden' . ($result !== '' ? ' (' . htmlspecialchars($result) . ')' : '') . '.';
            if ($completion->result(CompletionStep::Qualification)?->state === StepResult::DONE) {
                $html .= ' Qualifikation ' . htmlspecialchars((string) $completion->value(CompletionStep::Qualification, 'name')) . ' eingetragen.';
            }
            $documentId = $completion->value(CompletionStep::Certificate, 'document_id');
            if ($documentId !== null) {
                $link  = sprintf('<a href="%s">%s</a>', htmlspecialchars($base . 'documents/' . (int) $documentId), htmlspecialchars((string) $completion->value(CompletionStep::Certificate, 'docid')));
                $html .= $completion->value(CompletionStep::Certificate, 'draft') ? ' Urkunde ' . $link . ' als Entwurf angelegt.' : ' Urkunde ' . $link . ' ausgestellt.';
            }
        }
        (new PersonalLogManager())->addEntry($p->personnel_id, PersonalLogManager::TYPE_TRAINING, $html, self::EDITOR, [
            'change_type' => 'training_completed', 'course_id' => $p->course_id, 'participation_id' => $p->id, 'passed' => $completion->passed,
        ]);

        return StepResult::done('eingetragen');
    }

    private function notify(Completion $completion): StepResult
    {
        $p = $this->participation($completion);
        if ($p->personnel_id === null || !Capsule::table('intra_users')->where('aktenid', $p->personnel_id)->exists()) {
            return StepResult::skipped('Kein Konto mit dem Mitarbeiter verknüpft.');
        }
        if ($completion->passed) {
            $documentId = $completion->value(CompletionStep::Certificate, 'document_id');
            $issued     = $documentId !== null && !$completion->value(CompletionStep::Certificate, 'draft');
            Notify::personnel([$p->personnel_id], 'Glückwunsch, du hast den ' . $p->course->title . ' bestanden.', $issued ? 'documents/' . (int) $documentId : 'training/courses/' . $p->course_id, self::resultLabel($p) ?: null);
        } else {
            Notify::personnel([$p->personnel_id], $p->course->title . ' nicht bestanden', 'training/courses/' . $p->course_id, 'Die Leitung hat den Lehrgang abgeschlossen.');
        }

        return StepResult::done('Benachrichtigt');
    }

    private function announce(Completion $completion): StepResult
    {
        $p        = $this->participation($completion);
        $settings = Settings::of($p->course->type);
        $text     = mb_substr(self::fill($settings['announce_text'], $p), 0, 500);
        if ($settings['announce_channel'] === 'discord') {
            if ($settings['announce_webhook'] === '') {
                return StepResult::failed('Keine Webhook-URL eingetragen.');
            }
            DiscordWebhook::post($settings['announce_webhook'], $text);

            return StepResult::done('Discord');
        }
        Capsule::table('intra_training_announcements')->updateOrInsert(['participation_id' => $p->id], [
            'text' => $text, 'expires_at' => Carbon::now()->addDays(Settings::ANNOUNCE_DAYS), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]);

        return StepResult::done('Dashboard');
    }

    private function followUp(Completion $completion): StepResult
    {
        $p        = $this->participation($completion);
        $settings = Settings::of($p->course->type);
        [$kind, $id] = array_pad(explode(':', $settings['follow_up_target'], 2), 2, '');
        if (!in_array($kind, ['role', 'user'], true) || (int) $id <= 0) {
            return StepResult::failed('Niemand für die Folgeaufgabe eingetragen.');
        }
        $title = mb_substr(self::fill($settings['follow_up_title'], $p), 0, 200);
        $due   = $settings['follow_up_days'] > 0 ? Carbon::today()->addDays($settings['follow_up_days']) : null;
        // Bei einer Rolle bekommt jedes aktive Konto eine eigene Aufgabe.
        $users = $kind === 'user' ? [(int) $id] : Capsule::table('intra_users')->where('role', (int) $id)->where('is_active', 1)
            ->orderBy('id')->pluck('id')->map(static fn ($u): int => (int) $u)->all();
        if ($users === []) {
            return StepResult::skipped('Die Rolle hat keine aktiven Konten.');
        }
        foreach ($users as $userId) {
            Capsule::table('intra_training_follow_ups')->updateOrInsert(['participation_id' => $p->id, 'user_id' => $userId], [
                'personnel_id' => $p->personnel_id,
                'role_id'      => $kind === 'role' ? (int) $id : null,
                'title'        => $title,
                'due_on'       => $due?->toDateString(),
                'created_at'   => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ]);
        }
        $link = $p->personnel_id !== null ? 'personnel/profile?id=' . $p->personnel_id : 'training/manage/' . $p->course_id;
        Notify::users($users, 'Aufgabe: ' . $title, $link, $due !== null ? 'Frist ' . $due->format('d.m.Y') : null);

        return StepResult::done($kind === 'user' ? 'an Person' : 'an ' . count($users) . ' in der Rolle');
    }

    /** Platzhalter in Bekanntgabe und Folgeaufgabe. */
    public static function fill(string $text, Participation $p): string
    {
        return strtr($text, [
            '{person.name}'       => $p->name(),
            '{lehrgang.titel}'    => $p->course->title,
            '{lehrgang.art}'      => $p->course->type->name,
            '{qualifikation.name}' => (string) Qualifications::label($p->course->type->pass_qualification),
        ]);
    }

    /** „86 %, gut“, leer ohne Ergebnis. */
    public static function resultLabel(Participation $p): string
    {
        return implode(', ', array_filter([
            $p->result_percent !== null ? Attempt::percentLabel((float) $p->result_percent) : null,
            $p->grade,
        ]));
    }

    /**
     * Gezählte Versuche der Pflicht-Teile, in Prüfungsreihenfolge.
     *
     * @return list<array{0: \Plugin\Training\Models\Exam, 1: Attempt}>
     */
    public static function results(Participation $p): array
    {
        $board = ExamBoard::for($p->course, [$p]);
        $out   = [];
        foreach ($board->parts as $part) {
            $attempt = $part->required ? $board->state($p, $part)->result() : null;
            if ($attempt !== null) {
                $out[] = [$part, $attempt];
            }
        }

        return $out;
    }

    /**
     * Wer unterschreibt, als Mitarbeiter (für die Aussteller-Felder) und als
     * Konto (`issued_by`).
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function signer(Participation $p): array
    {
        $type = $p->course->type;
        $user = match ($type->signer_mode) {
            Settings::SIGNER_FIXED  => $type->signer_user_id,
            Settings::SIGNER_GRADER => Attempt::query()->where('participation_id', $p->id)->whereNotNull('graded_by')->orderByDesc('submitted_at')->orderByDesc('id')->value('graded_by'),
            default                 => $p->course->lead_user_id,
        };
        $user = $user !== null ? (int) $user : null;
        $personnel = $user !== null ? Capsule::table('intra_users')->where('id', $user)->value('aktenid') : null;

        return [$personnel !== null ? (int) $personnel : null, $user];
    }

    /** @return array<string, string> */
    private function variables(Participation $p): array
    {
        $course  = $p->course;
        $type    = $course->type;
        $points  = 0.0;
        $max     = 0.0;
        $examDay = null;
        foreach (self::results($p) as [, $attempt]) {
            $points += (float) $attempt->points;
            $max    += (float) $attempt->max_points;
            if ($attempt->submitted_at !== null && ($examDay === null || $attempt->submitted_at->greaterThan($examDay))) {
                $examDay = $attempt->submitted_at;
            }
        }
        $hours        = 0.0;
        $firstSession = null;
        foreach ($course->sessions as $session) {
            $hours += max(0, $session->ends_at->getTimestamp() - $session->starts_at->getTimestamp()) / 3600;
            if ($firstSession === null || $session->starts_at->lessThan($firstSession)) {
                $firstSession = $session->starts_at;
            }
        }
        $lead = $course->lead;
        $leadName = null;
        if ($lead !== null) {
            $leadName = Personnel::query()->find((int) $lead->aktenid)?->formalName() ?? ($lead->fullname ?: $lead->username);
        }

        return array_merge(CertificateVariables::make(
            $course->title,
            $type->name,
            $type->short,
            // Ohne festes Datum am Lehrgang zählen erster Termin und Abschluss
            // der Teilnahme, damit das Zertifikat keine leeren Zeilen hat.
            $course->starts_on ?? $firstSession,
            $course->ends_on ?? $p->finished_at,
            $leadName,
            $hours,
            $examDay ?? $p->finished_at,
            $p->result_percent !== null ? (float) $p->result_percent : null,
            null,
            $max > 0 ? $points : null,
            $max > 0 ? $max : null,
            $this->qualificationName($p),
            $p->expires_on,
        ), ['pruefung.note' => (string) $p->grade]);
    }

    /** Name der Qualifikation in der Form, die zum Geschlecht passt. */
    private function qualificationName(Participation $p): ?string
    {
        $key = $p->course->type->pass_qualification;
        if ($key === null || $key === '') {
            return null;
        }
        [$area, $value] = explode(':', $key, 2) + [1 => ''];
        $gender = $p->personnel !== null ? (int) $p->personnel->geschlecht : null;
        $skill  = match ($area) {
            'rd'    => AmbSkill::query()->find((int) $value),
            'fw'    => FdSkill::query()->find((int) $value),
            default => null,
        };

        return $skill?->displayName($gender) ?? Qualifications::label($key);
    }

    private function participation(Completion $completion): Participation
    {
        return $this->participations[$completion->participationId] ??= Participation::query()
            ->with(['course.type', 'course.lead', 'course.sessions', 'personnel'])
            ->find($completion->participationId)
            ?? throw new RuntimeException('Teilnahme ' . $completion->participationId . ' gibt es nicht mehr.');
    }
}
