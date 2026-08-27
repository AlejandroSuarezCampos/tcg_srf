-- ============================================================================
-- 035 — Universo de cada equipo, y equipos editables desde el panel
--
-- DOS cosas que van juntas porque tocan la misma tabla.
--
-- 1. UNIVERSO. Las cartas salen de dos sitios: las que son de la Superliga
--    Frontier —el juego propio— y las que vienen del Inazuma Eleven original.
--    La distinción es DECORATIVA: no cambia estadísticas, ni rarezas, ni
--    probabilidades, ni nada del motor. Solo se enseña, para que se sepa de
--    dónde viene cada jugador.
--
--    Va en el EQUIPO y no en la carta a propósito. Un equipo pertenece entero
--    a un universo —el Instituto Raimon no tiene jugadores de la Superliga—,
--    así que ponerlo aquí son 24 decisiones en vez de 469, y una carta nueva
--    hereda la correcta sin que nadie tenga que acordarse.
--    ponytail: si algún día hiciera falta una carta cuyo universo NO sea el de
--    su equipo, el sitio para una columna `cromos.universo` que lo pise es
--    este mismo patrón (NULL = hereda), pero hoy no hay ni un caso.
--
--    Por defecto 'srf'. Reasignar los equipos que sean del Inazuma original se
--    hace desde el panel, que es donde se sabe cuál es cuál; hacerlo aquí a
--    ciegas por el nombre acertaría en unos y no en otros.
--
-- 2. ESCUDO Y DESCRIPCIÓN. Hasta ahora `equipos` era solo un id y un nombre, y
--    no había ninguna pantalla para crearlos ni editarlos: para dar de alta un
--    equipo había que entrar en la base de datos a mano. Se le añade lo mínimo
--    para que tenga una ficha de verdad y se puedan gestionar desde el panel.
--
-- Aditiva y re-ejecutable. No cambia ni una fila.
-- ============================================================================

SET NAMES utf8mb4;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'universo'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE equipos ADD COLUMN universo ENUM(''srf'',''ie'') NOT NULL DEFAULT ''srf''
     COMMENT ''srf = Superliga Frontier. ie = Inazuma Eleven Canonical Series. Solo decorativo.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'escudo'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE equipos ADD COLUMN escudo VARCHAR(255) DEFAULT NULL
     COMMENT ''Ruta del escudo, relativa a la raíz del sitio. NULL = sin escudo.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'descripcion'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE equipos ADD COLUMN descripcion VARCHAR(255) DEFAULT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
