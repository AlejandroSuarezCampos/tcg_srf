-- ============================================================================
-- 034 — La rareza del rival se congela con el resto de la alineación
--
-- Acompaña a tres cambios del motor que van juntos:
--
--   1. `pve_subir_rareza_<dificultad>` PASA A TENER EFECTO. Existía desde el
--      principio —fila en `configuracion`, columna en `cadena_nodo_dificultad`,
--      campo en el editor— pero NO había una sola línea del motor que lo
--      leyera: ponerlo a 3 no cambiaba nada. Ahora, en dificultades altas, el
--      rival planta sus mismas cartas en su versión de rareza superior.
--
--   2. Las COMPOS y el AUMENTO entran en el marcador. Hasta ahora solo movían
--      la probabilidad que se muestra, y esa probabilidad dejó de decidir el
--      partido cuando el marcador pasó a salir de la simulación: montar
--      compos, ganar el ciclo de afinidad o acertar el Aumento no cambiaba el
--      resultado.
--
--   3. La subida de rareza y el multiplicador de compos se DESCUENTAN al
--      calibrar, para que no se apilen encima del multiplicador de fuerza.
--
-- Lo que necesita la base es solo esto: que la alineación congelada guarde con
-- qué rareza salió cada carta. Sin la columna, la pantalla leería la rareza
-- del catálogo y enseñaría un Común con estadísticas de Épico — números que no
-- cuadran con el marco de la carta que el jugador tiene delante.
--
-- La columna admite NULL a propósito: los duelos anteriores a este cambio y
-- todos los PvP la dejan vacía, y ahí la rareza buena sigue siendo la del
-- catálogo. El motor lee `COALESCE(da.id_rareza, c.id_rareza)`.
--
-- Aditiva y re-ejecutable. No toca ni una fila existente.
-- ============================================================================

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelo_alineaciones' AND COLUMN_NAME = 'id_rareza'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE duelo_alineaciones ADD COLUMN id_rareza TINYINT(3) DEFAULT NULL
     COMMENT ''Rareza con la que salió esta carta en ESTE duelo. NULL = la del catálogo.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
