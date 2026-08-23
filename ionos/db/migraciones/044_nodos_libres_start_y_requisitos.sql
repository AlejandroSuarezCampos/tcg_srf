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
