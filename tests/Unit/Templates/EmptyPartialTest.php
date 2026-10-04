<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

final class EmptyPartialTest extends TestCase
{
    private const PARTIAL = __DIR__ . '/../../../templates/partials/empty.php';

    /** @param array<string, mixed> $empty */
    private function render(array $empty): string
    {
        ob_start();
        require self::PARTIAL;
        return (string) ob_get_clean();
    }

    public function testRendersTheSharedMarkupAndEscapes(): void
    {
        $html = $this->render([
            'variant' => 'sm', 'tone' => 'ok', 'icon' => 'fa-check',
            'title' => 'Keine offenen <Mängel>', 'text' => 'Alle 16 Fahrzeuge sind einsatzbereit.',
        ]);
        self::assertStringContainsString('class="ignis-empty ignis-empty--sm" data-tone="ok"', $html);
        self::assertStringContainsString('<span class="ignis-empty__glyph" aria-hidden="true"><i class="fa-solid fa-check"></i></span>', $html);
        self::assertStringContainsString('<h3 class="ignis-empty__title">Keine offenen &lt;Mängel&gt;</h3>', $html);
        self::assertStringNotContainsString('ignis-empty__actions', $html);
    }

    public function testFallsBackOnUnknownVariantAndTone(): void
    {
        $html = $this->render(['variant' => 'x', 'tone' => 'y', 'title' => 'T']);
        self::assertStringContainsString('class="ignis-empty" data-tone="neutral"', $html);
    }

    public function testFirstStartDrawsAStillGhostList(): void
    {
        $html = $this->render(['variant' => 'first', 'tone' => 'info', 'title' => 'Noch keine Mitarbeitenden', 'ghostColumns' => 3]);
        self::assertSame(5, substr_count($html, '<div><i></i><i></i><i></i><i></i><i></i></div>'));
        self::assertStringContainsString('class="ignis-empty__ghost" aria-hidden="true" style="--ghost-cols: 3"', $html);
        self::assertStringStartsWith('<section class="ignis-bezel" aria-label="Erste Schritte"><div class="ignis-bezel__head"><i class="fa-solid fa-flag" aria-hidden="true"></i><h2 class="ignis-bezel__title">Erste Schritte</h2></div><div class="ignis-bezel__well ignis-bezel__well--flush"><div class="ignis-empty ignis-empty--first" data-tone="info">', $html);
        self::assertStringEndsWith('</div></div></section>', $html);
        self::assertStringNotContainsString('ignis-bezel', $this->render(['variant' => 'sm', 'title' => 'T']));
    }

    public function testQueryTipsCodeAndActions(): void
    {
        $html = $this->render([
            'variant' => 'sm', 'title' => 'Keine Protokolle gefunden',
            'query' => ['term' => '0499', 'filters' => [['label' => 'Wache 7', 'removeHref' => '/x?a=1&b=2']]],
            'tips' => ['Ohne Filter wird überall gesucht.'],
            'code' => 'HTTP 503',
            'actions' => [['label' => 'Filter entfernen', 'href' => '/x', 'style' => 'secondary', 'attrs' => ['data-ignis-drawer' => '', 'onclick' => 'alert(1)']]],
        ]);
        self::assertStringContainsString('<span class="ignis-empty__term">„0499“</span>', $html);
        self::assertStringContainsString('href="/x?a=1&amp;b=2"', $html);
        self::assertStringContainsString('<ul class="ignis-empty__tips"><li>Ohne Filter wird überall gesucht.</li></ul>', $html);
        self::assertStringContainsString('<p class="ignis-empty__code">HTTP 503</p>', $html);
        self::assertStringContainsString('class="ignis-btn ignis-btn--secondary ignis-btn--sm"', $html);
        self::assertStringContainsString('data-ignis-drawer=""', $html);
        self::assertStringNotContainsString('onclick', $html);
    }

    public function testSkipsTheQueryRowWithoutTermOrSafeFilter(): void
    {
        $html = $this->render([
            'variant' => 'sm', 'title' => 'Keine Treffer',
            'query' => ['term' => '', 'filters' => [['label' => 'Böse', 'removeHref' => 'javascript:alert(1)']]],
        ]);
        self::assertStringNotContainsString('ignis-empty__query', $html);
        self::assertStringNotContainsString('ignis-empty__query', $this->render(['title' => 'T', 'query' => []]));
    }

