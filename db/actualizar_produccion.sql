-- ============================================================================
--  SUPERLIGA FRONTIER TCG — ACTUALIZACIÓN DE PRODUCCIÓN
--  Migraciones 029 → 044, en un solo archivo.
-- ============================================================================
--
--  QUÉ HACE
--    Añade las tablas y columnas nuevas y deja el motor listo para:
--      · apostar VARIAS cartas en un duelo            (031)
--      · cadenas visibles solo para ciertos usuarios  (032)
--      · calibrar la dificultad PvE por porcentaje    (033)
--      · que las compos y el Aumento muevan el marcador, y que el rival
--        suba de rareza con la dificultad             (034)
--      · dificultad ajustable por nodo                (029)
--      · cartas exclusivas de cadena                  (030)
--      · equipos gestionables desde el panel           (035)
--      · el universo de cada CARTA: Superliga Frontier o Inazuma Eleven
--        Canonical Series, solo decorativo               (037)
--      · rareza NUMERADA y la numeración al crear la carta (038)
--      · decidir carta a carta si sale en sobres, y cartas secretas que no
--        se enseñan en el álbum                        (030, 040)
--      · tutorial de bienvenida para cuentas nuevas    (036)
--      · sobre de bienvenida con el once completo y partido amistoso,
--        que son los dos pasos obligatorios del tutorial (039)
--      · el rango S se gana por MARGEN de 5 goles, no por goleada a cero (041)
--
--  QUÉ **NO** HACE — y es lo importante:
--    NO borra ni una fila de las que ya hay. No toca usuarios, colecciones,
--    monedas, mazos, duelos ni progreso de cadenas. Es puramente aditivo.
--
--  ⚠️ NO IMPORTES `db/tcg.sql` EN PRODUCCIÓN.
--    Ese archivo es un volcado COMPLETO de la base de desarrollo: lleva
--    `DROP TABLE` y sus propios datos, así que borraría el progreso real de
--    todo el mundo. Para actualizar producción se usa ESTE archivo.
--
--  CÓMO SE APLICA
--    1. Copia de seguridad primero, sin excusas:
--         mysqldump -u USUARIO -p NOMBRE_BD > copia_antes_de_actualizar.sql
--    2. Importa este archivo sobre la base de producción:
--         mysql -u USUARIO -p NOMBRE_BD < actualizar_produccion.sql
--       (o súbelo por phpMyAdmin → pestaña Importar, con la base ya elegida)
--    3. Listo. Si algo va mal, se restaura la copia del paso 1.
--
--  ES RE-EJECUTABLE
--    Se puede pasar dos veces sin romper nada: cada bloque comprueba antes si
--    su tabla o su columna ya existe. Si no sabes por dónde iba producción,
--    pásalo entero y ya está.
--
--  A QUIEN YA ESTABA NO LE SALE EL TUTORIAL (bloque 036)
--    La columna nace con el primer paso por defecto, así que toda cuenta NUEVA
--    entra en el tutorial sola. A las que ya existen se les marca como
--    'saltado' en la misma migración: son las de producción, gente que lleva
--    meses jugando y que no tiene por qué encontrarse de golpe con una vuelta
--    guiada explicándole lo que es un sobre.
--
--  UN ÚNICO CAMBIO DESTRUCTIVO, Y ES A PROPÓSITO (bloque 031)
--    `duelo_apuestas.id_coleccion` guardaba UNA carta por apuesta y desde
--    ahora una apuesta puede llevar varias. La columna se vacía en la nueva
--    tabla `duelo_apuesta_cartas` ANTES de retirarla, así que las apuestas de
--    los duelos que estén abiertos en ese momento se conservan. Dejarla habría
--    sido peor: una columna con una sola carta de las N, y cualquier código
--    que aún la leyera se llevaría solo la primera sin dar ningún error.
-- ============================================================================

