-- =============================================================================
--  003 — CAPA 2: COMPOS (rasgos y sinergias)
-- =============================================================================
--  Migración ADITIVA. Se puede ejecutar más de una vez sin romper nada:
--  todo va con IF NOT EXISTS / INSERT IGNORE / ON DUPLICATE KEY UPDATE.
--
--  Aplicar con (el --default-character-set NO es opcional):
--    C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root tcg < db/migraciones/003_capa2_compos.sql
--
--  Sin ese flag, en Windows el cliente lee este fichero con la codepage de la
--  consola (CP850) en vez de UTF-8, y las tildes y eñes entran corrompidas:
--  "Montaña" se guarda como "Monta├▒a". Pasó de verdad la primera vez que se
--  aplicó. Se detecta comparando bytes contra un dato preexistente correcto:
--    SELECT HEX(nombre) FROM rasgos WHERE clave='montana';   -- debe dar ...c3b1...
--  Reaplicar la migración con el flag correcto lo arregla, porque todos los
--  INSERT llevan ON DUPLICATE KEY UPDATE sobre `nombre` y `descripcion`.
--
--  -------------------------------------------------------------------------
--  QUÉ TOCA DE LO YA EXISTENTE
--  -------------------------------------------------------------------------
--  · NO borra ni modifica ninguna fila de `usuarios`, `cromos`, `coleccion`,
--    `mercado`, `mazos` ni `duelos`.
--  · AÑADE columnas nuevas a `duelos` (trazabilidad de la Capa 2) y a
--    `duelo_aumentos` (el nivel de Tensión con el que se sortearon los tiers).
--  · CREA tablas nuevas: `rasgos`, `cromo_rasgos`, `duelo_compos`.
--  · AÑADE claves nuevas a `configuracion`.
--
--  Los duelos ya resueltos antes de esta migración se quedan con las columnas
--  nuevas a NULL: no se recalculan hacia atrás a propósito, porque su resultado
--  ya está cerrado y reescribirlo sería falsear el historial.
--
--  Fuente de los números: "Sistema de Compos, Balance y Simulación", §3 a §6.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1. CATÁLOGO DE RASGOS
-- -----------------------------------------------------------------------------
-- Un rasgo es una etiqueta que, con suficientes copias en la alineación
-- titular de 11, activa un bonus sobre una o dos líneas de combate.
--
-- Los porcentajes NO son uniformes entre rasgos a propósito (§3.3): la línea de
-- Portería pesa muy poco en el total (~9%) frente a Medio (~37%), así que
-- Montaña necesita un % mucho mayor que Bosque para tener el MISMO impacto real
-- sobre la fuerza del equipo. Están calibrados para equivaler, no para parecer
-- iguales.
--
-- `tipo`:
--   afinidad      → sale de cromos.id_afinidad (Fuego/Bosque/Viento/Montaña)
--   configuracion → sale de la tabla cromo_rasgos
--   derivado      → no se asigna a ninguna carta; se calcula (solo Tensión)

