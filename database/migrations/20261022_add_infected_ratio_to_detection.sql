ALTER TABLE `detection`
    ADD COLUMN `infected_ratio_percent` DECIMAL(5,2) NULL DEFAULT NULL
        COMMENT 'Estimasi persentase area daun yang terinfeksi';
