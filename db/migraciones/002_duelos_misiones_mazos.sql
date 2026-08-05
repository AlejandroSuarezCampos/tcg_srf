-- =============================================================================
--  002 — DUELOS, MISIONES Y MAZOS  (Fase 2)
-- =============================================================================
--  Migración ADITIVA. Se puede ejecutar más de una vez sin romper nada:
--  todo va con IF NOT EXISTS / INSERT IGNORE.
--
--  Aplicar con:
--    C:\xampp\mysql\bin\mysql.exe -u root tcg < db/migraciones/002_duelos_misiones_mazos.sql
--
--  -------------------------------------------------------------------------
--  AVISO EXPLÍCITO (lo exige §10 del documento de traspaso)
--  -------------------------------------------------------------------------
--  Esta migración ES LA ÚNICA de la Fase 2 que toca una tabla ya existente:
--
--    · AÑADE tres columnas a `cromos`: ataque, defensa, tecnica.
--    · ESCRIBE un valor inicial en esas tres columnas para las cartas de
--      posición jugadora (POR/DF/MC/DC) que aún estén a 0.
--
--  NO borra ni modifica ninguna columna ni fila preexistente de `usuarios`,
--  `cromos`, `coleccion` ni `mercado`. Los datos que ya había siguen intactos:
--  solo se rellenan columnas nuevas que antes no existían.
--
--  Las cartas que NO son jugador (ESCUDO, GER, ENT) se quedan a 0 a propósito:
--  por decisión de producto no entran en un mazo, así que no tienen combate.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1. ESTADÍSTICAS DE COMBATE EN `cromos`
-- -----------------------------------------------------------------------------
-- El componente de carta ya tenía el hueco reservado ($opts['stats'], §3 del
-- documento), pero las estadísticas no existían en ningún sitio. Son estas tres
-- porque son las que el propio documento usa de ejemplo: ATA / DEF / TÉC.

