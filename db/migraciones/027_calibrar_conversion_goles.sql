-- =====================================================================
-- 027 — La conversión de fuerza en goles pasa a ser calibrable
--
-- Nace de medir el §15.8: `duelo_k` ya NO decide el duelo (§15.10 lo dejó sin
-- lectores — se calcula, se guarda en probabilidad_victoria_creador y nadie
-- vuelve a mirarlo) y por tanto NO es la palanca de equilibrio que dice el
-- §15.8. La palanca real vive dentro de generarEventosPartido(): la
-- probabilidad de que un tramo del partido acabe en ocasión (`pOcasion`) y la
-- probabilidad de que una ocasión sea gol (`pGol`). Las dos eran constantes
-- escritas a fuego, sin fila en esta tabla y sin forma de tocarlas sin
-- desplegar código.
--
-- `partido_gol_base`/`partido_gol_sens` tienen un caso aparte: YA estaban
-- documentadas dentro del método como "el dial del equilibrio... y por eso
-- sale a configuracion", pero era falso — ningún llamante las pasaba nunca.
-- El dial existía en el comentario y no en la práctica. Esta migración lo
-- conecta de verdad (Tcg::opcionesSimulacion(), que sustituye a la antigua
-- opcionesPenalti()).
--
-- Los seis valores son EXACTAMENTE los que el motor tenía escritos a fuego,
-- así que aplicar esto no cambia ni un partido: solo lo hace calibrable.
--
--   partido_ocasion_base / _factor — probabilidad de que un tramo acabe en
--     ocasión = base + ratio_de_fuerza * factor, antes de acotar.
--   partido_ocasion_min / _max     — el acotado. El suelo es lo que garantiza
--     que el mazo más flojo pise el área; el techo, que el más fuerte no
--     convierta cada tramo en ocasión. Es el dial que de verdad aplana o abre
--     el duelo — medido: hoy el mejor mazo de rareza libre gana el 34,0 % de
--     los duelos reales contra el mejor SRF (§15.8c).
--   partido_gol_base / _sens       — probabilidad de que una ocasión sea gol
--     = base + peligro * sens, antes de acotar a [0.05, 0.45]. `sens` es el
--     dial específico de cuánto pesa la CALIDAD de la ocasión: medido sin
--     tocarlo, un 240 contra 100 pasaba del 69,1 % de la curva Elo al 91,0 %
--     (§15.10, branding/impacto-partido-analisis.md).
--
-- Aditiva y re-ejecutable: si alguien ya tocó un valor a mano, el
-- ON DUPLICATE KEY no se lo pisa, solo refresca la descripción.
-- =====================================================================

INSERT INTO configuracion (clave, valor, descripcion) VALUES
  ('partido_ocasion_base',   '0.10', 'Base de la probabilidad de ocasión por tramo, antes del ratio de fuerzas'),
  ('partido_ocasion_factor', '0.62', 'Cuánto pesa el ratio de fuerzas en la probabilidad de ocasión'),
  ('partido_ocasion_min',    '0.14', 'Suelo de probabilidad de ocasión: lo que pisa de área el mazo más flojo'),
  ('partido_ocasion_max',    '0.52', 'Techo de probabilidad de ocasión: lo que puede llegar a dominar el más fuerte'),
  ('partido_gol_base',       '0.06', 'Base de la probabilidad de que una ocasión sea gol, antes del peligro'),
  ('partido_gol_sens',       '0.30', 'Cuánto pesa el peligro de la ocasión en que sea gol: EL DIAL DEL EQUILIBRIO')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);
