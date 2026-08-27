-- =====================================================================
-- 054 — EL PARTIDO SE JUEGA.
--
-- Hasta aquí el marcador salía de `generarEventosPartido()`, que lo sorteaba
-- desde una semilla antes de que el jugador tocara nada. Esta tabla es donde
-- pasa a vivir lo que el jugador hace de verdad: una fila por jugada, con un
-- hueco para la decisión de cada bando, resuelta en servidor cuando ambos
-- huecos están llenos.
--
-- Está calcada de `duelo_penaltis` a propósito: ese patrón ya funciona en
-- producción con dos jugadores y sondeo, así que no se inventa sincronía nueva.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `partido_jugadas` (
  `id_duelo`       int(11) NOT NULL,
  `numero`         tinyint(3) unsigned NOT NULL COMMENT '1..partido_jugadas_num',
  `minuto`         smallint(5) unsigned NOT NULL,
  `zona`           enum('salida','creacion','area') NOT NULL,
  `id_poseedor`    int(11) NOT NULL,
  `accion`         varchar(24) DEFAULT NULL COMMENT 'NULL = el poseedor aún no ha decidido',
  `mj_atacante`    varchar(40) DEFAULT NULL COMMENT 'clave del catálogo; se fija al decidir la acción',
  `mj_defensor`    varchar(40) DEFAULT NULL,
  `opc_atacante`   varchar(24) DEFAULT NULL COMMENT 'solo en minijuegos de lectura',
  `opc_defensor`   varchar(24) DEFAULT NULL,
  `rend_atacante`  decimal(4,3) DEFAULT NULL COMMENT '0..1; lo calcula el SERVIDOR, nunca el cliente',
  `rend_defensor`  decimal(4,3) DEFAULT NULL,
  `val_atacante`   decimal(8,2) DEFAULT NULL,
  `val_defensor`   decimal(8,2) DEFAULT NULL,
  `auto_atacante`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 si se resolvió por plazo agotado',
  `auto_defensor`  tinyint(1) NOT NULL DEFAULT 0,
  `desenlace`      enum('gol','avanza','area','recupera') DEFAULT NULL COMMENT 'NULL = jugada abierta',
  `abierta`        datetime NOT NULL DEFAULT current_timestamp(),
  `resuelta`       datetime DEFAULT NULL,
  PRIMARY KEY (`id_duelo`,`numero`),
  KEY `idx_jugadas_abiertas` (`id_duelo`,`desenlace`),
  CONSTRAINT `fk_jugadas_duelo` FOREIGN KEY (`id_duelo`)
    REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- LOS DIALES. Todo lo que este motor deja ajustable sin tocar código.
-- ---------------------------------------------------------------------
INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
  ('partido_jugadas_num', '12',
   'Jugadas por partido. El usuario interviene en TODAS, así que este número es también cuántos minijuegos juega. Nace en 12, el techo de la horquilla pedida (7-12). Bajarlo reparte solo el reloj y los goles esperados.'),
  ('partido_stat_ref', '80',
   'Estadística que desbloquea el techo COMPLETO de multiplicador. Nace en 80 porque es el listón que el propio diseño llama élite. Bajarlo hace que más cartas alcancen el techo, o sea aplana la ventaja del buen equipo.'),
  ('habilidad_tope_pct', '100',
   'Red de seguridad: porcentaje máximo que la ejecución puede desviar el resultado respecto a lo que la estadística sola justifica. Con 100 el valor puede ir del 0 al 200 por cien de la stat, o sea que casi nunca muerde. Apretarlo es la palanca si el balance se desmadra.'),
  ('partido_decision_seg', '10',
   'Segundos para elegir accion o resolver un minijuego antes de que se aplique la opcion segura y rendimiento 0.'),
  ('partido_narracion_seg', '3',
   'Segundos reales que el reloj corre en vivo entre jugada y jugada mientras la narracion cuenta lo que pasa.')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);
