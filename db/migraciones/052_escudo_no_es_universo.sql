-- 052 — `equipos.escudo` con el UNIVERSO dentro en vez de una ruta
--
-- La `035` añadió a `equipos` dos columnas a la vez, `universo` y `escudo`, y
-- la `037` se llevó el universo a la carta y borró la columna. Dos equipos
-- (`RaptorXz` y `Nosfanáticos`, los que se crearon en esa ventana) se quedaron
-- con el valor del universo —el literal `srf` o `ie`— guardado en `escudo`.
--
-- No es cosmético: `panel/equipos.php` pinta el escudo como
-- `<img src=".<?= $eq['escudo'] ?>">`, así que esos dos equipos piden `./srf`
-- y `./ie` y salen con la imagen rota en la lista de administración.
--
-- Un escudo de verdad SIEMPRE es una ruta y por tanto lleva una barra
-- (`./assets/img/Escudos/…`). Cualquier valor sin barra no es un escudo, y la
-- columna admite NULL con el significado exacto de "sin escudo", así que ahí
-- vuelven. Escrito por lo que el dato NO es —y no como `IN ('srf','ie')`—
-- porque el mismo accidente con otro valor dejaría igual de rota la imagen.
--
-- Es idempotente: en cuanto no queda ninguna fila así, no toca nada.

UPDATE equipos
   SET escudo = NULL
 WHERE escudo IS NOT NULL
   AND escudo <> ''
   AND escudo NOT LIKE '%/%';
