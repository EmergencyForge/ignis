<?php
try {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO intra_config
        (config_key, config_value, config_type, category, description, is_editable, display_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $configs = [
        ['FEDERATION_ENABLED', 'false', 'boolean', 'funktionen', 'Instanzübergreifende Vernetzung aktivieren', 1, 50],
        ['FEDERATION_INSTANCE_ID', '', 'string', 'funktionen', 'Eindeutige Instanz-ID (wird automatisch generiert)', 0, 51],
        ['FEDERATION_INSTANCE_NAME', '', 'string', 'funktionen', 'Anzeigename dieser Instanz für verbundene Instanzen', 1, 52],
    ];

    foreach ($configs as $config) {
        $stmt->execute($config);
    }
} catch (PDOException $e) {
    echo $e->getMessage();
}
