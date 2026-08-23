-- =====================================================================
-- 022 — Cuánto puede mover el marcador un jugador con sus minijuegos
--
-- Sustituye a la §1.3 como límite. Antes el tope no era un número: era el
-- margen que dejaba libre el ganador ya sorteado (cabeCambioMarcador), así
-- que dependía del resultado y era ASIMÉTRICO — el que iba perdiendo por
-- un gol se quedaba sin poder tocar nada, que es justo lo que §15.5 tuvo
-- que arreglar por otro lado.
--
-- Con el partido decidiendo el duelo no hay ningún resultado previo al que
-- no se pueda contradecir, así que el límite pasa a ser de DISEÑO: cuánto
-- se le permite mover a cada uno. Se deja en 1 gol a propósito, que es el
-- margen que la §1.3 autorizaba en la práctica ("con un 2-1 puedo parar el
-- gol del rival"), para no meter en el mismo cambio "los minijuegos
-- deciden" y "los minijuegos deciden el doble".
--
-- Los minijuegos de impacto "partido" con efecto presupuesto_gol o
-- presupuesto_parada suman +1 sobre esto.
--
-- Subirlo hace que los minijuegos pesen más y que la fuerza del mazo pese
-- menos; bajarlo a 0 los deja en pura puntuación de actuación, sin tocar
-- el resultado. Aditiva y re-ejecutable.
-- =====================================================================

INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('partido_presupuesto_marcador', '1',
        'Goles que puede mover cada jugador con sus minijuegos en un partido')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- ---------------------------------------------------------------------
-- Y el plazo para dar por abandonado un partido en juego.
--
-- Hace falta porque el dinero de los dos está RETENIDO hasta que alguien
-- liquide, y hay dos formas de que el reloj no llegue nunca al final: que
-- el partido no arranque (arrancarlo es cosa del sondeo) o que se quede
-- parado en una decisión que nadie toma. Pasado este plazo se cierra con
-- el marcador tal cual esté.
--
-- Holgado a propósito: quien llega tarde a su partido todavía puede
-- jugarlo entero. Solo cuando ya no va a aparecer nadie se cierra.
-- ---------------------------------------------------------------------

INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('partido_abandono_seg', '3600',
        'Segundos tras los que un partido en juego se cierra por abandono')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);
