-- ef_mechanic MVP schema (idempotent).
-- Unique plates are recommended; model_hash is stored as a secondary key.

CREATE TABLE IF NOT EXISTS `ef_mechanic_mileage` (
    `plate` VARCHAR(16) NOT NULL,
    `model_hash` INT NOT NULL DEFAULT 0,
    `km` DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`plate`, `model_hash`),
    INDEX `idx_ef_mechanic_mileage_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ef_mechanic_parts` (
    `plate` VARCHAR(16) NOT NULL,
    `model_hash` INT NOT NULL DEFAULT 0,
    `part_id` VARCHAR(64) NOT NULL,
    `health` DECIMAL(5, 2) NOT NULL DEFAULT 100.00,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`plate`, `model_hash`, `part_id`),
    INDEX `idx_ef_mechanic_parts_health` (`health`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ef_mechanic_invoices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `location_id` VARCHAR(64) NOT NULL,
    `author` VARCHAR(64) NOT NULL,
    `customer` VARCHAR(64) DEFAULT NULL,
    `payload` LONGTEXT NOT NULL,
    `total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(16) NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_ef_mechanic_invoices_location` (`location_id`),
    INDEX `idx_ef_mechanic_invoices_status` (`status`),
    INDEX `idx_ef_mechanic_invoices_customer` (`customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ef_mechanic_accounts` (
    `location_id` VARCHAR(64) NOT NULL,
    `balance` DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ef_mechanic_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plate` VARCHAR(16) NOT NULL,
    `action` VARCHAR(64) NOT NULL,
    `actor` VARCHAR(64) DEFAULT NULL,
    `data` LONGTEXT NULL,
    `km` DECIMAL(12, 3) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ef_mechanic_history_plate` (`plate`),
    INDEX `idx_ef_mechanic_history_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
