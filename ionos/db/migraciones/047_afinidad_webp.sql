-- ============================================================================
-- 047 · EL HEXÁGONO DE AFINIDAD, QUE NO SE VEÍA EN NINGUNA PARTE
--
-- `afinidad.imagen` apuntaba a `.png` y en disco los cuatro iconos son
-- `.webp` desde hace tiempo. Las cuatro rutas daban 404.
--
-- ⚠️ POR QUÉ ESO BORRABA EL HEXÁGONO Y NO SALÍA UNA IMAGEN ROTA.
--    `assets/js/ui.js` escucha `error` de <img> en captura y, cuando la que
--    falla vive dentro de un `.carta-afinidad`, quita el hexágono entero —es
--    deliberado: una afinidad sin gráfico se prefiere invisible antes que con
--    el icono roto del navegador. Con las cuatro rutas muertas, la regla se
--    disparaba SIEMPRE: el hexágono no aparecía ni en las cartas ni en el
--    modal de ficha, y el síntoma que se veía («en el modal del jugador no
--    sale la afinidad») no tenía nada que ver con el modal.
--
--    Por eso esto se arregla en el dato y no en el modal: el modal era el
--    sitio donde más se notaba, no donde estaba el fallo.
--
-- Idempotente: reescribe la ruta entera a partir del nombre del fichero, así
-- que da igual cuántas veces se ejecute y da igual de qué extensión se venga.
-- 'no-afi' se queda con la cadena vacía, que es lo que significa "esta carta
-- no tiene afinidad" y lo que hace que el hexágono no se pinte.
-- ============================================================================

UPDATE afinidad SET imagen = './assets/img/Afinidades/montaña.webp' WHERE id = 1;
UPDATE afinidad SET imagen = './assets/img/Afinidades/fuego.webp'   WHERE id = 2;
UPDATE afinidad SET imagen = './assets/img/Afinidades/aire.webp'    WHERE id = 3;
UPDATE afinidad SET imagen = './assets/img/Afinidades/bosque.webp'  WHERE id = 4;
UPDATE afinidad SET imagen = ''                                     WHERE id = 5;


-- ----------------------------------------------------------------------------
-- COMPROBACIÓN
--
-- Las cuatro afinidades con gráfico tienen que acabar en `.webp`; 'no-afi',
-- vacía. Cualquier otra cosa y el hexágono vuelve a desaparecer.
-- ----------------------------------------------------------------------------
SELECT id, nombre, imagen,
       CASE
         WHEN id = 5 AND imagen = ''            THEN 'ok · sin gráfico a propósito'
         WHEN imagen LIKE '%.webp'              THEN 'ok'
         ELSE '⚠ revisar'
       END AS estado
  FROM afinidad
 ORDER BY id;
