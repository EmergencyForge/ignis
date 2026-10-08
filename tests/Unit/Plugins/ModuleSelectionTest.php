<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Plugins\ModuleSelection;
use App\Plugins\PluginLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Prüfung der Modulauswahl ohne Datenbank: Abhängigkeiten und die Liste
 * der wählbaren Module.
 */
final class ModuleSelectionTest extends TestCase
{
    private const DEPENDS = ['enotf' => [], 'enotf-v2' => ['enotf'], 'calendar' => []];
    private const NAMES = ['enotf' => 'eNOTF', 'enotf-v2' => 'eNOTF v2', 'calendar' => 'Kalender'];

    #[Test]
    public function eine_auswahl_mit_allen_abhaengigkeiten_ist_in_ordnung(): void
    {
        $this->assertSame([], ModuleSelection::validate(['enotf', 'enotf-v2'], self::DEPENDS, self::NAMES));
        $this->assertSame([], ModuleSelection::validate(['calendar'], self::DEPENDS, self::NAMES));
        $this->assertSame([], ModuleSelection::validate([], self::DEPENDS, self::NAMES));
    }

    #[Test]
    public function eine_fehlende_abhaengigkeit_wird_benannt(): void
    {
        $this->assertSame(
            ['eNOTF v2 braucht eNOTF.'],
            ModuleSelection::validate(['enotf-v2', 'calendar'], self::DEPENDS, self::NAMES),
        );
    }

    #[Test]
    public function waehlbar_sind_nur_mitgelieferte_plugins(): void
    {
        $this->assertSame([], array_diff(array_keys(ModuleSelection::MODULES), PluginLoader::BUNDLED));
        // Jedes mitgelieferte Plugin steht in der Auswahl.
        $this->assertSame([], array_diff(PluginLoader::BUNDLED, array_keys(ModuleSelection::MODULES)));
    }
}
