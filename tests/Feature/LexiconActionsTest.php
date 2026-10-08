<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Die Knöpfe am Lexikoneintrag (Archivieren, Anpinnen, Bearbeiternamen)
 * lesen Kennung und Aktion über EntryActionRequest. Vorher kam die
 * Kennung aus filter_input(INPUT_POST), das in den Tests nie etwas fand.
 * Speichern und Editorfelder prüft LexiconEditorTest.
 */
final class LexiconActionsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin']]);
    }

    private function eintrag(): int
    {
        return (int) Capsule::table('intra_kb_entries')->insertGetId(['type' => 'general', 'title' => 'Eintrag ' . uniqid()]);
    }

    /**
     * Meldung des letzten Requests, danach leer, damit die nächste nicht die alte sieht.
     *
     * @phpstan-impure
     */
    private function flash(): ?string
    {
        $text = $_SESSION['flash']['text'] ?? null;
        unset($_SESSION['flash']);

        return is_string($text) ? $text : null;
    }

    /** @return array<string,mixed> */
    private function zeile(int $id): array
    {
        return (array) Capsule::table('intra_kb_entries')->where('id', $id)->first();
    }

    #[Test]
    public function archivieren_und_wiederherstellen(): void
    {
        $id = $this->eintrag();

        $response = $this->post('/lexicon/archive', ['id' => (string) $id, 'action' => 'archive']);
        $this->assertRedirect($response, '/lexicon/view?id=' . $id);
        $this->assertSame(1, (int) $this->zeile($id)['is_archived']);

        $this->post('/lexicon/archive', ['id' => (string) $id, 'action' => 'restore']);
        $this->assertSame(0, (int) $this->zeile($id)['is_archived']);
    }

    #[Test]
    public function anpinnen_und_loesen(): void
    {
        $id = $this->eintrag();

        $this->assertRedirect($this->post('/lexicon/pin', ['id' => (string) $id, 'action' => 'pin']), '/lexicon/index');
        $this->assertSame(1, (int) $this->zeile($id)['is_pinned']);

        $this->post('/lexicon/pin', ['id' => (string) $id, 'action' => 'unpin']);
        $this->assertSame(0, (int) $this->zeile($id)['is_pinned']);
    }

    #[Test]
    public function bearbeiternamen_ein_und_ausblenden(): void
    {
        $id = $this->eintrag();

        $this->post('/lexicon/toggle-editor', ['id' => (string) $id]);
        $this->assertSame(1, (int) $this->zeile($id)['hide_editor']);

        $this->post('/lexicon/toggle-editor', ['id' => (string) $id]);
        $this->assertSame(0, (int) $this->zeile($id)['hide_editor']);
    }

    #[Test]
    public function falsche_aktion_oder_kennung_aendert_nichts(): void
    {
        $id = $this->eintrag();

        $response = $this->post('/lexicon/archive', ['id' => (string) $id, 'action' => 'pin']);
        $this->assertRedirect($response, '/lexicon/index');
        $meldungen = [$this->flash()];

        $this->post('/lexicon/pin', ['id' => 'abc', 'action' => 'pin']);
        $meldungen[] = $this->flash();

        $this->post('/lexicon/toggle-editor', ['id' => (string) $id, 'hide_editor' => '1']);
        $meldungen[] = $this->flash();

        $this->assertSame(['Ungültige Anfrage', 'Ungültige Anfrage', 'Ungültige ID'], $meldungen);

        $zeile = $this->zeile($id);
        $this->assertSame(0, (int) $zeile['is_archived']);
        $this->assertSame(0, (int) $zeile['is_pinned']);
        $this->assertSame(0, (int) $zeile['hide_editor']);
    }

    #[Test]
    public function eintrag_mit_fremdem_feld_wird_nicht_gespeichert(): void
    {
        $response = $this->post('/lexicon/create', ['type' => 'general', 'title' => 'Fremdfeld', 'created_by' => '1']);

        $this->assertOk($response);
        $this->assertBodyContains('Ungültige Eingabe.', $response);
        $this->assertFalse(Capsule::table('intra_kb_entries')->where('title', 'Fremdfeld')->exists());
    }
}
