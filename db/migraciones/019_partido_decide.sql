-- =====================================================================
-- 019 — Estado `en_juego`: el partido decide el resultado
--
-- Primer paso del cambio que pidió Alejandro: que el resultado NO venga
-- dado al empezar el partido, sino que se decida según se resuelven los
-- minijuegos, y que un empate se rompa en la tanda de penaltis. Todo el
-- razonamiento y las mediciones están en
-- branding/impacto-partido-analisis.md.
--
-- POR QUÉ HACE FALTA UN ESTADO NUEVO. Hoy el partido narrado se juega
-- sobre un duelo que ya está `resuelto` y ya está PAGADO: resolverDuelo()
-- decide el ganador, mueve las monedas o traspasa la carta y escribe
-- id_ganador, todo antes del primer minuto. Hasta `descontarGolRival()`
-- exige `estado = 'resuelto'` en su WHERE.
--
-- Con el partido decidiendo hace falta un hueco entre las dos cosas: el
-- encuentro se está jugando, las apuestas siguen retenidas (ya lo están
-- desde que se entra) y todavía no hay ganador. Eso es `en_juego`.
--
-- ADITIVA Y SIN EFECTO POR SÍ SOLA: añadir el valor al enum no cambia el
-- comportamiento de nada, porque ningún duelo lo usa todavía. Se puede
-- aplicar sin riesgo y revertir el código sin tocar la base.
--
-- Los 34 duelos ya resueltos se quedan como están.
-- =====================================================================

ALTER TABLE duelos
  MODIFY COLUMN estado
    ENUM('creado','aceptado','aumento_pendiente','listo_para_resolver',
         'en_juego','resuelto','cancelado')
    NOT NULL DEFAULT 'creado';
