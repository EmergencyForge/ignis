<?php
// Praktikant (3. Person) auf Fahrzeugen

try {
    $pdo->exec("ALTER TABLE `intra_edivi` ADD COLUMN `fzg_transp_perso_3` VARCHAR(255) DEFAULT NULL AFTER `fzg_transp_perso_2`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

try {
    $pdo->exec("ALTER TABLE `intra_edivi` ADD COLUMN `fzg_na_perso_3` VARCHAR(255) DEFAULT NULL AFTER `fzg_na_perso_2`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}
