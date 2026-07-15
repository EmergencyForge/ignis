<?php

namespace Plugin\Enotf\Helpers;

use App\Config\ConfigManager;

/**
 * Helper class for blood sugar unit conversion
 * 
 * Provides functions to convert between mg/dl and mmol/l
 * Conversion factor: 1 mg/dl = 0.0555 mmol/l
 */
class BloodSugarHelper
{
    private const MG_TO_MMOL_FACTOR = 0.0555;
    private const MMOL_TO_MG_FACTOR = 18.0180; // 1 / 0.0555

    private ConfigManager $configManager;
    private string $currentUnit;

    public function __construct(\PDO $pdo)
    {
        $this->configManager = new ConfigManager($pdo);
        $this->currentUnit = $this->configManager->get('ENOTF_BZ_UNIT', 'mg/dl');
    }

    /**
     * Get the current blood sugar unit from configuration
     * 
     * @return string The current unit ('mg/dl' or 'mmol/l')
     */
    public function getCurrentUnit(): string
    {
        return $this->currentUnit;
    }

    /**
     * Convert a blood sugar value from mg/dl to mmol/l
     * 
     * @param float|string $value The value in mg/dl
     * @return float The value in mmol/l
     */
    public static function mgToMmol($value): float
    {
        $numValue = is_string($value) ? floatval(str_replace(',', '.', $value)) : (float)$value;
        return round($numValue * self::MG_TO_MMOL_FACTOR, 1);
    }

    /**
     * Convert a blood sugar value from mmol/l to mg/dl
     * 
     * @param float|string $value The value in mmol/l
     * @return float The value in mg/dl
     */
    public static function mmolToMg($value): float
    {
        $numValue = is_string($value) ? floatval(str_replace(',', '.', $value)) : (float)$value;
        return round($numValue * self::MMOL_TO_MG_FACTOR, 0);
    }

    /**
     * Convert a blood sugar value from storage format (mg/dl) to display format
     * based on current configuration
     * 
     * @param float|string $value The value in mg/dl (storage format)
     * @return float The value in the configured unit
     */
    public function toDisplayUnit($value): float
    {
        // Behandle 'ng' (nicht gemessen) und 'nm' (nicht messbar) als 0 für numerische Berechnungen
        if (is_string($value) && in_array(strtolower(trim($value)), ['ng', 'nm'])) {
            return 0;
        }

        if ($this->currentUnit === 'mmol/l') {
            return self::mgToMmol($value);
        }

        $numValue = is_string($value) ? floatval(str_replace(',', '.', $value)) : (float)$value;
        return round($numValue, 0);
    }

    /**
     * Convert a blood sugar value from display format to storage format (mg/dl)
     * based on current configuration
     *
     * @param float|string $value The value in the configured unit
     * @return float The value in mg/dl (storage format)
     */
    public function toStorageUnit($value): float
    {
        // Behandle 'ng' (nicht gemessen) und 'nm' (nicht messbar) als 0 für Storage
        if (is_string($value) && in_array(strtolower(trim($value)), ['ng', 'nm'])) {
            return 0;
        }

        if ($this->currentUnit === 'mmol/l') {
            return self::mmolToMg($value);
        }

        $numValue = is_string($value) ? floatval(str_replace(',', '.', $value)) : (float)$value;
        return round($numValue, 0);
    }

    /**
     * Format a blood sugar value for display with unit
     * 
     * @param float|string $value The value in storage format (mg/dl)
     * @param bool $includeUnit Whether to include the unit in the output
     * @return string The formatted value with optional unit
     */
    public function formatValue($value, bool $includeUnit = true): string
    {
        // Behandle 'ng' (nicht gemessen) und 'nm' (nicht messbar) als Spezialfall
        if (is_string($value) && in_array(strtolower(trim($value)), ['ng', 'nm'])) {
            return strtolower(trim($value));
        }

        $displayValue = $this->toDisplayUnit($value);

        if ($this->currentUnit === 'mmol/l') {
            $formatted = number_format($displayValue, 1, ',', '.');
        } else {
            $formatted = number_format($displayValue, 0, ',', '.');
        }

        return $includeUnit ? $formatted . ' ' . $this->currentUnit : $formatted;
    }

    /**
     * Get the appropriate decimal places for the current unit
     * 
     * @return int Number of decimal places
     */
    public function getDecimalPlaces(): int
    {
        return $this->currentUnit === 'mmol/l' ? 1 : 0;
    }
}
