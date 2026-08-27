-- ============================================================================
-- 010 — Cadenas de Partido: mapa de nodos, ramificación y progreso (bloque C)
--
-- Aditiva y re-ejecutable. El motor PvE (007) ya sabe jugar un partido suelto;
-- esto le pone encima la estructura: en qué cadena está ese partido, qué nodos
-- desbloquea al ganarlo y qué progreso guarda.
--
-- DECISIÓN IMPORTANTE (Alejandro): el progreso se guarda PARTIDO A PARTIDO, a
-- cualquier dificultad, y perder no cuesta nada. Eso deja los nodos de cofre
-- SIN función de punto de guardado —no hay de dónde retroceder— así que son
-- recompensa e hito visual, no checkpoints. Por eso no existe ninguna columna
-- de "último checkpoint" en ninguna tabla: no haría falta nunca.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Las cadenas.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadenas (
  id_cadena   INT(11) NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  anfitrion   VARCHAR(80) DEFAULT NULL,
  orden       SMALLINT(6) NOT NULL DEFAULT 0,
  activa      TINYINT(1) NOT NULL DEFAULT 1,
  -- Formación que entrega su cofre final. NULL = esta cadena no da ninguna.
  formacion_recompensa VARCHAR(8) DEFAULT NULL,
  -- Cadena de temporada: pasada la fecha deja de poder jugarse. NULL = no
  -- caduca. Se comprueba de forma perezosa al listar/entrar, porque no hay cron.
  fecha_fin   DATETIME DEFAULT NULL,
  PRIMARY KEY (id_cadena)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 2. Nodos. `columna` es la profundidad (avance) y `fila` el carril vertical:
--    con esas dos coordenadas el mapa se dibuja solo, y una ramificación es
--    simplemente dos nodos en la misma columna y distinta fila.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_nodos (
  id_nodo   INT(11) NOT NULL AUTO_INCREMENT,
  id_cadena INT(11) NOT NULL,
  id_rival  INT(11) DEFAULT NULL,          -- NULL en los nodos de cofre
  tipo      ENUM('partido','cofre') NOT NULL DEFAULT 'partido',
  nombre    VARCHAR(80) DEFAULT NULL,
  columna   TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
  fila      TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
  es_final  TINYINT(1) NOT NULL DEFAULT 0, -- el cofre que cierra la cadena
  PRIMARY KEY (id_nodo),
  KEY idx_nodo_cadena (id_cadena),
  KEY idx_nodo_rival (id_rival),
  CONSTRAINT fk_nodo_cadena FOREIGN KEY (id_cadena)
    REFERENCES cadenas (id_cadena) ON DELETE CASCADE,
  CONSTRAINT fk_nodo_rival FOREIGN KEY (id_rival)
    REFERENCES cadena_rivales (id_rival) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 3. Aristas del mapa. Sirven para DOS cosas a la vez y por eso son una tabla
--    y no una columna "siguiente": dibujan las líneas del mapa y deciden qué
--    nodo se desbloquea con qué. Un nodo sin aristas de entrada es un inicio.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_aristas (
  id_origen  INT(11) NOT NULL,
  id_destino INT(11) NOT NULL,
  PRIMARY KEY (id_origen, id_destino),
  KEY idx_arista_destino (id_destino),
  CONSTRAINT fk_arista_origen FOREIGN KEY (id_origen)
    REFERENCES cadena_nodos (id_nodo) ON DELETE CASCADE,
  CONSTRAINT fk_arista_destino FOREIGN KEY (id_destino)
    REFERENCES cadena_nodos (id_nodo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 4. Requisitos para ENTRAR a una cadena. Se comprueban al intentar entrar,
--    no al ver la lista (§5 del briefing), y el modal enumera los que faltan.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_requisitos (
  id_requisito INT(11) NOT NULL AUTO_INCREMENT,
  id_cadena    INT(11) NOT NULL,
  tipo         ENUM('cadena','cromo') NOT NULL,
  valor        INT(11) NOT NULL,   -- id_cadena previa, o id_cromo que hay que tener
  PRIMARY KEY (id_requisito),
  KEY idx_req_cadena (id_cadena),
  CONSTRAINT fk_req_cadena FOREIGN KEY (id_cadena)
    REFERENCES cadenas (id_cadena) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 5. Progreso por nodo Y dificultad.
--    Por dificultad y no solo por nodo porque el briefing (§3.2) permite
--    rejugar el mismo nodo en otra dificultad para otra recompensa, así que
--    hay que saber a cuál se ganó y con qué rango.
--    `veces` cuenta TODOS los intentos (también las derrotas): lo necesita la
--    curva de recompensa decreciente del bloque D.
--    `mejor_rango` nunca empeora al rejugar — decisión de Alejandro.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_progreso (
  id_progreso INT(11) NOT NULL AUTO_INCREMENT,
  id_usuario  INT(11) NOT NULL,
  id_nodo     INT(11) NOT NULL,
  dificultad  ENUM('facil','medio','dificil','muy_dificil','extremo') NOT NULL,
  veces       INT(11) NOT NULL DEFAULT 0,
  victorias   INT(11) NOT NULL DEFAULT 0,
  mejor_rango CHAR(1) DEFAULT NULL,        -- NULL mientras no se haya ganado
  primera_victoria DATETIME DEFAULT NULL,
  PRIMARY KEY (id_progreso),
  UNIQUE KEY uq_progreso_nodo (id_usuario, id_nodo, dificultad),
  KEY idx_progreso_nodo (id_nodo),
  CONSTRAINT fk_progreso_cadena_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_progreso_cadena_nodo FOREIGN KEY (id_nodo)
    REFERENCES cadena_nodos (id_nodo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 6. Cofres ya reclamados. Tabla aparte del progreso porque un cofre no se
--    "juega": se alcanza y se abre una sola vez.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_cofres (
  id_usuario INT(11) NOT NULL,
  id_nodo    INT(11) NOT NULL,
  reclamado  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario, id_nodo),
  CONSTRAINT fk_cofre_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_cofre_nodo FOREIGN KEY (id_nodo)
    REFERENCES cadena_nodos (id_nodo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- 7. En qué nodo se jugó un duelo PvE.
-- ---------------------------------------------------------------------------
SET @col_existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelos' AND COLUMN_NAME = 'id_nodo'
);
SET @sql := IF(@col_existe = 0,
  'ALTER TABLE duelos ADD COLUMN id_nodo INT(11) NULL AFTER id_estilo_rival',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 8. CONTENIDO DE PRUEBA
--    Rivales y cadenas suficientes para que el mapa sea navegable y
--    verificable de verdad. El contenido definitivo (equipos con identidad,
--    lore y estilos con intención) se siembra en el bloque E; esto se puede
--    borrar entero entonces.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO cadena_rivales (id_rival, nombre, descripcion) VALUES
  (2, 'Brigada Cobalto',  'Rival de pruebas: bloque medio poblado.'),
  (3, 'Guardia Carmesí',  'Rival de pruebas: sale a por el partido.');

INSERT IGNORE INTO cadena_rival_estilos (id_estilo, id_rival, nombre, formacion) VALUES
  (3, 2, 'Equilibrio',    '442'),
  (4, 2, 'Centro poblado','352'),
  (5, 3, 'Tridente',      '433'),
  (6, 3, 'Muro y salida', '541');

-- Estilo 3 (1-4-4-2)
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (3,0,9), (3,1,36),(3,2,37),(3,3,38),(3,4,17),
  (3,5,20),(3,6,30),(3,7,31),(3,8,32), (3,9,11),(3,10,12);
-- Estilo 4 (1-3-5-2)
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (4,0,24), (4,1,18),(4,2,19),(4,3,22),
  (4,4,33),(4,5,34),(4,6,35),(4,7,13),(4,8,15), (4,9,14),(4,10,21);
-- Estilo 5 (1-4-3-3)
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (5,0,39), (5,1,22),(5,2,23),(5,3,36),(5,4,37),
  (5,5,16),(5,6,20),(5,7,33), (5,8,29),(5,9,40),(5,10,41);
-- Estilo 6 (1-5-4-1)
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (6,0,42), (6,1,17),(6,2,18),(6,3,19),(6,4,38),(6,5,23),
  (6,6,34),(6,7,35),(6,8,44),(6,9,30), (6,10,43);

-- --- Cadena 1: introductoria, con una ramificación y cofres intermedios -----
INSERT IGNORE INTO cadenas (id_cadena, nombre, descripcion, anfitrion, orden, formacion_recompensa) VALUES
  (1, 'Ruta de ascenso',
      'La primera ruta de la Superliga Frontier. Dos caminos, un mismo final.',
      'Escuadra Fantasma', 1, '352');

INSERT IGNORE INTO cadena_nodos (id_nodo, id_cadena, id_rival, tipo, nombre, columna, fila, es_final) VALUES
  (1,  1, 1,    'partido', 'Primer contacto',  0, 1, 0),
  (2,  1, 2,    'partido', 'Toma de contacto', 1, 1, 0),
  (3,  1, NULL, 'cofre',   'Alijo de ruta',    2, 1, 0),
  (4,  1, 3,    'partido', 'Vía alta',         3, 0, 0),
  (5,  1, 2,    'partido', 'Vía baja',         3, 2, 0),
  (6,  1, 1,    'partido', 'Cresta',           4, 0, 0),
  (7,  1, 3,    'partido', 'Vaguada',          4, 2, 0),
  (8,  1, NULL, 'cofre',   'Alijo alto',       5, 0, 0),
  (9,  1, NULL, 'cofre',   'Alijo bajo',       5, 2, 0),
  (10, 1, NULL, 'cofre',   'Cofre de ruta',    6, 1, 1);

INSERT IGNORE INTO cadena_aristas (id_origen, id_destino) VALUES
  (1,2), (2,3),
  (3,4), (3,5),           -- aquí se bifurca
  (4,6), (5,7),
  (6,8), (7,9),
  (8,10), (9,10);         -- y aquí vuelve a juntarse

-- --- Cadena 2: avanzada, bloqueada tras completar la primera ---------------
INSERT IGNORE INTO cadenas (id_cadena, nombre, descripcion, anfitrion, orden, formacion_recompensa) VALUES
  (2, 'Descenso de Frontier',
      'Solo para quien ya haya cerrado la ruta de ascenso.',
      'Guardia Carmesí', 2, '532');

INSERT IGNORE INTO cadena_nodos (id_nodo, id_cadena, id_rival, tipo, nombre, columna, fila, es_final) VALUES
  (11, 2, 3,    'partido', 'Portón',        0, 1, 0),
  (12, 2, 2,    'partido', 'Antesala',      1, 1, 0),
  (13, 2, NULL, 'cofre',   'Alijo sellado', 2, 1, 0),
  (14, 2, 1,    'partido', 'Ala este',      3, 0, 0),
  (15, 2, 3,    'partido', 'Ala oeste',     3, 2, 0),
  (16, 2, 2,    'partido', 'Confluencia',   4, 1, 0),
  (17, 2, 3,    'partido', 'Último muro',   5, 1, 0),
  (18, 2, NULL, 'cofre',   'Cofre final',   6, 1, 1);

INSERT IGNORE INTO cadena_aristas (id_origen, id_destino) VALUES
  (11,12), (12,13),
  (13,14), (13,15),
  (14,16), (15,16),       -- las dos alas convergen antes del final
  (16,17), (17,18);

INSERT IGNORE INTO cadena_requisitos (id_cadena, tipo, valor)
SELECT 2, 'cadena', 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM cadena_requisitos WHERE id_cadena = 2 AND tipo = 'cadena' AND valor = 1
);
