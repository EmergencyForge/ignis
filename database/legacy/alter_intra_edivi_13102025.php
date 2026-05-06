<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `na_nachf` TINYINT(1) DEFAULT NULL
    AFTER `transportziel`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `ebesonderheiten` TEXT DEFAULT NULL
    AFTER `na_nachf`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `rettungstechnik` TEXT DEFAULT NULL
    AFTER `sz_toleranz_2`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `lagerung` TINYINT(2) DEFAULT NULL
    AFTER `sz_toleranz_2`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `waerme_passiv` TINYINT(1) DEFAULT NULL
    AFTER `rettungstechnik`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `e_reposition` TINYINT(1) DEFAULT NULL
    AFTER `waerme_passiv`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `e_verband` TINYINT(1) DEFAULT NULL
    AFTER `e_reposition`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `e_krintervention` TINYINT(1) DEFAULT NULL
    AFTER `e_verband`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `e_kuehlung` TINYINT(1) DEFAULT NULL
    AFTER `e_krintervention`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `waerme_aktiv` TINYINT(1) DEFAULT NULL
    AFTER `e_kuehlung`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `e_narkose` TINYINT(1) DEFAULT NULL
    AFTER `waerme_aktiv`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `e_tourniquet` TINYINT(1) DEFAULT NULL
    AFTER `e_narkose`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `e_cpr` TINYINT(1) DEFAULT NULL
    AFTER `e_tourniquet`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `c_rekap` TINYINT(1) DEFAULT NULL
    AFTER `c_puls_rad`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `c_blutung` TINYINT(1) DEFAULT NULL
    AFTER `c_rekap`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
