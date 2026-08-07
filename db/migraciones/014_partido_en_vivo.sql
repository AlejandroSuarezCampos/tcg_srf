-- ============================================================================
-- 014 — EL PARTIDO NARRADO PASA A SER UNA FASE EN VIVO DEL SERVIDOR
--
-- Hasta ahora la reproducción del partido vivía entera en el navegador: cada
-- jugador la veía en su propio reloj, cuando cargaba la página con ?nuevo=1, y
-- un minijuego solo detenía SU tiempo. Con dos personas en el mismo duelo eso
-- no se sostiene: los dos tienen que estar viendo el mismo minuto, y cuando
-- salta un minijuego el reloj tiene que pararse para ambos.
--
-- El minuto no lo lleva ningún proceso de fondo: NO HAY CRON en este proyecto
-- (ver CLAUDE.md §8). Se deriva del reloj de pared en cada sondeo, igual que
-- ya se hace con el plazo del Aumento:
--
--     minuto = (NOW() - partido_inicio - partido_pausa_seg) * ritmo
--
-- Aditiva y re-ejecutable, como el resto.
-- ============================================================================

-- --- Reloj del partido -------------------------------------------------------
ALTER TABLE `duelos`
  ADD COLUMN IF NOT EXISTS `partido_inicio` DATETIME NULL DEFAULT NULL
    COMMENT 'Cuándo arrancó el partido narrado. NULL = todavía no ha empezado.',
  ADD COLUMN IF NOT EXISTS `partido_pausado_en` DATETIME NULL DEFAULT NULL
    COMMENT 'Desde cuándo está detenido por un minijuego. NULL = corriendo.',
  ADD COLUMN IF NOT EXISTS `partido_pausa_seg` INT NOT NULL DEFAULT 0
    COMMENT 'Segundos acumulados de pausa, que no cuentan para el minuto.';

-- --- Presencia REAL de cada jugador ------------------------------------------
-- `ultimo_latido` solo servía para el creador esperando en la sala. Aquí hace
-- falta saber si cada uno de los dos sigue delante, porque el partido arranca
-- cuando ambos han aparecido y el ausente cobra su fallback.
ALTER TABLE `duelos`
  ADD COLUMN IF NOT EXISTS `latido_creador` DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `latido_rival`   DATETIME NULL DEFAULT NULL;

-- --- Minijuegos resueltos ----------------------------------------------------
-- Antes vivían en $_SESSION, que valía mientras el partido lo veía una sola
-- persona en una sola pestaña. Ya no: el desenlace tiene que ser el MISMO para
-- los dos jugadores y sobrevivir a cada sondeo.
--
-- La clave primaria incluye el usuario porque una misma jugada podrá ofrecer
-- decisión a los dos a la vez cuando llegue el catálogo completo (El Momento de
-- la Verdad para quien tira, Leer la Mente para quien para).
CREATE TABLE IF NOT EXISTS `duelo_minijuegos` (
  `id_duelo`    int(11)     NOT NULL,
  `id_evento`   int(11)     NOT NULL COMMENT 'id del evento dentro de la narración de ese duelo',
  `id_usuario`  int(11)     NOT NULL,
  `minijuego`   varchar(40) NOT NULL COMMENT 'clave del catálogo (db/minijuegos.php)',
  `opcion`      varchar(40) NOT NULL COMMENT 'vacío = se agotó el plazo y se aplicó la segura',
  `resultado`   enum('acierto','fallo') NOT NULL,
  `aplicado`    tinyint(1)  NOT NULL DEFAULT 0 COMMENT '1 si llegó a mover el marcador',
  `resuelto_en` timestamp   NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_duelo`, `id_evento`, `id_usuario`),
  KEY `duelo` (`id_duelo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --- Parámetros de ritmo -----------------------------------------------------
-- Van en `configuracion` y no como constantes: es la regla del proyecto para
-- todo número que haya que calibrar jugando (CLAUDE.md §5.4).
INSERT IGNORE INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
  ('partido_duracion_seg', '45',
   'Segundos reales que dura el partido narrado de punta a punta, SIN contar las pausas de los minijuegos. Los dos jugadores lo ven a la vez, así que subirlo alarga la espera de ambos.'),
  ('partido_espera_seg', '15',
   'Segundos que se espera a que aparezcan los dos antes de arrancar igualmente. Quien no esté, se pierde el partido.'),
  ('partido_latido_max', '12',
   'Segundos sin latido para dar por ausente a un jugador y resolverle sus minijuegos con la opción segura.'),
  ('partido_minijuegos_max', '2',
   'Decisiones que se le ofrecen a CADA jugador por partido. Cuidado al subirlo: el reloj se para para los dos en cada una, así que dos jugadores a 3 son seis pausas y el partido se hace eterno.');
