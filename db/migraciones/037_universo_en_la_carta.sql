-- ============================================================================
-- 037 — El universo pasa del EQUIPO a la CARTA
--
-- La `035` puso `universo` en `equipos`, razonando que un equipo pertenece
-- entero a un universo y que así eran 24 decisiones en vez de 469. Decisión de
-- Alejandro: va en la carta. Y tiene sentido — un equipo puede alinear a un
-- personaje del Inazuma original junto a jugadores propios, y con el universo
-- en el equipo eso no se puede contar.
--
-- El traslado NO pierde nada: cada carta hereda el universo que tenía su
-- equipo antes de que la columna desaparezca. Lo que hoy está bien marcado
-- sigue estándolo, y a partir de ahora se puede afinar carta a carta.
--
-- Sigue siendo DECORATIVO: no lo lee el motor de partido, ni el de sobres, ni
-- el de cadenas. No cambia estadísticas, rarezas ni probabilidades.
--
-- Aditiva salvo por la columna que se retira, y re-ejecutable.
-- ============================================================================

SET NAMES utf8mb4;

-- 1. La columna nueva, en la carta.
SET @existeCarta := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cromos' AND COLUMN_NAME = 'universo'
);
SET @sql := IF(@existeCarta = 0,
  'ALTER TABLE cromos ADD COLUMN universo ENUM(''srf'',''ie'') NOT NULL DEFAULT ''srf''
     COMMENT ''srf = Superliga Frontier. ie = Inazuma Eleven Canonical Series. Solo decorativo.''
     AFTER id_equipo',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Heredar lo que ya estuviera marcado en el equipo, ANTES de retirarlo.
SET @existeEquipo := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'universo'
);
SET @sql := IF(@existeEquipo = 1 AND @existeCarta = 0,
  'UPDATE cromos c INNER JOIN equipos e ON e.id_equipo = c.id_equipo
     SET c.universo = e.universo',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Retirar la del equipo: dos sitios para el mismo dato acaban discrepando,
--    y entonces nadie sabe cuál manda.
SET @sql := IF(@existeEquipo = 1,
  'ALTER TABLE equipos DROP COLUMN universo',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
