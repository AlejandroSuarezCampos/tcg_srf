-- ============================================================================
-- 011 — Recompensas de Cadenas de Partido (bloque D)
--
-- Aditiva y re-ejecutable.
--
-- Tres piezas:
--   1. Cartas limitadas y numeradas (§6.5 del briefing): cupo total en
--      `cromos.cupo_numerado`, cada copia emitida queda en `cadena_numeracion`
--      apuntando a la fila de `coleccion` que la representa. NO es una tabla
--      de inventario aparte: la copia numerada ES una fila de coleccion como
--      cualquier otra, así que ya hereda gratis ser vendible y apostable
--      (decisión de Alejandro: transferibles), aparecer en colección/álbum,
--      etc. El número de serie sigue al dueño porque sigue a `id_coleccion`,
--      no a una cuenta.
--   2. `cadena_loot`: qué puede caer en cada nodo, con probabilidad y un
--      rango mínimo opcional (mismo patrón de tiers que el Aumento).
--   3. `cadena_drops`: registro histórico de lo entregado (§11 del briefing),
--      y también de dónde lee la pantalla de resultado qué mostrar.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Cartas limitadas.
-- ---------------------------------------------------------------------------
SET @col_existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cromos' AND COLUMN_NAME = 'cupo_numerado'
);
SET @sql := IF(@col_existe = 0,
  'ALTER TABLE cromos ADD COLUMN cupo_numerado INT(11) UNSIGNED NULL AFTER tecnica',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS cadena_numeracion (
  id_numeracion INT(11) NOT NULL AUTO_INCREMENT,
  id_cromo      INT(11) NOT NULL,
  numero_serie  INT(11) UNSIGNED NOT NULL,
  id_coleccion  INT(11) NOT NULL,
  otorgado      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_numeracion),
  UNIQUE KEY uq_numeracion_serie (id_cromo, numero_serie),
  UNIQUE KEY uq_numeracion_coleccion (id_coleccion),
  CONSTRAINT fk_numeracion_cromo FOREIGN KEY (id_cromo)
    REFERENCES cromos (id_cromo) ON DELETE CASCADE,
  CONSTRAINT fk_numeracion_coleccion FOREIGN KEY (id_coleccion)
    REFERENCES coleccion (id_coleccion) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 2. Loot table por nodo.
--    `rango_minimo` NULL = no exige rango (así son los cofres, que no tienen
--    rango). En un partido, exige haber sacado ESE rango o uno mejor.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_loot (
  id_loot       INT(11) NOT NULL AUTO_INCREMENT,
  id_nodo       INT(11) NOT NULL,
  tipo          ENUM('monedas','cromo','cromo_limitado') NOT NULL,
  id_cromo      INT(11) DEFAULT NULL,
  monedas       INT(11) UNSIGNED DEFAULT NULL,
  probabilidad  DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  rango_minimo  CHAR(1) DEFAULT NULL,
  PRIMARY KEY (id_loot),
  KEY idx_loot_nodo (id_nodo),
  CONSTRAINT fk_loot_nodo FOREIGN KEY (id_nodo)
    REFERENCES cadena_nodos (id_nodo) ON DELETE CASCADE,
  CONSTRAINT fk_loot_cromo FOREIGN KEY (id_cromo)
    REFERENCES cromos (id_cromo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 3. Historial de drops. `id_duelo` es NULL en los cofres, que no vienen de
--    ningún duelo concreto.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_drops (
  id_drop      INT(11) NOT NULL AUTO_INCREMENT,
  id_usuario   INT(11) NOT NULL,
  id_duelo     INT(11) DEFAULT NULL,
  id_nodo      INT(11) NOT NULL,
  tipo         ENUM('monedas','cromo','cromo_limitado','formacion') NOT NULL,
  id_cromo     INT(11) DEFAULT NULL,
  numero_serie INT(11) UNSIGNED DEFAULT NULL,
  monedas      INT(11) UNSIGNED DEFAULT NULL,
  formacion    VARCHAR(8) DEFAULT NULL,
  creado       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_drop),
  KEY idx_drop_duelo (id_duelo),
  KEY idx_drop_usuario (id_usuario),
  CONSTRAINT fk_drop_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_drop_nodo FOREIGN KEY (id_nodo)
    REFERENCES cadena_nodos (id_nodo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 4. Parámetros de balance. Nada de números en el código: se leen con
--    $db->config(). Solo Fácil y Medio decrecen por repetición (§12 del
--    briefing: "recompensas en Difícil/Extremo se mantienen altas incluso en
--    repetición"), así que Difícil/Muy difícil/Extremo no tienen parámetro de
--    decrecimiento — su factor es siempre 1.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO configuracion (clave, valor) VALUES
  ('pve_recompensa_facil',       '80'),
  ('pve_recompensa_medio',       '160'),
  ('pve_recompensa_dificil',     '320'),
  ('pve_recompensa_muy_dificil', '520'),
  ('pve_recompensa_extremo',     '750'),

  -- factor = max(piso, tasa ^ veces_ya_jugado_antes), solo en facil/medio
  ('pve_recompensa_decrecimiento_tasa', '0.55'),
  ('pve_recompensa_decrecimiento_piso', '0.15'),

  ('pve_recompensa_mult_rango_s', '1.50'),
  ('pve_recompensa_mult_rango_a', '1.20'),
  ('pve_recompensa_mult_rango_b', '1.00');

-- ---------------------------------------------------------------------------
-- 5. Contenido de prueba: loot en los nodos ya sembrados en 010, para que el
--    mecanismo sea comprobable de punta a punta. Cupo numerado de ejemplo
--    sobre un cromo del catálogo de pruebas — el catálogo definitivo de
--    cartas exclusivas de PvE es contenido del bloque E.
-- ---------------------------------------------------------------------------

-- Cromo 43 marcado como limitado a 5 copias. Va colgado de un PARTIDO
-- (nodo 1), nunca de un cofre: un cofre se reclama, no se puntúa, así que no
-- tiene rango con el que exigir nada — ponerlo ahí lo habría dejado
-- inalcanzable para siempre.
UPDATE cromos SET cupo_numerado = 5 WHERE id_cromo = 43 AND cupo_numerado IS NULL;

INSERT IGNORE INTO cadena_loot (id_loot, id_nodo, tipo, monedas, probabilidad, rango_minimo) VALUES
  -- cofres de ruta: monedas garantizadas
  (1, 3,  'monedas', 300,  100, NULL),
  (2, 8,  'monedas', 250,  100, NULL),
  (3, 9,  'monedas', 250,  100, NULL),
  -- cofre final: monedas garantizadas
  (4, 10, 'monedas', 1000, 100, NULL),
  (5, 13, 'monedas', 900,  100, NULL),
  (6, 18, 'monedas', 1500, 100, NULL);

INSERT IGNORE INTO cadena_loot (id_loot, id_nodo, tipo, id_cromo, probabilidad, rango_minimo) VALUES
  -- un cromo normal, tirada de probabilidad media, cualquier rango
  (7, 6, 'cromo', 41, 35, NULL),
  -- la carta limitada: solo con S en el primer partido de la ruta
  (8, 1, 'cromo_limitado', 43, 100, 'S');
