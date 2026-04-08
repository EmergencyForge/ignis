<?php
try {
    // 1. ERNENNUNGSURKUNDE (Template ID 14)
    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, field_options, is_required, gender_specific, sort_order)
VALUES (14, 'erhalter_rang', 'Dienstgrad', 'db_dg', NULL, 1, 1, 0)
");
    $stmt->execute();

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (14, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 1)
");
    $stmt->execute();

    // 2. BEFÖRDERUNGSURKUNDE (Template ID 1)
    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, field_options, is_required, gender_specific, sort_order)
VALUES (1, 'erhalter_rang', 'Neuer Dienstgrad', 'db_dg', NULL, 1, 1, 0)
");
    $stmt->execute();

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (1, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 1)
");
    $stmt->execute();

    // 3. ENTLASSUNGSURKUNDE (Template ID 2)
    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (2, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 0)
");
    $stmt->execute();

    // 4. AUSBILDUNGSZERTIFIKAT (Template ID 5)
    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, field_options, is_required, gender_specific, sort_order)
VALUES (5, 'erhalter_rang_rd', 'Qualifikation', 'db_rdq', NULL, 1, 1, 0)
");
    $stmt->execute();

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (5, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 1)
");
    $stmt->execute();

    // 5. LEHRGANGSZERTIFIKAT (Template ID 6)
    $qualiOptionsLehrgang = json_encode([
        ["value" => "0", "label" => "Brandmeister/-in", "label_m" => "Brandmeister", "label_w" => "Brandmeisterin"],
        ["value" => "1", "label" => "Gruppenführer/-in", "label_m" => "Gruppenführer", "label_w" => "Gruppenführerin"],
        ["value" => "2", "label" => "Zugführer/-in", "label_m" => "Zugführer", "label_w" => "Zugführerin"],
        ["value" => "4", "label" => "Sonderfahrzeug-Maschinist/-in", "label_m" => "Sonderfahrzeug-Maschinist", "label_w" => "Sonderfahrzeug-Maschinistin"]
    ]);

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, field_options, is_required, gender_specific, sort_order)
VALUES (6, 'erhalter_quali', 'Qualifikation', 'select', ?, 1, 1, 0)
");
    $stmt->execute([$qualiOptionsLehrgang]);

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (6, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 1)
");
    $stmt->execute();

    // Config für Lehrgangszertifikat (nur setzen, wenn noch leer)
    $configLehrgang = json_encode([
        "fields" => [
            "erhalter_quali" => [
                "type" => "select",
                "label" => "Qualifikation",
                "options" => json_decode($qualiOptionsLehrgang, true),
                "gender_specific" => true
            ]
        ]
    ]);
    $pdo->prepare("UPDATE intra_dokument_templates SET config = ? WHERE id = 6 AND (config IS NULL OR config = '{}' OR config = '')")->execute([$configLehrgang]);

    // 6. FACHLEHRGANG (Template ID 7)
    $qualiOptionsFachlehrgang = json_encode([
        ["value" => "3", "label" => "Leitstellen-Disponent/-in", "label_m" => "Leitstellen-Disponent", "label_w" => "Leitstellen-Disponentin"],
        ["value" => "5", "label" => "Helfergrundmodul (SEG)", "label_m" => "Helfergrundmodul (SEG)", "label_w" => "Helfergrundmodul (SEG)"],
        ["value" => "6", "label" => "SEG-Sanitäter/-in", "label_m" => "SEG-Sanitäter", "label_w" => "SEG-Sanitäterin"],
        ["value" => "7", "label" => "Gruppenführer/-in-BevS", "label_m" => "Gruppenführer-BevS", "label_w" => "Gruppenführerin-BevS"],
        ["value" => "8", "label" => "HEMS-TC", "label_m" => "HEMS-TC", "label_w" => "HEMS-TC"],
        ["value" => "9", "label" => "Luftrettungspilot/-in", "label_m" => "Luftrettungspilot", "label_w" => "Luftrettungspilotin"]
    ]);

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, field_options, is_required, gender_specific, sort_order)
VALUES (7, 'erhalter_quali', 'Qualifikation', 'select', ?, 1, 1, 0)
");
    $stmt->execute([$qualiOptionsFachlehrgang]);

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (7, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 1)
");
    $stmt->execute();

    // Config für Fachlehrgang (nur setzen, wenn noch leer)
    $configFachlehrgang = json_encode([
        "fields" => [
            "erhalter_quali" => [
                "type" => "select",
                "label" => "Qualifikation",
                "options" => json_decode($qualiOptionsFachlehrgang, true),
                "gender_specific" => true
            ]
        ]
    ]);
    $pdo->prepare("UPDATE intra_dokument_templates SET config = ? WHERE id = 7 AND (config IS NULL OR config = '{}' OR config = '')")->execute([$configFachlehrgang]);

    // 7. SCHRIFTLICHE ABMAHNUNG (Template ID 10)
    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (10, 'inhalt', 'Begründung', 'richtext', 1, 0)
");
    $stmt->execute();

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (10, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 1)
");
    $stmt->execute();

    // 8. VORLÄUFIGE DIENSTENTHEBUNG (Template ID 11)
    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (11, 'inhalt', 'Begründung', 'richtext', 1, 0)
");
    $stmt->execute();

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (11, 'suspendtime', 'Suspendiert bis (leer für unbegrenzt)', 'date', 0, 1)
");
    $stmt->execute();

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (11, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 2)
");
    $stmt->execute();

    // 9. DIENSTENTFERNUNG (Template ID 12)
    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (12, 'inhalt', 'Begründung', 'richtext', 1, 0)
");
    $stmt->execute();

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (12, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 1)
");
    $stmt->execute();

    // 10. AUßERORDENTLICHE KÜNDIGUNG (Template ID 13)
    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (13, 'inhalt', 'Begründung', 'richtext', 1, 0)
");
    $stmt->execute();

    $stmt = $pdo->prepare("
INSERT IGNORE INTO intra_dokument_template_fields
(template_id, field_name, field_label, field_type, is_required, sort_order)
VALUES (13, 'ausstellungsdatum', 'Ausstellungsdatum', 'date', 1, 1)
");
    $stmt->execute();

    echo "✓ Template-Felder eingefügt\n";
    echo " - Ernennungsurkunde: erhalter_rang (db_dg)\n";
    echo " - Beförderungsurkunde: erhalter_rang (db_dg)\n";
    echo " - Entlassungsurkunde: keine speziellen Felder\n";
    echo " - Ausbildungszertifikat: erhalter_rang_rd (db_rdq)\n";
    echo " - Lehrgangszertifikat: erhalter_quali (select mit 4 Optionen)\n";
    echo " - Fachlehrgang: erhalter_quali (select mit 6 Optionen)\n";
    echo " - Alle Schreiben: inhalt (richtext)\n";
    echo " - Dienstenthebung: zusätzlich suspendtime (date, optional)\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