    public function testStepsCarryStateAndProgress(): void
    {
        $html = $this->render([
            'title' => 'ignis ist fast startklar',
            'steps' => [['label' => 'Wache hinterlegt', 'state' => 'done'], ['label' => 'Fahrzeuge anlegen', 'state' => 'current'], ['label' => 'Vorlage wählen', 'state' => 'todo']],
        ]);
        self::assertStringContainsString('<span aria-hidden="true">1 von 3 erledigt</span><div class="ignis-progress ignis-progress--sm" role="progressbar" aria-label="1 von 3 erledigt" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100" style="--value:0.33"><div class="ignis-progress__bar"></div></div>', $html);
        self::assertStringContainsString('<li data-state="current">', $html);
    }

    public function testStepCanCarryANote(): void
    {
        $html = $this->render([
            'title' => 'T',
            'steps' => [['label' => 'Systemdaten anpassen', 'note' => 'Noch offen: <URL>'], ['label' => 'Rollen']],
        ]);
        self::assertStringContainsString('<span>Systemdaten anpassen<span class="ignis-empty__step-note">Noch offen: &lt;URL&gt;</span></span>', $html);
        self::assertStringContainsString('<span>Rollen</span>', $html);
    }

    public function testStepNumbersIgnoreStringKeys(): void
    {
        $html = $this->render([
            'title' => 'T',
            'steps' => ['wache' => ['label' => 'Wache', 'state' => 'current'], 'fahrzeuge' => ['label' => 'Fahrzeuge']],
        ]);
        self::assertStringContainsString('<span class="ignis-empty__step-no">1</span><span>Wache</span>', $html);
        self::assertStringContainsString('<span class="ignis-empty__step-no">2</span><span>Fahrzeuge</span>', $html);
    }

    public function testInlineVariant(): void
    {
        $html = $this->render(['variant' => 'inline', 'icon' => 'fa-kit-medical', 'text' => 'Noch keine Maßnahmen dokumentiert.']);
        self::assertStringContainsString('<div class="ignis-empty ignis-empty--inline"><i class="fa-solid fa-kit-medical" aria-hidden="true"></i><span>Noch keine Maßnahmen dokumentiert.</span>', $html);
    }

    public function testRejectsUnsafeLinkTargets(): void
    {
        $html = $this->render([
            'title' => 'T',
            'query' => ['filters' => [
                ['label' => 'Böse', 'removeHref' => '//evil.test'],
                ['label' => 'Böse', 'removeHref' => '/\\evil.test'],
                ['label' => 'Gut', 'removeHref' => '/personnel?x=1'],
            ]],
            'actions' => [['label' => 'Klick', 'href' => 'javascript:alert(1)']],
        ]);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('<button type="button" class="ignis-btn ignis-btn--secondary">Klick</button>', $html);
        self::assertStringNotContainsString('evil.test', $html);
        self::assertStringNotContainsString('Böse', $html);
        self::assertStringContainsString('href="/personnel?x=1"', $html);
    }

    public function testOnlyAssignsPrefixedVariables(): void
    {
        $source = (string) file_get_contents(self::PARTIAL);

        // Zuweisungen ($x = …, $x .= …) …
        preg_match_all('/\$(\w+)\s*(?:=(?!=)|\.=)/', $source, $assignments);
        // … und foreach-Schleifenvariablen (as $x) sowie (as $k => $v).
        preg_match_all('/\bas\s+\$(\w+)\s*\)/', $source, $simpleLoopVars);
        preg_match_all('/\bas\s+\$(\w+)\s*=>\s*\$(\w+)\s*\)/', $source, $pairLoopVars);

        $names = array_merge($assignments[1], $simpleLoopVars[1], $pairLoopVars[1], $pairLoopVars[2]);
        foreach (array_unique($names) as $name) {
            self::assertStringStartsWith('empty', $name, "Partial weist \$$name zu");
        }
    }
}
