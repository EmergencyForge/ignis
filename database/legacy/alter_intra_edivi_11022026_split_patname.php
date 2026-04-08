<?php
// Teilt patname in pat_vorname + pat_nachname auf und fügt pat_synced hinzu

try {
    $pdo->exec("ALTER TABLE `intra_edivi` ADD COLUMN `pat_vorname` VARCHAR(255) NULL DEFAULT NULL AFTER `patname`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

try {
    $pdo->exec("ALTER TABLE `intra_edivi` ADD COLUMN `pat_nachname` VARCHAR(255) NULL DEFAULT NULL AFTER `pat_vorname`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

try {
    $pdo->exec("ALTER TABLE `intra_edivi` ADD COLUMN `pat_synced` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pat_nachname`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

// Bestehende patname-Werte in pat_vorname + pat_nachname aufteilen
// Format: "Nachname, Vorname" oder nur "Nachname"
try {
    $rows = $pdo->query("SELECT id, patname FROM intra_edivi WHERE patname IS NOT NULL AND patname != '' AND (pat_vorname IS NULL AND pat_nachname IS NULL)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (strpos($row['patname'], ',') !== false) {
            [$nachname, $vorname] = array_map('trim', explode(',', $row['patname'], 2));
        } else {
            $nachname = trim($row['patname']);
            $vorname = '';
        }
        $stmt = $pdo->prepare("UPDATE intra_edivi SET pat_nachname = ?, pat_vorname = ? WHERE id = ?");
        $stmt->execute([$nachname, $vorname ?: null, $row['id']]);
    }
} catch (PDOException $e) {
    echo "Datenmigration-Fehler: " . $e->getMessage();
}
