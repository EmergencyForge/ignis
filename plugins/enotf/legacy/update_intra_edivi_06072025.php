<?php
try {
    $sql = <<<SQL
        ALTER TABLE `intra_edivi` 
        ADD UNIQUE KEY `uk_enr` (`enr`);
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
