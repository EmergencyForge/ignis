<?php

namespace Tests\Unit\Helpers;

use App\Helpers\Flash;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function successSetsFlashWithCorrectType(): void
    {
        Flash::success('Gespeichert');

        $flash = $_SESSION['flash'];
        $this->assertSame('success', $flash['type']);
        $this->assertSame('Erfolg!', $flash['title']);
        $this->assertSame('Gespeichert', $flash['text']);
    }

    #[Test]
    public function errorSetsFlashWithDangerType(): void
    {
        Flash::error('Fehlgeschlagen');

        $flash = $_SESSION['flash'];
        $this->assertSame('danger', $flash['type']);
        $this->assertSame('Fehler!', $flash['title']);
        $this->assertSame('Fehlgeschlagen', $flash['text']);
    }

    #[Test]
    public function warningSetsFlashCorrectly(): void
    {
        Flash::warning('Aufpassen');

        $flash = $_SESSION['flash'];
        $this->assertSame('warning', $flash['type']);
        $this->assertSame('Achtung!', $flash['title']);
    }

    #[Test]
    public function infoSetsFlashCorrectly(): void
    {
        Flash::info('Hinweis');

        $flash = $_SESSION['flash'];
        $this->assertSame('info', $flash['type']);
        $this->assertSame('Information', $flash['title']);
    }

    #[Test]
    public function customTitleOverridesDefault(): void
    {
        Flash::success('Text', 'Mein Titel');

        $flash = $_SESSION['flash'];
        $this->assertSame('Mein Titel', $flash['title']);
    }

    #[Test]
    public function getReturnsFlashAndRemovesIt(): void
    {
        Flash::success('Test');

        $flash = Flash::get();

        $this->assertNotNull($flash);
        $this->assertSame('success', $flash['type']);
        $this->assertSame('Test', $flash['text']);

        // Should be removed from session
        $this->assertArrayNotHasKey('flash', $_SESSION);
    }

    #[Test]
    public function getReturnsNullWhenNoFlash(): void
    {
        $this->assertNull(Flash::get());
    }

    #[Test]
    public function dangerIsAliasForError(): void
    {
        Flash::danger('Problem');

        $flash = $_SESSION['flash'];
        $this->assertSame('danger', $flash['type']);
        $this->assertSame('Fehler!', $flash['title']);
    }

    #[Test]
    public function laterFlashOverwritesEarlierOne(): void
    {
        Flash::success('Erste');
        Flash::error('Zweite');

        $flash = Flash::get();
        $this->assertSame('danger', $flash['type']);
        $this->assertSame('Zweite', $flash['text']);
    }

    #[Test]
    public function legacySetWorksWithKnownKeys(): void
    {
        Flash::set('role', 'deleted');

        $flash = Flash::get();
        $this->assertSame('success', $flash['type']);
        $this->assertStringContainsString('Rolle', $flash['text']);
    }

    #[Test]
    public function legacySetIgnoresUnknownKeys(): void
    {
        Flash::set('nonexistent', 'unknown');

        $this->assertArrayNotHasKey('flash', $_SESSION);
    }

    #[Test]
    public function legacySetReplacesParameters(): void
    {
        Flash::set('user', 'new-password', ['username' => 'Max', 'pass' => 'abc123']);

        $flash = Flash::get();
        $this->assertStringContainsString('Max', $flash['text']);
        $this->assertStringContainsString('abc123', $flash['text']);
    }

    #[Test]
    public function legacySetTreatsUnknownKeysOfPlainTypesAsText(): void
    {
        Flash::set('error', 'Antrag nicht gefunden.');

        $flash = Flash::get();
        $this->assertSame('danger', $flash['type']);
        $this->assertSame('Fehler!', $flash['title']);
        $this->assertSame('Antrag nicht gefunden.', $flash['text']);
    }

    #[Test]
    public function legacyTextsCarryNoMarkup(): void
    {
        Flash::set('user', 'new-password', ['username' => 'Max', 'pass' => 'abc123']);
        $this->assertStringNotContainsString('<', Flash::get()['text']);

        Flash::set('warning', 'no-fullname');
        $this->assertStringNotContainsString('<', Flash::get()['text']);
    }

    #[Test]
    public function renderEscapesParametersAndText(): void
    {
        Flash::set('user', 'new-password', ['username' => '<script>alert(1)</script>', 'pass' => 'safe']);

        $html = $this->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    #[Test]
    public function renderWritesTemplateAndNoscriptFallback(): void
    {
        Flash::success('Gespeichert & fertig', 'Titel "A"');

        $html = $this->render();

        $this->assertStringContainsString(
            '<template data-ignis-flash data-variant="success" data-title="Titel &quot;A&quot;"><div>Gespeichert &amp; fertig</div></template>',
            $html,
        );
        $this->assertStringContainsString('<noscript><div class="ignis-alert ignis-alert--success mb-4" id="flash-alert" role="status">', $html);
        $this->assertStringContainsString('<div class="ignis-alert__title">Titel &quot;A&quot;</div>Gespeichert &amp; fertig</div>', $html);
        $this->assertArrayNotHasKey('flash', $_SESSION, 'render() verbraucht die Meldung.');
    }

    #[Test]
    public function renderMarksErrorsAsAlert(): void
    {
        Flash::error('Kaputt');

        $html = $this->render();

        $this->assertStringContainsString('data-variant="danger"', $html);
        $this->assertStringContainsString('ignis-alert--danger mb-4" id="flash-alert" role="alert"', $html);
    }

    #[Test]
    public function renderWritesNothingWithoutFlash(): void
    {
        $this->assertSame('', $this->render());
    }

    private function render(): string
    {
        ob_start();
        Flash::render();

        return (string) ob_get_clean();
    }
}
