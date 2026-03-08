<?php
/**
 * Migration: Erweitere FULLTEXT-Indizes für KB Volltextsuche
 *
 * - Alter Index nur auf title, subtitle, med_wirkstoff
 * - Neue Indizes für content, Medikament-Felder und Maßnahmen-Felder
 */

use App\Utils\DatabaseHelper;

// Index 1: Erweitere Hauptindex um content
if (DatabaseHelper::indexExists($pdo, 'intra_kb_entries', 'idx_kb_fulltext')) {
    try {
        $pdo->exec("ALTER TABLE `intra_kb_entries` DROP INDEX `idx_kb_fulltext`");
    } catch (PDOException $e) {
        error_log("Could not drop idx_kb_fulltext: " . $e->getMessage());
    }
}

try {
    $pdo->exec("ALTER TABLE `intra_kb_entries` ADD FULLTEXT INDEX `idx_kb_fulltext` (`title`, `subtitle`, `content`)");
    echo "  ✓ FULLTEXT Index idx_kb_fulltext erweitert (title, subtitle, content)\n";
} catch (PDOException $e) {
    error_log("FULLTEXT index idx_kb_fulltext creation failed: " . $e->getMessage());
}

// Index 2: Medikament-Felder
if (!DatabaseHelper::indexExists($pdo, 'intra_kb_entries', 'idx_kb_fulltext_med')) {
    try {
        $pdo->exec("ALTER TABLE `intra_kb_entries` ADD FULLTEXT INDEX `idx_kb_fulltext_med` (`med_wirkstoff`, `med_wirkstoffgruppe`, `med_indikationen`, `med_kontraindikationen`, `med_dosierung`, `med_besonderheiten`)");
        echo "  ✓ FULLTEXT Index idx_kb_fulltext_med erstellt\n";
    } catch (PDOException $e) {
        error_log("FULLTEXT index idx_kb_fulltext_med creation failed: " . $e->getMessage());
    }
}

// Index 3: Maßnahmen-Felder
if (!DatabaseHelper::indexExists($pdo, 'intra_kb_entries', 'idx_kb_fulltext_mass')) {
    try {
        $pdo->exec("ALTER TABLE `intra_kb_entries` ADD FULLTEXT INDEX `idx_kb_fulltext_mass` (`mass_indikationen`, `mass_kontraindikationen`, `mass_durchfuehrung`, `mass_risiken`)");
        echo "  ✓ FULLTEXT Index idx_kb_fulltext_mass erstellt\n";
    } catch (PDOException $e) {
        error_log("FULLTEXT index idx_kb_fulltext_mass creation failed: " . $e->getMessage());
    }
}
