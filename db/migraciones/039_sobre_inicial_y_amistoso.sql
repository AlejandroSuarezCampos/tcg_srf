-- ============================================================================
-- 039 — Sobre de bienvenida y partido amistoso (para el tutorial)
--
-- El tutorial (`036`) obligaba a montar un mazo titular sin darle a nadie con
-- qué montarlo: una cuenta nueva no tiene cartas, así que el paso era
-- imposible hasta gastarse las monedas de inicio a ciegas y con suerte sacar
-- once jugadores de las posiciones que hacen falta. Ahora se le entrega un
-- sobre de bienvenida con EXACTAMENTE el once que pide la formación base.
--
-- Y el partido obligatorio deja de ser de una cadena: es un AMISTOSO. Motivos:
--   · una instalación puede no tener ninguna cadena creada, y entonces el
--     tutorial no se podía terminar;
--   · entrar en una cadena por obligación gasta el primer nodo y deja el
--     progreso tocado antes de que nadie sepa lo que es una cadena;
--   · y un amistoso no reparte botín ni cuenta para nada, que es lo que se
--     quiere de un partido de prueba.
--
-- Tres columnas y una fila. Aditiva y re-ejecutable.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. Qué sobre es el de bienvenida
-- ---------------------------------------------------------------------------
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sobre' AND COLUMN_NAME = 'inicial'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE sobre ADD COLUMN inicial TINYINT(1) NOT NULL DEFAULT 0
     COMMENT ''1 = sobre de bienvenida: gratis, una sola vez y con el once completo.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- El sobre en sí. `cantidad` = 11 porque son los once huecos de la formación
-- base; el reparto por posiciones lo hace el motor al abrirlo, no esta fila.
-- Se cuelga de la primera expansión activa: es de donde saldrán las cartas.
INSERT INTO `sobre` (`id_expansion`, `nombre`, `cantidad`, `precio`, `imagen`, `activo`, `inicial`)
SELECT e.id_expansion, 'Sobre de bienvenida', 11, 0, './assets/img/Sobres/basico.png', 1, 1
FROM `expansiones` e
WHERE e.activo = 1
  AND NOT EXISTS (SELECT 1 FROM `sobre` s WHERE s.inicial = 1)
ORDER BY e.id_expansion
LIMIT 1;

-- ---------------------------------------------------------------------------
-- 2. Quién lo ha abierto ya
--
-- Una columna en el usuario y no una fila en otra tabla: la pregunta es
-- "¿este usuario ya lo abrió?" y se hace en cada carga de la pantalla de
-- sobres. Y hace falta guardarlo aparte del tutorial porque el tutorial se
-- puede repetir desde el perfil, y el sobre no.
-- ---------------------------------------------------------------------------
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'sobre_inicial_abierto'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE usuarios ADD COLUMN sobre_inicial_abierto TINYINT(1) NOT NULL DEFAULT 0
     COMMENT ''1 = ya abrió el sobre de bienvenida. No se repite ni repitiendo el tutorial.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- A quien ya estaba no se le debe un sobre de bienvenida: lleva meses jugando.
SET @sql := IF(@existe = 0,
  'UPDATE usuarios SET sobre_inicial_abierto = 1',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. Partidos amistosos
--
-- Marca los partidos de prueba. No reparten botín, no cuentan para misiones y
-- no salen en el historial: existen para que alguien vea cómo va un partido.
-- ---------------------------------------------------------------------------
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelos' AND COLUMN_NAME = 'amistoso'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE duelos ADD COLUMN amistoso TINYINT(1) NOT NULL DEFAULT 0
     COMMENT ''1 = partido de prueba: sin apuesta, sin botín y fuera del historial.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
