ALTER TABLE `user`
    ADD COLUMN `firebase_uid` VARCHAR(128) NULL DEFAULT NULL,
    ADD UNIQUE KEY `uq_user_firebase_uid` (`firebase_uid`);
