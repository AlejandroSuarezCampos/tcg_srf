-- ============================================================================
-- 043 · LOS EQUIPOS DE CADENA PUEDEN SALTARSE LAS REGLAS, Y LAS DIFICULTADES
--       SE PUEDEN QUITAR DE UNA CADENA ENTERA
--
-- 1) `sin_malus` por nodo y dificultad
--    `compos_libres` ya existía en la tabla desde la `029` pero NO LA LEÍA
--    NADIE: era una columna muerta. Ahora quita el tope por línea del rival
--    automático, y esta nueva le perdona además el malus de coherencia.
--
--    Las dos son reglas pensadas para el juego ENTRE PERSONAS —que nadie
--    apile compos sin límite, que un mazo carísimo sin coherencia pague por
--    ello—. A un rival de cadena, que no compite por nada ni sube en ninguna
--    clasificación, solo le impedían ser el jefe final que se pretendía. Con
--    las dos puestas se puede montar un once con once legendarias, compos por
--    encima del tope y sin castigo, que es exactamente lo que se pedía.
--
--    NUNCA se aplican al jugador: se leen solo para el lado del bot.
--
-- 2) Quitar dificultades de una cadena entera
--    Ya se podía desactivar una dificultad NODO A NODO (`activa = 0`). Lo que
--    faltaba era hacerlo de una vez para toda la cadena, que es como se monta
--    una cadena "solo Extremo": con veinte nodos había que entrar veinte veces.
--    No hace falta columna nueva — el editor escribe la misma fila `activa=0`
--    en todos los nodos de la cadena de una tacada.
--
-- Idempotente.
-- ============================================================================

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cadena_nodo_dificultad'
    AND COLUMN_NAME = 'sin_malus'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cadena_nodo_dificultad
     ADD COLUMN sin_malus TINYINT(1) NULL DEFAULT NULL
     COMMENT ''1 = el rival de este nodo no paga malus de coherencia. NULL = como el global.''
     AFTER compos_libres',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Valores globales por dificultad, para poder activarlo sin ir nodo a nodo.
-- A cero por defecto: nada cambia hasta que alguien lo encienda a propósito.
INSERT INTO configuracion (clave, valor) VALUES
  ('pve_compos_libres_facil',       '0'),
  ('pve_compos_libres_medio',       '0'),
  ('pve_compos_libres_dificil',     '0'),
  ('pve_compos_libres_muy_dificil', '0'),
  ('pve_compos_libres_extremo',     '0'),
  ('pve_sin_malus_facil',           '0'),
  ('pve_sin_malus_medio',           '0'),
  ('pve_sin_malus_dificil',         '0'),
  ('pve_sin_malus_muy_dificil',     '0'),
  ('pve_sin_malus_extremo',         '0')
ON DUPLICATE KEY UPDATE clave = clave;
