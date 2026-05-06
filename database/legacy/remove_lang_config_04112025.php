<?php
try {
    // Remove LANG config entry as it's not currently in use
    $sql = <<<SQL
    DELETE FROM intra_config WHERE config_key = 'LANG';
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
