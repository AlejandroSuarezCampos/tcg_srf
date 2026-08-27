-- ============================================================================
-- 026 — EL PREMIO DEL CAMINO PERFECTO (§15.12, decisión 4 de Alejandro)
--
-- El mecanismo ya está construido: caminoPerfectoHastaCofre() mira si existe un
-- camino de raíz al cofre con TODOS los partidos en rango S y en dificultad
-- `extremo`, y reclamarCofre() pasa entonces rango 'S' a otorgarLootNodo(). Lo
-- que faltaba era el premio, que es puro dato: una fila de `cadena_loot` con
-- `rango_minimo = 'S'`, como cualquier otro botín.
--
-- ⚠️ OJO, ESTO CONTRADICE UN COMENTARIO DE LA `011` Y ES A PROPÓSITO. Allí se
-- escribió que un `rango_minimo` colgado de un cofre lo dejaría "inalcanzable
-- para siempre", porque un cofre se reclama y no se puntúa. Era cierto entonces:
-- reclamarCofre() pasaba `null` siempre. Desde el §15.12 un cofre SÍ puede traer
-- rango, y es exactamente el del camino perfecto.
--
-- ⚠️ Y AVISO QUE HAY QUE LEER ANTES DE ESPERAR QUE ESTO CAIGA: con
-- `pve_rango_s_goles = 5` el rango S en Extremo sale el 0,0 % de las veces —
-- medido sobre 300 partidos de cadena jugados enteros—, así que el premio
-- EXISTE PERO NO SE PUEDE CONSEGUIR todavía. Es una decisión consciente de
-- Alejandro: los umbrales se calibran cuando estén las cartas definitivas, no
-- antes. Ver §15.12.
--
-- Aditiva y re-ejecutable, como el resto.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. La carta numerada.
--
-- ⚠️ CARTA PROVISIONAL. Alejandro pidió "un cromo limitado numerado" y dejó la
-- elección concreta para más adelante: esto es un MARCADOR DE POSICIÓN. Para
-- cambiarla basta con mover el id de cromo aquí y en las dos filas de abajo —
-- y quitarle el cupo a la anterior si ya no debe ser limitada.
--
-- `cupo_numerado` solo lo lee el botín de cadenas (otorgarCromoLimitado) y las
-- pantallas que enseñan el número de serie: marcar una carta como numerada NO
-- la toca en sobres ni en el mercado.
--
-- No se reutiliza `Bala Gasgula` (id 43), que es la limitada del nodo 1, porque
-- sus 5 copias YA están emitidas: colgarla aquí no entregaría nada.
-- ---------------------------------------------------------------------------
UPDATE cromos SET cupo_numerado = 10
WHERE id_cromo = 4 AND cupo_numerado IS NULL;   -- Shawn Froste (SRF, DC, Viento)

-- ---------------------------------------------------------------------------
-- 2. El premio, en el COFRE FINAL de cada cadena.
--
-- Va en los `es_final` y no en los cofres intermedios porque el camino perfecto
-- es el logro de recorrer la cadena entera en S: premiarlo en el primer alijo,
-- que está a dos partidos de la raíz, lo abarataría.
--
-- `probabilidad = 100`: no es una tirada, es la recompensa del camino. Si el
-- cupo se agota, otorgarCromoLimitado() devuelve null y sencillamente no cae —
-- no es un error, es lo que significa "limitada".
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO cadena_loot
  (id_loot, id_nodo, tipo, id_cromo, monedas, probabilidad, rango_minimo) VALUES
  (12, 10, 'cromo_limitado', 4, NULL, 100, 'S'),   -- Ruta de ascenso  → Cofre de ruta
  (13, 18, 'cromo_limitado', 4, NULL, 100, 'S');   -- Descenso de Frontier → Cofre final
