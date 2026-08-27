-- ============================================================================
-- 032 — Cadenas visibles solo para ciertos usuarios
--
-- Hasta aquí una cadena tenía un único interruptor, `activa`, y era binario:
-- la ve todo el mundo o no la ve nadie. Eso no cubre lo que hace falta —probar
-- una cadena antes de publicarla, dar una cadena a un grupo concreto, montar
-- un evento cerrado— y la única forma de aproximarlo era dejarla inactiva y
-- publicarla a ciegas.
--
-- Se resuelve con DOS piezas y no con una:
--
--   · `visibilidad` dice CÓMO se decide quién la ve. Una tabla de invitados a
--     secas no basta: "sin invitados" tendría que significar o bien "la ve
--     todo el mundo" o bien "no la ve nadie", y las dos lecturas hacen falta.
--     Con una columna explícita, una cadena restringida y todavía sin
--     invitados no la ve nadie, que es lo correcto y lo seguro.
--   · `cadena_usuarios` dice QUIÉN, cuando la respuesta no es "todos".
--
-- `activa` NO desaparece ni se mezcla con esto. Son preguntas distintas:
-- `activa` es "¿esta cadena está publicada?" y `visibilidad` es "¿para quién?".
-- Una cadena inactiva no la ve nadie por muchos invitados que tenga.
--
-- Aditiva y re-ejecutable. El valor por defecto es 'todos', así que las
-- cadenas que ya existen se comportan exactamente igual que antes.
-- ============================================================================

SET @existe := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadenas' AND COLUMN_NAME = 'visibilidad'
);
SET @sql := IF(@existe = 0,
  'ALTER TABLE cadenas ADD COLUMN visibilidad ENUM(''todos'',''elegidos'') NOT NULL DEFAULT ''todos''
     COMMENT ''todos = pública. elegidos = solo quien esté en cadena_usuarios.''
     AFTER activa',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- Los invitados. Clave primaria compuesta: invitar dos veces a la misma
-- persona es la misma invitación, no dos, y así lo dice el esquema en vez de
-- tener que comprobarlo en PHP antes de cada INSERT.
--
-- Los dos CASCADE son intencionados: si se borra la cadena, sus invitaciones
-- no significan nada; si se borra la cuenta, tampoco.
CREATE TABLE IF NOT EXISTS `cadena_usuarios` (
  `id_cadena`  INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `invitado`   DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cadena`, `id_usuario`),
  KEY `idx_cadena_usuarios_usuario` (`id_usuario`),
  CONSTRAINT `fk_cadena_usuarios_cadena` FOREIGN KEY (`id_cadena`)
    REFERENCES `cadenas` (`id_cadena`) ON DELETE CASCADE,
  CONSTRAINT `fk_cadena_usuarios_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
