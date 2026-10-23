DELETE FROM `role`
WHERE `nama_role` = 'Penyuluh Pertanian'
  AND NOT EXISTS (
      SELECT 1
      FROM `user`
      WHERE `user`.`id_role` = `role`.`id_role`
  );
