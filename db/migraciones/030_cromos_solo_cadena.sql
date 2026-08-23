-- ============================================================================
-- 030 — Cartas exclusivas de cadena
--
-- Cartas que EXISTEN para que las lleven los equipos de las cadenas, pero que
-- ningún jugador puede conseguir: no salen en sobres y no cuentan para el
-- progreso del álbum (si contaran, el álbum sería imposible de completar por
-- definición y la barra mentiría a todo el mundo).
--
-- Es una columna y no una expansión aparte a propósito: una expansión oculta
-- obligaría a filtrarla en cada consulta que hoy une con `expansiones`, y a
-- decidir qué pasa con sus sobres. Una bandera en la carta se lee donde hace
-- falta y no toca nada más.
--
-- Aditiva y re-ejecutable. El bloque IF es la forma de que un segundo pase no
-- reviente: MariaDB no tiene ADD COLUMN IF NOT EXISTS en todas las versiones.
-- ============================================================================

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cromos' AND COLUMN_NAME = 'solo_cadena'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cromos ADD COLUMN solo_cadena TINYINT(1) NOT NULL DEFAULT 0
     COMMENT ''1 = exclusiva de cadenas: ni sobres ni progreso de álbum''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índice para que el filtro de sobres no barra la tabla entera cuando el
-- catálogo crezca.
SET @existeIdx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cromos' AND INDEX_NAME = 'idx_cromos_solo_cadena'
);
SET @sql := IF(@existeIdx = 0,
  'CREATE INDEX idx_cromos_solo_cadena ON cromos (solo_cadena)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
