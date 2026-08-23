-- =====================================================================
-- 020 — Ganar un minijuego SUBE la probabilidad de gol, no lo regala
--
-- Decisión de Alejandro: "si ganas un minijuego en un punto decisivo que
-- sea ocasión de gol". Hasta ahora un acierto movía el marcador siempre
-- (100 %), así que leer bien la jugada equivalía a marcar. Con esto puedes
-- adivinarle la intención y que se estrelle en el palo, como en el fútbol.
--
-- Lo que NO cambia: fallar sigue sin castigar. El minijuego solo puede
-- mejorar tu partido, nunca empeorarlo (ver resolverMinijuego), así que un
-- fallo deja la jugada exactamente como estaba.
--
-- El sorteo es determinista por (duelo, evento): el sondeo repite y la
-- resolución se puede reintentar, así que con azar real el mismo acierto
-- podría entrar una vez y no la siguiente, y los dos jugadores verían
-- desenlaces distintos.
--
-- CUIDADO AL BAJARLO: por debajo de ~0,5 el minijuego empieza a sentirse
-- injusto (aciertas más veces de las que pasa algo) y por encima de 0,9
-- vuelve a ser el regalo de antes. 0,70 deja el gol como lo más probable
-- sin garantizarlo.
--
-- Aditiva y re-ejecutable. INSERT IGNORE como las anteriores: cambiar el
-- valor aquí NO pisa una base ya migrada, hay que hacer el UPDATE a mano.
-- =====================================================================

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('partido_minijuego_prob_gol', '0.70',
   'Probabilidad de que un ACIERTO en un minijuego acabe moviendo el marcador. Antes era siempre 1. Fallar sigue sin castigar.');
