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
