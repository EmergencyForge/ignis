<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\Navigation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Navigation::sidebarGroups() baut aus groups() die Sidebar-Ansicht: die
 * placement=settings-Gruppen (config/navigation.php) verschwinden dort,
 * „Verwaltung" bekommt stattdessen den Einstellungen-Eintrag dazu, sobald
 * mindestens eine Settings-Gruppe nach der Rechteprüfung einen Eintrag hat.
 */
final class NavigationTest extends TestCase
{
    protected function setUp(): void
    {
        // Name in einer Variablen: ein zweites wörtliches define('BASE_PATH')
        // ließe PHPStan die Konstante im ganzen Projekt vergessen (siehe
        // NavigationConfigTest).
        $constant = 'BASE_PATH';
        if (!defined($constant)) {
            define($constant, '/');
        }
        $_SERVER['REQUEST_URI'] = '/index';
    }

    /** @return list<array<string, mixed>> */
    private function groupsFixture(bool $settingsItemActive = false): array
    {
        return [
            ['id' => 'personal', 'label' => 'Personal', 'items' => [
                ['label' => 'Mitarbeiter', 'href' => '/personnel/list', 'icon' => 'fa-solid fa-id-badge', 'active' => false],
            ]],
            ['id' => 'admin', 'label' => 'Verwaltung', 'items' => [
                ['label' => 'Benutzer', 'href' => '/users/list', 'icon' => 'fa-solid fa-users', 'active' => false],
            ]],
            ['id' => 'access', 'label' => 'Zugang & Rechte', 'placement' => 'settings', 'items' => [
                ['label' => 'Rollen', 'href' => '/users/roles/index', 'icon' => 'fa-solid fa-user-shield', 'active' => $settingsItemActive],
            ]],
        ];
    }

    #[Test]
    public function settings_gruppen_erscheinen_nicht_in_der_sidebar(): void
    {
        $visible = Navigation::sidebarGroups($this->groupsFixture());

        $ids = array_column($visible, 'id');
        $this->assertNotContains('access', $ids);
        $this->assertContains('admin', $ids);
        $this->assertContains('personal', $ids);
    }

    #[Test]
    public function verwaltung_bekommt_den_einstellungen_eintrag_dazu(): void
    {
        $visible = Navigation::sidebarGroups($this->groupsFixture());

        $admin = current(array_filter($visible, static fn (array $g): bool => $g['id'] === 'admin'));
        $labels = array_column($admin['items'], 'label');

        $this->assertSame(['Benutzer', 'Einstellungen'], $labels);
        $this->assertSame('/settings/index', $admin['items'][1]['href']);
    }

    #[Test]
    public function ohne_settings_eintraege_bleibt_verwaltung_ohne_einstellungen(): void
    {
        $groups = $this->groupsFixture();
        $groups[2]['items'] = [];

        $visible = Navigation::sidebarGroups($groups);

        $admin = current(array_filter($visible, static fn (array $g): bool => $g['id'] === 'admin'));
        $this->assertCount(1, $admin['items'], 'Ohne Kachel bleibt es bei Benutzer allein.');
    }

    #[Test]
    public function einstellungen_ist_aktiv_wenn_ein_settings_eintrag_aktiv_ist(): void
    {
        $visible = Navigation::sidebarGroups($this->groupsFixture(settingsItemActive: true));

        $admin = current(array_filter($visible, static fn (array $g): bool => $g['id'] === 'admin'));
        $this->assertTrue($admin['items'][1]['active']);
    }

    #[Test]
    public function verwaltung_kommt_auch_zurueck_wenn_benutzer_fehlt(): void
    {
        // groups() lässt eine Gruppe ohne sichtbare Einträge ganz weg. Fehlt
        // etwa das Recht für Benutzer, kommt „admin" hier nie an. Trotzdem
        // muss die Sidebar Einstellungen zeigen, wenn ein anderes Recht für
        // einen Settings-Bereich reicht.
        $groups = [
            ['id' => 'access', 'label' => 'Zugang & Rechte', 'placement' => 'settings', 'items' => [
                ['label' => 'Rollen', 'href' => '/users/roles/index', 'icon' => 'fa-solid fa-user-shield', 'active' => false],
            ]],
        ];

        $visible = Navigation::sidebarGroups($groups);

        $admin = current(array_filter($visible, static fn (array $g): bool => $g['id'] === 'admin'));
        $this->assertNotFalse($admin, 'Verwaltung fehlt, obwohl es eine Settings-Kachel gibt.');
        $this->assertSame(['Einstellungen'], array_column($admin['items'], 'label'));
    }

    #[Test]
    public function einstellungen_ist_aktiv_auf_der_uebersicht_selbst(): void
    {
        $_SERVER['REQUEST_URI'] = '/settings/index';

        $visible = Navigation::sidebarGroups($this->groupsFixture());

        $admin = current(array_filter($visible, static fn (array $g): bool => $g['id'] === 'admin'));
        $this->assertTrue($admin['items'][1]['active']);
    }
}
