<?php

/**
 * Add tactical symbol fields to vehicles table
 * Date: 2025-12-25
 */

if (!isset($pdo)) {
    die('Database connection not available');
}

try {
    // Add grundzeichen column
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `grundzeichen` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Grundzeichen' AFTER `kennzeichen`
    ");
    echo "✓ Spalte 'grundzeichen' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'grundzeichen' existiert bereits\n";
    } else {
        throw $e;
    }
}

try {
    // Add organisation column
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `organisation` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Organisation' AFTER `grundzeichen`
    ");
    echo "✓ Spalte 'organisation' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'organisation' existiert bereits\n";
    } else {
        throw $e;
    }
}

try {
    // Add fachaufgabe column
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `fachaufgabe` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Fachaufgabe' AFTER `organisation`
    ");
    echo "✓ Spalte 'fachaufgabe' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'fachaufgabe' existiert bereits\n";
    } else {
        throw $e;
    }
}

try {
    // Add einheit column
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `einheit` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Einheit' AFTER `fachaufgabe`
    ");
    echo "✓ Spalte 'einheit' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'einheit' existiert bereits\n";
    } else {
        throw $e;
    }
}

try {
    // Add symbol column
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `symbol` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Symbol' AFTER `einheit`
    ");
    echo "✓ Spalte 'symbol' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'symbol' existiert bereits\n";
    } else {
        throw $e;
    }
}

try {
    // Add text column
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `text` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Text' AFTER `symbol`
    ");
    echo "✓ Spalte 'text' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'text' existiert bereits\n";
    } else {
        throw $e;
    }
}

try {
    // Add tz_name column
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `tz_name` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Name' AFTER `text`
    ");
    echo "✓ Spalte 'tz_name' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'tz_name' existiert bereits\n";
    } else {
        throw $e;
    }
}

try {
    // Add typ column
    $pdo->exec("
        ALTER TABLE intra_fahrzeuge
        ADD COLUMN `typ` VARCHAR(100) NULL COMMENT 'Taktisches Zeichen: Typ (einsatz, geplant, etc.)' AFTER `tz_name`
    ");
    echo "✓ Spalte 'typ' zu 'intra_fahrzeuge' hinzugefügt\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⏭️  Spalte 'typ' existiert bereits\n";
    } else {
        throw $e;
    }
}
