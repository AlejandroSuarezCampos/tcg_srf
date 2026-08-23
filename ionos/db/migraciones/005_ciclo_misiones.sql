-- ============================================================================
-- 005 — Preparación de ciclo para Misiones (unica / diaria / semanal)
-- Aditiva y re-ejecutable. No activa nada: todas las misiones existentes
-- quedan en 'unica' (comportamiento actual, sin cambios de conducta).
-- La lógica de diaria/semanal NO se construye en esta pasada — solo se deja
-- el hueco en el esquema para no tener que rediseñar cuando se decida usarla.
-- ============================================================================

-- 1. Ciclo por misión.
SET @col_existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'misiones' AND COLUMN_NAME = 'ciclo'
);
SET @sql := IF(@col_existe = 0,
  'ALTER TABLE misiones ADD COLUMN ciclo ENUM(''unica'',''diaria'',''semanal'') NOT NULL DEFAULT ''unica'' AFTER tipo',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Periodo del reclamo. Cadena vacía (no NULL) para 'unica', porque un
--    índice único de MySQL no considera duplicados dos NULL — con '' sí se
--    sigue impidiendo reclamar dos veces una misión de una sola vez.
--    Formato futuro: 'YYYY-MM-DD' para diaria, 'YYYY-Wnn' para semanal.
SET @col_existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'misiones_progreso' AND COLUMN_NAME = 'periodo'
);
SET @sql := IF(@col_existe = 0,
  'ALTER TABLE misiones_progreso ADD COLUMN periodo VARCHAR(10) NOT NULL DEFAULT '''' AFTER id_mision',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. La unicidad pasa a incluir el periodo: hoy periodo siempre es '', así
--    que en la práctica sigue siendo "una vez por usuario y misión".
SET @idx_existe := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'misiones_progreso'
    AND INDEX_NAME = 'uq_progreso_usuario_mision_periodo'
);
SET @sql := IF(@idx_existe = 0,
  'ALTER TABLE misiones_progreso DROP INDEX uq_progreso_usuario_mision, ADD UNIQUE KEY uq_progreso_usuario_mision_periodo (id_usuario, id_mision, periodo)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
