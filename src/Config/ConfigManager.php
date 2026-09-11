<?php

namespace App\Config;

use Illuminate\Database\Capsule\Manager as Capsule;

class ConfigManager
{
    private static ?array $configCache = null;

    /**
     * Load all configuration values from database and define them as constants
     * This maintains backward compatibility with existing code using define()
     */
    public function loadAndDefineConfig(): void
    {
        $configs = $this->getAllConfig();

        foreach ($configs as $config) {
            $key = $config['config_key'];
            $value = $config['config_value'];
            $type = $config['config_type'];

            // Convert value based on type
            $definedValue = $this->convertValue($value, $type);

            // Define constant if not already defined
            if (!defined($key)) {
                define($key, $definedValue);
            }
        }
    }

    /**
     * Get all configuration values from database
     *
     * @return array Array of configuration records
     */
    public function getAllConfig(): array
    {
        // Use cache if available
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        try {
            self::$configCache = Capsule::table('intra_config')
                ->orderBy('display_order')
                ->orderBy('config_key')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
            return self::$configCache;
        } catch (\PDOException $e) {
            \App\Logging\Logger::warning("Failed to load config: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get configuration values grouped by category
     *
     * @return array Array grouped by category
     */
    public function getConfigByCategory(): array
    {
        $configs = $this->getAllConfig();
        $grouped = [];

        foreach ($configs as $config) {
            $category = $config['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $config;
        }

        return $grouped;
    }

    /**
     * Get a single configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default if not found
     */
    public function get(string $key, $default = null)
    {
        try {
            $result = Capsule::table('intra_config')
                ->where('config_key', $key)
                ->first(['config_value', 'config_type']);

            if ($result) {
                return $this->convertValue($result->config_value, $result->config_type);
            }

            return $default;
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to get config value: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value New value
     * @param int|null $userId User ID making the change
     * @return bool Success status
     */
    public function update(string $key, $value, ?int $userId = null): bool
    {
        try {
            // Clear cache
            self::$configCache = null;

            Capsule::table('intra_config')
                ->where('config_key', $key)
                ->where('is_editable', 1)
                ->update([
                    'config_value' => $value,
                    'updated_by'   => $userId,
                    'updated_at'   => Capsule::raw('NOW()'),
                ]);

            return true;
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Failed to update config value: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update multiple configuration values at once
     *
     * @param array $updates Array of key => value pairs
     * @param int|null $userId User ID making the changes
     * @return array Array with success status and list of failed keys
     */
    public function updateMultiple(array $updates, ?int $userId = null): array
    {
        $updated = [];

        $connection = Capsule::connection();
        $connection->beginTransaction();

        try {
            foreach ($updates as $key => $value) {
                $connection->table('intra_config')
                    ->where('config_key', $key)
                    ->where('is_editable', 1)
                    ->update([
                        'config_value' => $value,
                        'updated_by'   => $userId,
                        'updated_at'   => Capsule::raw('NOW()'),
                    ]);
                $updated[] = $key;
            }

            $connection->commit();
            // Clear cache after successful commit
            self::$configCache = null;
            return ['success' => true, 'updated' => $updated, 'failed' => []];
        } catch (\PDOException $e) {
            $connection->rollBack();
            \App\Logging\Logger::error("Failed to update multiple config values: " . $e->getMessage());
            return ['success' => false, 'updated' => [], 'failed' => array_keys($updates)];
        }
    }

    /**
     * Convert string value to appropriate type
     *
     * @param string $value String value from database
     * @param string $type Type specification
     * @return mixed Converted value
     */
    private function convertValue(?string $value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'boolean':
                return $value === 'true' || $value === '1' || $value === 'yes';
            case 'integer':
                return (int)$value;
            case 'color':
            case 'url':
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * Get category display name
     *
     * @param string $category Category key
     * @return string Display name
     */
    public function getCategoryDisplayName(string $category): string
    {
        $names = [
            'basis' => 'Basis Daten',
            'server' => 'Server Daten',
            'rp' => 'RP Daten',
            'funktionen' => 'Funktionen',
            'integrationen' => 'Integrationen',
            'rechtliches' => 'Rechtliches',
        ];

        return $names[$category] ?? ucfirst($category);
    }
}
