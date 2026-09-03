<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

/**
 * config/navigation.php hat eine feste Form, weil drei Konsumenten daran
 * hängen: die Sidebar, das Neu-Menü und die Palette (App\Helpers\Navigation).
 * Jede Gruppe hat id, label und items; jeder Eintrag label, href und icon,
 * eine Schnellaktion type, target und label. Die Plugin-Fragmente prüft
 * PluginLoaderTest.
 */
final class NavigationConfigTest extends TestCase
{
    /** @return list<array<string,mixed>> */
    private function navigationGroups(): array
    {
        // Die Konfiguration baut ihre Links mit BASE_PATH (assets/config/config.php).
        // Der Name steht hier in einer Variablen: ein zweites wörtliches
        // define('BASE_PATH') ließe PHPStan die Konstante im ganzen Projekt
        // vergessen (714 Meldungen „Constant BASE_PATH not found").
        $constant = 'BASE_PATH';
        if (!defined($constant)) {
            define($constant, '/');
        }
        /** @var array{groups: list<array<string,mixed>>} $config */
        $config = require dirname(__DIR__, 3) . '/config/navigation.php';

        return $config['groups'];
    }

    public function testEveryGroupAndItemHasTheExpectedShape(): void
    {
        $ids = [];
        $hrefs = [];
        foreach ($this->navigationGroups() as $group) {
            $this->assertIsString($group['id']);
            $this->assertArrayHasKey('label', $group);
            $this->assertTrue($group['label'] === null || (is_string($group['label']) && $group['label'] !== ''));
            $this->assertIsArray($group['items']);
            $ids[] = $group['id'];

            foreach ($group['items'] as $item) {
                $this->assertNotSame('', $item['label']);
                $this->assertStringStartsWith('/', $item['href']);
                $this->assertMatchesRegularExpression('~^fa-[a-z]+ fa-[a-z0-9-]+$~', $item['icon'], 'icon ist die volle Font-Awesome-Klasse');
                if (isset($item['permissions'])) {
                    $this->assertNotEmpty($item['permissions']);
                }
                foreach ($item['match'] ?? [] as $prefix) {
                    $this->assertStringStartsWith('/', $prefix);
                }
                if (isset($item['quick_action'])) {
                    $this->assertContains($item['quick_action']['type'], ['link', 'modal']);
                    $this->assertNotSame('', $item['quick_action']['target']);
                    $this->assertNotSame('', $item['quick_action']['label']);
                }
                $hrefs[] = $item['href'];
            }
        }

        $this->assertSame($ids, array_values(array_unique($ids)), 'Eine Gruppen-id steht doppelt.');
        $this->assertSame($hrefs, array_values(array_unique($hrefs)), 'Ein Ziel steht doppelt in der Navigation.');
    }

    public function testAnchorsForThePluginsExist(): void
    {
        $ids = array_column($this->navigationGroups(), 'id');

        // start: Lexikon; protokolle: eNOTF, fireTab, MANV-Board; settings: eNOTF-Einstellungen
        foreach (['start', 'protokolle', 'settings'] as $anchor) {
            $this->assertContains($anchor, $ids, "Gruppe '$anchor' fehlt, die Plugin-Fragmente hängen sich dort ein.");
        }
        $this->assertSame('start', $ids[0]);
        $this->assertNull($this->navigationGroups()[0]['label']);
    }
}
