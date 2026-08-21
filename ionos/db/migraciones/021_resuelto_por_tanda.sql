-- =====================================================================
-- 021 — Marca de que un duelo se decidió en la tanda de penaltis
--
-- Con el partido decidiendo el resultado, los EMPATES existen por primera
-- vez: el §1.3 los hacía imposibles cuando el ganador venía pre-sorteado.
-- Un empate se rompe en la tanda, y hay que poder distinguir un duelo
-- ganado en el campo de uno ganado en los once metros — para el relato,
-- para el veredicto y para no mentirle al jugador en la pantalla de
-- resultado.
--
-- Aditiva y re-ejecutable. Los duelos ya resueltos quedan en 0, que es
-- correcto: ninguno se decidió en tanda porque la tanda no existía.
-- =====================================================================

ALTER TABLE duelos
  ADD COLUMN IF NOT EXISTS resuelto_por_tanda TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'El empate se rompio en la tanda de penaltis';
