<?php

namespace App\Utils;

use PDO;
use PDOException;

/**
 * DatabaseHelper - Hilfsfunktionen für Datenbank-Operationen
 * 
 * Verwendung in Migrations:
 *   use App\Utils\DatabaseHelper;
 *   DatabaseHelper::addIndexIfNotExists($pdo, 'table', 'idx_name', 'column');
 */
class DatabaseHelper
{
    /**
     * Prüft ob ein Index existiert
     */
    public static function indexExists(PDO $pdo, string $table, string $indexName): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = :indexName");
            $stmt->execute(['indexName' => $indexName]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Tabelle existiert möglicherweise nicht
            return false;
        }
    }

    /**
     * Fügt einen Index hinzu, wenn er noch nicht existiert
     * 
     * @param PDO $pdo PDO-Verbindung
     * @param string $table Tabellenname
     * @param string $indexName Name des Index
     * @param string $columns Spalten (z.B. 'col1' oder 'col1, col2' oder 'col1(50)')
     * @param string $indexType INDEX, UNIQUE, FULLTEXT
     * @return bool True wenn Index erstellt wurde, False wenn bereits vorhanden oder Fehler
     */
    public static function addIndexIfNotExists(
        PDO $pdo,
        string $table,
        string $indexName,
        string $columns,
        string $indexType = 'INDEX'
    ): bool {
        if (self::indexExists($pdo, $table, $indexName)) {
            return false; // Index existiert bereits
        }

        try {
            $sql = "ALTER TABLE `$table` ADD $indexType `$indexName` ($columns)";
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            \App\Logging\Logger::error("Index creation failed for $table.$indexName: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Entfernt einen Index, wenn er existiert
     */
    public static function dropIndexIfExists(PDO $pdo, string $table, string $indexName): bool
    {
        if (!self::indexExists($pdo, $table, $indexName)) {
            return false;
        }

        try {
            $pdo->exec("ALTER TABLE `$table` DROP INDEX `$indexName`");
            return true;
        } catch (PDOException $e) {
            \App\Logging\Logger::error("Index drop failed for $table.$indexName: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prüft ob eine Spalte existiert
     */
    public static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
            $stmt->execute(['column' => $column]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Prüft ob eine Tabelle existiert
     */
    public static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
            $stmt->execute(['table' => $table]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Fügt eine Spalte hinzu, wenn sie noch nicht existiert
     */
    public static function addColumnIfNotExists(
        PDO $pdo,
        string $table,
        string $column,
        string $definition,
        ?string $after = null
    ): bool {
        if (self::columnExists($pdo, $table, $column)) {
            return false;
        }

        try {
            $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
            if ($after !== null) {
                $sql .= " AFTER `$after`";
            }
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            \App\Logging\Logger::error("Column creation failed for $table.$column: " . $e->getMessage());
            return false;
        }
    }
}
