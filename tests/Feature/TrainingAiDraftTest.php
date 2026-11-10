<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\FabricaClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\AiDrafts;
use Plugin\Training\Models\CourseType;
use Plugin\Training\Models\Module;
use Plugin\Training\Models\Question;
use Plugin\Training\Models\Topic;
use Plugin\Training\Models\Unit;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * KI-Entwürfe für den Fragenpool, mit einer gemockten Konsole: Knopf nur bei
 * Freischaltung, Entwürfe landen ausgeschaltet mit Quelle, Anschalten gibt
 * sie frei, und ohne Bestätigung eines Admins geht kein Material hinaus.
 */
final class TrainingAiDraftTest extends FeatureTestCase
{
    private const INSTANCE = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    private CourseType $type;
    private Unit $unit;
    private int $article;

    protected function setUp(): void
    {
        parent::setUp();
        Capsule::table('intra_config')->where('config_key', AiDrafts::ACK_KEY)->update(['config_value' => '']);

        $this->type = new CourseType();
        $this->type->name            = 'Atemschutz ' . bin2hex(random_bytes(3));
        $this->type->short           = 'AGT';
        $this->type->enrollment_mode = 'open';
        $this->type->save();
        $module = new Module();
        $module->course_type_id = $this->type->id;
        $module->title          = 'Grundlagen';
        $module->save();
        $this->unit = new Unit();
        $this->unit->module_id = $module->id;
        $this->unit->title     = 'Atmung';
        $this->unit->kind      = 'text';
        $this->unit->content   = ['doc' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Der Mensch atmet etwa 15 Mal pro Minute.']]]]]];
        $this->unit->save();
        $this->article = (int) Capsule::table('intra_kb_entries')->insertGetId([
            'type' => 'measure', 'title' => 'Atemschutzüberwachung', 'content' => '<p>Der Überwacher notiert die Zeiten.</p>',
            'mass_durchfuehrung' => '<p>Druck alle 10 Minuten abfragen.</p>', 'is_archived' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        AiDrafts::useClient(null);
        Capsule::table('intra_kb_entries')->where('id', $this->article)->delete();
        Capsule::table('intra_config')->where('config_key', AiDrafts::ACK_KEY)->update(['config_value' => '']);
        parent::tearDown();
    }

    /** @param list<Response> $answers */
    private function console(array $answers): void
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler($answers));
        $stack->push(Middleware::history($this->sent));
        AiDrafts::useClient(new FabricaClient('https://console.example.test', self::INSTANCE, self::INSTANCE . '.' . str_repeat('s', 43), new Client(['handler' => $stack])));
    }

    private function state(bool $enabled = true): Response
    {
        return new Response(200, [], (string) json_encode(['available' => true, 'enabled' => $enabled, 'budgetCents' => 500, 'spentCents' => 0, 'remainingCents' => 500]));
    }

    /** @param list<string> $permissions */
    private function as(array $permissions): void
    {
        $_SESSION = [];
        $this->actingAs((int) FixtureFactory::user()->id, ['permissions' => $permissions, 'cirs_username' => 'test']);
    }

    #[Test]
    public function drafts_arrive_switched_off_with_their_source_and_turn_on_like_any_question(): void
    {
        $this->as(['admin']);
        $topic = new Topic();
        $topic->course_type_id = $this->type->id;
        $topic->name           = 'Atemschutz';
        $topic->save();
        $typeUrl = '/training/types/' . $this->type->id;

        $this->console([$this->state(false)]);
        $this->assertBodyNotContains('Mit KI vorschlagen', $this->get($typeUrl));

        $draft = static fn (string $body, array $spec, string $kind = 'short_answer'): array => [
            'type' => $kind, 'body' => $body, 'explanation' => 'Steht im Artikel.', 'topicId' => $topic->id,
            'source' => ['title' => 'Atmung', 'page' => null], 'spec' => $spec,
        ];
        $this->console([
            $this->state(),
            $this->state(),
            new Response(200, [], (string) json_encode([
                'questions' => [
                    $draft('Wie oft atmet ein Mensch pro Minute?', ['accepted' => ['15'], 'tolerant' => true]),
                    $draft('Kaputt', ['options' => ['Nur eine'], 'correct' => [0], 'partial' => false], 'single_choice'),
                ],
                'rejected' => 1, 'costCents' => 8, 'remainingCents' => 492,
            ])),
            $this->state(),
        ]);
        $this->assertBodyContains('Mit KI vorschlagen', $this->get($typeUrl));
        $form = $this->get($typeUrl . '/questions/ai');
        $this->assertBodyContains('Artikel „Atmung“', $form);
        $this->assertBodyContains('Atemschutzüberwachung', $form);
        $this->assertBodyContains('Noch 5,00 $ in diesem Monat', $form);
        $this->assertBodyNotContains('value="free_text"', $form);

        $this->assertRedirect($this->post($typeUrl . '/questions/ai', [
            'units' => [(string) $this->unit->id], 'articles' => [(string) $this->article], 'count' => '5',
            'types' => ['short_answer', 'free_text'], 'acknowledge' => '1',
        ]));
        $sent = json_decode((string) $this->sent[2]['request']->getBody(), true);
        $this->assertStringEndsWith('/api/instance/ai/questions', (string) $this->sent[2]['request']->getUri());
        $this->assertSame(['kind' => 'text', 'title' => 'Atmung', 'text' => 'Der Mensch atmet etwa 15 Mal pro Minute.'], $sent['sources'][0]);
        $this->assertSame('Atemschutzüberwachung', $sent['sources'][1]['title']);
        $this->assertStringContainsString('Durchführung: Druck alle 10 Minuten abfragen.', $sent['sources'][1]['text']);
        $this->assertStringContainsString('Der Überwacher notiert die Zeiten.', $sent['sources'][1]['text']);
        $this->assertSame(['short_answer'], $sent['types'], 'Freitext geht nie an die KI.');
        $this->assertTrue(AiDrafts::acknowledged());

        $question = Question::query()->where('course_type_id', $this->type->id)->sole();
        $this->assertFalse($question->is_active);
        $this->assertTrue($question->ai_draft);
        $this->assertSame('Atmung', $question->source);
        $this->assertSame($topic->id, $question->topic_id);
        $this->assertBodyContains('KI-Entwurf', $this->get($typeUrl, ['query' => ['draft' => '1']]));

        $this->post('/training/questions/' . $question->id . '/active', ['active' => '1']);
        $question->refresh();
        $this->assertTrue($question->is_active);
        $this->assertFalse($question->ai_draft);
    }

    #[Test]
    public function without_an_admins_confirmation_nothing_leaves_ignis(): void
    {
        $this->as(['training.exams']);
        $this->console([$this->state(), $this->state()]);

        $this->assertBodyContains('Erst ein Administrator', $this->get('/training/types/' . $this->type->id . '/questions/ai'));
        $this->assertRedirect($this->post('/training/types/' . $this->type->id . '/questions/ai', ['units' => [(string) $this->unit->id], 'acknowledge' => '1']));
        $this->assertCount(1, $this->sent, 'Nur der Stand wurde gefragt, kein Material geschickt.');
        $this->assertSame(0, Question::query()->where('course_type_id', $this->type->id)->count());
        $this->assertFalse(AiDrafts::acknowledged());
    }
}
