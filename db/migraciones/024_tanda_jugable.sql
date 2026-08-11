-- =====================================================================
-- 024 — La tanda de penaltis se JUEGA
--
-- Hasta ahora la tanda existía pero se resolvía en el servidor y el jugador
-- no la veía (§15.10). Medido sobre 300 duelos: el 27,7 % acababa en
-- empate, o sea que MÁS DE UNO DE CADA CUATRO duelos se decidía en algo
-- que nadie llegaba a jugar. Era el agujero más grande que quedaba.
--
-- Regla que pidió Alejandro, y es toda la regla: la portería se divide en
-- CUATRO huecos, tirador y portero eligen uno cada uno, y **si coinciden es
-- parada; si no, gol**. Ni Ataque ni Portería entran en la cuenta: es un
-- pulso de intenciones, no de estadísticas.
--
-- ⚠️ ES LA PRIMERA INTERACCIÓN SIMULTÁNEA DEL JUEGO. En todos los minijuegos
-- anteriores el dato oculto sale de las cartas y el servidor lo puede
-- recalcular cuando quiera; aquí el dato oculto es **lo que el otro jugador
-- está eligiendo en este mismo momento**. Por eso hace falta una tabla: las
-- elecciones NO son derivables de `valor_sorteo`, hay que guardarlas.
--
-- La clave primaria (id_duelo, ronda, turno) da la idempotencia gratis, el
-- mismo truco que `duelo_minijuegos`: dos peticiones a la vez no pueden
-- crear dos veces el mismo tiro.
--
-- Aditiva y re-ejecutable.
-- =====================================================================

CREATE TABLE IF NOT EXISTS duelo_penaltis (
  id_duelo      INT NOT NULL,
  -- 1..5 son los tiros reglamentarios; de 6 en adelante, muerte súbita.
  ronda         TINYINT UNSIGNED NOT NULL,
  -- 0 = tira el creador del duelo, 1 = tira el rival. En cada tiro los DOS
  -- jugadores actúan: uno elige dónde tira y el otro dónde se lanza, así que
  -- nadie se queda mirando.
  turno         TINYINT UNSIGNED NOT NULL,

  zona_tirador  VARCHAR(16) DEFAULT NULL,
  zona_portero  VARCHAR(16) DEFAULT NULL,

  -- Si la eligió el sistema al vencer el plazo, para poder distinguir después
  -- una decisión real de una automática (igual que `por_defecto` en los
  -- aumentos) y para no contarle al jugador como suya una que no tomó.
  auto_tirador  TINYINT(1) NOT NULL DEFAULT 0,
  auto_portero  TINYINT(1) NOT NULL DEFAULT 0,

  -- NULL mientras el tiro sigue abierto. 1 gol, 0 parada.
  gol           TINYINT(1) DEFAULT NULL,

  -- Desde cuándo cuenta el plazo de este tiro. Lo pone el servidor al abrirlo,
  -- no el cliente: adelantar el reloj del navegador no adelanta nada.
  abierto       DATETIME NOT NULL DEFAULT current_timestamp(),
  resuelto      DATETIME DEFAULT NULL,

  PRIMARY KEY (id_duelo, ronda, turno),
  CONSTRAINT fk_penaltis_duelo FOREIGN KEY (id_duelo)
    REFERENCES duelos (id_duelo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Plazo de cada tiro.
--
-- Más largo que el de un minijuego (9 s) a propósito: aquí no estás leyendo
-- una jugada, estás intentando adivinar a una persona, y ese pulso necesita
-- un par de segundos más. Pero acotado, porque el otro está esperando.
-- ---------------------------------------------------------------------

INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('tanda_plazo_seg', '12',
        'Segundos para elegir hueco en un penalti antes de que decida el sistema')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);
