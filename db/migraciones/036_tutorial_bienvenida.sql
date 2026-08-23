-- ============================================================================
-- 036 — Tutorial de bienvenida
--
-- Quien se registra hoy aterriza en una web con nueve secciones y ninguna
-- explicación: no sabe qué es una compo, ni que los duelos se juegan con el
-- mazo TITULAR y no con uno cualquiera, ni que puede apostar cartas y
-- perderlas de verdad. El tutorial le da una vuelta guiada por todo y le hace
-- pasar por las dos cosas sin las que no puede jugar: montar su primer mazo y
-- disputar un partido.
--
-- Una sola columna, y guarda EN QUÉ PASO va.
--
-- No hace falta más: `'hecho'` y `'saltado'` son dos valores terminales de esa
-- misma columna, así que se sabe si terminó, si lo saltó o por dónde lo dejó
-- —y lo dejará a medias, porque el tutorial se recorre en varias páginas y
-- cualquiera cierra la pestaña en mitad—. Con una tabla aparte habría que
-- crear una fila por usuario para no saber nada más.
--
-- El valor por defecto es el primer paso, así que TODA cuenta nueva entra en
-- el tutorial sin que el registro tenga que acordarse de nada.
--
-- ⚠️ Las cuentas que YA existen se marcan como 'saltado', no como pendientes.
-- Son las de producción: gente que lleva meses jugando y que no tiene por qué
-- encontrarse de golpe con un tutorial explicándole lo que es un sobre.
--
-- Aditiva y re-ejecutable.
-- ============================================================================

SET NAMES utf8mb4;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tutorial_paso'
);

SET @sql := IF(@existe = 0,
  'ALTER TABLE usuarios ADD COLUMN tutorial_paso VARCHAR(32) NOT NULL DEFAULT ''bienvenida''
     COMMENT ''Paso del tutorial en el que va. "hecho" o "saltado" = terminado.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Solo la primera vez: a quien ya estaba, el tutorial no le sale.
SET @sql := IF(@existe = 0,
  'UPDATE usuarios SET tutorial_paso = ''saltado''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