ALTER TABLE `cromos`
  ADD COLUMN IF NOT EXISTS `ataque`   TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_afinidad`,
  ADD COLUMN IF NOT EXISTS `defensa`  TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `ataque`,
  ADD COLUMN IF NOT EXISTS `tecnica`  TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `defensa`;


-- Siembra inicial. La fuerza sale de la rareza (presupuesto de poder) y el
-- reparto entre las tres estadísticas sale de la posición, para que un portero
-- no tenga el mismo perfil que un delantero.
--
-- La variación por carta es determinista (depende de id_cromo, no de RAND()):
-- así dos cartas de la misma rareza y posición no salen clonadas, pero volver a
-- ejecutar la migración da exactamente el mismo resultado.
--
-- El WHERE ... = 0 hace que esto NO pise valores que ya se hayan ajustado a
-- mano desde el panel: solo rellena lo que sigue sin tocar.

UPDATE `cromos`
SET
  `ataque` = GREATEST(1, LEAST(99,
      CASE `id_rareza`
        WHEN 1 THEN 55 WHEN 2 THEN 63 WHEN 3 THEN 71
        WHEN 4 THEN 79 WHEN 5 THEN 87 WHEN 6 THEN 94 ELSE 55 END
    + CASE `posicion`
        WHEN 'POR' THEN -25 WHEN 'DF' THEN -12 WHEN 'MC' THEN 0 WHEN 'DC' THEN 12 ELSE 0 END
    + (MOD(`id_cromo` * 7, 9) - 4)
  )),
  `defensa` = GREATEST(1, LEAST(99,
      CASE `id_rareza`
        WHEN 1 THEN 55 WHEN 2 THEN 63 WHEN 3 THEN 71
        WHEN 4 THEN 79 WHEN 5 THEN 87 WHEN 6 THEN 94 ELSE 55 END
    + CASE `posicion`
        WHEN 'POR' THEN 12 WHEN 'DF' THEN 10 WHEN 'MC' THEN 0 WHEN 'DC' THEN -12 ELSE 0 END
    + (MOD(`id_cromo` * 11, 9) - 4)
  )),
  `tecnica` = GREATEST(1, LEAST(99,
      CASE `id_rareza`
        WHEN 1 THEN 55 WHEN 2 THEN 63 WHEN 3 THEN 71
        WHEN 4 THEN 79 WHEN 5 THEN 87 WHEN 6 THEN 94 ELSE 55 END
    + CASE `posicion`
        WHEN 'POR' THEN 2 WHEN 'DF' THEN -2 WHEN 'MC' THEN 8 WHEN 'DC' THEN 2 ELSE 0 END
    + (MOD(`id_cromo` * 13, 9) - 4)
  ))
WHERE `posicion` IN ('POR', 'DF', 'MC', 'DC')
  AND `ataque` = 0 AND `defensa` = 0 AND `tecnica` = 0;


-- -----------------------------------------------------------------------------
-- 2. MAZOS
-- -----------------------------------------------------------------------------
-- Un mazo es una alineación de 11 cartas de posición jugadora. `titular` marca
-- con cuál se duela; se fuerza a uno solo por usuario desde PHP, no con un
-- índice único, porque "ninguno titular" también es un estado válido.

CREATE TABLE IF NOT EXISTS `mazos` (
  `id_mazo`    INT(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` INT(11) NOT NULL,
  `nombre`     VARCHAR(60) NOT NULL,
  `titular`    TINYINT(1) NOT NULL DEFAULT 0,
  `creado`     DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_mazo`),
  KEY `idx_mazos_usuario` (`id_usuario`),
  CONSTRAINT `fk_mazos_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Las cartas del mazo apuntan a `coleccion` (la COPIA concreta que posee el
-- usuario), no a `cromos`, igual que hace el mercado. Así "tener la carta en un
-- mazo" es un hecho sobre un objeto que se posee, no sobre un tipo de carta.
--
-- Una misma copia SÍ puede estar en varios mazos a la vez: con un catálogo
-- pequeño, obligar a duplicados para armar un segundo mazo sería frustrante sin
-- aportar nada. Lo que sí se impide es repetirla dentro del mismo mazo.
--
-- `hueco` es la posición en la alineación (0 portería, 1-4 defensa, 5-8 medio,
-- 9-10 ataque). CUALQUIER carta puede ir en CUALQUIER hueco: poner un defensa
-- de delantero es legal y es justo donde vive el metajuego. Lo que decide su
-- aportación no es su posición nominal sino el hueco en el que lo pongas, y en
-- ese hueco rinde con la estadística de esa línea. Ver Tcg::HUECOS.
CREATE TABLE IF NOT EXISTS `mazo_cartas` (
  `id_mazo_carta` INT(11) NOT NULL AUTO_INCREMENT,
  `id_mazo`       INT(11) NOT NULL,
  `id_coleccion`  INT(11) NOT NULL,
  `hueco`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_mazo_carta`),
  UNIQUE KEY `uq_mazo_copia` (`id_mazo`, `id_coleccion`),
  UNIQUE KEY `uq_mazo_hueco` (`id_mazo`, `hueco`),
  KEY `idx_mazocartas_coleccion` (`id_coleccion`),
  CONSTRAINT `fk_mazocartas_mazo` FOREIGN KEY (`id_mazo`)
    REFERENCES `mazos` (`id_mazo`) ON DELETE CASCADE,
  CONSTRAINT `fk_mazocartas_coleccion` FOREIGN KEY (`id_coleccion`)
    REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Para instalaciones que ya hubieran corrido una versión anterior de este mismo
-- fichero (la Fase 2 no ha salido de la máquina de desarrollo, así que esto solo
-- afecta a mazos de prueba). Requiere `mazo_cartas` vacía o ya con huecos
-- asignados: si no, la clave única de hueco chocaría con las filas antiguas.
ALTER TABLE `mazo_cartas`
  ADD COLUMN IF NOT EXISTS `hueco` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_coleccion`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_mazo_hueco` (`id_mazo`, `hueco`);


-- -----------------------------------------------------------------------------
-- 3. DUELOS
-- -----------------------------------------------------------------------------
-- Sala asíncrona: el creador abre y espera, el rival entra y el servidor
-- resuelve en ese mismo instante dentro de una transacción. La cuenta atrás y
-- el revelado simultáneo son teatro de cliente sobre un resultado ya cerrado;
-- no hay websockets ni turnos que sincronizar.
--
-- Los goles se guardan para poder reconstruir el marcador al recargar: sin
-- ellos, volver a entrar en un duelo resuelto no podría mostrar el resultado.

CREATE TABLE IF NOT EXISTS `duelos` (
  `id_duelo`          INT(11) NOT NULL AUTO_INCREMENT,
  `id_creador`        INT(11) NOT NULL,
  `id_rival`          INT(11) DEFAULT NULL,
  `id_mazo_creador`   INT(11) NOT NULL,
  `id_mazo_rival`     INT(11) DEFAULT NULL,
  `tipo_apuesta`      ENUM('monedas','carta') NOT NULL,
  `monedas`           INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `id_rareza_apuesta` INT(11) DEFAULT NULL,
  `estado`            ENUM('abierto','resuelto','cancelado') NOT NULL DEFAULT 'abierto',
  `id_ganador`        INT(11) DEFAULT NULL,
  `goles_creador`     TINYINT UNSIGNED DEFAULT NULL,
  `goles_rival`       TINYINT UNSIGNED DEFAULT NULL,
  `creado`            DATETIME NOT NULL DEFAULT current_timestamp(),
  `resuelto`          DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_duelo`),
  KEY `idx_duelos_estado` (`estado`),
  KEY `idx_duelos_creador` (`id_creador`),
  KEY `idx_duelos_rival` (`id_rival`),
  KEY `fk_duelos_rareza` (`id_rareza_apuesta`),
  CONSTRAINT `fk_duelos_creador` FOREIGN KEY (`id_creador`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_duelos_rival` FOREIGN KEY (`id_rival`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_duelos_rareza` FOREIGN KEY (`id_rareza_apuesta`)
    REFERENCES `rarezas` (`id_rareza`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Estados del duelo (máquina de estados de la especificación de combate, §7).
-- Se declaran los seis desde el principio aunque los dos de la fase de aumento
-- todavía no se usen: cambiar un ENUM más tarde es una migración destructiva
-- sobre filas vivas, y declararlos ahora no cuesta nada.
--   creado              el creador abrió la sala y espera rival
--   aceptado            un rival ha entrado con su alineación
--   aumento_pendiente   ambos deben elegir su aumento pre-partido (Capa 3)
--   listo_para_resolver ambas elecciones cerradas (por elección o por plazo)
--   resuelto            calculado, apuestas movidas, trazabilidad guardada
--   cancelado           nadie entró y el creador retiró la sala
ALTER TABLE `duelos`
  MODIFY COLUMN `estado` ENUM(
    'creado','aceptado','aumento_pendiente','listo_para_resolver','resuelto','cancelado'
  ) NOT NULL DEFAULT 'creado';

-- Trazabilidad de la resolución. Se guarda TODO lo que intervino en el
-- resultado, no solo el ganador: sin esto no se puede auditar un duelo ni
-- explicar por qué salió lo que salió (§13 de la especificación).
ALTER TABLE `duelos`
  ADD COLUMN IF NOT EXISTS `total_bruto_creador`   DECIMAL(12,4) DEFAULT NULL AFTER `id_rareza_apuesta`,
  ADD COLUMN IF NOT EXISTS `total_bruto_rival`     DECIMAL(12,4) DEFAULT NULL AFTER `total_bruto_creador`,
  ADD COLUMN IF NOT EXISTS `total_final_creador`   DECIMAL(12,4) DEFAULT NULL AFTER `total_bruto_rival`,
  ADD COLUMN IF NOT EXISTS `total_final_rival`     DECIMAL(12,4) DEFAULT NULL AFTER `total_final_creador`,
  ADD COLUMN IF NOT EXISTS `probabilidad_victoria_creador` DECIMAL(12,4) DEFAULT NULL AFTER `total_final_rival`,
  ADD COLUMN IF NOT EXISTS `valor_sorteo`          DECIMAL(12,4) DEFAULT NULL AFTER `probabilidad_victoria_creador`,
  ADD COLUMN IF NOT EXISTS `k_utilizado`           DECIMAL(12,4) DEFAULT NULL AFTER `valor_sorteo`,
  ADD COLUMN IF NOT EXISTS `aumento_vence`         DATETIME      DEFAULT NULL AFTER `k_utilizado`,
  -- Latido del creador. Mientras la sala espera rival, el creador está DENTRO
  -- de ella y no puede hacer otra cosa en la web; si se va, la sala muere.
  -- Sin websockets, "estar dentro" se traduce en que su pantalla manda un
  -- latido periódico. Si deja de latir, la sala se cancela y se le devuelve lo
  -- apostado (ver cancelarSalasAbandonadas()).
  ADD COLUMN IF NOT EXISTS `ultimo_latido`         DATETIME      DEFAULT NULL AFTER `aumento_vence`;


-- Instantánea de la alineación con la que cada jugador se comprometió.
--
-- No se guarda una referencia al mazo: se copian los números. Entre que un
-- jugador entra al duelo y el duelo se resuelve hay una ventana (la fase de
-- aumento) en la que podría irse a mazos.php y mejorar su alineación. Con la
-- foto congelada, editar el mazo después de comprometerse no cambia nada.
--
-- Copiar las estadísticas (y no solo el id del cromo) hace además que un
-- reajuste de balance en `cromos` no reescriba el pasado de duelos ya jugados.
CREATE TABLE IF NOT EXISTS `duelo_alineaciones` (
  `id_alineacion` INT(11) NOT NULL AUTO_INCREMENT,
  `id_duelo`      INT(11) NOT NULL,
  `id_usuario`    INT(11) NOT NULL,
  `hueco`         TINYINT UNSIGNED NOT NULL,
  `id_cromo`      INT(11) NOT NULL,
  `ataque`        TINYINT UNSIGNED NOT NULL,
  `defensa`       TINYINT UNSIGNED NOT NULL,
  `tecnica`       TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_alineacion`),
  UNIQUE KEY `uq_alineacion_duelo_usuario_hueco` (`id_duelo`, `id_usuario`, `hueco`),
  KEY `idx_alineacion_cromo` (`id_cromo`),
  CONSTRAINT `fk_alineacion_duelo` FOREIGN KEY (`id_duelo`)
    REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE,
  CONSTRAINT `fk_alineacion_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_alineacion_cromo` FOREIGN KEY (`id_cromo`)
    REFERENCES `cromos` (`id_cromo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Parámetros de balance. La especificación (§9.1) exige que K se pueda ajustar
-- sin tocar código, y lo mismo vale para el acotado de probabilidad y el plazo
-- de la fase de aumento. Clave/valor en texto para no tener que migrar el
-- esquema cada vez que aparezca un parámetro nuevo.
CREATE TABLE IF NOT EXISTS `configuracion` (
  `clave`       VARCHAR(50) NOT NULL,
  `valor`       VARCHAR(255) NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Valores de arranque. Son PROVISIONALES: el documento de balance todavía no
-- existe (§14 de la especificación los deja pendientes). Están puestos para que
-- el sistema funcione y se puedan calibrar con duelos reales.
--   duelo_k = 400 es la K clásica de Elo. Con los totales que produce hoy una
--   alineación (600-900), una diferencia de ~220 puntos deja al fuerte ganando
--   ~4 de cada 5 duelos, que es el orden de magnitud buscado.
INSERT IGNORE INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
  ('duelo_k',            '400',  'K de la curva Elo de resolución. Menor = la diferencia de fuerza pesa más.'),
  ('duelo_p_min',        '0.05', 'Probabilidad mínima de victoria. Nunca 0: el mazo débil siempre tiene opción.'),
  ('duelo_p_max',        '0.95', 'Probabilidad máxima de victoria. Nunca 1.'),
  ('duelo_plazo_aumento','30',   'Segundos para elegir aumento antes de que lo elija el sistema.'),
  ('duelo_latido_max',   '45',   'Segundos sin latido del creador antes de dar la sala por abandonada y cancelarla. Con menos, un parpadeo de red o un cambio de pestaña de un segundo ya mataba la sala: los navegadores estrangulan los temporizadores de las pestañas en segundo plano.');


-- Lo que pone cada lado sobre la mesa. Fila por usuario y duelo: en apuesta de
-- monedas se llena `monedas`, en apuesta de carta se llena `id_coleccion`.
CREATE TABLE IF NOT EXISTS `duelo_apuestas` (
  `id_apuesta`   INT(11) NOT NULL AUTO_INCREMENT,
  `id_duelo`     INT(11) NOT NULL,
  `id_usuario`   INT(11) NOT NULL,
  `monedas`      INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `id_coleccion` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id_apuesta`),
  UNIQUE KEY `uq_apuesta_duelo_usuario` (`id_duelo`, `id_usuario`),
  KEY `idx_apuestas_usuario` (`id_usuario`),
  KEY `idx_apuestas_coleccion` (`id_coleccion`),
  CONSTRAINT `fk_apuestas_duelo` FOREIGN KEY (`id_duelo`)
    REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE,
  CONSTRAINT `fk_apuestas_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_apuestas_coleccion` FOREIGN KEY (`id_coleccion`)
    REFERENCES `coleccion` (`id_coleccion`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Aumento pre-partido (Capa 3 de la especificación de combate).
--
-- Se ofrecen 3 opciones a cada jugador y elige una, válida solo para ese duelo.
-- Las 3 filas se generan UNA vez al entrar en la fase y no se regeneran nunca:
-- si se regenerasen, recargar la página sería una tirada gratis hasta sacar un
-- Prisma. Por eso se persisten antes de enseñarlas.
--
-- `stat` va en minúscula a propósito: coincide con los nombres reales de las
-- columnas de `cromos`, así que aplicar el bono no necesita tabla de traducción.
CREATE TABLE IF NOT EXISTS `duelo_aumentos` (
  `id_aumento`  INT(11) NOT NULL AUTO_INCREMENT,
  `id_duelo`    INT(11) NOT NULL,
  `id_usuario`  INT(11) NOT NULL,
  `opcion`      TINYINT UNSIGNED NOT NULL,
  `stat`        ENUM('ataque','defensa','tecnica') NOT NULL,
  `tier`        ENUM('plata','oro','prisma') NOT NULL,
  `porcentaje`  DECIMAL(5,2) NOT NULL,
  `elegida`     TINYINT(1) NOT NULL DEFAULT 0,
  `por_defecto` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_aumento`),
  UNIQUE KEY `uq_aumento_duelo_usuario_opcion` (`id_duelo`, `id_usuario`, `opcion`),
  KEY `idx_aumentos_usuario` (`id_usuario`),
  CONSTRAINT `fk_aumentos_duelo` FOREIGN KEY (`id_duelo`)
    REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE,
  CONSTRAINT `fk_aumentos_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- -----------------------------------------------------------------------------
-- 4. MISIONES
-- -----------------------------------------------------------------------------
-- `tipo` decide de qué consulta sale el progreso. El progreso NO se guarda: se
-- deriva de lo que la base de datos ya registra (cartas en la colección, duelos
-- ganados...). Duplicar contadores es la forma más fácil de que la interfaz
-- acabe mintiendo, así que solo se persiste lo que no se puede deducir: si la
-- recompensa ya se cobró.

CREATE TABLE IF NOT EXISTS `misiones` (
  `id_mision`          INT(11) NOT NULL AUTO_INCREMENT,
  `nombre`             VARCHAR(80) NOT NULL,
  `descripcion`        VARCHAR(255) NOT NULL,
  `tipo`               ENUM('cartas_distintas','copias_totales','duelos_jugados','duelos_ganados','expansiones_completas','mazos_creados') NOT NULL,
  `objetivo`           INT(11) UNSIGNED NOT NULL,
  `recompensa_monedas` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `activo`             TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_mision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `misiones_progreso` (
  `id_progreso`      INT(11) NOT NULL AUTO_INCREMENT,
  `id_usuario`       INT(11) NOT NULL,
  `id_mision`        INT(11) NOT NULL,
  `fecha_reclamada`  DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_progreso`),
  UNIQUE KEY `uq_progreso_usuario_mision` (`id_usuario`, `id_mision`),
  KEY `idx_progreso_mision` (`id_mision`),
  CONSTRAINT `fk_progreso_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_progreso_mision` FOREIGN KEY (`id_mision`)
    REFERENCES `misiones` (`id_mision`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Misiones de arranque. Texto en tono de ficha de competición: sin argot gamer,
-- sin superlativos y sin chistes nuevos (§6 del documento).
INSERT IGNORE INTO `misiones`
  (`id_mision`, `nombre`, `descripcion`, `tipo`, `objetivo`, `recompensa_monedas`, `activo`) VALUES
  (1, 'Primeras fichas',      'Consigue 10 cromos distintos.',                      'cartas_distintas',      10,   250, 1),
  (2, 'Plantilla amplia',     'Consigue 25 cromos distintos.',                      'cartas_distintas',      25,   600, 1),
  (3, 'Archivo completo',     'Consigue 40 cromos distintos.',                      'cartas_distintas',      40,  1500, 1),
  (4, 'Fondo de armario',     'Acumula 100 cromos contando repetidos.',             'copias_totales',       100,   400, 1),
  (5, 'Alineación inscrita',  'Crea tu primer mazo de 11 jugadores.',               'mazos_creados',          1,   300, 1),
  (6, 'Debut en competición', 'Disputa tu primer duelo.',                           'duelos_jugados',         1,   250, 1),
  (7, 'Racha de temporada',   'Gana 5 duelos.',                                     'duelos_ganados',         5,   900, 1),
  (8, 'Expansión al día',     'Completa todas las cartas de una expansión.',        'expansiones_completas',  1,  1200, 1);


-- -----------------------------------------------------------------------------
-- 5. MINIJUEGOS
-- -----------------------------------------------------------------------------
-- La tabla se crea ahora para no volver a tocar el esquema más adelante, pero
-- todavía no hay ningún minijuego definido: queda fuera del alcance de esta
-- tanda por decisión de Alejandro. `juego` es un texto libre (p. ej. 'quiz')
-- justamente porque aún no sabemos cuáles serán.

CREATE TABLE IF NOT EXISTS `minijuegos_partidas` (
  `id_partida`         INT(11) NOT NULL AUTO_INCREMENT,
  `id_usuario`         INT(11) NOT NULL,
  `juego`              VARCHAR(40) NOT NULL,
  `puntuacion`         INT(11) NOT NULL DEFAULT 0,
  `recompensa_monedas` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `jugado`             DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_partida`),
  KEY `idx_partidas_usuario` (`id_usuario`),
  KEY `idx_partidas_juego` (`juego`),
  CONSTRAINT `fk_partidas_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
