-- ============================================================================
-- 008 — Quinta dificultad (Muy difícil) + parámetros a recalibrar
--
-- Aditiva y re-ejecutable. El ALTER de enum es la única parte no trivial:
-- MariaDB no tiene "ADD VALUE" para enums, así que se reescribe la columna
-- entera con MODIFY. Es seguro porque solo AÑADE un valor, ningún dato
-- existente usa 'muy_dificil' todavía.
-- ============================================================================

SET @tipo_actual := (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelos' AND COLUMN_NAME = 'dificultad'
);
SET @sql := IF(@tipo_actual NOT LIKE '%muy_dificil%',
  'ALTER TABLE duelos MODIFY COLUMN dificultad ENUM(''facil'',''medio'',''dificil'',''muy_dificil'',''extremo'') NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Parámetros de la nueva dificultad. Los valores son provisionales: los fija
-- de verdad la calibración por simulación que se ejecuta después de aplicar
-- esta migración (bloque de balance del formulario de estadísticas ponderadas).
INSERT IGNORE INTO configuracion (clave, valor) VALUES
  ('pve_mult_muy_dificil',          '1.25'),
  ('pve_compos_mult_muy_dificil',   '1.55'),
  ('pve_tiers_muy_dificil',         '20,40,40'),
  ('pve_rareza_max_muy_dificil',    '0');
