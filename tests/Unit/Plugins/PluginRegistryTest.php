<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Plugins\Plugin;
use App\Plugins\PluginManifest;
use App\Plugins\PluginRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PluginRegistryTest extends TestCase
{
    /**
     * @param list<string> $depends
     */
    private function plugin(string $id, array $depends = [], string $ignisRequire = '*'): Plugin
    {
        $manifest = PluginManifest::fromArray([
            'id' => $id,
            'name' => ucfirst($id),
            'version' => '1.0.0',
            'requires' => ['ignis' => $ignisRequire],
            'depends' => $depends,
        ]);
        return new Plugin($manifest, '/virtual/' . $id);
    }

    /**
     * @param list<Plugin> $plugins
     * @return array<string, Plugin>
     */
    private function keyed(array $plugins): array
    {
        $out = [];
        foreach ($plugins as $p) {
            $out[$p->id()] = $p;
        }
        return $out;
    }

    /**
     * @param list<Plugin> $active
     * @return list<string>
     */
    private function ids(array $active): array
    {
        return array_map(static fn (Plugin $p): string => $p->id(), $active);
    }

    #[Test]
    public function it_activates_enabled_and_compatible_plugins(): void
    {
        $registry = new PluginRegistry($this->keyed([
            $this->plugin('enotf'),
            $this->plugin('firetab'),
        ]));

        $registry->resolve(['enotf', 'firetab'], '1.5.0');

        $this->assertSame(['enotf', 'firetab'], $this->ids($registry->active()));
        $this->assertSame([], $registry->skipped());
    }

    #[Test]
    public function it_ignores_disabled_plugins_without_a_skip_reason(): void
    {
        $registry = new PluginRegistry($this->keyed([
            $this->plugin('enotf'),
            $this->plugin('firetab'),
        ]));

        $registry->resolve(['enotf'], '1.5.0');

        $this->assertSame(['enotf'], $this->ids($registry->active()));
        // firetab is simply off, not an error
        $this->assertSame([], $registry->skipped());
    }

    #[Test]
    public function it_skips_version_incompatible_plugins(): void
    {
        $registry = new PluginRegistry($this->keyed([
            $this->plugin('enotf', [], '>=2.0'),
        ]));

        $registry->resolve(['enotf'], '1.5.0');

        $this->assertSame([], $registry->active());
        $skipped = $registry->skipped();
        $this->assertCount(1, $skipped);
        $this->assertSame('enotf', $skipped[0]['id']);
        $this->assertStringContainsString('ignis', $skipped[0]['reason']);
    }

    #[Test]
    public function it_orders_dependencies_before_dependents(): void
    {
        $registry = new PluginRegistry($this->keyed([
            $this->plugin('firetab', ['vehicles']),
            $this->plugin('vehicles'),
        ]));

        $registry->resolve(['firetab', 'vehicles'], '1.5.0');

        $order = $this->ids($registry->active());
        $this->assertSame(['vehicles', 'firetab'], $order);
    }

    #[Test]
    public function it_skips_a_plugin_whose_dependency_is_disabled(): void
    {
        $registry = new PluginRegistry($this->keyed([
            $this->plugin('firetab', ['vehicles']),
            $this->plugin('vehicles'),
        ]));

        // vehicles installed but not enabled
        $registry->resolve(['firetab'], '1.5.0');

        $this->assertSame([], $this->ids($registry->active()));
        $reasons = $registry->skipped();
        $this->assertCount(1, $reasons);
        $this->assertSame('firetab', $reasons[0]['id']);
        $this->assertStringContainsString('nicht aktiv', $reasons[0]['reason']);
    }

    #[Test]
    public function it_skips_a_plugin_whose_dependency_is_not_installed(): void
    {
        $registry = new PluginRegistry($this->keyed([
            $this->plugin('firetab', ['vehicles']),
        ]));

        $registry->resolve(['firetab'], '1.5.0');

        $this->assertSame([], $this->ids($registry->active()));
        $reasons = $registry->skipped();
        $this->assertStringContainsString('nicht installiert', $reasons[0]['reason']);
    }

    #[Test]
    public function it_cascades_dependency_removal(): void
    {
        // c depends on b depends on a; a is incompatible → all three drop
        $registry = new PluginRegistry($this->keyed([
            $this->plugin('a', [], '>=9.0'),
            $this->plugin('b', ['a']),
            $this->plugin('c', ['b']),
        ]));

        $registry->resolve(['a', 'b', 'c'], '1.5.0');

        $this->assertSame([], $this->ids($registry->active()));
        $this->assertCount(3, $registry->skipped());
    }

    #[Test]
    public function it_detects_dependency_cycles(): void
    {
        $registry = new PluginRegistry($this->keyed([
            $this->plugin('a', ['b']),
            $this->plugin('b', ['a']),
        ]));

        $registry->resolve(['a', 'b'], '1.5.0');

        $this->assertSame([], $this->ids($registry->active()));
        $reasons = array_map(static fn ($s) => $s['reason'], $registry->skipped());
        $this->assertNotEmpty(array_filter($reasons, static fn ($r) => str_contains($r, 'Zyklische')));
    }

    #[Test]
    public function it_discovers_plugins_from_a_directory_and_skips_broken_ones(): void
    {
        $registry = PluginRegistry::fromDirectory(__DIR__ . '/fixtures/plugins');

        $this->assertArrayHasKey('good', $registry->all());
        $this->assertArrayNotHasKey('broken', $registry->all());

        $skipped = $registry->skipped();
        $this->assertCount(1, $skipped);
        $this->assertSame('broken', $skipped[0]['id']);
        $this->assertStringContainsString('Manifest', $skipped[0]['reason']);
    }
}
