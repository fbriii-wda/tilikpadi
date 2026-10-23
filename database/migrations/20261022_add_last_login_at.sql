ALTER TABLE `user`
    ADD COLUMN `last_login_at` DATETIME NULL DEFAULT NULL,
    ADD INDEX `idx_user_last_login_at` (`last_login_at`);
