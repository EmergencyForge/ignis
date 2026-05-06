<?php
try {
    // System-Templates anlegen (nur wenn noch nicht vorhanden)
    $templates = [
        ['id' => 1, 'name' => 'Beförderungsurkunde', 'category' => 'urkunde', 'file' => 'befoerderung.html.twig'],
        ['id' => 2, 'name' => 'Entlassungsurkunde', 'category' => 'urkunde', 'file' => 'entlassung.html.twig'],
        ['id' => 5, 'name' => 'Ausbildungszertifikat', 'category' => 'zertifikat', 'file' => 'ausbildung.html.twig'],
        ['id' => 6, 'name' => 'Lehrgangszertifikat', 'category' => 'zertifikat', 'file' => 'lehrgang.html.twig'],
        ['id' => 7, 'name' => 'Lehrgangszertifikat Fachdienste', 'category' => 'zertifikat', 'file' => 'fachlehrgang.html.twig'],
        ['id' => 10, 'name' => 'Schriftliche Abmahnung', 'category' => 'schreiben', 'file' => 'abmahnung.html.twig'],
        ['id' => 11, 'name' => 'Vorläufige Dienstenthebung', 'category' => 'schreiben', 'file' => 'dienstenthebung.html.twig'],
        ['id' => 12, 'name' => 'Dienstentfernung', 'category' => 'schreiben', 'file' => 'dienstentfernung.html.twig'],
        ['id' => 13, 'name' => 'Außerordentliche Kündigung', 'category' => 'schreiben', 'file' => 'kuendigung.html.twig'],
        ['id' => 14, 'name' => 'Ernennungsurkunde', 'category' => 'urkunde', 'file' => 'ernennung.html.twig'],
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO intra_dokument_templates (id, name, category, template_file, is_system, config)
        VALUES (:id, :name, :category, :file, 1, '{}')
    ");

    foreach ($templates as $tpl) {
        $stmt->execute([
            'id' => $tpl['id'],
            'name' => $tpl['name'],
            'category' => $tpl['category'],
            'file' => $tpl['file']
        ]);
    }

    echo "✓ System-Templates angelegt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
