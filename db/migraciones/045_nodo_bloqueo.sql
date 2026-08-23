-- ============================================================================
-- 045 · EL NODO DE BLOQUEO
--
--   1) Un tipo de nodo nuevo, `bloqueo`: la señal de STOP del mapa.
--   2) Sus requisitos, en tabla propia (`cadena_nodo_requisitos`).
--   3) `cadena_progreso` empieza a guardar goles y porterías a cero, que hasta
--      hoy se calculaban, se enseñaban y se tiraban.
--
-- Aditiva e idempotente. Ninguna cadena existente cambia: sin nodos de bloqueo
-- el mapa se recorre exactamente igual que antes.
--
-- ⚠️ POR QUÉ UN TIPO DE NODO Y NO UN "CANDADO" EN EL NODO DE PARTIDO.
--    Un requisito colgado del partido siguiente obligaría a leerlo en cada
--    nodo y a decidir qué se enseña cuando son varios los que salen de una
--    bifurcación. Como nodo propio se resuelve solo: `mapaCadena()` ya abre un
--    nodo cuando ALGUNO de sus predecesores está superado, así que un bloqueo
--    puesto en medio corta el paso sin ninguna regla nueva — lo único que
--    cambia es DE DÓNDE sale su "superado", que en vez de ganar un partido es
--    cumplir sus requisitos. Y como se dibuja, se ve venir desde lejos, que es
--    justamente lo que hace un STOP en el mapa.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1) EL TIPO `bloqueo`
--
-- Mismo patrón que la `044` con `inicio`: se comprueba el ENUM antes de
-- tocarlo para que re-ejecutar la migración no falle.
-- ----------------------------------------------------------------------------
SET @tipo := (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadena_nodos' AND COLUMN_NAME = 'tipo'
);
SET @sql := IF(LOCATE('bloqueo', @tipo) = 0,
  'ALTER TABLE cadena_nodos MODIFY COLUMN tipo ENUM(''partido'',''cofre'',''inicio'',''bloqueo'') NOT NULL DEFAULT ''partido''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ----------------------------------------------------------------------------
-- 2) LOS REQUISITOS DEL NODO
--
-- Tabla propia y no `cadena_requisitos` con un `id_nodo` opcional: los de
-- cadena se comprueban al ENTRAR (una vez, y o entras o no), y los de nodo se
-- comprueban al RECORRER (cambian a mitad de partida, tienen barra de progreso
-- y se vuelven a mirar tras cada partido). Meterlos en la misma tabla obligaría
-- a que la mitad de las filas tuviera una de las dos columnas a NULL siempre.
--
-- Los seis primeros tipos son los mismos que la `044` dejó a nivel de cadena, y
-- se leen igual. Los cinco últimos son de nodo y no existían:
--
--   rango_previos   todos los partidos de un camino hasta aquí, en rango >= X
--   nodos_cadena    haber ganado X nodos de esta cadena
--   goles_partido   haber metido X goles en UN partido de esta cadena
--   porteria_cero   haber ganado X partidos de esta cadena sin encajar
--   victorias       haber ganado X partidos de esta cadena (cuenta repeticiones)
--
-- ⚠️ `dificultad` es columna propia y no un segundo número dentro de `valor`.
--    Es la lección que dejó escrita la `044`: un campo que significa dos cosas
--    según la fila es como se acaba comparando peras con manzanas. NULL = vale
--    cualquier dificultad; puesta = solo cuenta lo jugado en esa.
--
--    Importa de verdad en `rango_previos`: las cinco dificultades están siempre
--    abiertas y `mejor_rango` se guarda por dificultad, así que sin fijarla se
--    puede granjear la S entera en Fácil. Es el mismo agujero que ya avisaba
--    `caminoPerfectoHastaCofre()` en el §15.12.
--
-- `valor` en `rango_previos` es el orden de ORDEN_RANGO (1=S, 2=A, 3=B) y no la
-- letra, porque la comparación tiene que ser numérica: alfabéticamente 'A' < 'S'
-- y la S saldría peor que la A.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cadena_nodo_requisitos (
  id_requisito INT(11) NOT NULL AUTO_INCREMENT,
  id_nodo      INT(11) NOT NULL,
  tipo         ENUM('cadena','cromo','nivel_album','monedas','duelos','rareza',
                    'rango_previos','nodos_cadena','goles_partido','porteria_cero','victorias')
               NOT NULL DEFAULT 'nodos_cadena',
  valor        INT(11) NOT NULL DEFAULT 0
               COMMENT 'Qué se pide. En rango_previos es el orden de rango (1=S, 2=A, 3=B).',
  cantidad     INT(10) UNSIGNED DEFAULT NULL
               COMMENT 'Cuántas. Solo la usa el requisito de rareza; el resto va en `valor`.',
  dificultad   ENUM('facil','medio','dificil','muy_dificil','extremo') DEFAULT NULL
               COMMENT 'NULL = cuenta cualquier dificultad. Puesta = solo esa.',
  PRIMARY KEY (id_requisito),
  KEY idx_nodo_requisito (id_nodo),
  CONSTRAINT fk_nodo_requisito_nodo FOREIGN KEY (id_nodo)
    REFERENCES cadena_nodos (id_nodo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ----------------------------------------------------------------------------
-- 3) GOLES Y PORTERÍAS A CERO EN EL PROGRESO
--
-- `cadena_progreso` guardaba `veces`, `victorias` y `mejor_rango`. El marcador
-- del partido se calculaba entero, se enseñaba al jugador y se perdía, así que
-- "mete 5 goles en un partido" no se podía ni comprobar ni enseñar.
--
-- `mas_goles` es el RÉCORD (el máximo de un solo partido), no una suma: es lo
-- que pide el requisito. `porterias_cero` sí es una cuenta acumulada.
--
-- Las dos NUNCA empeoran al rejugar, mismo criterio que `mejor_rango`: probar
-- otra dificultad no puede costarte un récord que ya tenías (decisión de
-- Alejandro, §15.12).
--
-- Las filas que ya existen quedan a 0 y no a NULL: 0 es la lectura correcta
-- —nadie tenía récord antes de que esto se guardara— y evita que cada
-- comparación tenga que acordarse del NULL.
-- ----------------------------------------------------------------------------
SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadena_progreso' AND COLUMN_NAME = 'mas_goles'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cadena_progreso
     ADD COLUMN mas_goles TINYINT(3) UNSIGNED NOT NULL DEFAULT 0
       COMMENT ''Récord de goles marcados en UN partido de este nodo a esta dificultad.'',
     ADD COLUMN porterias_cero SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0
       COMMENT ''Victorias sin encajar en este nodo a esta dificultad.''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
