<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Plugins\Plugin;
use App\Plugins\PluginLoader;
use App\Plugins\PluginManifest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Testet die Merge-Logik des Loaders gegen das Fixture-Plugin „good".
 * Das aktive Set wird gestubbt, damit kein Datenbank-Zugriff nötig ist —
 * die Auflösung selbst deckt PluginRegistryTest ab.
 */
class PluginLoaderTest extends TestCase
{
    /**
     * @param list<Plugin> $plugins
     */
    private function loaderWith(array $plugins): PluginLoader
    {
        return new class($plugins) extends PluginLoader {
            /** @param list<Plugin> $stubbed */
            public function __construct(private readonly array $stubbed)
            {
                // PDO wird nicht gebraucht — active() ist gestubbt.
            }

            public function active(): array
            {
                return $this->stubbed;
            }
        };
    }

    private function goodPlugin(): Plugin
    {
        $dir = __DIR__ . '/fixtures/plugins/good';
        $manifest = PluginManifest::fromArray(require $dir . '/manifest.php');
        return new Plugin($manifest, $dir);
    }

    #[Test]
    public function it_appends_plugin_navigation_to_the_rail(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $config = ['rail' => [['id' => 'core', 'label' => 'Core']]];
        $merged = $loader->mergeNavigation($config);

        $this->assertCount(2, $merged['rail']);
        $this->assertSame('core', $merged['rail'][0]['id']);
        $this->assertSame('good', $merged['rail'][1]['id']);
    }

    #[Test]
    public function it_merges_event_listeners_without_replacing_core_ones(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $map = ['App\\Events\\SomethingHappened' => ['Core\\Listener']];
        $merged = $loader->mergeEventMap($map);

        $this->assertSame(
            ['Core\\Listener', 'GoodPlugin\\Listeners\\OnSomething'],
            $merged['App\\Events\\SomethingHappened']
        );
    }

    #[Test]
    public function it_appends_console_commands(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $merged = $loader->mergeConsoleCommands(['Core\\Command']);

        $this->assertSame(['Core\\Command', 'GoodPlugin\\Console\\SyncCommand'], $merged);
    }

    #[Test]
    public function it_merges_permission_groups_by_name(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $groups = ['Protokolle' => ['core.view' => 'Kern ansehen']];
        $merged = $loader->mergePermissionGroups($groups);

        $this->assertArrayHasKey('Good Plugin', $merged);
        $this->assertSame('Good Plugin ansehen', $merged['Good Plugin']['good.view']);
        // gleichnamige Gruppe wird zusammengeführt, nicht ersetzt
        $this->assertSame('Kern ansehen', $merged['Protokolle']['core.view']);
        $this->assertSame('Good-Einträge im Protokoll sehen', $merged['Protokolle']['good.audit']);
    }

    #[Test]
    public function it_lists_existing_route_fragments_only(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $files = $loader->routeFiles();

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('routes.web.php', $files[0]);
    }

    #[Test]
    public function it_reports_active_plugins_by_id(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $this->assertTrue($loader->isActive('good'));
        $this->assertFalse($loader->isActive('missing'));
    }

    #[Test]
    public function it_autoloads_plugin_classes_and_registers_their_policies(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $loader->registerAutoloading();
        $loader->registerPolicies();

        $this->assertTrue(class_exists('GoodPluginFixture\\Policies\\GoodresPolicy'));
        $this->assertTrue(\App\Auth\Gate::allows('goodres.view'));
        $this->assertFalse(\App\Auth\Gate::allows('goodres.edit'));
    }

    #[Test]
    public function plugins_without_fragments_contribute_nothing(): void
    {
        // Manifest-only Plugin: kein navigation.php, events.php, …
        $bare = new Plugin(
            PluginManifest::fromArray(['id' => 'bare', 'name' => 'Bare', 'version' => '1.0.0']),
            sys_get_temp_dir()
        );
        $loader = $this->loaderWith([$bare]);

        $this->assertSame(['rail' => []], $loader->mergeNavigation(['rail' => []]));
        $this->assertSame([], $loader->mergeEventMap([]));
        $this->assertSame([], $loader->mergeConsoleCommands([]));
        $this->assertSame([], $loader->mergePermissionGroups([]));
        $this->assertSame([], $loader->routeFiles());
    }
}
