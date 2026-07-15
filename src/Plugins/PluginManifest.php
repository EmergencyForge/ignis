<?php

declare(strict_types=1);

namespace App\Plugins;

use InvalidArgumentException;

/**
 * Typisierte, validierte Repräsentation einer Plugin-`manifest.php`.
 *
 * Ein Manifest ist die einzige Pflichtdatei eines Plugins. Es liefert die
 * Metadaten, die der PluginRegistry braucht, um Kompatibilität und
 * Abhängigkeiten aufzulösen, bevor irgendein Plugin-Code geladen wird.
 *
 * Beispiel (plugins/enotf/manifest.php):
 *
 *     return [
 *         'id'              => 'enotf',
 *         'name'            => 'eNOTF – Notfallprotokolle',
 *         'version'         => '1.0.0',
 *         'vendor'          => 'EmergencyForge',
 *         'requires'        => ['ignis' => '>=1.2 <2.0'],
 *         'depends'         => [],
 *         'permissions'     => ['enotf.view', 'enotf.edit', 'enotf.admin'],
 *         'default_enabled' => true,
 *         'removable'       => true,
 *     ];
 */
final class PluginManifest
{
    /**
     * @param string        $id            Eindeutige, stabile Plugin-ID (kebab/snake, [a-z0-9_-])
     * @param string        $name          Menschlich lesbarer Anzeigename
     * @param string        $version       Semver-Version des Plugins
     * @param string        $vendor        Herausgeber (z.B. "EmergencyForge")
     * @param string        $ignisRequire  Kompatibilitätsbereich zur ignis-Version
     * @param list<string>  $depends       IDs anderer Plugins, die aktiv sein müssen
     * @param list<string>  $permissions   Permissions, die dieses Plugin einbringt
     * @param bool          $defaultEnabled Bei Erstinstallation direkt aktiv?
     * @param bool          $removable      Darf der Nutzer es deaktivieren?
     */
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $vendor,
        public readonly string $ignisRequire,
        public readonly array $depends,
        public readonly array $permissions,
        public readonly bool $defaultEnabled,
        public readonly bool $removable,
    ) {}

    /**
     * Baut ein Manifest aus dem rohen Array (Rückgabewert von manifest.php).
     *
     * @param array<array-key, mixed> $data
     * @throws InvalidArgumentException wenn Pflichtfelder fehlen/ungültig sind
     */
    public static function fromArray(array $data): self
    {
        $id = self::requireString($data, 'id');
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id)) {
            throw new InvalidArgumentException("Plugin-ID '{$id}' ist ungültig (erlaubt: a-z, 0-9, _, -).");
        }

        $version = self::requireString($data, 'version');
        $requires = $data['requires'] ?? [];
        if (!is_array($requires)) {
            throw new InvalidArgumentException("Plugin '{$id}': 'requires' muss ein Array sein.");
        }

        return new self(
            id: $id,
            name: self::requireString($data, 'name'),
            version: $version,
            vendor: isset($data['vendor']) ? (string) $data['vendor'] : 'Unbekannt',
            ignisRequire: isset($requires['ignis']) ? (string) $requires['ignis'] : '*',
            depends: self::stringList($data['depends'] ?? []),
            permissions: self::stringList($data['permissions'] ?? []),
            defaultEnabled: (bool) ($data['default_enabled'] ?? false),
            removable: (bool) ($data['removable'] ?? true),
        );
    }

    /**
     * Ist dieses Plugin mit der laufenden ignis-Version kompatibel?
     */
    public function isCompatibleWith(string $ignisVersion): bool
    {
        return VersionConstraint::satisfies($ignisVersion, $this->ignisRequire);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Plugin-Manifest: Pflichtfeld '{$key}' fehlt oder ist leer.");
        }
        return trim($value);
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_map(static fn ($v): string => (string) $v, $value));
    }
}
