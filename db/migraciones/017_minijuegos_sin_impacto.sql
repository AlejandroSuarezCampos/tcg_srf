-- =====================================================================
-- 017 — Tope de decisiones que NO pueden mover el marcador
--
-- Lo necesita la apertura de los minijuegos defensivos y de la familia
-- árbitro (§15.4): esas entradas declaran impacto "ninguno" porque no hay
-- gol que quitar ni sumar, pero SÍ gastan una de las decisiones del
-- partido, y con los tres huecos defensivos nuevos pasaron a ser mayoría
-- de las candidatas. Sin este tope, a un jugador le podían tocar sus dos
-- decisiones sin ninguna capaz de tocar el resultado — el mismo problema
-- que §15.5 arregló en su día por el otro lado.
--
-- OJO: NO sube el número de pausas del partido, que es el coste de ritmo
-- de verdad. El total sigue siendo `partido_minijuegos_max`; esto solo
-- acota cuántas de ellas pueden ser irrelevantes para el marcador.
--
-- Aditiva y re-ejecutable. Igual que la 016, entra con INSERT IGNORE: si
-- ya existe la fila NO se pisa el valor, para no borrar el calibrado.
-- Para cambiarlo en una base ya migrada hay que hacer el UPDATE a mano.
-- =====================================================================

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('partido_minijuegos_sin_impacto_max', '1',
   'Cuantas de las decisiones de un partido pueden ser de impacto "ninguno" (arbitro y defensivas sin gol que mover). No sube el total, que lo fija partido_minijuegos_max.');
