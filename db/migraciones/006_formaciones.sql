-- ============================================================================
-- 006 — Formaciones alternativas al 1-4-4-2
--
-- Aditiva y re-ejecutable. Todo lo que existe hoy queda en '442', que es
-- exactamente el comportamiento actual: ningún mazo ni ningún duelo ya
-- resuelto cambia de significado al aplicar esta migración.
--
-- La DEFINICIÓN de cada formación (qué línea es cada hueco y en qué
-- coordenada se pinta sobre el campo) vive en PHP, en Tcg::FORMACIONES, no
-- aquí: es estructura, no contenido editable. La base de datos solo guarda
-- QUÉ formación usa cada mazo y CUÁLES ha desbloqueado cada jugador.
-- ============================================================================

-- 1. Formación de cada mazo.
SET @col_existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mazos' AND COLUMN_NAME = 'formacion'
);
SET @sql := IF(@col_existe = 0,
  'ALTER TABLE mazos ADD COLUMN formacion VARCHAR(8) NOT NULL DEFAULT ''442'' AFTER nombre',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Formación CONGELADA de cada bando en un duelo.
--    Sin esto, cambiar la formación del mazo después de comprometerse
--    reinterpretaría las líneas de un duelo ya jugado: los mismos 11 huecos
--    puntuarían con otra estadística y el desglose mentiría sobre lo ocurrido.
--    NULL = duelo anterior a esta migración, que por definición fue un 442.
SET @col_existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelos' AND COLUMN_NAME = 'formacion_creador'
);
SET @sql := IF(@col_existe = 0,
  'ALTER TABLE duelos
     ADD COLUMN formacion_creador VARCHAR(8) NULL AFTER id_mazo_rival,
     ADD COLUMN formacion_rival   VARCHAR(8) NULL AFTER formacion_creador',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Formaciones desbloqueadas por jugador.
--    Solo se guardan las GANADAS. Las libres (1-4-4-2 y 4-3-3) no ocupan
--    ninguna fila: están disponibles siempre y para todos, así que insertar
--    una fila por usuario sería guardar lo que ya se sabe sin preguntarlo.
CREATE TABLE IF NOT EXISTS formaciones_usuario (
  id_formacion_usuario INT(11) NOT NULL AUTO_INCREMENT,
  id_usuario           INT(11) NOT NULL,
  formacion            VARCHAR(8) NOT NULL,
  obtenida             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_formacion_usuario),
  UNIQUE KEY uq_formacion_usuario (id_usuario, formacion),
  CONSTRAINT fk_formacion_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
