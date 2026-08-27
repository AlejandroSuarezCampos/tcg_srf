-- ============================================================================
-- 031 — Apostar VARIAS cartas en un duelo
--
-- Hasta aquí una apuesta de cartas era exactamente una carta por lado, y eso
-- vivía en `duelo_apuestas.id_coleccion`: una columna, una carta, sin sitio
-- donde poner la segunda. Ahora cada lado pone N cartas de la misma rareza y
-- el que pierde las entrega todas.
--
-- Por qué una tabla aparte y no N columnas ni N filas en `duelo_apuestas`:
--   · N columnas (id_coleccion_1..5) pone un techo arbitrario en el esquema y
--     obliga a un OR por columna en cada consulta que pregunte "¿está esta
--     copia apostada?".
--   · N filas en `duelo_apuestas` obligaría a tirar su UNIQUE (id_duelo,
--     id_usuario), que es justo lo que impide que alguien entre dos veces en
--     la misma sala, y dejaría `monedas` repetido en cada fila.
-- Una tabla de unión conserva las dos garantías y no tiene techo.
--
-- `duelos.cartas_apuesta` guarda CUÁNTAS pone cada lado. Va en el duelo y no
-- se deduce contando filas porque el rival tiene que saber cuántas necesita
-- ANTES de poner ninguna: es parte de las condiciones de la sala, igual que
-- la rareza.
--
-- Aditiva y re-ejecutable. La columna vieja se rellena en la nueva tabla y
-- después desaparece: dejarla ahí, con una sola carta de las N, sería una
-- columna que miente — y la mitad del código que aún la leyera se llevaría
-- solo la primera carta del lote sin dar ningún error.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Cuántas cartas pone cada lado
-- ---------------------------------------------------------------------------
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelos' AND COLUMN_NAME = 'cartas_apuesta'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE duelos ADD COLUMN cartas_apuesta TINYINT UNSIGNED NOT NULL DEFAULT 1
     COMMENT ''Cuántas cartas pone CADA lado. Solo aplica a tipo_apuesta = carta.''
     AFTER id_rareza_apuesta',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------------
-- 2. Qué cartas concretas pone cada lado
--
-- La clave primaria compuesta hace de guardia sola: la misma copia no puede
-- estar dos veces en la misma apuesta, así que no hace falta comprobarlo en
-- PHP antes de insertar.
--
-- `id_coleccion` es UNIQUE a propósito y NO por duelo: una copia solo puede
-- estar comprometida en un duelo a la vez. Sin esta restricción, abrir dos
-- salas seguidas con la misma carta compilaba, se guardaba y solo reventaba
-- al perder las dos — con una sola copia para entregar dos veces.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `duelo_apuesta_cartas` (
  `id_apuesta`   INT(11) NOT NULL,
  `id_coleccion` INT(11) NOT NULL,
  PRIMARY KEY (`id_apuesta`, `id_coleccion`),
  UNIQUE KEY `uq_dac_coleccion` (`id_coleccion`),
  CONSTRAINT `fk_dac_apuesta` FOREIGN KEY (`id_apuesta`)
    REFERENCES `duelo_apuestas` (`id_apuesta`) ON DELETE CASCADE,
  CONSTRAINT `fk_dac_coleccion` FOREIGN KEY (`id_coleccion`)
    REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------------
-- 3. Traer las apuestas que ya existen
--
-- Antes de tocar la columna vieja: si esto no corriera, los duelos abiertos
-- ahora mismo se quedarían sin carta apostada y el ganador no cobraría.
-- `INSERT IGNORE` para que un segundo pase no choque con la clave primaria.
-- ---------------------------------------------------------------------------
SET @existeVieja := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelo_apuestas' AND COLUMN_NAME = 'id_coleccion'
);
SET @sql := IF(@existeVieja = 1,
  'INSERT IGNORE INTO duelo_apuesta_cartas (id_apuesta, id_coleccion)
     SELECT id_apuesta, id_coleccion FROM duelo_apuestas WHERE id_coleccion IS NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------------
-- 4. Retirar la columna vieja (primero su clave ajena, que si no lo impide)
-- ---------------------------------------------------------------------------
SET @existeFk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelo_apuestas'
    AND CONSTRAINT_NAME = 'fk_apuestas_coleccion'
);
SET @sql := IF(@existeFk = 1,
  'ALTER TABLE duelo_apuestas DROP FOREIGN KEY fk_apuestas_coleccion',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existeIdx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelo_apuestas'
    AND INDEX_NAME = 'idx_apuestas_coleccion'
);
SET @sql := IF(@existeIdx = 1,
  'ALTER TABLE duelo_apuestas DROP INDEX idx_apuestas_coleccion',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@existeVieja = 1,
  'ALTER TABLE duelo_apuestas DROP COLUMN id_coleccion',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------------
-- 5. Techo de cuántas cartas caben en una apuesta
--
-- Configurable y no constante en PHP porque es una decisión de equilibrio, no
-- de programa: sin techo, quien tiene 40 comunes puede abrir una sala que
-- nadie más puede aceptar, y la lista de salas se llena de salas muertas.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
  ('duelo_cartas_max', '5', 'Máximo de cartas que se pueden apostar por lado en un duelo de cartas.');