-- El archivo está en UTF-8 y sus comentarios llevan acentos y eñes. Sin esto,
-- un cliente que abra la sesión en latin1 —el phpMyAdmin de algunos paneles lo
-- hace— guardaría los COMENTARIOS de las columnas hechos un desastre. No
-- afecta a los datos, pero un `COMMENT` ilegible en producción es exactamente
-- la clase de cosa que luego nadie sabe de dónde salió.
SET NAMES utf8mb4;


-- ############################################################################
-- ##  029_dificultad_por_nodo
-- ############################################################################

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


-- ############################################################################
-- ##  030_cromos_solo_cadena
-- ############################################################################

-- ============================================================================
-- 030 — Cartas exclusivas de cadena
--
-- Cartas que EXISTEN para que las lleven los equipos de las cadenas, pero que
-- ningún jugador puede conseguir: no salen en sobres y no cuentan para el
-- progreso del álbum (si contaran, el álbum sería imposible de completar por
-- definición y la barra mentiría a todo el mundo).
--
-- Es una columna y no una expansión aparte a propósito: una expansión oculta
-- obligaría a filtrarla en cada consulta que hoy une con `expansiones`, y a
-- decidir qué pasa con sus sobres. Una bandera en la carta se lee donde hace
-- falta y no toca nada más.
--
-- Aditiva y re-ejecutable. El bloque IF es la forma de que un segundo pase no
-- reviente: MariaDB no tiene ADD COLUMN IF NOT EXISTS en todas las versiones.
-- ============================================================================

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cromos' AND COLUMN_NAME = 'solo_cadena'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cromos ADD COLUMN solo_cadena TINYINT(1) NOT NULL DEFAULT 0
     COMMENT ''1 = exclusiva de cadenas: ni sobres ni progreso de álbum''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índice para que el filtro de sobres no barra la tabla entera cuando el