CREATE TABLE IF NOT EXISTS `rasgos` (
  `id_rasgo`   INT(11) NOT NULL AUTO_INCREMENT,
  `clave`      VARCHAR(30)  NOT NULL,
  `nombre`     VARCHAR(50)  NOT NULL,
  `tipo`       ENUM('afinidad','configuracion','derivado') NOT NULL,
  -- Línea(s) de combate que refuerza. linea_2 solo lo usan Justicia y Brecha.
  `linea_1`    ENUM('POR','DF','MC','DC') DEFAULT NULL,
  `linea_2`    ENUM('POR','DF','MC','DC') DEFAULT NULL,
  -- Umbrales de copias en el once. §3.2: 2/5/11 uniformemente.
  `umbral_1`   TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `umbral_2`   TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `umbral_3`   TINYINT UNSIGNED NOT NULL DEFAULT 11,
  -- Bonus por nivel, en % sobre la(s) línea(s). Tensión los deja a 0: no da
  -- fuerza, mejora las probabilidades del Aumento (§3.6).
  `pct_1`      DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  `pct_2`      DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  `pct_3`      DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id_rasgo`),
  UNIQUE KEY `uq_rasgo_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Afinidades elementales (§3.3). El ciclo Fuego>Bosque>Viento>Montaña>Fuego es
-- canon de Inazuma Eleven (Fūrinkazan) y no se toca nunca; lo único ajustable
-- es la magnitud del bonus, que vive en `configuracion`.
INSERT INTO `rasgos`
  (`clave`, `nombre`, `tipo`, `linea_1`, `linea_2`, `pct_1`, `pct_2`, `pct_3`, `descripcion`)
VALUES
  ('fuego',   'Fuego',   'afinidad', 'DC',  NULL, 2.990,  6.970, 13.940, 'Refuerza la línea de Ataque.'),
  ('bosque',  'Bosque',  'afinidad', 'MC',  NULL, 1.600,  3.740,  7.490, 'Refuerza la línea de Medio.'),
  ('viento',  'Viento',  'afinidad', 'DF',  NULL, 1.790,  4.170,  8.330, 'Refuerza la línea de Defensa.'),
  ('montana', 'Montaña', 'afinidad', 'POR', NULL, 6.750, 15.750, 31.500, 'Refuerza la línea de Portería.')
ON DUPLICATE KEY UPDATE
  `nombre`=VALUES(`nombre`), `tipo`=VALUES(`tipo`),
  `linea_1`=VALUES(`linea_1`), `linea_2`=VALUES(`linea_2`),
  `pct_1`=VALUES(`pct_1`), `pct_2`=VALUES(`pct_2`), `pct_3`=VALUES(`pct_3`),
  `descripcion`=VALUES(`descripcion`);


-- Configuraciones (§3.4). Calibradas MÁS BAJO que las afinidades a nivel
-- equivalente porque pueden coexistir con una afinidad sobre la MISMA línea
-- (p. ej. Contraataque y Fuego, ambos en Ataque); ese solapamiento lo absorben
-- los rendimientos decrecientes.
INSERT INTO `rasgos`
  (`clave`, `nombre`, `tipo`, `linea_1`, `linea_2`, `pct_1`, `pct_2`, `pct_3`, `descripcion`)
VALUES
  ('contraataque', 'Contraataque', 'configuracion', 'DC', NULL,  2.990, 6.970, 13.940, 'Refuerza la línea de Ataque.'),
  ('vinculo',      'Vínculo',      'configuracion', 'MC', NULL,  1.600, 3.740,  7.490, 'Refuerza la línea de Medio.'),
  ('justicia',     'Justicia',     'configuracion', 'DC', 'DF',  0.750, 1.680,  3.350, 'Refuerza Ataque y Defensa por igual.'),
  ('brecha',       'Brecha',       'configuracion', 'DC', 'POR', 1.380, 3.110,  6.210, 'Refuerza Ataque y Portería por igual.')
ON DUPLICATE KEY UPDATE
  `nombre`=VALUES(`nombre`), `tipo`=VALUES(`tipo`),
  `linea_1`=VALUES(`linea_1`), `linea_2`=VALUES(`linea_2`),
  `pct_1`=VALUES(`pct_1`), `pct_2`=VALUES(`pct_2`), `pct_3`=VALUES(`pct_3`),
  `descripcion`=VALUES(`descripcion`);


-- Tensión (§3.6). NO se asigna a cartas y NO da fuerza.
--
-- Su primera versión sí sumaba % al total (+0,7/+1,4/+2,2) y FALLÓ: bajo
-- dinámica de replicador, el estilo que más la aprovechaba se comía el 100%
-- del meta en ~100 generaciones. Ahora premia la diversidad mejorando las
-- probabilidades de tier del Aumento pre-partido, que sí superó esa prueba.
--
-- Sus umbrales son de RASGOS DISTINTOS activos (3/5/7), no de copias de carta:
-- por eso no comparte los 2/5/11 del resto.
INSERT INTO `rasgos`
  (`clave`, `nombre`, `tipo`, `linea_1`, `linea_2`,
   `umbral_1`, `umbral_2`, `umbral_3`, `pct_1`, `pct_2`, `pct_3`, `descripcion`)
VALUES
  ('tension', 'Tensión', 'derivado', NULL, NULL,
   3, 5, 7, 0.000, 0.000, 0.000,
   'No da fuerza: mejora tus probabilidades de tier del Aumento pre-partido. Se activa por número de rasgos DISTINTOS activos.')
ON DUPLICATE KEY UPDATE
  `nombre`=VALUES(`nombre`), `tipo`=VALUES(`tipo`),
  `umbral_1`=VALUES(`umbral_1`), `umbral_2`=VALUES(`umbral_2`), `umbral_3`=VALUES(`umbral_3`),
  `descripcion`=VALUES(`descripcion`);


-- -----------------------------------------------------------------------------
-- 2. QUÉ RASGO DE CONFIGURACIÓN LLEVA CADA CARTA
-- -----------------------------------------------------------------------------
-- Solo guarda rasgos de tipo `configuracion`. La afinidad NO se duplica aquí:
-- sigue viviendo únicamente en cromos.id_afinidad, para que no puedan
-- desincronizarse dos fuentes de la misma verdad.
--
-- `manual` = 1 marca una asignación hecha a mano desde el panel. La rutina de
-- derivación automática NUNCA pisa una fila con manual = 1, así que se puede
-- rederivar el catálogo entero sin perder el trabajo curado a mano.

CREATE TABLE IF NOT EXISTS `cromo_rasgos` (
  `id_cromo_rasgo` INT(11) NOT NULL AUTO_INCREMENT,
  `id_cromo`       INT(11) NOT NULL,
  `id_rasgo`       INT(11) NOT NULL,
  `manual`         TINYINT(1) NOT NULL DEFAULT 0,
  `asignado`       DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cromo_rasgo`),
  UNIQUE KEY `uq_cromo_rasgo` (`id_cromo`, `id_rasgo`),
  KEY `idx_cromo` (`id_cromo`),
  KEY `idx_rasgo` (`id_rasgo`),
  CONSTRAINT `fk_cr_cromo` FOREIGN KEY (`id_cromo`) REFERENCES `cromos` (`id_cromo`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_rasgo` FOREIGN KEY (`id_rasgo`) REFERENCES `rasgos` (`id_rasgo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- -----------------------------------------------------------------------------
-- 3. COMPOS CONGELADAS POR DUELO
-- -----------------------------------------------------------------------------
-- Igual que la alineación, las compos se congelan en el momento de
-- comprometerse al duelo. Editar el mazo o reasignar un rasgo después NO
-- cambia un duelo ya empezado.

CREATE TABLE IF NOT EXISTS `duelo_compos` (
  `id_duelo_compo` INT(11) NOT NULL AUTO_INCREMENT,
  `id_duelo`       INT(11) NOT NULL,
  `id_usuario`     INT(11) NOT NULL,
  `id_rasgo`       INT(11) NOT NULL,
  `copias`         TINYINT UNSIGNED NOT NULL,
  `nivel`          TINYINT UNSIGNED NOT NULL,
  `pct_nominal`    DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id_duelo_compo`),
  UNIQUE KEY `uq_duelo_usuario_rasgo` (`id_duelo`, `id_usuario`, `id_rasgo`),
  KEY `idx_duelo_usuario` (`id_duelo`, `id_usuario`),
  CONSTRAINT `fk_dc_duelo` FOREIGN KEY (`id_duelo`) REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE,
  CONSTRAINT `fk_dc_rasgo` FOREIGN KEY (`id_rasgo`) REFERENCES `rasgos` (`id_rasgo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- -----------------------------------------------------------------------------
-- 4. TRAZABILIDAD DE LA CAPA 2 EN `duelos`
-- -----------------------------------------------------------------------------
-- Igual que ya se guarda la probabilidad y el sorteo, se guarda de dónde salió
-- cada ajuste: sin esto, un resultado raro es imposible de auditar después.

ALTER TABLE `duelos`
  ADD COLUMN IF NOT EXISTS `afinidad_dom_creador` VARCHAR(20) DEFAULT NULL AFTER `k_utilizado`,
  ADD COLUMN IF NOT EXISTS `afinidad_dom_rival`   VARCHAR(20) DEFAULT NULL AFTER `afinidad_dom_creador`,
  ADD COLUMN IF NOT EXISTS `ciclo_bonus_creador`  DECIMAL(6,3) DEFAULT NULL AFTER `afinidad_dom_rival`,
  ADD COLUMN IF NOT EXISTS `ciclo_bonus_rival`    DECIMAL(6,3) DEFAULT NULL AFTER `ciclo_bonus_creador`,
  ADD COLUMN IF NOT EXISTS `malus_coh_creador`    DECIMAL(6,3) DEFAULT NULL AFTER `ciclo_bonus_rival`,
  ADD COLUMN IF NOT EXISTS `malus_coh_rival`      DECIMAL(6,3) DEFAULT NULL AFTER `malus_coh_creador`,
  ADD COLUMN IF NOT EXISTS `tension_creador`      TINYINT UNSIGNED DEFAULT NULL AFTER `malus_coh_rival`,
  ADD COLUMN IF NOT EXISTS `tension_rival`        TINYINT UNSIGNED DEFAULT NULL AFTER `tension_creador`;


-- El Aumento se sortea con las probabilidades que dicte la Tensión del jugador,
-- así que hay que dejar constancia de con qué nivel se sorteó.
ALTER TABLE `duelo_aumentos`
  ADD COLUMN IF NOT EXISTS `tension_nivel` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `porcentaje`;


-- -----------------------------------------------------------------------------
-- 5. PARÁMETROS DE BALANCE
-- -----------------------------------------------------------------------------
-- Todos van en `configuracion` y no como constantes en el código, siguiendo la
-- convención ya establecida en la Fase 2: los números de balance se tocan sin
-- desplegar código.

INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
  ('line_cap', '20',
   'Tope máximo (%) del bonus acumulado de COMPOS sobre una sola línea, tras rendimientos decrecientes. Es la salvaguarda anti-exploit principal: se probó un escenario con 7 rasgos simultáneos sobre Ataque y contuvo el desborde sin romper el balance. No incluye el Aumento, que se suma aparte.'),
  ('compo_pesos_dr', '1.0,0.7,0.45,0.25',
   'Rendimientos decrecientes cuando varios rasgos distintos empujan la MISMA línea: se ordenan de mayor a menor y se pesan así. El cuarto y siguientes usan el último peso.'),
  ('ciclo_contra_afinidad_bonus', '5.5',
   'Bonus (%) al total del equipo cuya afinidad dominante contra a la del rival. Ciclo canon: Fuego>Bosque>Viento>Montaña>Fuego. Neutro (empate de afinidad dominante) ni gana ni pierde. Valor elegido tras barrer 11 magnitudes con ~22M de duelos: 5,5 dio la menor amplitud.'),
  ('coherencia_umbral_libre', '2.5',
   'Rareza media (Común=1 … SRF=6) por debajo de la cual no se exige ninguna coherencia de compos. 2,5 equivale a "Raro".'),
  ('coherencia_malus_rate', '3.0',
   'Cuánto se exige de compo_index por cada punto de rareza por encima del umbral libre. Subido desde 1,6 tras comprobar que el valor bajo dejaba un margen demasiado pequeño para que el malus se notara.'),
  ('coherencia_malus_tope', '18',
   'Tope duro (%) del malus de coherencia. Con un equipo de rareza máxima y cero compos se llega a este tope, y eso basta para que pierda contra un equipo bien construido de rareza inferior.')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);


-- -----------------------------------------------------------------------------
-- 6. PROBABILIDADES DE TIER DEL AUMENTO SEGÚN TENSIÓN (§3.6)
-- -----------------------------------------------------------------------------
-- Nivel 0 es exactamente la tabla base que ya usaba el juego (60/30/10), así
-- que un jugador sin Tensión no nota ningún cambio respecto a antes.

INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
  ('tension_tiers_0', '60,30,10', 'Probabilidades Plata/Oro/Prisma del Aumento sin Tensión (0-2 rasgos distintos). Es la tabla base del juego.'),
  ('tension_tiers_1', '55,31,14', 'Probabilidades Plata/Oro/Prisma con Tensión nivel 1 (3 rasgos distintos).'),
  ('tension_tiers_2', '50,33,17', 'Probabilidades Plata/Oro/Prisma con Tensión nivel 2 (5 rasgos distintos).'),
  ('tension_tiers_3', '43,36,21', 'Probabilidades Plata/Oro/Prisma con Tensión nivel 3 (7 rasgos distintos).')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);
