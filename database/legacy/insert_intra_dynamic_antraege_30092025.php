<?php
try {
    $sql = <<<SQL
    INSERT IGNORE INTO `intra_antrag_typen` 
        (`name`, `beschreibung`, `icon`, `aktiv`, `sortierung`, `tabelle_name`) 
        VALUES 
    ('Beförderungsantrag', 'Antrag auf Beförderung in den nächsten Dienstgrad', 'fa-solid fa-angles-up', 1, 1, NULL);

    SET @bef_typ_id = LAST_INSERT_ID();

    INSERT IGNORE INTO `intra_antrag_felder` 
        (`antragstyp_id`, `feldname`, `label`, `feldtyp`, `pflichtfeld`, `sortierung`, `breite`, `readonly`, `auto_fill`) 
        VALUES
        (@bef_typ_id, 'name_dn', 'Name und Dienstnummer', 'text', 1, 1, 'half', 1, 'fullname_dienstnr'),
        (@bef_typ_id, 'dienstgrad', 'Aktueller Dienstgrad', 'text', 1, 2, 'half', 1, 'dienstgrad'),
    (@bef_typ_id, 'freitext', 'Schriftlicher Antrag', 'textarea', 1, 3, 'full', 0, NULL);

    INSERT IGNORE INTO `intra_antrag_typen` 
        (`name`, `beschreibung`, `icon`, `aktiv`, `sortierung`, `tabelle_name`) 
        VALUES 
    ('Urlaubsantrag', 'Beantragung von Urlaub oder Dienstfreistellung', 'fa-solid fa-umbrella-beach', 1, 2, NULL);

    SET @urlaub_typ_id = LAST_INSERT_ID();

    INSERT IGNORE INTO `intra_antrag_felder` 
        (`antragstyp_id`, `feldname`, `label`, `feldtyp`, `pflichtfeld`, `sortierung`, `breite`, `platzhalter`, `auto_fill`) 
        VALUES
        (@urlaub_typ_id, 'name_dn', 'Name und Dienstnummer', 'text', 1, 1, 'half', NULL, 'fullname_dienstnr'),
        (@urlaub_typ_id, 'dienstgrad', 'Dienstgrad', 'text', 1, 2, 'half', NULL, 'dienstgrad'),
        (@urlaub_typ_id, 'von_datum', 'Urlaub von', 'date', 1, 3, 'half', 'TT.MM.JJJJ', NULL),
        (@urlaub_typ_id, 'bis_datum', 'Urlaub bis', 'date', 1, 4, 'half', 'TT.MM.JJJJ', NULL),
    (@urlaub_typ_id, 'grund', 'Grund / Anmerkungen', 'textarea', 0, 5, 'full', 'Optional: Begründung für den Urlaubsantrag', NULL);
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
