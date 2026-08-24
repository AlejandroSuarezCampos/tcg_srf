-- ============================================================================
-- 048 · DESHACER LOS «NODOS FINALES» QUE NADIE MARCÓ
--
-- Reparación de datos del bug de `assets/ajax/cadena_admin.php`: el endpoint
-- `actualizar_nodo` leía la casilla con `isset($_POST['es_final'])`, y como el
-- editor manda SIEMPRE la clave (vacía cuando está desmarcada), `isset('')` es
-- `true` y cada guardado ponía `es_final = 1`. El código ya está arreglado;
-- esto limpia lo que dejó escrito.
--
-- ⚠️ NO ERA SOLO LA CASILLA. `cadenaCompletada()` pregunta por
--    `es_final = 1`, así que una cadena se daba por completada en cuanto se
--    reclamaba el cofre de CUALQUIER nodo marcado — es decir, del primero. Y
--    `cofreFinalCadena()` cuelga de ahí la recompensa de formación. Con medio
--    mapa marcado como final, las dos cosas respondían al nodo equivocado.
--
-- ⚠️ SOLO SE TOCAN LAS CADENAS CON MÁS DE UN FINAL, y se resetean enteras.
--    Una cadena tiene un final o no tiene ninguno; dos o más es imposible por
--    diseño, así que esas son exactamente las contaminadas y no hay forma de
--    adivinar cuál de todos era el bueno. Las que tienen uno solo se quedan
--    como están: o nunca las tocó el bug, o ya se corrigieron a mano, y en los
--    dos casos el dato es de fiar.
--
--    Después de pasar esto hay que volver a marcar a mano el cofre final de
--    las cadenas afectadas. Sin final marcado, la cadena no se puede completar
--    y no entrega su formación — la consulta del pie dice cuáles se han
--    quedado así.
--
-- Idempotente: en cuanto no queda ninguna cadena con dos finales, no hace nada.
-- ============================================================================

UPDATE cadena_nodos n
  JOIN (
    SELECT id_cadena
      FROM cadena_nodos
     WHERE es_final = 1
     GROUP BY id_cadena
    HAVING COUNT(*) > 1
  ) AS contaminadas ON contaminadas.id_cadena = n.id_cadena
   SET n.es_final = 0
 WHERE n.es_final = 1;


-- ----------------------------------------------------------------------------
-- QUÉ REVISAR A MANO
--
-- Ninguna cadena puede quedar con más de un final. Las que se queden con
-- CERO y tengan cofres son las que hay que volver a marcar en el editor.
-- ----------------------------------------------------------------------------
SELECT c.id_cadena,
       c.nombre,
       SUM(n.es_final = 1)              AS finales,
       SUM(n.tipo = 'cofre')            AS cofres,
       CASE
         WHEN SUM(n.es_final = 1) > 1                             THEN '⚠ sigue contaminada'
         WHEN SUM(n.es_final = 1) = 0 AND SUM(n.tipo = 'cofre') > 0
              THEN '→ marca su cofre final en el editor'
         WHEN SUM(n.es_final = 1) = 1                             THEN 'ok'
         ELSE 'sin cofres, nada que marcar'
       END AS estado
  FROM cadenas c
  JOIN cadena_nodos n ON n.id_cadena = c.id_cadena
 GROUP BY c.id_cadena, c.nombre
 ORDER BY finales DESC, c.id_cadena;
