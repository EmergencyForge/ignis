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
     * @param string                $id            Eindeutige, stabile Plugin-ID (kebab/snake, [a-z0-9_-])
     * @param string                $name          Menschlich lesbarer Anzeigename
     * @param string                $version       Semver-Version des Plugins
     * @param string                $vendor        Herausgeber (z.B. "EmergencyForge")
     * @param string                $ignisRequire  Kompatibilitätsbereich zur ignis-Version
     * @param list<string>          $depends       IDs anderer Plugins, die aktiv sein müssen
     * @param list<string>          $permissions   Permissions, die dieses Plugin einbringt
     * @param array<string, string> $autoload      PSR-4-Map: Namespace-Prefix => Verzeichnis (relativ zum Plugin)
     * @param array<string, string> $policies      Gate-Ressource => Policy-Klasse (FQCN)
     * @param bool                  $defaultEnabled Bei Erstinstallation direkt aktiv?
     * @param bool                  $removable      Darf der Nutzer es deaktivieren?
     */
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $vendor,
        public readonly string $ignisRequire,
        public readonly array $depends,
        public readonly array $permissions,
        public readonly array $autoload,
        public readonly array $policies,
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
            autoload: self::stringMap($data['autoload'] ?? []),
            policies: self::stringMap($data['policies'] ?? []),
            defaultEnabled: (bool) ($data['default_enabled'] ?? false),
            removable: (bool) ($data['removable'] ?? true),
        );
    }

    /**
     * Liest ein Manifest ohne es als PHP-Code auszuführen. Erlaubt ist nur
     * eine einzelne `return [...]`-Anweisung aus skalaren Literalen und
     * Arrays. Funktionsaufrufe, Variablen, Konstanten und Ausdrücke werden
     * abgewiesen, bevor der literal-only Ausdruck ausgewertet wird.
     */
    public static function fromFile(string $path): self
    {
        $source = @file_get_contents($path);
        if (!is_string($source) || $source === '') {
            throw new InvalidArgumentException('Plugin-Manifest konnte nicht gelesen werden.');
        }

        $tokens = token_get_all($source);
        $expression = '';
        $seenReturn = false;
        $seenSemicolon = false;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                [$type, $text] = $token;
                if (in_array($type, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if ($type === T_RETURN && !$seenReturn) {
                    $seenReturn = true;
                    continue;
                }
                if (!$seenReturn || $seenSemicolon) {
                    throw new InvalidArgumentException('Plugin-Manifest darf nur `return [...]` enthalten.');
                }
                if (in_array($type, [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_ARRAY, T_DOUBLE_ARROW], true)) {
                    $expression .= $text;
                    continue;
                }
                if ($type === T_STRING && in_array(strtolower($text), ['true', 'false', 'null'], true)) {
                    $expression .= strtolower($text);
                    continue;
                }
                throw new InvalidArgumentException('Plugin-Manifest enthält nicht erlaubten ausführbaren Code.');
            }

            if (!$seenReturn || $seenSemicolon) {
                if (trim($token) === '') {
                    continue;
                }
                throw new InvalidArgumentException('Plugin-Manifest darf nur `return [...]` enthalten.');
            }
            if ($token === ';') {
                $seenSemicolon = true;
                continue;
            }
            if (!in_array($token, ['[', ']', '(', ')', ',', '=>', '-', '+'], true)) {
                throw new InvalidArgumentException('Plugin-Manifest enthält einen nicht erlaubten Ausdruck.');
            }
            $expression .= $token;
        }

        if (!$seenReturn || !$seenSemicolon || trim($expression) === '') {
            throw new InvalidArgumentException('Plugin-Manifest enthält keine vollständige return-Anweisung.');
        }

        /** @var mixed $data */
        $data = eval('return ' . $expression . ';');
        if (!is_array($data)) {
            throw new InvalidArgumentException('Plugin-Manifest gibt kein Array zurück.');
        }
        return self::fromArray($data);
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

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && is_string($v) && $k !== '' && $v !== '') {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