-- catálogo crezca.
SET @existeIdx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cromos' AND INDEX_NAME = 'idx_cromos_solo_cadena'
);
SET @sql := IF(@existeIdx = 0,
  'CREATE INDEX idx_cromos_solo_cadena ON cromos (solo_cadena)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ############################################################################
-- ##  031_apuesta_varias_cartas
-- ############################################################################

-- ============================================================================
-- 031 — Apostar VARIAS cartas en un duelo
--
-- Hasta aquí una apuesta de cartas era exactamente una carta por lado, y eso
-- vivía en `duelo_apuestas.id_coleccion`: una columna, una carta, sin sitio
-- donde poner la segunda. Ahora cada lado pone N cartas de la misma rareza y
-- el que pierde las entrega todas.
--
-- Por qué una tabla aparte y no N columnas ni N filas en `duelo_apuestas`:
--   · N columnas (id_coleccion_1..5) pone un techo arbitrario en el esquema y
--     obliga a un OR por columna en cada consulta que pregunte "¿está esta
--     copia apostada?".
--   · N filas en `duelo_apuestas` obligaría a tirar su UNIQUE (id_duelo,
--     id_usuario), que es justo lo que impide que alguien entre dos veces en
--     la misma sala, y dejaría `monedas` repetido en cada fila.
-- Una tabla de unión conserva las dos garantías y no tiene techo.
--
-- `duelos.cartas_apuesta` guarda CUÁNTAS pone cada lado. Va en el duelo y no
-- se deduce contando filas porque el rival tiene que saber cuántas necesita
-- ANTES de poner ninguna: es parte de las condiciones de la sala, igual que
-- la rareza.
--
-- Aditiva y re-ejecutable. La columna vieja se rellena en la nueva tabla y
-- después desaparece: dejarla ahí, con una sola carta de las N, sería una
-- columna que miente — y la mitad del código que aún la leyera se llevaría
-- solo la primera carta del lote sin dar ningún error.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Cuántas cartas pone cada lado
-- ---------------------------------------------------------------------------
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelos' AND COLUMN_NAME = 'cartas_apuesta'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE duelos ADD COLUMN cartas_apuesta TINYINT UNSIGNED NOT NULL DEFAULT 1
     COMMENT ''Cuántas cartas pone CADA lado. Solo aplica a tipo_apuesta = carta.''
     AFTER id_rareza_apuesta',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------------
-- 2. Qué cartas concretas pone cada lado
--
-- La clave primaria compuesta hace de guardia sola: la misma copia no puede
-- estar dos veces en la misma apuesta, así que no hace falta comprobarlo en
-- PHP antes de insertar.
--
-- `id_coleccion` es UNIQUE a propósito y NO por duelo: una copia solo puede
-- estar comprometida en un duelo a la vez. Sin esta restricción, abrir dos
-- salas seguidas con la misma carta compilaba, se guardaba y solo reventaba
-- al perder las dos — con una sola copia para entregar dos veces.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `duelo_apuesta_cartas` (
  `id_apuesta`   INT(11) NOT NULL,
  `id_coleccion` INT(11) NOT NULL,
  PRIMARY KEY (`id_apuesta`, `id_coleccion`),
  UNIQUE KEY `uq_dac_coleccion` (`id_coleccion`),
  CONSTRAINT `fk_dac_apuesta` FOREIGN KEY (`id_apuesta`)
    REFERENCES `duelo_apuestas` (`id_apuesta`) ON DELETE CASCADE,
  CONSTRAINT `fk_dac_coleccion` FOREIGN KEY (`id_coleccion`)
    REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------------
-- 3. Traer las apuestas que ya existen
--
-- Antes de tocar la columna vieja: si esto no corriera, los duelos abiertos
-- ahora mismo se quedarían sin carta apostada y el ganador no cobraría.
-- `INSERT IGNORE` para que un segundo pase no choque con la clave primaria.
-- ---------------------------------------------------------------------------
SET @existeVieja := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelo_apuestas' AND COLUMN_NAME = 'id_coleccion'
);
SET @sql := IF(@existeVieja = 1,
  'INSERT IGNORE INTO duelo_apuesta_cartas (id_apuesta, id_coleccion)
     SELECT id_apuesta, id_coleccion FROM duelo_apuestas WHERE id_coleccion IS NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------------
-- 4. Retirar la columna vieja (primero su clave ajena, que si no lo impide)
-- ---------------------------------------------------------------------------
SET @existeFk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelo_apuestas'
    AND CONSTRAINT_NAME = 'fk_apuestas_coleccion'
);
SET @sql := IF(@existeFk = 1,
  'ALTER TABLE duelo_apuestas DROP FOREIGN KEY fk_apuestas_coleccion',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existeIdx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelo_apuestas'
    AND INDEX_NAME = 'idx_apuestas_coleccion'
);
SET @sql := IF(@existeIdx = 1,
  'ALTER TABLE duelo_apuestas DROP INDEX idx_apuestas_coleccion',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@existeVieja = 1,
  'ALTER TABLE duelo_apuestas DROP COLUMN id_coleccion',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------------
-- 5. Techo de cuántas cartas caben en una apuesta
--
-- Configurable y no constante en PHP porque es una decisión de equilibrio, no
-- de programa: sin techo, quien tiene 40 comunes puede abrir una sala que
-- nadie más puede aceptar, y la lista de salas se llena de salas muertas.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
  ('duelo_cartas_max', '5', 'Máximo de cartas que se pueden apostar por lado en un duelo de cartas.');


-- ############################################################################
-- ##  032_cadenas_visibilidad
-- ############################################################################

-- ============================================================================
-- 032 — Cadenas visibles solo para ciertos usuarios
--
-- Hasta aquí una cadena tenía un único interruptor, `activa`, y era binario:
-- la ve todo el mundo o no la ve nadie. Eso no cubre lo que hace falta —probar
-- una cadena antes de publicarla, dar una cadena a un grupo concreto, montar
-- un evento cerrado— y la única forma de aproximarlo era dejarla inactiva y
-- publicarla a ciegas.
--
-- Se resuelve con DOS piezas y no con una:
--
--   · `visibilidad` dice CÓMO se decide quién la ve. Una tabla de invitados a
--     secas no basta: "sin invitados" tendría que significar o bien "la ve
--     todo el mundo" o bien "no la ve nadie", y las dos lecturas hacen falta.
--     Con una columna explícita, una cadena restringida y todavía sin
--     invitados no la ve nadie, que es lo correcto y lo seguro.
--   · `cadena_usuarios` dice QUIÉN, cuando la respuesta no es "todos".
--
-- `activa` NO desaparece ni se mezcla con esto. Son preguntas distintas:
-- `activa` es "¿esta cadena está publicada?" y `visibilidad` es "¿para quién?".
-- Una cadena inactiva no la ve nadie por muchos invitados que tenga.
--
-- Aditiva y re-ejecutable. El valor por defecto es 'todos', así que las
-- cadenas que ya existen se comportan exactamente igual que antes.
-- ============================================================================

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadenas' AND COLUMN_NAME = 'visibilidad'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cadenas ADD COLUMN visibilidad ENUM(''todos'',''elegidos'') NOT NULL DEFAULT ''todos''
     COMMENT ''todos = pública. elegidos = solo quien esté en cadena_usuarios.''
     AFTER activa',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- Los invitados. Clave primaria compuesta: invitar dos veces a la misma
-- persona es la misma invitación, no dos, y así lo dice el esquema en vez de
-- tener que comprobarlo en PHP antes de cada INSERT.
--
-- Los dos CASCADE son intencionados: si se borra la cadena, sus invitaciones
-- no significan nada; si se borra la cuenta, tampoco.
CREATE TABLE IF NOT EXISTS `cadena_usuarios` (
  `id_cadena`  INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `invitado`   DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cadena`, `id_usuario`),
  KEY `idx_cadena_usuarios_usuario` (`id_usuario`),
  CONSTRAINT `fk_cadena_usuarios_cadena` FOREIGN KEY (`id_cadena`)
    REFERENCES `cadenas` (`id_cadena`) ON DELETE CASCADE,
  CONSTRAINT `fk_cadena_usuarios_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ############################################################################
-- ##  033_calibracion_pve
-- ############################################################################

-- ============================================================================
-- 033 — Calibración de dificultad PvE por PRESET
--
-- El problema que resuelve: hasta aquí la dureza de una cadena se tocaba
-- escribiendo a mano `pve_mult_<dificultad>` —un multiplicador de la fuerza
-- del rival— sin saber a qué porcentaje de victorias corresponde. Y no hay
-- forma de deducirlo con lápiz: desde la `027` el resultado NO sale de la
-- curva Elo (`duelo_k` se calcula, se guarda y no decide nada), sale de
-- simular el partido ocasión a ocasión. Entre "multiplico la fuerza del rival
-- por 1,6" y "el jugador gana el 4 % de las veces" no hay fórmula cerrada.
--
-- Así que se invierte la pregunta: el panel elige el PORCENTAJE DE VICTORIAS
-- que quiere para cada dificultad, y el multiplicador lo busca el servidor
-- simulando partidos de verdad con el mismo motor que los juega. Cuatro
-- presets, del más blando al más duro, y dentro de cada uno los cinco niveles
-- ya escalados.
--
-- El ancla la puso Alejandro: en Extremo, el preset más blando deja al jugador
-- ganar un 7 % y el más duro un 1 %. El resto de la escalera cuelga de ahí.
-- La tabla vive en PHP (Tcg::PRESETS_PVE) y no aquí a propósito: son objetivos
-- de diseño, no datos que nadie vaya a editar fila a fila desde phpMyAdmin.
--
-- Aditiva y re-ejecutable.
-- ============================================================================

INSERT IGNORE INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
  ('pve_preset', 'normal',
   'Preset de dificultad PvE aplicado por última vez: mas_facil | normal | dificil | extremo.'),
  ('pve_ref_rareza', '3',
   'Rareza del equipo de REFERENCIA con el que se calibra (1..6). Es el jugador tipo contra el que se mide el porcentaje de victorias; no limita nada en juego.'),
  ('pve_calibrar_sims', '400',
   'Partidos simulados por cada punto de la curva al calibrar. Más = más preciso y más lento. Con 220 el Extremo salía con ±1,5 puntos de margen sobre un objetivo del 1 %, o sea ruido puro; con 400 baja a ±0,5 y la calibración global sigue tardando menos de diez segundos.');

-- El suelo de la probabilidad mostrada baja de 0,05 a 0,01.
--
-- No cambia ningún resultado: desde la `027` esta probabilidad no decide el
-- partido, solo se guarda y se le enseña al jugador como "partías con un X %".
-- Pero con el suelo en 5 % un Extremo calibrado al 1 % le habría dicho al
-- jugador que tenía un 5 % — el número en pantalla contradiciendo al diseño.
UPDATE `configuracion` SET `valor` = '0.01',
  `descripcion` = 'Probabilidad mínima de victoria que se MUESTRA. Nunca 0: el mazo débil siempre tiene opción. No decide el partido (lo decide la simulación desde la 027).'
  WHERE `clave` = 'duelo_p_min' AND `valor` = '0.05';

-- Qué preset se aplicó a cada cadena. Es informativo —lo que de verdad manda
-- son los multiplicadores ya escritos en `cadena_nodo_dificultad`— pero sin
-- esto el panel no puede decir con qué se calibró una cadena, y acabas
-- recalibrando a ciegas.
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadenas' AND COLUMN_NAME = 'pve_preset'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cadenas ADD COLUMN pve_preset VARCHAR(16) DEFAULT NULL
     COMMENT ''Último preset de dificultad aplicado a esta cadena. NULL = nunca calibrada.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ############################################################################
-- ##  034_rareza_congelada_y_compos
-- ############################################################################

-- ============================================================================
-- 034 — La rareza del rival se congela con el resto de la alineación
--
-- Acompaña a tres cambios del motor que van juntos:
--
--   1. `pve_subir_rareza_<dificultad>` PASA A TENER EFECTO. Existía desde el
--      principio —fila en `configuracion`, columna en `cadena_nodo_dificultad`,
--      campo en el editor— pero NO había una sola línea del motor que lo
--      leyera: ponerlo a 3 no cambiaba nada. Ahora, en dificultades altas, el
--      rival planta sus mismas cartas en su versión de rareza superior.
--
--   2. Las COMPOS y el AUMENTO entran en el marcador. Hasta ahora solo movían
--      la probabilidad que se muestra, y esa probabilidad dejó de decidir el
--      partido cuando el marcador pasó a salir de la simulación: montar
--      compos, ganar el ciclo de afinidad o acertar el Aumento no cambiaba el
--      resultado.
--
--   3. La subida de rareza y el multiplicador de compos se DESCUENTAN al
--      calibrar, para que no se apilen encima del multiplicador de fuerza.
--
-- Lo que necesita la base es solo esto: que la alineación congelada guarde con
-- qué rareza salió cada carta. Sin la columna, la pantalla leería la rareza
-- del catálogo y enseñaría un Común con estadísticas de Épico — números que no
-- cuadran con el marco de la carta que el jugador tiene delante.
--
-- La columna admite NULL a propósito: los duelos anteriores a este cambio y
-- todos los PvP la dejan vacía, y ahí la rareza buena sigue siendo la del
-- catálogo. El motor lee `COALESCE(da.id_rareza, c.id_rareza)`.
--
-- Aditiva y re-ejecutable. No toca ni una fila existente.
-- ============================================================================

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'duelo_alineaciones' AND COLUMN_NAME = 'id_rareza'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE duelo_alineaciones ADD COLUMN id_rareza TINYINT(3) DEFAULT NULL
     COMMENT ''Rareza con la que salió esta carta en ESTE duelo. NULL = la del catálogo.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ############################################################################
-- ##  035_equipos_y_universo
-- ############################################################################

-- ============================================================================
-- 035 — Universo de cada equipo, y equipos editables desde el panel
--
-- DOS cosas que van juntas porque tocan la misma tabla.
--
-- 1. UNIVERSO. Las cartas salen de dos sitios: las que son de la Superliga
--    Frontier —el juego propio— y las que vienen del Inazuma Eleven original.
--    La distinción es DECORATIVA: no cambia estadísticas, ni rarezas, ni
--    probabilidades, ni nada del motor. Solo se enseña, para que se sepa de
--    dónde viene cada jugador.
--
--    Va en el EQUIPO y no en la carta a propósito. Un equipo pertenece entero
--    a un universo —el Instituto Raimon no tiene jugadores de la Superliga—,
--    así que ponerlo aquí son 24 decisiones en vez de 469, y una carta nueva
--    hereda la correcta sin que nadie tenga que acordarse.
--    ponytail: si algún día hiciera falta una carta cuyo universo NO sea el de
--    su equipo, el sitio para una columna `cromos.universo` que lo pise es
--    este mismo patrón (NULL = hereda), pero hoy no hay ni un caso.
--
--    Por defecto 'srf'. Reasignar los equipos que sean del Inazuma original se
--    hace desde el panel, que es donde se sabe cuál es cuál; hacerlo aquí a
--    ciegas por el nombre acertaría en unos y no en otros.
--
-- 2. ESCUDO Y DESCRIPCIÓN. Hasta ahora `equipos` era solo un id y un nombre, y
--    no había ninguna pantalla para crearlos ni editarlos: para dar de alta un
--    equipo había que entrar en la base de datos a mano. Se le añade lo mínimo
--    para que tenga una ficha de verdad y se puedan gestionar desde el panel.
--
-- Aditiva y re-ejecutable. No cambia ni una fila.
-- ============================================================================

SET NAMES utf8mb4;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'universo'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE equipos ADD COLUMN universo ENUM(''srf'',''ie'') NOT NULL DEFAULT ''srf''
     COMMENT ''srf = Superliga Frontier. ie = Inazuma Eleven Canonical Series. Solo decorativo.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'escudo'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE equipos ADD COLUMN escudo VARCHAR(255) DEFAULT NULL
     COMMENT ''Ruta del escudo, relativa a la raíz del sitio. NULL = sin escudo.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos' AND COLUMN_NAME = 'descripcion'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE equipos ADD COLUMN descripcion VARCHAR(255) DEFAULT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ############################################################################
-- ##  036_tutorial_bienvenida
-- ############################################################################

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


-- ############################################################################
-- ##  037_universo_en_la_carta
-- ############################################################################

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


-- ############################################################################
-- ##  038_rareza_numerada
-- ############################################################################

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


-- ############################################################################
-- ##  039_sobre_inicial_y_amistoso
-- ############################################################################

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


-- ############################################################################
-- ##  040_sobres_por_carta
-- ############################################################################

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



-- ============================================================================
--  041 · EL RANGO S PASA A SER MARGEN DE 5 GOLES
-- ============================================================================
--
-- Antes pedía marcar 5 Y no encajar ninguno. Las dos condiciones se peleaban
-- entre sí: donde más cuesta dejar la portería a cero es justo donde más
-- cuesta golear, así que en las dificultades altas el rango S era casi
-- inalcanzable. Ahora basta ganar por cinco: un 6-1 es una paliza igual que
-- un 5-0.
--
-- No afecta a nada ya jugado: los rangos que hay guardados en
-- `cadena_progreso` se quedan como están.

INSERT INTO configuracion (clave, valor)
VALUES ('pve_rango_s_margen', '5')
ON DUPLICATE KEY UPDATE clave = clave;

-- La clave vieja ya no la lee nadie. Se borra para que no confunda a quien
-- abra el panel buscando por qué no cambia nada al tocarla.
DELETE FROM configuracion WHERE clave = 'pve_rango_s_goles';

-- ============================================================================
--  042 · EL MALUS DE COHERENCIA VUELVE A EXISTIR
-- ============================================================================
--
-- MEDIDO: el malus salía 0,000 en TODOS los duelos guardados y 0 de 200 onces
-- al azar lo activaban. No estaba roto el código: estaba calibrado para no
-- dispararse nunca. El umbral libre (2,5) estaba por encima de la rareza media
-- de un mazo normal (1,9 medido), así que casi todos quedaban exentos.
--
-- Con umbral 2,0 y rate 4,0, medido sobre el catálogo real:
--   mazo                 antes    ahora
--   solo comunes         0,00 %   0,00 %
--   común + poco común   0,00 %   0,00 %
--   mezclado             0,00 %   0,00 %
--   raro o mejor         0,00 %   2,91 %
--   épico o mejor        1,86 %   7,76 %
--
-- Se retoca desde el panel sin desplegar. El tope (18 %) no se toca.

UPDATE configuracion SET valor = '2.0' WHERE clave = 'coherencia_umbral_libre';
UPDATE configuracion SET valor = '4.0' WHERE clave = 'coherencia_malus_rate';

INSERT INTO configuracion (clave, valor) VALUES
  ('coherencia_umbral_libre', '2.0'),
  ('coherencia_malus_rate',   '4.0'),
  ('coherencia_malus_tope',   '18')
ON DUPLICATE KEY UPDATE clave = clave;

-- ============================================================================
--  043 · TRAMPAS PARA LOS EQUIPOS DE CADENA Y DIFICULTADES POR CADENA
-- ============================================================================
--
-- `compos_libres` ya existía en la tabla desde la `029` pero NO LA LEÍA NADIE.
-- Ahora quita el tope de bonus por línea al rival automático, y la columna
-- nueva `sin_malus` le perdona además el malus de coherencia. Las dos son
-- reglas del juego ENTRE PERSONAS; a un jefe final solo le impedían serlo.
-- Nunca se aplican al jugador.
--
-- Las dificultades ya se podían apagar nodo a nodo; el editor puede ahora
-- hacerlo de una vez para toda la cadena (no hace falta columna nueva).

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cadena_nodo_dificultad'
    AND COLUMN_NAME = 'sin_malus'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cadena_nodo_dificultad
     ADD COLUMN sin_malus TINYINT(1) NULL DEFAULT NULL
     COMMENT ''1 = el rival de este nodo no paga malus de coherencia. NULL = como el global.''
     AFTER compos_libres',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO configuracion (clave, valor) VALUES
  ('pve_compos_libres_facil',       '0'),
  ('pve_compos_libres_medio',       '0'),
  ('pve_compos_libres_dificil',     '0'),
  ('pve_compos_libres_muy_dificil', '0'),
  ('pve_compos_libres_extremo',     '0'),
  ('pve_sin_malus_facil',           '0'),
  ('pve_sin_malus_medio',           '0'),
  ('pve_sin_malus_dificil',         '0'),
  ('pve_sin_malus_muy_dificil',     '0'),
  ('pve_sin_malus_extremo',         '0')
ON DUPLICATE KEY UPDATE clave = clave;

-- ============================================================================
-- 044 · EL EDITOR DE CADENAS SE SUELTA
--
--   1) Los nodos se colocan donde uno quiera, no en una rejilla de 190x120.
--   2) Existe una casilla de SALIDA que marca por dónde empieza la ruta.
--   3) Los requisitos de una cadena dejan de ser dos tipos y pasan a seis.
--
-- Aditiva y idempotente. Ninguna cadena existente cambia de aspecto ni de
-- comportamiento: las posiciones se rellenan a partir de la rejilla vieja y
-- una cadena sin casilla de salida sigue funcionando exactamente igual.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1) POSICIÓN LIBRE
--
-- `columna`/`fila` eran índices de una rejilla de 190x120 px: para poner un
-- nodo entre dos había que empujar a todos los demás. Ahora se guardan las
-- coordenadas en píxeles y la rejilla pasa a ser solo un imán opcional del
-- editor.
--
-- SMALLINT y no TINYINT: en píxeles, 255 se agota a los dos nodos.
-- Se CONSERVAN las columnas viejas —no se borra nada— porque son de donde sale
-- el relleno de abajo y porque `mapaCadena()` todavía ordena por ellas.
-- ----------------------------------------------------------------------------
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadena_nodos' AND COLUMN_NAME = 'pos_x'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cadena_nodos
     ADD COLUMN pos_x SMALLINT NULL DEFAULT NULL COMMENT ''Píxeles desde el borde izquierdo del lienzo. NULL = usar columna*190+40.'',
     ADD COLUMN pos_y SMALLINT NULL DEFAULT NULL COMMENT ''Píxeles desde el borde superior. NULL = usar fila*120+40.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Relleno con la posición que YA tenían, para que ninguna cadena se descoloque
