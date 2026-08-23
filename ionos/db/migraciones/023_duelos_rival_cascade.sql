-- =====================================================================
-- 023 — `duelos.id_rival` pasa de SET NULL a CASCADE
--
-- Corrige una ASIMETRÍA, no añade una pérdida. `id_creador` ya era
-- CASCADE, así que al borrar un usuario los duelos que él CREÓ ya
-- desaparecían del historial del otro jugador, mientras que los duelos en
-- los que fue RIVAL sobrevivían con el rival en blanco. La mitad de un
-- historial compartido se borraba y la otra mitad se quedaba a medias.
--
-- Decisión de Alejandro, ya prevista antes de esta sesión: al borrar una
-- cuenta se va todo lo suyo. Hace falta además para la limpieza previa a
-- producción, donde se borran los usuarios de prueba y no interesa dejar
-- duelos huérfanos apuntando a nadie.
--
-- ⚠️ CONSECUENCIAS QUE HAY QUE TENER PRESENTES
--
-- 1. Borrar un usuario borra duelos del historial de OTRO jugador (los que
--    ese otro creó contra él). Es lo pretendido, pero es real.
-- 2. `CPU` (id 10) es el rival de TODOS los partidos de cadena — hoy 20.
--    Borrarlo se los llevaría. `CPU` no se borra nunca, y ya estaba dicho
--    en el CLAUDE.md; esto le añade un motivo más.
-- 3. Las misiones `duelos_jugados` y `duelos_ganados` cuentan filas de
--    `duelos`, así que el progreso NO reclamado de un jugador puede BAJAR
--    si se borra a su rival. El progreso ya reclamado se queda congelado
--    (ver migración 005), así que no se le quita nada a nadie.
--
-- `id_rival` sigue admitiendo NULL, que es su estado legítimo mientras una
-- sala está abierta y nadie ha entrado: CASCADE solo dice qué pasa cuando
-- el usuario al que apunta se borra, no obliga a que apunte a alguien.
--
-- Re-ejecutable, y con la única forma que funciona aquí: el `IF EXISTS` del
-- DROP. `ADD CONSTRAINT IF NOT EXISTS` NO existe para claves ajenas en
-- MariaDB 10.4 —da error de sintaxis, comprobado— así que no se usa: al ir
-- el DROP delante, el ADD a secas siempre encuentra el sitio libre.
-- =====================================================================

ALTER TABLE duelos DROP FOREIGN KEY IF EXISTS fk_duelos_rival;

ALTER TABLE duelos
  ADD CONSTRAINT fk_duelos_rival
  FOREIGN KEY (id_rival) REFERENCES usuarios (id_usuario)
  ON DELETE CASCADE;
