-- ============================================================================
-- 049 · MÁS AIRE ANTES DEL PITIDO
--
-- `partido_espera_seg` son los segundos que el servidor aguanta a que aparezcan
-- los dos jugadores antes de arrancar el reloj igualmente. Estaba en 15.
--
-- ⚠️ POR QUÉ SUBE. La presentación de alineaciones duraba 7,4 s y no daba
--    tiempo a leer el nombre del rival, su formación, sus cuatro líneas y su
--    aumento antes de que la pantalla pasara a lo siguiente. Alargada a ~11 s
--    —más los hasta 2,5 s de precarga de imágenes— se juntaba peligrosamente
--    con los 15: quien tuviera la conexión regular podía ver cómo el partido
--    arrancaba con la intro todavía en pantalla, perdiendo minutos reales.
--
--    22 s deja margen para la intro completa en el peor caso y sigue siendo
--    corto para el propósito de la espera, que es no dejar a nadie colgado
--    indefinidamente esperando a un rival que no va a venir.
--
-- Idempotente: es un UPDATE con valor fijo sobre una clave concreta.
-- ============================================================================

UPDATE configuracion SET valor = '22' WHERE clave = 'partido_espera_seg';

-- Por si la fila no existiera todavía en alguna instalación.
INSERT INTO configuracion (clave, valor, descripcion)
SELECT 'partido_espera_seg', '22',
       'Segundos que se espera a que aparezcan los dos antes de arrancar igualmente. Quien no esté, se pierde el partido.'
 WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'partido_espera_seg');


-- ----------------------------------------------------------------------------
-- COMPROBACIÓN
-- ----------------------------------------------------------------------------
SELECT clave, valor, descripcion
  FROM configuracion
 WHERE clave = 'partido_espera_seg';
