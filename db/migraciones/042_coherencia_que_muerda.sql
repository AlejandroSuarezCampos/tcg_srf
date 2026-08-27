-- ============================================================================
-- 042 · EL MALUS DE COHERENCIA VUELVE A EXISTIR
--
-- MEDIDO ANTES DE TOCAR NADA: el malus salía 0,000 en TODOS los duelos
-- guardados, y 0 de 200 onces al azar del catálogo lo activaban. No estaba
-- roto el código —la fórmula se aplica donde debe—: estaba calibrado de forma
-- que no llegaba a dispararse nunca.
--
-- Por qué no saltaba:
--   · `coherencia_umbral_libre = 2.5` está POR ENCIMA de la rareza media de un
--     mazo normal (1,9 medido), así que casi todos quedaban exentos de entrada.
--   · y con `rate = 3`, lo exigido solo superaba el índice de compos que trae
--     cualquier once (4, también medido) a partir de rareza media 3,83, o sea
--     un mazo de épicas para arriba. Y aun ahí el castigo era del 1,86 %.
--
-- Con umbral 2,0 y rate 4,0, medido sobre el mismo catálogo:
--   mazo                 rareza  compos  malus antes  malus ahora
--   solo comunes          1.00     4       0,00 %      0,00 %
--   común + poco común    1.36     4       0,00 %      0,00 %
--   mezclado              1.91     4       0,00 %      0,00 %
--   raro o mejor          3.55     4       0,00 %      2,91 %
--   épico o mejor         4.45     4       1,86 %      7,76 %
--
-- Que es lo que la mecánica decía hacer: un mazo caro sin compos que lo
-- sostengan paga; uno normal no paga nada; y uno caro CON compos buenas
-- (índice por encima de lo exigido) sigue sin pagar.
--
-- Los dos valores siguen en `configuracion` y se retocan desde el panel sin
-- desplegar. El tope (18 %) no se toca.
-- ============================================================================

UPDATE configuracion SET valor = '2.0' WHERE clave = 'coherencia_umbral_libre';
UPDATE configuracion SET valor = '4.0' WHERE clave = 'coherencia_malus_rate';

-- Por si la instalación es anterior a que existieran las filas.
INSERT INTO configuracion (clave, valor) VALUES
  ('coherencia_umbral_libre', '2.0'),
  ('coherencia_malus_rate',   '4.0'),
  ('coherencia_malus_tope',   '18')
ON DUPLICATE KEY UPDATE clave = clave;
