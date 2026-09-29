<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * ComponentClassTest (Unit) durchsucht Quelltext nach den alten
 * Klassennamen als Zeichenkette — trifft aber keine Klasse, die aus
 * Fragmenten zusammengesetzt wird (z. B. `'ignis-chip--' .
 * $this->getStatusClass($status)`, siehe SystemUpdater.php, oder
 * `role.color` in templates/roles/index.php). Dieser Test rendert stattdessen
 * echte Seiten und prüft das AUSGELIEFERTE HTML — das sieht jede
 * Zusammensetzung, egal wie dynamisch, weil am Ende nur die fertige Klasse
 * zählt.
 *
 * Die Routen decken die Haupt-Listenseiten ab (Chips/Alerts/Buttons mit
 * Nutzerdaten) plus die Seiten, die I1 (docs/specs/2026-09-28-gemeinsames-
 * css.md) an dynamischer Klassenbildung angefasst hat (Flash, role.color,
 * SystemUpdater-Diagnose). Routen, die in dieser Umgebung nicht mit 200
 * antworten (z. B. ein Plugin, das die Test-DB nicht aktiviert hat), werden
 * übersprungen — andere Statuscodes haben ShellTest/MciBoardTest usw.
 * schon im Blick.
 */
final class OldVocabularyRenderTest extends FeatureTestCase
{
    /** Routen ohne Parameter, die die migrierten Seiten abdecken. */
    private const ROUTES = [
        '/index',
        '/users/list',
        '/settings/index',
        '/settings/system/index',
        '/settings/system/config',
        '/settings/system/updater',
        '/settings/system/cron',
        '/calendar',
        '/forms/admin/list',
        '/personnel/list',
        '/users/roles/index',
        '/settings/vehicles/vehicles/index',
        '/settings/vehicles/vehload/index',
        '/settings/vehicles/defects/index',
        '/logbook/index',
        '/mci/patient-view', // nur falls das Plugin in dieser Umgebung aktiv ist
        '/mail',             // ohne Postfach: die Erklärseite; mit Postfach siehe MailPagesTest
        '/mail/compose',
    ];

    /** Alte Suffixe je Komponentenfamilie — "info"/"warn" sind für Chip/Alert gültiges NEUES Vokabular, für Btn nicht, deshalb getrennte Listen. */
    private const OLD_SUFFIXES = [
        'btn'   => ['accent', 'soft-[a-z]+', 'outline-[a-z]+', 'success', 'info', 'warning'],
        'chip'  => ['success', 'warning', 'accent'],
        'alert' => ['success', 'warning', 'error'],
        'snack' => ['success', 'warning', 'error'],
    ];

    private function login(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    #[Test]
    public function testNoRenderedPageUsesAnOldModifierClass(): void
    {
        $this->login();

        // users/list rendert auch die Flash-Meldung aus der Hülle — Flash::
        // success() setzte vor der Migration `ignis-alert--success` in den
        // <noscript>-Kasten, jetzt muss `getAlertClassSuffix()` das abfangen.
        \App\Helpers\Flash::success('Testmeldung für den Vokabular-Check');

        $found = [];

        foreach (self::ROUTES as $route) {
            $response = $this->get($route);
            if ($response->status !== 200) {
                continue;
            }

            foreach (self::OLD_SUFFIXES as $family => $suffixes) {
                $pattern = '~class="[^"]*ignis-' . $family . '--(' . implode('|', $suffixes) . ')(?![a-zA-Z0-9_-])~';
                if (preg_match($pattern, $response->body, $m) === 1) {
                    $found[] = "$route: ignis-$family--{$m[1]}";
                }
            }
            if (preg_match('~class="[^"]*ignis-filter-links(?![a-zA-Z0-9_-])~', $response->body) === 1) {
                $found[] = "$route: ignis-filter-links";
            }
        }

        $this->assertSame(
            [],
            $found,
            "Altes Vokabular im ausgelieferten HTML (auch dynamisch zusammengesetzt):\n  " . implode("\n  ", $found),
        );
    }
}
