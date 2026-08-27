-- ============================================================================
-- 028 — LÍMITE DE INTENTOS DE LOGIN (auditoría de seguridad)
--
-- login.php no tenía ningún freno: se podían probar contraseñas sin límite,
-- ni siquiera un pequeño retraso. Esta tabla guarda, por IP + nombre de
-- usuario probado, cuántos intentos fallidos seguidos lleva y desde cuándo
-- está bloqueado. No se guarda nada de logins correctos.
--
-- Aditiva y re-ejecutable, como el resto.
-- ============================================================================

CREATE TABLE IF NOT EXISTS login_intentos (
  ip               VARCHAR(45)  NOT NULL,
  nombre           VARCHAR(50)  NOT NULL,
  intentos         INT          NOT NULL DEFAULT 0,
  ultimo_intento   DATETIME     NOT NULL,
  bloqueado_hasta  DATETIME     NULL,
  PRIMARY KEY (ip, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
