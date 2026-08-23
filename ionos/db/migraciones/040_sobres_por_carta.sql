-- ============================================================================
-- 040 — Decidir carta a carta si sale en sobres
--
-- Hasta ahora, que una carta pudiera salir de un sobre dependía SOLO de su
-- rareza: la probabilidad vive en `rarezas` y el sorteo agrupa por ella. La
-- única forma de sacar una carta del reparto era `solo_cadena`, que además la
-- esconde del álbum — o sea, todo o nada.
--
-- Con las tiradas NUMERADAS (`038`) eso deja de valer. Una carta numerada
-- existe en N copias y quien la diseña tiene que poder decidir si esas copias
-- se reparten en sobres o solo como botín, sin esconderla del álbum: es una
-- carta que la gente persigue, y para perseguirla hay que poder verla.
--
-- Son DOS EJES DISTINTOS y por eso son dos columnas:
--   · `solo_cadena` — ¿se ve en el álbum? (secreta o no)
--   · `en_sobres`   — ¿entra en el sorteo de los sobres?
-- Meterlos en una sola obligaría a esconder del álbum todo lo que no salga en
-- sobres, que es justo lo contrario de lo que hace falta aquí.
--
-- Aditiva y re-ejecutable.
-- ============================================================================

SET NAMES utf8mb4;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cromos' AND COLUMN_NAME = 'en_sobres'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cromos ADD COLUMN en_sobres TINYINT(1) NOT NULL DEFAULT 1
     COMMENT ''1 = entra en el sorteo de los sobres. 0 = solo por otras vías (botín, códigos).''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Las numeradas que ya existan quedan FUERA de los sobres por defecto.
-- Es la opción prudente: una tirada limitada repartiéndose sola en sobres se
-- agota sin que nadie lo haya decidido, y eso no se puede deshacer.
SET @sql := IF(@existe = 0,
  'UPDATE cromos SET en_sobres = 0 WHERE id_rareza = 7',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- La rareza NUMERADA pasa de 0,00 a 0,25 de probabilidad.
--
-- Con 0,00 no podía salir NUNCA de un sobre, ni aunque se marcara una carta
-- como `en_sobres = 1`: el sorteo reparte el peso entre las rarezas presentes
-- y una a cero no se elige jamás. Ahora la decisión la toma la carta —el eje
-- de arriba— y la rareza solo dice cuánto pesa cuando alguna participa.
--
-- 0,25 la deja por debajo de la SRF (0,50), que es lo que corresponde a algo
-- de lo que existen contadas copias.
UPDATE `rarezas` SET `probabilidad` = 0.25 WHERE `id_rareza` = 7 AND `probabilidad` = 0.00;
