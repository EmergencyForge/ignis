<?php

declare(strict_types=1);

namespace Plugin\Training;

use App\Auth\FabricaClient;
use App\Plugins\PluginLoader;
use DomainException;
use EmergencyForge\Editor\Renderer;
use EmergencyForge\Training\Question\QuestionType;
use EmergencyForge\Training\UnitKind;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\Topic;
use Plugin\Training\Models\Unit;
use Throwable;

/**
 * Fragenentwürfe von der KI, über die EmergencyForge-Konsole. ignis hält
 * keinen Schlüssel: ob es die KI gibt und was sie kosten darf, entscheidet
 * die Konsole je Instanz.
 */
final class AiDrafts
{
    /** Einmal von einem Admin bestätigt, dass Material an Anthropic geht. */
    public const ACK_KEY = 'TRAINING_AI_ACKNOWLEDGED';

    /** Roh-PDF je Durchgang; als base64 bleibt das unter der Grenze der Konsole. */
    public const MAX_PDF_BYTES = 20 * 1024 * 1024;

    /** So viele Quellen nimmt die Konsole je Durchgang. */
    public const MAX_SOURCES = 20;

    private const STATE_TTL = 300;
    private const MAX_TEXT  = 400_000;

    /** Felder der Wissensdatenbank neben dem Fließtext, mit ihrer Überschrift. */
    private const KB_FIELDS = [
        'med_wirkstoff'           => 'Wirkstoff',
        'med_wirkstoffgruppe'     => 'Wirkstoffgruppe',
        'med_wirkmechanismus'     => 'Wirkmechanismus',
        'med_indikationen'        => 'Indikationen',
        'med_kontraindikationen'  => 'Kontraindikationen',
        'med_uaw'                 => 'Unerwünschte Wirkungen',
        'med_dosierung'           => 'Dosierung',
        'med_besonderheiten'      => 'Besonderheiten',
        'mass_wirkprinzip'        => 'Wirkprinzip',
        'mass_indikationen'       => 'Indikationen',
        'mass_kontraindikationen' => 'Kontraindikationen',
        'mass_risiken'            => 'Risiken',
        'mass_alternativen'       => 'Alternativen',
        'mass_durchfuehrung'      => 'Durchführung',
    ];

    private static ?FabricaClient $client = null;

    /** Tests setzen hier einen Client mit gemocktem HTTP. */
    public static function useClient(?FabricaClient $client): void
    {
        self::$client = $client;
        unset($_SESSION['training_ai_state']);
    }

