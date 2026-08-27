-- ============================================================================
-- 029 — Dificultad ajustable POR NODO
--
-- ⚠️ ESTA MIGRACIÓN FORMALIZA UNA TABLA QUE YA EXISTÍA EN LA BASE LOCAL.
--
-- Al abrir el trabajo del editor de nodos se encontró `cadena_nodo_dificultad`
-- creada en la base `tcg` de esta máquina, con un diseño completo y sensato…
-- y SIN migración que la creara y SIN una sola línea de código que la leyera
-- (0 referencias en todo el repo, 0 filas en la tabla). Lo mismo pasa con tres
-- familias de parámetros en `configuracion` —`pve_subir_rareza_*`,
-- `pve_pesos_ia_*` y `pve_compos_libres_*`—: tienen valores puestos y nadie
-- los lee.
--
-- O sea: había un sistema DISEÑADO y no construido, solo en esta base de
-- datos. Esta migración lo hace reproducible (una copia limpia del repo no lo
-- tendría) sin cambiar ni una columna de lo que ya había. Es aditiva y
-- re-ejecutable, como todas.
--
-- QUÉ HACE CADA COLUMNA. Cada una PISA el parámetro global equivalente
-- (`pve_<algo>_<dificultad>` en `configuracion`) solo para ese nodo y esa
-- dificultad. NULL = no se pisa nada, manda el global. Así una cadena puede
-- tener un Extremo más duro que otro sin tocar el Extremo de todo el juego.
-- ============================================================================

CREATE TABLE IF NOT EXISTS cadena_nodo_dificultad (
  id_nodo       INT(11) NOT NULL,
  dificultad    ENUM('facil','medio','dificil','muy_dificil','extremo') NOT NULL,
  activa        TINYINT(1) NOT NULL DEFAULT 1,
  mult_fuerza   DECIMAL(5,3) DEFAULT NULL COMMENT 'Pisa pve_mult_<dif>',
  mult_compos   DECIMAL(5,3) DEFAULT NULL COMMENT 'Pisa pve_compos_mult_<dif>',
  subir_rareza  TINYINT(3)   DEFAULT NULL COMMENT 'Pisa pve_subir_rareza_<dif>',
  pesos_ia      DECIMAL(4,3) DEFAULT NULL COMMENT 'Pisa pve_pesos_ia_<dif>, 0..1',
  compos_libres TINYINT(1)   DEFAULT NULL COMMENT 'Pisa pve_compos_libres_<dif>',
  rareza_max    TINYINT(3)   DEFAULT NULL COMMENT 'Pisa pve_rareza_max_<dif> (limita al JUGADOR)',
  tiers         VARCHAR(20)  DEFAULT NULL COMMENT 'Pisa pve_tiers_<dif>, "plata,oro,prisma"',
  id_estilo     INT(11)      DEFAULT NULL COMMENT 'Estilo forzado; NULL = uno al azar',
  PRIMARY KEY (id_nodo, dificultad),
  KEY idx_nodo_dif_estilo (id_estilo),
  CONSTRAINT fk_nodo_dif_nodo   FOREIGN KEY (id_nodo)   REFERENCES cadena_nodos (id_nodo) ON DELETE CASCADE,
  CONSTRAINT fk_nodo_dif_estilo FOREIGN KEY (id_estilo) REFERENCES cadena_rival_estilos (id_estilo) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Los parámetros globales que la tabla puede pisar. Los tres primeros grupos
-- ya estaban en la base local pero NO en ninguna migración: se declaran aquí
-- para que una instalación limpia los tenga. `INSERT IGNORE` respeta el valor
-- que ya haya puesto Alejandro; los de aquí son solo el punto de partida.
--
-- Ojo con estos tres grupos, que no están todos igual de vivos:
--   · `pve_subir_rareza_*`  YA LO LEE EL MOTOR desde la migración `034`: en
--     dificultades altas el rival planta sus cartas subidas de rareza.
--   · `pve_pesos_ia_*` y `pve_compos_libres_*` siguen SIN lector. Se declaran
--     para que la tabla de arriba tenga a qué caer; se conectarán cuando el
--     rival tenga IA de alineación.
INSERT IGNORE INTO configuracion (clave, valor) VALUES
  ('pve_subir_rareza_facil',        '0'),
  ('pve_subir_rareza_medio',        '0'),
  ('pve_subir_rareza_dificil',      '1'),
  ('pve_subir_rareza_muy_dificil',  '2'),
  ('pve_subir_rareza_extremo',      '3'),

  ('pve_pesos_ia_facil',            '0'),
  ('pve_pesos_ia_medio',            '0'),
  ('pve_pesos_ia_dificil',          '0.25'),
  ('pve_pesos_ia_muy_dificil',      '0.55'),
  ('pve_pesos_ia_extremo',          '0.85'),

  ('pve_compos_libres_facil',       '0'),
  ('pve_compos_libres_medio',       '0'),
  ('pve_compos_libres_dificil',     '0'),
  ('pve_compos_libres_muy_dificil', '1'),
  ('pve_compos_libres_extremo',     '1');
