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
    public function legacySetEscapesParameters(): void
    {
        Flash::set('user', 'new-password', ['username' => '<script>alert(1)</script>', 'pass' => 'safe']);

        $flash = Flash::get();
        $this->assertStringNotContainsString('<script>', $flash['text']);
    }
}
