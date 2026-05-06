<?php
try {
    // Migriere bestehende Dokumente auf template_id
    $migrations = [
        0 => 14, // Ernennungsurkunde
        1 => 2,  // Beförderungsurkunde
        2 => 3,  // Entlassungsurkunde
        5 => 5,  // Ausbildungszertifikat
        6 => 6,  // Lehrgangszertifikat
        7 => 7,  // Fachlehrgang
        10 => 10, // Abmahnung
        11 => 11, // Dienstenthebung
        12 => 12, // Dienstentfernung
        13 => 13  // Kündigung
    ];

    foreach ($migrations as $type => $templateId) {
        $stmt = $pdo->prepare("
            UPDATE intra_mitarbeiter_dokumente 
            SET template_id = ? 
            WHERE type = ? AND (template_id IS NULL OR template_id = 0)
        ");
        $stmt->execute([$templateId, $type]);
    }

    // Migriere alte Daten in custom_data JSON
    $stmt = $pdo->query("
        SELECT id, type, erhalter_rang, erhalter_rang_rd, erhalter_quali, inhalt, suspendtime 
        FROM intra_mitarbeiter_dokumente 
        WHERE custom_data IS NULL
    ");

    $updateStmt = $pdo->prepare("UPDATE intra_mitarbeiter_dokumente SET custom_data = ? WHERE id = ?");

    while ($doc = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customData = [];

        if ($doc['erhalter_rang']) $customData['erhalter_rang'] = $doc['erhalter_rang'];
        if ($doc['erhalter_rang_rd']) $customData['erhalter_rang_rd'] = $doc['erhalter_rang_rd'];
        if ($doc['erhalter_quali']) $customData['erhalter_quali'] = $doc['erhalter_quali'];
        if ($doc['inhalt']) $customData['inhalt'] = $doc['inhalt'];
        if ($doc['suspendtime']) $customData['suspendtime'] = $doc['suspendtime'];

        if (!empty($customData)) {
            $updateStmt->execute([json_encode($customData), $doc['id']]);
        }
    }

    echo "✓ Bestehende Dokumente migriert\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
