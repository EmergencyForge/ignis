<?php
try {
    $sql = <<<SQL
    INSERT IGNORE INTO `intra_users_roles` (`id`, `priority`, `name`, `color`, `permissions`, `created_at`, `default`, `admin`) VALUES
        (1, 10, 'Admin', 'danger', '["admin"]', '2025-03-23 22:17:15', 0, 1),
        (2, 100, 'SGL', 'primary', '["application.view", "application.edit", "edivi.view", "personnel.view", "personnel.edit", "personnel.documents.manage", "users.view", "users.edit", "users.create", "files.upload", "files.log.view"]', '2025-03-23 22:27:45', 0 , 0),
        (3, 110, 'TL', 'primary', '["personnel.view", "personnel.documents.manage"]', '2025-03-23 22:28:16', 0 , 0),
        (4, 200, 'QM-RD', 'info', '["personnel.view", "edivi.view", "edivi.edit"]', '2025-03-23 22:30:31', 0 , 0),
        (5, 210, 'Ausbilder', 'success', '["personnel.view", "personnel.documents.manage"]', '2025-03-23 22:31:57', 0 , 0),
        (6, 220, 'Personaler', 'success', '["personnel.view", "personnel.edit", "personnel.documents.manage"]', '2025-03-23 22:32:18', 0 , 0),
        (7, 999, 'Gast', 'secondary', '[]', '2025-03-23 22:33:25', 1, 0);
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
