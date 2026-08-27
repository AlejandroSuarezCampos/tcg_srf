-- ============================================================================
-- 038 — Rareza NUMERADA: tiradas limitadas con número de serie
--
-- Una carta numerada no es "una carta muy rara": es una carta de la que
-- existen N copias en todo el juego y cada una lleva su número (#7/50). Lo
-- que la hace especial no es la probabilidad, es el cupo.
--
-- Por eso su `probabilidad` es 0,00 y no un número pequeño: NO sale de los
-- sobres. Si saliera, el cupo lo decidiría el azar y dejaría de ser una
-- tirada limitada — se repartiría hasta agotarse sin que nadie lo controle.
-- Se entrega desde el botín de las cadenas (tipo `cromo_limitado`), que ya
-- sabía numerar desde antes; lo que faltaba era la rareza y poder fijar el
-- cupo al crear la carta.
--
-- La maquinaria de numeración YA EXISTÍA y no se toca:
--   · `cromos.cupo_numerado` — cuántas copias existen. NULL o 0 = sin límite.
--   · `cadena_numeracion`    — qué número le tocó a cada copia entregada.
--   · `otorgarCromoLimitado()` reparte con `FOR UPDATE`, así que dos personas
--     ganando la última copia a la vez no pueden llevarse las dos.
-- Lo único que faltaba era la rareza en sí y un campo en el panel.
--
-- Aditiva y re-ejecutable.
-- ============================================================================

SET NAMES utf8mb4;

-- id 7 explícito: el componente de carta y el CSS eligen el color, el borde y
-- la marca no cromática por ese número. Dejarlo al AUTO_INCREMENT haría que
-- una instalación limpia y una ya existente acabaran con ids distintos y el
-- mismo CSS pintando rarezas diferentes.
INSERT IGNORE INTO `rarezas` (`id_rareza`, `nombre`, `probabilidad`) VALUES
  (7, 'Numerada', 0.00);

-- El cupo por carta. Existía ya en la mayoría de instalaciones (la usaba el
-- botín limitado de las cadenas), pero nunca tuvo migración propia: si esta
-- base no la tiene, se crea aquí.
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cromos' AND COLUMN_NAME = 'cupo_numerado'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cromos ADD COLUMN cupo_numerado INT(11) UNSIGNED DEFAULT NULL
     COMMENT ''Cuántas copias existen de esta carta. NULL o 0 = sin límite.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- La tabla que guarda qué número le tocó a cada copia. Igual que arriba: la
-- usaban las cadenas pero no tenía migración.
CREATE TABLE IF NOT EXISTS `cadena_numeracion` (
  `id_numeracion` INT(11) NOT NULL AUTO_INCREMENT,
  `id_cromo`      INT(11) NOT NULL,
  `numero_serie`  INT(11) UNSIGNED NOT NULL,
  `id_coleccion`  INT(11) NOT NULL,
  `otorgado`      DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_numeracion`),
  -- Una copia solo puede llevar UN número, y un número solo puede estar en
  -- una copia. Las dos cosas las garantiza el esquema y no el PHP.
  UNIQUE KEY `uq_numeracion_coleccion` (`id_coleccion`),
  -- Este UNIQUE hace de índice por `id_cromo` también: es su columna más a la
  -- izquierda. Un KEY aparte sobre `id_cromo` sería redundante y solo costaría
  -- tiempo en cada escritura.
  UNIQUE KEY `uq_numeracion_cromo_serie` (`id_cromo`, `numero_serie`),
  CONSTRAINT `fk_numeracion_cromo` FOREIGN KEY (`id_cromo`)
    REFERENCES `cromos` (`id_cromo`) ON DELETE CASCADE,
  CONSTRAINT `fk_numeracion_coleccion` FOREIGN KEY (`id_coleccion`)
    REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
