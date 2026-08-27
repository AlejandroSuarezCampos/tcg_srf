-- =============================================================================
-- 050 · LOS RATIOS SON POR SOBRE, Y SHAWN FROSTE DEJA DE SER NUMERADO
-- =============================================================================
--
-- DOS COSAS, y las dos son de datos:
--
-- 1) RECALIBRAR `rarezas.probabilidad`.
--    Desde el §15.x el sorteo del sobre es UNA tirada por sobre, no una por
--    carta, así que el número de esta tabla ya se leía como «porcentaje de
--    SOBRES que traen esta rareza». Lo que cambia aquí son los valores, que
--    seguían siendo los del reparto viejo y hacían los tiers altos demasiado
--    frecuentes. La escala nueva, pedida tal cual:
--
--        Numerada    0,10 %  →  1 de cada 1.000 sobres
--        SRF         0,20 %  →  1 de cada   500 sobres
--        Legendario  0,3333% →  1 de cada   300 sobres
--        Épico       1,00 %  →  1 de cada   100 sobres
--
--    Las tres rarezas base (Común, Poco común, Raro) NO llevan número por
--    sobre y no pueden llevarlo: son el relleno, salen varias veces en el
--    mismo sobre. Su probabilidad se reparte entre ellas —el sorteo del
--    relleno renormaliza sobre el grupo—, así que 60/25/10 significa 63,2 %,
--    26,3 % y 10,5 % de las cartas de relleno. Se dejan como están: mover una
--    solo cambia el reparto interno del relleno, nunca los ratios de premio.
--
--    ⚠️ Ya no hace falta que la tabla sume 100: desde esta migración la tirada
--    de premio va contra una escala de 100 FIJA (`Tcg::sortearPremio()`), así
--    que cada número de aquí es su porcentaje por sobre y punto, sin depender
--    de lo que sumen las demás filas ni de qué rarezas tenga la expansión.
--
-- 2) QUITARLE LA TIRADA LIMITADA A SHAWN FROSTE (id_cromo 4).
--    Es un SRF (rareza 6) y tenía `cupo_numerado = 10`, así que los sobres lo
--    entregaban con número de serie: «numerada a 10». No lo es, y de momento
--    no existe ninguna numerada SRF —la única numerada de verdad es RaptorXz
--    (id 515), rareza 7, cupo 1, `solo_cadena`—. Se le quita el cupo y se
--    borran los números ya emitidos, para que las copias que la gente tiene
--    dejen de mostrarse numeradas. Las COPIAS NO SE TOCAN: siguen en la
--    colección de quien las sacó, solo pierden el número.
--
--    Que no vuelva a pasar no depende de este UPDATE: el código ignora ahora
--    el cupo de cualquier carta que no sea de la rareza Numerada
--    (`Tcg::RAREZA_NUMERADA`), tanto en el bombo del sobre como en la emisión.
--    Esto solo limpia lo que ya estaba puesto.
--
-- Idempotente: se puede correr dos veces sin efecto añadido.
-- =============================================================================

START TRANSACTION;

-- --- 1) escala nueva ---------------------------------------------------------
UPDATE `rarezas` SET `probabilidad` = 1.0000 WHERE `id_rareza` = 4;   -- Épico       1/100
UPDATE `rarezas` SET `probabilidad` = 0.3333 WHERE `id_rareza` = 5;   -- Legendario  1/300
UPDATE `rarezas` SET `probabilidad` = 0.2000 WHERE `id_rareza` = 6;   -- SRF         1/500
UPDATE `rarezas` SET `probabilidad` = 0.1000 WHERE `id_rareza` = 7;   -- Numerada    1/1000

-- --- 2) Shawn Froste deja de ser numerado ------------------------------------
-- Los números emitidos primero: mientras exista la fila en `cadena_numeracion`,
-- la colección la pinta con su «10/10» aunque el cupo ya esté a NULL.
DELETE n FROM `cadena_numeracion` n
INNER JOIN `cromos` c ON c.id_cromo = n.id_cromo
WHERE c.nombre = 'Shawn Froste' AND c.id_rareza <> 7;

UPDATE `cromos` SET `cupo_numerado` = NULL
WHERE `nombre` = 'Shawn Froste' AND `id_rareza` <> 7;

-- Red de seguridad: CUALQUIER carta con cupo que no sea de la rareza Numerada
-- está mal configurada por definición. Hoy solo es Shawn, pero limpiarlas todas
-- cuesta lo mismo y evita tener que volver aquí con la siguiente.
DELETE n FROM `cadena_numeracion` n
INNER JOIN `cromos` c ON c.id_cromo = n.id_cromo
WHERE c.id_rareza <> 7;

UPDATE `cromos` SET `cupo_numerado` = NULL
WHERE `id_rareza` <> 7 AND `cupo_numerado` IS NOT NULL;

COMMIT;

-- --- comprobación ------------------------------------------------------------
-- Debe salir 0 filas.
SELECT c.id_cromo, c.nombre, c.id_rareza, c.cupo_numerado
FROM `cromos` c
WHERE c.id_rareza <> 7 AND c.cupo_numerado IS NOT NULL;

-- Y esto, la escala nueva con su «1 de cada N».
SELECT `id_rareza`, `nombre`, `probabilidad`,
       CASE WHEN `id_rareza` >= 4 AND `probabilidad` > 0
            THEN CONCAT('1 de cada ', ROUND(100 / `probabilidad`), ' sobres')
            ELSE 'relleno (varias por sobre)' END AS `ratio`
FROM `rarezas` ORDER BY `id_rareza`;
