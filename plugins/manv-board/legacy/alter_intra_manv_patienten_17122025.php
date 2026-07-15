<?php
try {
    $sql = <<<SQL
ALTER TABLE `intra_manv_patienten` 
MODIFY COLUMN `sichtungskategorie` 
enum('SK1','SK2','SK3','SK4','SK5','SK6','tot') DEFAULT NULL 
COMMENT 'SK1=rot/Akute Lebensgefahr, SK2=gelb/Schwere Folgeschäden, SK3=grün/Spätere Behandlung, SK4=blau/Keine zeitnahe Versorgung, SK5=schwarz/Tot, SK6=lila/Beteiligter ohne Verletzung';
SQL;

    $pdo->exec($sql);
    echo "✅ Spalte sichtungskategorie in intra_manv_patienten erfolgreich erweitert (SK5, SK6 hinzugefügt)\n";
} catch (PDOException $e) {
    $message = $e->getMessage();
    // Ignoriere Fehler, wenn die Werte bereits existieren oder Spalte nicht geändert werden muss
    if (strpos($message, "duplicate") === false && strpos($message, "Duplicate") === false) {
        echo "ℹ️  Migration alter_intra_manv_patienten_17122025: " . $message . "\n";
    } else {
        echo "✅ Spalte sichtungskategorie bereits aktualisiert\n";
    }
}
