<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * Die Dialoge von eNOTF v2 (Teilen, QM) bauen ihr Markup in JavaScript und
 * werden deshalb von keinem Template-Test erfasst. Sie öffnen im eDIVI-Look
 * über die gemeinsame Klasse `ev2-edivi-dialog`, deren Regeln in
 * `_share-qm-assets.php` stehen — nicht mit den Bausteinen der Verwaltung.
 *
 * Zwei Dinge hält dieser Test fest:
 *
 * 1. Keine Verwaltungs- oder Bootstrap-Klassen im Dialog-Markup. Das ist
 *    nicht nur Optik: `form-select` erzeugt ein natives Popup, und das
 *    zeigt der FiveM-Ingame-Browser nicht an. Selects müssen `ignis-input`
 *    tragen, damit Ev2Select sie erfasst (Selektor `select.ignis-input`).
 * 2. Beide Dialoge setzen die gemeinsame Scope-Klasse — ohne sie greift
 *    keine einzige Regel aus dem Styleblock.
 */
final class EnotfV2DialogClassTest extends TestCase
{
    /** Dialog-Skripte, die ihr Markup selbst bauen. */
    private const DIALOG_ASSETS = [
        'plugins/enotf-v2/assets/share.js',
        'plugins/enotf-v2/assets/qm.js',
    ];

    /** Klassen aus der Verwaltung bzw. aus Bootstrap, die hier nichts zu suchen haben. */
    private const FORBIDDEN_CLASSES = [
        'form-select',
        'form-control',
        'ignis-alert',
        'ignis-radio',
        'twplus-description-table',
        'mb-3',
        'mt-2',
        'ml-4',
        'w-100',
    ];

    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $relative): string
    {
        $path = $this->repoRoot() . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_dialog_assets_avoid_admin_and_bootstrap_classes(): void
    {
        foreach (self::DIALOG_ASSETS as $relative) {
            $source = $this->read($relative);

            foreach (self::FORBIDDEN_CLASSES as $class) {
                self::assertDoesNotMatchRegularExpression(
                    '/\b' . preg_quote($class, '/') . '\b/',
                    $source,
                    $relative . ' trägt die Klasse "' . $class . '". Die Dialoge laufen im eDIVI-Look: '
                    . 'edivi__box statt ignis-alert, ignis-input am <select> statt form-select '
                    . '(sonst greift Ev2Select nicht und der FiveM-CEF zeigt kein Popup).'
                );
            }
        }
    }

    public function test_selects_in_dialog_markup_carry_ignis_input(): void
    {
        foreach (self::DIALOG_ASSETS as $relative) {
            $source = $this->read($relative);

            // Nur Selects aus dem gebauten Markup, also innerhalb eines
            // JS-Strings — das <select> in den Kopfkommentaren zählt nicht.
            preg_match_all("/'\\s*<select\\b[^>]*>/", $source, $matches);

            foreach ($matches[0] as $tag) {
                self::assertStringContainsString(
                    'class="ignis-input"',
                    $tag,
                    $relative . ': ' . $tag . ' — Ev2Select erfasst nur select.ignis-input. '
                    . 'Ohne die Klasse bleibt das Popup im FiveM-Ingame-Browser unsichtbar.'
                );
            }
        }
    }

    public function test_both_dialogs_use_the_shared_edivi_scope(): void
    {
        foreach (self::DIALOG_ASSETS as $relative) {
            self::assertStringContainsString(
                'ev2-edivi-dialog',
                $this->read($relative),
                $relative . ' setzt die Scope-Klasse ev2-edivi-dialog nicht. Ohne sie greift '
                . 'keine Regel aus dem Styleblock in _share-qm-assets.php.'
            );
        }
    }

    public function test_shared_scope_is_defined_once_for_both_dialogs(): void
    {
        $styles = $this->read('plugins/enotf-v2/templates/_share-qm-assets.php');

        self::assertStringContainsString('.ev2-edivi-dialog .edivi__box', $styles);
        self::assertStringContainsString('.ev2-edivi-dialog__error', $styles);
        self::assertStringContainsString('.ev2-edivi-dialog__facts', $styles);
        self::assertStringContainsString('.ev2-edivi-dialog__choice', $styles);
    }
}
