<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `diagnose_haupt` TEXT DEFAULT NULL 
    AFTER `medis`;
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
    ADD COLUMN `diagnose_weitere` TEXT DEFAULT NULL 
    AFTER `diagnose_haupt`;
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
    ADD COLUMN `psych` TEXT DEFAULT NULL 
    AFTER `medis`;
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
    ADD COLUMN `uebergabe_an` TINYINT(3) DEFAULT NULL 
    AFTER `anmerkungen`;
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
    ADD COLUMN `uebergabe_ort` TINYINT(3) DEFAULT NULL 
    AFTER `anmerkungen`;
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
    ADD COLUMN `entlastungspunktion` TINYINT(1) DEFAULT NULL 
    AFTER `awsicherung_2`;
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
    ADD COLUMN `hws_immo` TINYINT(1) DEFAULT NULL 
    AFTER `entlastungspunktion`;
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
    ADD COLUMN `c_puls_rad` TINYINT(3) DEFAULT NULL 
    AFTER `c_ekg`;
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
    ADD COLUMN `c_puls_reg` TINYINT(3) DEFAULT NULL 
    AFTER `c_ekg`;
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
    ADD COLUMN `eart` TINYINT(1) DEFAULT NULL 
    AFTER `eort`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