    public static function client(): ?FabricaClient
    {
        if (self::$client !== null) {
            return self::$client;
        }
        try {
            return FabricaClient::fromEnvironment();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Stand der Freischaltung, ein paar Minuten in der Sitzung gemerkt, damit
     * nicht jeder Aufruf des Fragenpools die Konsole fragt. `null` heißt:
     * keine Konsole oder nicht erreichbar.
     *
     * @return array{available:bool,enabled:bool,budgetCents:int,spentCents:int,remainingCents:int}|null
     */
    public static function state(bool $fresh = false): ?array
    {
        $cached = $_SESSION['training_ai_state'] ?? null;
        if (!$fresh && is_array($cached) && ($cached['at'] ?? 0) > time() - self::STATE_TTL) {
            return $cached['state'];
        }
        $client = self::client();
        if ($client === null) {
            return null;
        }
        try {
            $state = $client->aiState();
        } catch (Throwable) {
            $state = null;
        }
        $_SESSION['training_ai_state'] = ['at' => time(), 'state' => $state];

        return $state;
    }

    public static function offered(): bool
    {
        $state = self::state();

        return $state !== null && $state['available'] && $state['enabled'];
    }

    public static function forgetState(): void
    {
        unset($_SESSION['training_ai_state']);
    }

    public static function acknowledged(): bool
    {
        return (string) Capsule::table('intra_config')->where('config_key', self::ACK_KEY)->value('config_value') !== '';
    }

    public static function acknowledge(?int $userId): void
    {
        // ConfigManager schreibt nur Werte, die man in den Einstellungen ändern darf.
        Capsule::table('intra_config')->updateOrInsert(['config_key' => self::ACK_KEY], [
            'config_value' => date('c'),
            'config_type'  => 'string',
            'category'     => 'funktionen',
            'is_editable'  => 0,
            'updated_by'   => $userId,
        ]);
    }

    /**
     * Was aus dem Lehrplan der Lehrgangsart als Material taugt: Artikel,
     * PDFs, Präsentationen über ihre umgewandelte PDF und eingebundene
     * Wissensartikel.
     *
     * @return list<array{unit:Unit,module:string,kind:'text'|'pdf'|'kb',label:string,icon:string,converted:bool}>
     */
    public static function sources(CourseType $type): array
    {
        $kb      = self::knowledgeBase();
        $files   = new FileStore();
        $sources = [];
        foreach ($type->modules()->with('units.file')->get() as $module) {
            foreach ($module->units as $unit) {
                $kind = $unit->type();
                if ($kind === UnitKind::Text && self::unitText($unit) !== '') {
                    $sources[] = ['unit' => $unit, 'module' => $module->title, 'kind' => 'text', 'label' => 'Artikel „' . $unit->title . '“', 'icon' => 'fa-align-left', 'converted' => false];
                } elseif ($kind === UnitKind::File && $unit->file !== null && ($unit->file->isPdf() ? $files->path($unit->file) : $files->pdfPath($unit->file)) !== null) {
                    $sources[] = ['unit' => $unit, 'module' => $module->title, 'kind' => 'pdf', 'label' => $unit->file->original_name, 'icon' => $unit->file->icon(), 'converted' => !$unit->file->isPdf()];
                } elseif ($kind === UnitKind::KnowledgeArticle && $kb && $unit->kb_entry_id !== null) {
                    $sources[] = ['unit' => $unit, 'module' => $module->title, 'kind' => 'kb', 'label' => 'Wissensartikel „' . $unit->title . '“', 'icon' => 'fa-book', 'converted' => false];
                }
            }
        }

        return $sources;
    }

    /**
     * Gelesenes Material einer Quelle aus dem Lehrplan.
     *
     * @param array{unit:Unit,kind:'text'|'pdf'|'kb'} $source
     * @return array{title:string,kind:'text'|'pdf',content:string}|null
     */
    public static function document(array $source): ?array
    {
        $unit = $source['unit'];
        if ($source['kind'] === 'text') {
            return ['title' => $unit->title, 'kind' => 'text', 'content' => self::unitText($unit)];
        }
        if ($source['kind'] === 'kb') {
            return $unit->kb_entry_id !== null ? self::article($unit->kb_entry_id) : null;
        }
        $file = $unit->file;
        if ($file === null) {
            return null;
        }
        $files = new FileStore();
        $path  = $file->isPdf() ? $files->path($file) : $files->pdfPath($file);
        $data  = $path !== null ? file_get_contents($path) : false;

        return $data === false ? null : ['title' => $file->original_name, 'kind' => 'pdf', 'content' => $data];
    }

    /**
     * Alle nicht archivierten Wissensartikel für die Auswahl „Weitere
     * Wissensartikel“, leer ohne das Plugin.
     *
     * @return array<int, string>
     */
    public static function articles(): array
    {
        return self::knowledgeBase()
            ? array_map('strval', Capsule::table('intra_kb_entries')->where('is_archived', 0)->orderBy('title')->pluck('title', 'id')->all())
            : [];
    }

    /** @return array{title:string,kind:'text',content:string}|null */
    public static function article(int $id): ?array
    {
        $entry = self::knowledgeBase() ? Capsule::table('intra_kb_entries')->where('id', $id)->where('is_archived', 0)->first() : null;
        if ($entry === null) {
            return null;
        }
        $row   = (array) $entry;
        $parts = array_filter([trim((string) ($row['subtitle'] ?? ''))]);
        foreach (self::KB_FIELDS as $column => $label) {
            $value = self::plainText((string) ($row[$column] ?? ''));
            if ($value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }
        $parts[] = self::plainText((string) ($row['content'] ?? ''));
        $text    = trim(implode("\n\n", array_filter($parts)));

        return $text === '' ? null : ['title' => (string) $row['title'], 'kind' => 'text', 'content' => mb_substr($text, 0, self::MAX_TEXT)];
    }

    /** Text aus HTML, Absätze bleiben getrennt. */
    public static function plainText(string $html): string
    {
        $html = preg_replace('#<(br|/p|/li|/h[1-6]|/div|/tr)\b[^>]*>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? $text) ?? $text), 0, self::MAX_TEXT);
    }

    /**
     * Die Anfrage an die Konsole.
     *
     * @param list<array{title:string,kind:'text'|'pdf',content:string}> $documents
     * @param list<QuestionType> $types
     * @return array<string,mixed>
     */
    public static function request(CourseType $type, array $documents, int $count, array $types, ?int $topicId): array
    {
        $topics   = Topic::query()->where('course_type_id', $type->id)->orderBy('position')->orderBy('name')->get(['id', 'name']);
        $existing = Question::query()->where('course_type_id', $type->id)->whereNull('deleted_at')->orderByDesc('id')->limit(2000)->pluck('body')
            ->map(static fn (string $body): string => mb_substr($body, 0, 2000))->all();

        return array_filter([
            'sources' => array_map(static fn (array $doc): array => $doc['kind'] === 'pdf'
                ? ['kind' => 'pdf', 'title' => mb_substr($doc['title'], 0, 200), 'data' => base64_encode($doc['content'])]
                : ['kind' => 'text', 'title' => mb_substr($doc['title'], 0, 200), 'text' => $doc['content']], $documents),
            'count'    => max(1, min(30, $count)),
            'types'    => $types !== [] ? array_map(static fn (QuestionType $t): string => $t->value, $types) : null,
            'topics'   => $topics->map(static fn (object $t): array => ['id' => (int) $t->id, 'title' => mb_substr((string) $t->name, 0, 200)])->all(),
            'topicId'  => $topicId,
            'existing' => $existing,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Legt die Entwürfe ausgeschaltet an. Was ignis selbst nicht speichern
     * würde, fällt weg und zählt zu den verworfenen.
     *
     * @param list<array<string,mixed>> $drafts
     * @return array{added:int,rejected:int}
     */
    public static function store(CourseType $type, array $drafts, ?int $userId): array
    {
        $topics   = Topic::query()->where('course_type_id', $type->id)->pluck('id')->all();
        $added    = 0;
        $rejected = 0;
        foreach ($drafts as $draft) {
            $kind = QuestionType::tryFrom((string) ($draft['type'] ?? ''));
            $body = mb_substr(trim((string) ($draft['body'] ?? '')), 0, 5000);
            $spec = is_array($draft['spec'] ?? null) ? $draft['spec'] : null;
            try {
                if ($kind === null || $kind === QuestionType::FreeText) {
                    throw new DomainException();
                }
                Pool::validate($kind, $body, 1.0, $spec);
            } catch (InvalidArgumentException | DomainException) {
                $rejected++;
                continue;
            }
            $topic       = (int) ($draft['topicId'] ?? 0);
            $source      = is_array($draft['source'] ?? null) ? $draft['source'] : [];
            $title       = trim((string) ($source['title'] ?? ''));
            $page        = (int) ($source['page'] ?? 0);
            $explanation = trim((string) ($draft['explanation'] ?? ''));

            $question = new Question(['course_type_id' => $type->id, 'version' => 1]);
            $question->ai_draft = true;
            $question->source   = $title === '' ? null : mb_substr($title . ($page > 0 ? ', S. ' . $page : ''), 0, 255);
            Pool::save($question, [
                'type'        => $kind->value,
                'body'        => $body,
                'points'      => 1.0,
                'spec'        => $spec,
                'explanation' => $explanation === '' ? null : mb_substr($explanation, 0, 5000),
                'topic_id'    => in_array($topic, $topics, true) ? $topic : null,
                'is_active'   => false,
            ], $userId);
            $added++;
        }

        return ['added' => $added, 'rejected' => $rejected];
    }

    private static function knowledgeBase(): bool
    {
        try {
            return app(PluginLoader::class)->isActive('knowledge-base');
        } catch (Throwable) {
            return false;
        }
    }

    private static function unitText(Unit $unit): string
    {
        $doc = $unit->content['doc'] ?? null;

        return is_array($doc) ? self::plainText((new Renderer())->render($doc)->html) : '';
    }
}
