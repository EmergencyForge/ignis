<?php

declare(strict_types=1);

namespace Plugin\Training\Controllers;

use App\Auth\Permissions;
use App\Session\SessionManager;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use EmergencyForge\Training\Question\QuestionType;
use Plugin\Training\AiDrafts;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Topic;
use RuntimeException;

/**
 * „Fragen mit KI vorschlagen“ im Fragenpool einer Lehrgangsart
 * (`training.exams`). Die Entwürfe landen ausgeschaltet im Pool und werden
 * dort geprüft.
 */
final class AiDraftController extends BaseController
{
    /** GET /training/types/{id}/questions/ai */
    public function form(Request $request, string $id): Response
    {
        $type = CourseType::query()->find((int) $id);
        if ($type === null) {
            return self::notFound();
        }
        $draft = SessionManager::get('training_ai_draft');
        SessionManager::forget('training_ai_draft');

        return $this->page('types/ai', [
            'type'         => $type,
            'state'        => AiDrafts::state(true),
            'acknowledged' => AiDrafts::acknowledged(),
            'admin'        => Permissions::check(['admin']),
            'sources'      => AiDrafts::sources($type),
            'articles'     => AiDrafts::articles(),
            'topics'       => Topic::query()->where('course_type_id', $type->id)->orderBy('position')->orderBy('name')->get()->all(),
            'draft'        => is_array($draft) ? $draft : null,
        ]);
    }

    /** POST /training/types/{id}/questions/ai */
    public function generate(Request $request, string $id): Response
    {
        $type   = CourseType::query()->find((int) $id);
        $client = AiDrafts::client();
        if ($type === null) {
            return self::notFound();
        }
        $post     = $request->post;
        $units    = array_map('intval', (array) ($post['units'] ?? []));
        $articles = array_values(array_unique(array_map('intval', (array) ($post['articles'] ?? []))));
        $count    = max(1, min(30, (int) ($post['count'] ?? 10)));
        $types    = array_values(array_filter(array_map(static fn (mixed $v): ?QuestionType => QuestionType::tryFrom((string) $v), (array) ($post['types'] ?? [])), static fn (?QuestionType $t): bool => $t !== null && $t !== QuestionType::FreeText));
        $topicId  = (int) ($post['topic_id'] ?? 0);
        $topicId  = $topicId > 0 && Topic::query()->where('course_type_id', $type->id)->whereKey($topicId)->exists() ? $topicId : null;
        $back     = static function (string $message) use ($type, $units, $articles, $count, $types, $topicId): Response {
            SessionManager::set('training_ai_draft', ['units' => $units, 'articles' => $articles, 'count' => $count, 'types' => array_map(static fn (QuestionType $t): string => $t->value, $types), 'topic_id' => $topicId]);

            return self::back('training/types/' . $type->id . '/questions/ai', $message);
        };

        if ($client === null) {
            return $back('Diese Instanz ist nicht mit der EmergencyForge-Konsole verbunden.');
        }
        if (!AiDrafts::acknowledged()) {
            if (!Permissions::check(['admin'])) {
                return $back('Ein Administrator muss den Hinweis zum Datenschutz zuerst einmal bestätigen.');
            }
            if (empty($post['acknowledge'])) {
                return $back('Bitte bestätige den Hinweis zum Datenschutz.');
            }
            AiDrafts::acknowledge(self::userId() ?: null);
            (new AuditLogger())->log(self::userId(), 'KI-Fragenentwürfe freigegeben', 'Material für Fragenentwürfe darf an Anthropic gehen.', 'Ausbildung', 0, ['change_type' => 'training_ai_acknowledged']);
        }

        $documents = [];
        foreach (AiDrafts::sources($type) as $source) {
            if (in_array($source['unit']->id, $units, true) && ($document = AiDrafts::document($source)) !== null) {
                $documents[] = $document;
            }
        }
        foreach ($articles as $article) {
            if (($document = AiDrafts::article($article)) !== null) {
                $documents[] = $document;
            }
        }
        $upload = $request->files['file'] ?? null;
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $content = (int) ($upload['error'] ?? 0) === UPLOAD_ERR_OK ? (string) file_get_contents((string) $upload['tmp_name']) : '';
            if (!str_starts_with($content, '%PDF-')) {
                return $back('Die hochgeladene Datei ist keine PDF.');
            }
            $documents[] = ['title' => mb_substr(basename((string) ($upload['name'] ?? 'unterlage.pdf')), 0, 200), 'kind' => 'pdf', 'content' => $content];
        }
        $pdfBytes = array_sum(array_map(static fn (array $doc): int => $doc['kind'] === 'pdf' ? strlen($doc['content']) : 0, $documents));
        if ($documents === []) {
            return $back('Bitte wähle Material aus oder lade eine PDF hoch.');
        }
        if (count($documents) > AiDrafts::MAX_SOURCES) {
            return $back('Höchstens ' . AiDrafts::MAX_SOURCES . ' Quellen auf einmal. Bitte weniger auswählen.');
        }
        if ($pdfBytes > AiDrafts::MAX_PDF_BYTES) {
            return $back('Die PDFs sind zusammen größer als 20 MB. Bitte weniger auf einmal wählen.');
        }

        @set_time_limit(300);
        try {
            $result = $client->aiQuestions(AiDrafts::request($type, $documents, $count, $types, $topicId));
        } catch (RuntimeException $e) {
            AiDrafts::forgetState();

            return $back($e->getMessage());
        }
        AiDrafts::forgetState();
        $stored   = AiDrafts::store($type, $result['questions'], self::userId() ?: null);
        $rejected = $result['rejected'] + $stored['rejected'];
        (new AuditLogger())->log(self::userId(), 'KI-Fragenentwürfe angelegt', sprintf('%s: %d Entwürfe, %d verworfen.', htmlspecialchars($type->name), $stored['added'], $rejected), 'Ausbildung', 0, [
            'change_type' => 'training_questions_ai', 'course_type_id' => $type->id, 'added' => $stored['added'], 'rejected' => $rejected, 'cost_cents' => $result['costCents'],
        ]);

        if ($stored['added'] === 0) {
            return $back('Die KI hat keine brauchbaren Fragen geliefert. Versuche es mit anderem Material oder anderen Arten.');
        }

        return self::back('training/types/' . $type->id . '?draft=1#fragenpool', null, sprintf(
            '%s als Entwurf angelegt%s. Prüfe sie und schalte sie an, dann kommen sie in Prüfungen. Kosten: %s $.',
            $stored['added'] === 1 ? 'Eine Frage' : $stored['added'] . ' Fragen',
            $rejected > 0 ? ', ' . $rejected . ' verworfen, weil sie doppelt oder unvollständig waren' : '',
            number_format($result['costCents'] / 100, 2, ',', '.'),
        ));
    }
}
