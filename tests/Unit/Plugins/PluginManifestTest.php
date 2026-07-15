<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Plugins\PluginManifest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PluginManifestTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private function validData(): array
    {
        return [
            'id' => 'enotf',
            'name' => 'eNOTF – Notfallprotokolle',
            'version' => '1.0.0',
            'vendor' => 'EmergencyForge',
            'requires' => ['ignis' => '>=1.2 <2.0'],
            'depends' => ['vehicles'],
            'permissions' => ['enotf.view', 'enotf.edit'],
            'default_enabled' => true,
            'removable' => true,
        ];
    }

    #[Test]
    public function it_parses_a_valid_manifest(): void
    {
        $m = PluginManifest::fromArray($this->validData());

        $this->assertSame('enotf', $m->id);
        $this->assertSame('eNOTF – Notfallprotokolle', $m->name);
        $this->assertSame('1.0.0', $m->version);
        $this->assertSame('EmergencyForge', $m->vendor);
        $this->assertSame('>=1.2 <2.0', $m->ignisRequire);
        $this->assertSame(['vehicles'], $m->depends);
        $this->assertSame(['enotf.view', 'enotf.edit'], $m->permissions);
        $this->assertTrue($m->defaultEnabled);
        $this->assertTrue($m->removable);
    }

    #[Test]
    public function it_applies_sensible_defaults(): void
    {
        $m = PluginManifest::fromArray([
            'id' => 'minimal',
            'name' => 'Minimal',
            'version' => '0.1.0',
        ]);

        $this->assertSame('Unbekannt', $m->vendor);
        $this->assertSame('*', $m->ignisRequire);
        $this->assertSame([], $m->depends);
        $this->assertSame([], $m->permissions);
        $this->assertFalse($m->defaultEnabled);
        $this->assertTrue($m->removable, 'removable defaults to true');
    }

    #[Test]
    public function it_rejects_a_missing_id(): void
    {
        $data = $this->validData();
        unset($data['id']);

        $this->expectException(InvalidArgumentException::class);
        PluginManifest::fromArray($data);
    }

    #[Test]
    public function it_rejects_an_invalid_id(): void
    {
        $data = $this->validData();
        $data['id'] = 'Not Valid!';

        $this->expectException(InvalidArgumentException::class);
        PluginManifest::fromArray($data);
    }

    #[Test]
    public function it_rejects_a_blank_name(): void
    {
        $data = $this->validData();
        $data['name'] = '   ';

        $this->expectException(InvalidArgumentException::class);
        PluginManifest::fromArray($data);
    }

    #[Test]
    public function it_checks_ignis_compatibility(): void
    {
        $m = PluginManifest::fromArray($this->validData());

        $this->assertTrue($m->isCompatibleWith('1.5.0'));
        $this->assertFalse($m->isCompatibleWith('2.0.0'));
        $this->assertFalse($m->isCompatibleWith('1.0.0'));
    }
}
