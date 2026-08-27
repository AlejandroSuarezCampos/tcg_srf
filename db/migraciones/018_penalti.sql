-- =====================================================================
-- 018 — Frecuencia del penalti
--
-- El penalti no es un tipo de evento nuevo: el motor convierte una ocasión
-- ya resuelta en pena máxima, así que estos dos números NO tocan el
-- marcador ni el ganador (§15.1: la narración es una capa encima del
-- resultado). Solo eligen a cuál de las ocasiones ya decididas se le pone
-- el traje de penalti.
--
-- Son DOS probabilidades y no una porque el sesgo es lo que hace que un
-- penalti se sienta como un penalti: sin él salía marcado solo el 29 % de
-- las veces (en el fútbol real es el ~78 %) y la pena máxima se leía como
-- una moneda al aire que casi siempre falla.
--
-- OJO AL EQUILIBRIO ENTRE LOS DOS LADOS, que es la razón de que esto sea
-- calibrable y no una constante: un penalti MARCADO le da la decisión al
-- que defiende (Leer la Mente, que puede sacarlo) y un penalti FALLADO al
-- que ataca (El Momento de la Verdad, que puede meterlo). Cuanto más
-- realista es el porcentaje de acierto, menos aparece el insignia del
-- catálogo. Con los valores de aquí: ~0,43 penaltis por partido, el 70 %
-- marcados, y el atacante recibe su decisión 1 partido de cada 8.
--   · subir `prob_gol`   → más penaltis, más realistas, insignia más rara
--   · subir `prob_fallo` → más penaltis fallados, insignia más frecuente
--
-- Aditiva y re-ejecutable. INSERT IGNORE como las anteriores: cambiar el
-- valor aquí NO pisa una base ya migrada, hay que hacer el UPDATE a mano.
-- =====================================================================

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
  ('partido_penalti_prob_gol', '0.12',
   'Probabilidad de que una ocasion que YA acabo en gol se narre como penalti. No cambia el marcador, solo el relato.'),
  ('partido_penalti_prob_fallo', '0.018',
   'Probabilidad de que una ocasion que NO acabo en gol se narre como penalti fallado. Sube el insignia del catalogo a costa de realismo.');
