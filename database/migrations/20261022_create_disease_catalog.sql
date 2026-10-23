CREATE TABLE `disease_catalog` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `disease_name` VARCHAR(150) NOT NULL,
    `cause` TEXT NOT NULL,
    `infected_ratio` DECIMAL(5,2) NOT NULL,
    `severity` ENUM('Ringan', 'Sedang', 'Parah') NOT NULL,
    `recommendation` TEXT NOT NULL,
    `image_path` VARCHAR(512) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_disease_catalog_name` (`disease_name`),
    KEY `idx_disease_catalog_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
