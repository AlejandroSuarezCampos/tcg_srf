-- ============================================================================
-- 041 · EL RANGO S PASA A SER MARGEN DE 5 GOLES
--
-- Antes pedía marcar 5 Y no encajar ninguno. Las dos condiciones se peleaban
-- entre sí: donde más cuesta dejar la portería a cero es justo donde más
-- cuesta golear, así que en las dificultades altas el rango S era casi
-- inalcanzable. Ahora basta ganar por cinco: un 6-1 es una paliza igual que
-- un 5-0.
--
-- Idempotente: se puede pasar dos veces sin romper nada.
-- ============================================================================

INSERT INTO configuracion (clave, valor)
VALUES ('pve_rango_s_margen', '5')
ON DUPLICATE KEY UPDATE clave = clave;

-- La clave vieja ya no la lee nadie. Se borra para que no confunda a quien
-- abra el panel buscando por qué no cambia nada al tocarla.
DELETE FROM configuracion WHERE clave = 'pve_rango_s_goles';
