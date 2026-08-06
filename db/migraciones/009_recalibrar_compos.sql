-- ============================================================================
-- 009 — Recalibración de los % de Compos tras el cambio a estadísticas
-- ponderadas por línea (Tcg::PESOS_LINEA).
--
-- Con el peso único por línea, Portería pesaba ~8,9% del total del equipo y
-- Medio ~37,4% — de ahí que Montaña necesitase un +31,5% en Nivel 3 para
-- rendir tanto como el +7,49% de Vínculo. Con las tres estadísticas
-- ponderadas, Portería pasa a pesar ~15,45% y Medio ~35,27%: el mismo +31,5%
-- de Montaña ahora vale casi el DOBLE de lo que valía Vínculo maximizado
-- (una inversión real, verificada por cálculo, no solo una sospecha).
--
-- Se reescala cada % por el mismo factor: (reparto viejo de su línea) / (reparto
-- nuevo de su línea), para que el impacto total de cada rasgo maximizado sobre
-- la fuerza del equipo sea IDÉNTICO al que tenía antes de este cambio. Los
-- rasgos de dos líneas (Justicia, Brecha) se reescalan por la suma de sus dos
-- líneas. Re-ejecutable: siempre fija los mismos valores absolutos.
-- ============================================================================

UPDATE rasgos SET pct_1 = 2.70,  pct_2 = 6.30,  pct_3 = 12.59 WHERE clave = 'fuego';
UPDATE rasgos SET pct_1 = 1.70,  pct_2 = 3.97,  pct_3 = 7.94  WHERE clave = 'bosque';
UPDATE rasgos SET pct_1 = 2.23,  pct_2 = 5.19,  pct_3 = 10.36 WHERE clave = 'viento';
UPDATE rasgos SET pct_1 = 3.88,  pct_2 = 9.06,  pct_3 = 18.13 WHERE clave = 'montana';
UPDATE rasgos SET pct_1 = 2.70,  pct_2 = 6.30,  pct_3 = 12.59 WHERE clave = 'contraataque';
UPDATE rasgos SET pct_1 = 1.70,  pct_2 = 3.97,  pct_3 = 7.94  WHERE clave = 'vinculo';
UPDATE rasgos SET pct_1 = 0.82,  pct_2 = 1.83,  pct_3 = 3.65  WHERE clave = 'justicia';
UPDATE rasgos SET pct_1 = 1.06,  pct_2 = 2.39,  pct_3 = 4.77  WHERE clave = 'brecha';
