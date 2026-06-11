-- Migration: add_manifest_documents_table
-- Note: Ensure PHP ini settings allow upload_max_filesize >= 10M and post_max_size >= 20M

CREATE TABLE IF NOT EXISTS `manifest_documents` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `manifest_id`   INT NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name`   VARCHAR(255) NOT NULL,
    `file_path`     VARCHAR(500) NOT NULL,
    `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
    `mime_type`     VARCHAR(100) DEFAULT NULL,
    `uploaded_by`   INT NOT NULL,
    `uploaded_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_manifest_id` (`manifest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
