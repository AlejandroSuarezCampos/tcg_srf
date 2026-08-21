-- =====================================================================
-- 025 — Interruptor de pruebas: forzar que todos los partidos acaben 1-1
--
-- ⚠️ ESTO NO ES UNA REGLA DEL JUEGO. Es un interruptor para poder probar la
-- tanda de penaltis a mano: solo empata el 27,7 % de los duelos, así que sin
-- esto hay que jugar cuatro partidos enteros para ver una tanda.
--
-- Con `depuracion_forzar_empate = 1`:
--   · todo partido PvP acaba 1-1 y por tanto se va a la tanda;
--   · el presupuesto de marcador de los minijuegos pasa a 0, porque si no un
--     acierto rompería el empate y el partido no llegaría a los penaltis.
--     Las decisiones se siguen ofreciendo y siguen contando para la actuación.
--
-- ENTRA A 0. Se activa a mano cuando hace falta y se apaga igual:
--   UPDATE configuracion SET valor='1' WHERE clave='depuracion_forzar_empate';
--   UPDATE configuracion SET valor='0' WHERE clave='depuracion_forzar_empate';
--
-- Está en `configuracion` y no en el código a propósito: un interruptor de
-- pruebas escondido en un `if` es un interruptor que se queda puesto. Aquí se
-- ve en el panel, se apaga sin desplegar y aparece en cualquier repaso de la
-- tabla de calibrado.
--
-- ⚠️ ANTES DE SUBIR A PRODUCCIÓN, comprobar que vale 0. Si todos los duelos
-- salen empatados, esto es lo PRIMERO que hay que mirar.
--
-- Aditiva y re-ejecutable. `INSERT IGNORE` para no pisar el valor si ya está
-- puesto a mano (el mismo patrón que el resto de parámetros, ver §5.2).
-- =====================================================================

INSERT IGNORE INTO configuracion (clave, valor, descripcion)
VALUES ('depuracion_forzar_empate', '0',
        'PRUEBAS: fuerza 1-1 en todo partido PvP para llegar siempre a la tanda');

UPDATE configuracion
SET descripcion = 'PRUEBAS: fuerza 1-1 en todo partido PvP para llegar siempre a la tanda'
WHERE clave = 'depuracion_forzar_empate';
