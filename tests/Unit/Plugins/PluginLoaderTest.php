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
    public function it_collects_the_notification_types_from_the_manifest(): void
    {
        $dir = __DIR__ . '/fixtures/plugins/good';
        require_once $dir . '/src/Notifications/WidgetType.php';
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $types = $loader->notificationTypes();

        $this->assertCount(1, $types);
        $this->assertSame('widget', $types[0]->key());
        $this->assertSame('Widgets', $types[0]->label());

        // Eine Klasse, die es nicht gibt, fällt weg statt den Rest mitzureißen.
        $broken = new Plugin(PluginManifest::fromArray([
            'id' => 'broken', 'name' => 'Broken', 'version' => '1.0.0',
            'notifications' => ['Nope\\Missing', 'GoodPluginFixture\\Notifications\\WidgetType'],
        ]), $dir);
        $this->assertCount(1, $this->loaderWith([$broken])->notificationTypes());
    }

    #[Test]
    public function it_appends_plugin_navigation_to_the_groups(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $config = ['groups' => [['id' => 'core', 'label' => 'Core', 'items' => []]]];
        $merged = $loader->mergeNavigation($config);

        // Fragment 1 (Einzellink) wird eine eigene Gruppe, Fragment 2
        // (merge_into) hängt seine Einträge an die core-Gruppe.
        $this->assertCount(2, $merged['groups']);
        $this->assertSame('core', $merged['groups'][0]['id']);
        $this->assertSame(['Tool'], array_column($merged['groups'][0]['items'], 'label'));
        $this->assertSame('good', $merged['groups'][1]['id']);
        $this->assertSame('/good', $merged['groups'][1]['items'][0]['href']);
    }

    #[Test]
    public function merge_into_falls_back_to_an_own_group_when_the_target_is_missing(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $merged = $loader->mergeNavigation(['groups' => []]);

        $ids = array_column($merged['groups'], 'id');
        $this->assertContains('good', $ids);
        $this->assertContains('good-extra', $ids);
    }

    #[Test]
    public function legacy_sections_pass_icon_and_permissions_down_to_their_items(): void
    {
        $loader = $this->loaderWith([$this->goodPlugin()]);

        $merged = $loader->mergeNavigation(['groups' => []]);

        $byId = array_column($merged['groups'], null, 'id');
        $tool = $byId['good-extra']['items'][0];
        $this->assertSame('fa-solid fa-puzzle-piece', $tool['icon'], 'Icon des Fragments erbt der Eintrag');
        $this->assertSame(['good.view'], $tool['permissions'], 'Permissions der Section erbt der Eintrag');
        $this->assertSame('fa-solid fa-puzzle-piece', $byId['good']['items'][0]['icon']);
    }

    #[Test]
    public function groups_without_items_disappear(): void
    {
        $loader = $this->loaderWith([]);

        $merged = $loader->mergeNavigation(['groups' => [
            ['id' => 'anchor', 'label' => 'Protokolle', 'items' => []],
            ['id' => 'kept', 'label' => 'Kern', 'items' => [['label' => 'A', 'href' => '/a', 'icon' => 'fa-solid fa-a']]],
        ]]);

        $this->assertSame(['kept'], array_column($merged['groups'], 'id'));
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
    public function bundled_plugins_count_as_installed_without_a_marker(): void
    {
        $this->assertTrue(PluginLoader::isBundled('knowledge-base'));
        $this->assertTrue(PluginLoader::isInstalledDir('knowledge-base', sys_get_temp_dir()));
    }

    #[Test]
    public function third_party_plugins_require_the_install_marker(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ignis-plugin-gate-' . getmypid();
        mkdir($dir, 0777, true);

        try {
            $this->assertFalse(PluginLoader::isBundled('community-thing'));
            $this->assertFalse(
                PluginLoader::isInstalledDir('community-thing', $dir),
                'ohne Marker gilt ein Fremd-Plugin als nicht installiert'
            );

            $plugin = new Plugin(
                PluginManifest::fromArray(['id' => 'community-thing', 'name' => 'Community Thing', 'version' => '1.0.0']),
                $dir
            );
            $this->assertTrue(PluginLoader::markInstalled($plugin));
            $this->assertTrue(PluginLoader::isInstalledDir('community-thing', $dir));
        } finally {
            @unlink($dir . DIRECTORY_SEPARATOR . '.installed');
            @rmdir($dir);
        }
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

        $this->assertSame(['groups' => []], $loader->mergeNavigation(['groups' => []]));
        $this->assertSame([], $loader->mergeEventMap([]));
        $this->assertSame([], $loader->mergeConsoleCommands([]));
        $this->assertSame([], $loader->mergePermissionGroups([]));
        $this->assertSame([], $loader->routeFiles());
    }
}
