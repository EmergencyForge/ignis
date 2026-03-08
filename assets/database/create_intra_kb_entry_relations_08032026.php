<?php
/**
 * Migration: Verknüpfte KB-Einträge (Querverweise)
 *
 * Bidirektionale Verknüpfung: Wenn A mit B verknüpft ist, ist B auch mit A verknüpft.
 * Nur eine Richtung wird gespeichert (entry_id < related_entry_id).
 */

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `intra_kb_entry_relations` (
        `entry_id` INT NOT NULL,
        `related_entry_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`entry_id`, `related_entry_id`),
        CONSTRAINT `fk_kb_rel_entry` FOREIGN KEY (`entry_id`) REFERENCES `intra_kb_entries` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_kb_rel_related` FOREIGN KEY (`related_entry_id`) REFERENCES `intra_kb_entries` (`id`) ON DELETE CASCADE,
        CHECK (`entry_id` < `related_entry_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  ✓ Tabelle intra_kb_entry_relations erstellt\n";
