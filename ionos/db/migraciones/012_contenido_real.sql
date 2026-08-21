-- ============================================================================
-- 012 — Contenido real de Cadenas (bloque E)
--
-- Aditiva y re-ejecutable.
--
-- Sustituye los tres rivales de prueba del bloque B (Escuadra Fantasma,
-- Brigada Cobalto, Guardia Carmesí — cromos elegidos casi al azar, solo para
-- poder verificar el motor) por rivales construidos con las plantillas REALES
-- del catálogo. Solo dos equipos tienen suficientes cartas propias para armar
-- un once: Instituto Zeus (17) y Academia Plenilunio (16). El resto del
-- catálogo son apariciones de 1-2 cartas (Alpino, Inazuma Kids CF, Triple C,
-- Zanark Domain), así que el tercer rival es una selección declarada como tal
-- (nunca se hace pasar por un club que no existe con ese roster).
--
-- El estilo de cada rival sale de su plantilla, no al revés:
--   · Instituto Zeus tiene 5 defensas propios contra solo 4 medios → bloque
--     compacto, ambos estilos con línea de atrás completa.
--   · Academia Plenilunio tiene 7 medios propios contra solo 3 defensas → no
--     PUEDE jugar un 4 en el fondo con su plantilla real, así que sus dos
--     estilos son de medio campo poblado (3-5-2 y 3-6-1).
--   · La Selección junta los 5 cameos de una sola carta (todos SRF) con
--     defensas y medios prestados: es, a propósito, el rival más fuerte.
--
-- Los 12 nodos de partido ya sembrados en 010 pasan de los rivales de prueba
-- a estos tres. Los rivales viejos se desactivan (activo=0), no se borran:
-- el progreso ya jugado contra ellos en pruebas queda íntegro en el historial.
-- ============================================================================

UPDATE cadena_rivales SET activo = 0 WHERE id_rival IN (1, 2, 3);

INSERT IGNORE INTO cadena_rivales (id_rival, nombre, descripcion) VALUES
  (4, 'Instituto Zeus',
      'Línea de cinco atrás y disciplina de bloque. No regala espacios.'),
  (5, 'Academia Plenilunio',
      'Mediocampo con siete nombres propios. Juegan con el balón, no sin él.'),
  (6, 'Selección Frontier',
      'Ojeo across the liga: cinco fichas de una sola carta en el mismo campo.');

INSERT IGNORE INTO cadena_rival_estilos (id_estilo, id_rival, nombre, formacion) VALUES
  (7,  4, 'Bloque Zeus',           '442'),
  (8,  4, 'Muralla Zeus',          '541'),
  (9,  5, 'Recital Plenilunio',    '352'),
  (10, 5, 'Rueda Plenilunio',      '361'),
  (11, 6, 'Ofensiva Selección',    '433'),
  (12, 6, 'Contragolpe Selección', '352');

-- Estilo 7 — Bloque Zeus (1-4-4-2): POR, 4 DF, 4 MC, 2 DC. Toda la plantilla
-- de Zeus salvo un portero y tres delanteros de sobra.
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (7,0,9), (7,1,17),(7,2,18),(7,3,19),(7,4,22),
  (7,5,13),(7,6,15),(7,7,16),(7,8,20), (7,9,8),(7,10,10);

-- Estilo 8 — Muralla Zeus (1-5-4-1): los cinco defensas propios de Zeus a la
-- vez, algo que su plantilla permite y la de cualquier otro rival no.
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (8,0,24), (8,1,17),(8,2,18),(8,3,19),(8,4,22),(8,5,23),
  (8,6,13),(8,7,15),(8,8,16),(8,9,20), (8,10,11);

-- Estilo 9 — Recital Plenilunio (1-3-5-2): los tres defensas propios, cinco
-- de los siete medios.
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (9,0,39), (9,1,36),(9,2,37),(9,3,38),
  (9,4,30),(9,5,32),(9,6,44),(9,7,35),(9,8,31), (9,9,29),(9,10,41);

-- Estilo 10 — Rueda Plenilunio (1-3-6-1): seis de los siete medios a la vez.
-- Es la formación que su plantilla pide a gritos.
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (10,0,42), (10,1,36),(10,2,37),(10,3,38),
  (10,4,30),(10,5,31),(10,6,32),(10,7,33),(10,8,34),(10,9,44), (10,10,43);

-- Estilo 11 — Ofensiva Selección (1-4-3-3): las tres SRF de una sola carta
-- (Zanark Domain, Alpino, Inazuma Kids CF) juntas arriba, con defensa y
-- medio prestados de Zeus y Plenilunio.
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (11,0,5), (11,1,17),(11,2,18),(11,3,19),(11,4,22),
  (11,5,30),(11,6,32),(11,7,44), (11,8,3),(11,9,4),(11,10,6);

-- Estilo 12 — Contragolpe Selección (1-3-5-2): el otro portero SRF (Triple C)
-- y solo dos de las tres SRF arriba, para que no sean un calco del estilo 11.
INSERT IGNORE INTO cadena_rival_cartas (id_estilo, hueco, id_cromo) VALUES
  (12,0,7), (12,1,36),(12,2,37),(12,3,38),
  (12,4,13),(12,5,20),(12,6,15),(12,7,16),(12,8,31), (12,9,3),(12,10,4);

-- ---------------------------------------------------------------------------
-- Reasignación de los nodos de partido. La Ruta de ascenso (introductoria)
-- se queda solo con Zeus/Plenilunio; el Descenso de Frontier (avanzada,
-- bloqueada tras completar la primera) mete a la Selección en sus tramos
-- finales, que es donde tiene sentido que aparezca el rival más fuerte.
-- ---------------------------------------------------------------------------
UPDATE cadena_nodos SET id_rival = 4 WHERE id_nodo IN (1, 4, 6);   -- Zeus
UPDATE cadena_nodos SET id_rival = 5 WHERE id_nodo IN (2, 5, 7);   -- Plenilunio
UPDATE cadena_nodos SET id_rival = 5 WHERE id_nodo = 11;           -- Plenilunio
UPDATE cadena_nodos SET id_rival = 4 WHERE id_nodo IN (12, 16);    -- Zeus
UPDATE cadena_nodos SET id_rival = 6 WHERE id_nodo IN (14, 15, 17); -- Selección

-- ---------------------------------------------------------------------------
-- Loot adicional: los nodos que se quedaron sin ninguna fila en 011 (fuera de
-- los cofres y del par de nodos de prueba) reciben una tirada modesta de
-- cromo, coherente con el rival al que se enfrentan en ese nodo.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO cadena_loot (id_loot, id_nodo, tipo, id_cromo, probabilidad, rango_minimo) VALUES
  (9,  2,  'cromo', 40, 20, NULL),   -- Nora Flexion (Plenilunio, común)
  (10, 12, 'cromo', 21, 20, NULL),   -- Terri Ann Thrope (Zeus, común)
  (11, 16, 'cromo', 14, 20, NULL);   -- Nikas Himmelstein (Zeus, poco común)
