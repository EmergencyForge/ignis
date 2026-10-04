<?php

declare(strict_types=1);

namespace App\Setup;

use App\Config\ConfigManager;

/**
 * Was an der Grundeinrichtung in intra_config noch fehlt.
 *
 * Zwei Stufen: Pflicht hält den Schritt „Systemdaten anpassen" auf dem
 * Dashboard offen, Empfehlungen erscheinen nur als Hinweis. Ein gesperrtes
 * Feld (is_editable = 0, etwa SYSTEM_URL auf fabrica, dort setzt
 * docker/entrypoint.sh den Wert) gilt als erledigt, ebenso ein Schlüssel,
 * den es in der Installation nicht gibt.
 *
 * Genutzt vom Dashboard (assets/components/index/setup-checklist.php), der
 * Übersicht /settings/index und der Konfiguration mit ?setup=1.
 */
final class SetupCheck
{
    public const REQUIRED = 'required';
    public const RECOMMENDED = 'recommended';

    /** Beispielwerte aus der Erstbefüllung (Migration 20250607000053). */
    private const SAMPLES = [
        'RP_STREET'   => ['Straße', 'Musterweg 0815'],
        'RP_ZIP'      => ['PLZ', '1337'],
        'SERVER_CITY' => ['Stadt', 'Musterstadt'],
    ];

    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    /** @param iterable<array<string, mixed>> $rows Zeilen aus intra_config */
    public function __construct(iterable $rows)
    {
        foreach ($rows as $row) {
            $this->rows[(string) $row['config_key']] = $row;
        }
    }

    public static function fromConfig(): self
    {
        return new self((new ConfigManager())->getAllConfig());
    }

    /**
     * Offene Punkte, Pflicht zuerst.
     *
     * @return list<array{key: string, label: string, level: string, text: string}>
     */
    public function open(): array
    {
        $open = [];

        foreach (['SYSTEM_URL' => 'System-URL', 'SERVER_NAME' => 'Servername'] as $key => $label) {
            if ($this->editableWith($key, static fn (string $value): bool => $value === '' || $value === 'CHANGE_ME')) {
                $open[] = ['key' => $key, 'label' => $label, 'level' => self::REQUIRED, 'text' => 'Noch nicht eingetragen.'];
            }
        }

        if ($this->editableWith('SYSTEM_NAME', static fn (string $value): bool => $value === 'intraRP')) {
            $open[] = ['key' => 'SYSTEM_NAME', 'label' => 'Name des Intranets', 'level' => self::RECOMMENDED, 'text' => 'Steht noch auf „intraRP“, dem alten Produktnamen.'];
        }

        foreach (self::SAMPLES as $key => [$label, $sample]) {
            if ($this->editableWith($key, static fn (string $value): bool => $value === $sample)) {
                $open[] = ['key' => $key, 'label' => $label, 'level' => self::RECOMMENDED, 'text' => 'Steht noch auf dem Beispielwert „' . $sample . '“.'];
            }
        }

        $pinOn = in_array(strtolower(trim((string) ($this->rows['ENOTF_USE_PIN']['config_value'] ?? ''))), ['true', '1', 'yes'], true);
        if ($pinOn && $this->editableWith('ENOTF_PIN', static fn (string $value): bool => $value === '1234')) {
            $open[] = ['key' => 'ENOTF_PIN', 'label' => 'eNOTF-PIN', 'level' => self::RECOMMENDED, 'text' => 'Steht noch auf 1234. Die Standard-PIN ist ein Sicherheitsrisiko, weil jeder sie kennt.'];
        }

        return $open;
    }

    /** @return list<array{key: string, label: string, level: string, text: string}> */
    public function required(): array
    {
        return array_values(array_filter($this->open(), static fn (array $item): bool => $item['level'] === self::REQUIRED));
    }

    /** @return list<array{key: string, label: string, level: string, text: string}> */
    public function recommended(): array
    {
        return array_values(array_filter($this->open(), static fn (array $item): bool => $item['level'] === self::RECOMMENDED));
    }

    /** Nichts Pflichtiges mehr offen. */
    public function isComplete(): bool
    {
        return $this->required() === [];
    }

    /** @param callable(string): bool $isOpen */
    private function editableWith(string $key, callable $isOpen): bool
    {
        $row = $this->rows[$key] ?? null;
        if ($row === null || !(bool) ($row['is_editable'] ?? false)) {
            return false;
        }

        return $isOpen(trim((string) ($row['config_value'] ?? '')));
    }
}