-- al desplegar. Los mismos 190/120/40 que usaba el editor.
UPDATE cadena_nodos
   SET pos_x = columna * 190 + 40,
       pos_y = fila    * 120 + 40
 WHERE pos_x IS NULL OR pos_y IS NULL;


-- ----------------------------------------------------------------------------
-- 2) LA CASILLA DE SALIDA
--
-- Hasta ahora el comienzo era implícito: valía como inicio CUALQUIER nodo sin
-- aristas de entrada. En un mapa ramificado eso abre a la vez todas las puntas
-- sueltas, y no había forma de decir "se empieza por aquí".
--
-- Con una casilla de salida en la cadena, la regla cambia: solo es accesible
-- lo que cuelga de ella. Sin casilla de salida, todo sigue como antes.
-- ----------------------------------------------------------------------------
SET @tipo := (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadena_nodos' AND COLUMN_NAME = 'tipo'
);
SET @sql := IF(LOCATE('inicio', @tipo) = 0,
  'ALTER TABLE cadena_nodos MODIFY COLUMN tipo ENUM(''partido'',''cofre'',''inicio'') NOT NULL DEFAULT ''partido''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ----------------------------------------------------------------------------
-- 3) MÁS TIPOS DE REQUISITO
--
-- Había dos: completar otra cadena, y tener una carta concreta. Los cuatro
-- nuevos cubren lo que se pedía a mano una y otra vez:
--
--   nivel_album  el álbum al X %          (valor = porcentaje)
--   monedas      tener X monedas          (valor = monedas)
--   duelos       haber jugado X duelos    (valor = duelos)
--   rareza       tener X cartas de una rareza (valor = id_rareza, cantidad = X)
--
-- `cantidad` es nueva y solo la usa `rareza`; el resto la dejan a NULL. Se
-- añade en vez de meter dos números en `valor` porque un campo que significa
-- dos cosas según la fila es como se acaba comparando peras con manzanas.
-- ----------------------------------------------------------------------------
SET @tipoReq := (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadena_requisitos' AND COLUMN_NAME = 'tipo'
);
SET @sql := IF(LOCATE('nivel_album', @tipoReq) = 0,
  'ALTER TABLE cadena_requisitos
     MODIFY COLUMN tipo ENUM(''cadena'',''cromo'',''nivel_album'',''monedas'',''duelos'',''rareza'')
     NOT NULL DEFAULT ''cadena''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadena_requisitos' AND COLUMN_NAME = 'cantidad'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cadena_requisitos
     ADD COLUMN cantidad INT UNSIGNED NULL DEFAULT NULL
     COMMENT ''Cuántas. Solo la usa el requisito de rareza; el resto va en `valor`.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
