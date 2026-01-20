<?php

namespace App\Config;

use PDO;
use PDOException;

class ConfigManager
{
    private PDO $pdo;
    private static ?array $configCache = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

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
            $stmt = $this->pdo->query("
                SELECT * FROM intra_config 
                ORDER BY display_order ASC, config_key ASC
            ");
            self::$configCache = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return self::$configCache;
        } catch (PDOException $e) {
            error_log("Failed to load config: " . $e->getMessage());
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
     * @return mixed|null Configuration value or null if not found
     */
    public function get(string $key)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT config_value, config_type FROM intra_config 
                WHERE config_key = ?
            ");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $this->convertValue($result['config_value'], $result['config_type']);
            }

            return null;
        } catch (PDOException $e) {
            error_log("Failed to get config value: " . $e->getMessage());
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

            $stmt = $this->pdo->prepare("
                UPDATE intra_config 
                SET config_value = ?, updated_by = ?, updated_at = NOW()
                WHERE config_key = ? AND is_editable = 1
            ");

            return $stmt->execute([$value, $userId, $key]);
        } catch (PDOException $e) {
            error_log("Failed to update config value: " . $e->getMessage());
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
        $failed = [];
        $updated = [];

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                UPDATE intra_config 
                SET config_value = ?, updated_by = ?, updated_at = NOW()
                WHERE config_key = ? AND is_editable = 1
            ");

            foreach ($updates as $key => $value) {
                if ($stmt->execute([$value, $userId, $key])) {
                    $updated[] = $key;
                } else {
                    $failed[] = $key;
                }
            }

            if (empty($failed)) {
                $this->pdo->commit();
                // Clear cache after successful commit
                self::$configCache = null;
                return ['success' => true, 'updated' => $updated, 'failed' => []];
            } else {
                $this->pdo->rollBack();
                return ['success' => false, 'updated' => [], 'failed' => array_keys($updates)];
            }
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Failed to update multiple config values: " . $e->getMessage());
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
            'telemetrie' => 'Telemetrie & Announcements',
        ];

        return $names[$category] ?? ucfirst($category);
    }
}
