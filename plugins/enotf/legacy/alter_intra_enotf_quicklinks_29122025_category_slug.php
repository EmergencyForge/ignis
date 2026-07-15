<?php
try {
    // First, add the new category_slug column
    $sql = <<<SQL
    ALTER TABLE `intra_enotf_quicklinks`
    ADD COLUMN `category_slug` varchar(100) NULL AFTER `category`;
  SQL;

    $pdo->exec($sql);

    // Copy values from category to category_slug
    $pdo->exec("UPDATE `intra_enotf_quicklinks` SET `category_slug` = `category`");

    // Now make category_slug NOT NULL
    $pdo->exec("ALTER TABLE `intra_enotf_quicklinks` MODIFY `category_slug` varchar(100) NOT NULL");

    // Add index
    $pdo->exec("ALTER TABLE `intra_enotf_quicklinks` ADD KEY `idx_category_slug` (`category_slug`)");

    // Drop the old category column
    $pdo->exec("ALTER TABLE `intra_enotf_quicklinks` DROP COLUMN `category`");
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
