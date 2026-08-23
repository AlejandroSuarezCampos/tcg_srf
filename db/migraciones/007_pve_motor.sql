-- ============================================================================
-- 007 — Motor PvE (Cadenas de Partido), bloque B
--
-- Aditiva y re-ejecutable. No toca nada del PvP: un duelo entre dos personas
-- sigue teniendo `dificultad` a NULL y se resuelve exactamente igual que antes.
-- La marca de que un duelo es PvE es precisamente `dificultad IS NOT NULL`.
--
-- El motor de combate NO se duplica: el rival es un "jugador virtual" que se
-- congela en duelo_alineaciones como cualquier otro, así que fuerzaAlineacion,
-- las compos, el aumento y la curva Elo son literalmente los mismos.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. El usuario que hace de rival en todos los duelos PvE.
--    Existe solo para que las filas de duelo_alineaciones tengan un
--    id_usuario al que colgarse: el nombre que ve el jugador es el del equipo
--    rival (cadena_rivales.nombre), nunca este.
--    password_hash = '*' no es un hash válido, así que password_verify()
--    devuelve false siempre y esta cuenta no puede iniciar sesión.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO usuarios (nombre, password_hash, monedas, dictador)
VALUES ('CPU', '*', 0, 0);

-- ---------------------------------------------------------------------------
-- 2. Equipos rivales y sus estilos.
--    Un rival tiene plantilla fija pero 2-3 ESTILOS (alineaciones distintas
--    con la misma gente). El estilo se elige al azar en cada enfrentamiento y
--    no se le enseña al jugador: es lo que evita que el PvE se memorice.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_rivales (
  id_rival    INT(11) NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(80) NOT NULL,
  escudo      VARCHAR(255) DEFAULT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  activo      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_rival)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cadena_rival_estilos (
  id_estilo INT(11) NOT NULL AUTO_INCREMENT,
  id_rival  INT(11) NOT NULL,
  nombre    VARCHAR(60) NOT NULL,
  formacion VARCHAR(8) NOT NULL DEFAULT '442',
  PRIMARY KEY (id_estilo),
  KEY idx_estilo_rival (id_rival),
  CONSTRAINT fk_estilo_rival FOREIGN KEY (id_rival)
    REFERENCES cadena_rivales (id_rival) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La alineación de un estilo: qué cromo va en qué hueco. Apunta a `cromos` y
-- no a `coleccion` porque el rival no POSEE cartas, solo las representa.
CREATE TABLE IF NOT EXISTS cadena_rival_cartas (
  id_estilo INT(11) NOT NULL,
  hueco     TINYINT(3) UNSIGNED NOT NULL,
  id_cromo  INT(11) NOT NULL,
  PRIMARY KEY (id_estilo, hueco),
  KEY idx_rival_carta_cromo (id_cromo),
  CONSTRAINT fk_rival_carta_estilo FOREIGN KEY (id_estilo)
    REFERENCES cadena_rival_estilos (id_estilo) ON DELETE CASCADE,
  CONSTRAINT fk_rival_carta_cromo FOREIGN KEY (id_cromo)
    REFERENCES cromos (id_cromo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 3. Marcas de PvE en `duelos`.
-- ---------------------------------------------------------------------------
SET @col_existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelos' AND COLUMN_NAME = 'dificultad'
);
SET @sql := IF(@col_existe = 0,
  'ALTER TABLE duelos
     ADD COLUMN dificultad ENUM(''facil'',''medio'',''dificil'',''extremo'') NULL AFTER estado,
     ADD COLUMN id_estilo_rival INT(11) NULL AFTER dificultad,
     ADD COLUMN rango CHAR(1) NULL AFTER id_estilo_rival',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 4. Parámetros de balance. Como manda la convención del proyecto, ningún
--    número de balance vive en el código: todos se leen con $db->config().
--
--    Los multiplicadores se aplican a la fuerza POR LÍNEA del rival, no a su
--    total: así la dificultad se nota igual en quién gana y en el marcador,
--    en vez de subir la probabilidad de derrota y dejar goleadas absurdas.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO configuracion (clave, valor) VALUES
  ('pve_mult_facil',          '0.72'),
  ('pve_mult_medio',          '0.92'),
  ('pve_mult_dificil',        '1.15'),
  ('pve_mult_extremo',        '1.35'),

  -- cuánto se le multiplican al rival los bonus de compos ya calculados
  ('pve_compos_mult_facil',   '1.0'),
  ('pve_compos_mult_medio',   '1.0'),
  ('pve_compos_mult_dificil', '1.35'),
  ('pve_compos_mult_extremo', '1.70'),

  -- probabilidades Plata/Oro/Prisma del AUMENTO DEL RIVAL. El del jugador no
  -- se toca: sale de su Tensión, igual que en PvP.
  ('pve_tiers_facil',         '60,30,10'),
  ('pve_tiers_medio',         '55,31,14'),
  ('pve_tiers_dificil',       '35,40,25'),
  ('pve_tiers_extremo',       '0,0,100'),

  -- Marcador de cadena. Media de goles de cada lado:
  --   lambda = escala x (ataque medio por jugador / muro medio rival) ^ exponente
  -- El exponente por encima de 1 hace que una superioridad clara golee en vez
  -- de ganar por poco, que es lo que hace distinguibles los rangos S y B.
  ('pve_goles_escala',        '2.6'),
  ('pve_goles_exponente',     '1.6'),
  ('pve_goles_max',           '9'),

  -- umbrales de rango. S exige portería a cero; A, margen suficiente.
  ('pve_rango_s_goles',       '5'),
  ('pve_rango_a_margen',      '3'),

  -- rareza máxima admitida por dificultad; 0 = sin límite. En Fácil no entran
  -- legendarias ni SRF. Como se duela SIEMPRE con el titular, no se corrige el
  -- mazo por su cuenta: se bloquea la entrada y se explica por qué.
  ('pve_rareza_max_facil',    '4'),
  ('pve_rareza_max_medio',    '0'),
  ('pve_rareza_max_dificil',  '0'),
  ('pve_rareza_max_extremo',  '0');

-- ---------------------------------------------------------------------------
-- 5. Un rival de prueba con dos estilos, para poder jugar un nodo ya.
--    El contenido de verdad se siembra en el bloque E; esto existe para que
--    el motor sea verificable de punta a punta desde hoy.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO cadena_rivales (id_rival, nombre, descripcion) VALUES
  (1, 'Escuadra Fantasma', 'Rival de pruebas del motor PvE.');

INSERT IGNORE INTO cadena_rival_estilos (id_estilo, id_rival, nombre, formacion) VALUES
  (1, 1, 'Bloque bajo',  '532'),
  (2, 1, 'Presión alta', '433');

-- Estilo 1 (1-5-3-2): POR, 5 DF, 3 MC, 2 DC
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (1, 0, 5),
  (1, 1, 17), (1, 2, 18), (1, 3, 19), (1, 4, 22), (1, 5, 23),
  (1, 6, 13), (1, 7, 15), (1, 8, 16),
  (1, 9, 3),  (1, 10, 4);

-- Estilo 2 (1-4-3-3): POR, 4 DF, 3 MC, 3 DC
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (2, 0, 7),
  (2, 1, 36), (2, 2, 37), (2, 3, 38), (2, 4, 23),
  (2, 5, 30), (2, 6, 31), (2, 7, 32),
  (2, 8, 6),  (2, 9, 8),  (2, 10, 10);
