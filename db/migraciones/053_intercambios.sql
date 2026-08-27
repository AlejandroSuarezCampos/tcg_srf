-- ============================================================================
-- 053 — INTERCAMBIOS entre jugadores (cartas por cartas, sin monedas)
--
-- La cuarta vía por la que una carta cambia de dueño, después del mercado
-- (monedas), la apuesta de duelo (se la lleva quien gana) y el botín de
-- cadena. Esta no cobra nada: se dan cartas y se reciben cartas. Para pagar
-- con monedas ya está el mercado, y meterlas aquí abriría un mercado paralelo
-- sin la horquilla de precio que `publicarAnuncio()` valida a propósito.
--
-- UNA SOLA TABLA PARA LAS DOS VÍAS. `id_destinatario` NULL = anuncio del
-- tablón, abierto a cualquiera; con valor = oferta dirigida a esa persona.
-- Es exactamente lo que ya hace `duelos.id_rival` para distinguir una sala
-- abierta de un reto, y ahorra duplicar los cinco estados, la caducidad y el
-- compromiso de las copias en dos tablas gemelas.
--
-- QUÉ SE DA Y QUÉ SE PIDE VAN EN TABLAS DISTINTAS, Y NO SON LO MISMO:
--
--   `intercambio_da`     COPIAS CONCRETAS (`id_coleccion`). Son del emisor,
--                        existen ya y quedan comprometidas desde que se
--                        propone: no se pueden vender, descartar, alinear ni
--                        apostar mientras la oferta viva.
--
--   `intercambio_busca`  CROMOS (`id_cromo`), no copias. Dos copias del mismo
--                        cromo son intercambiables —lo dice `listarColeccion-
--                        Vendible()` y lo asume todo el proyecto—, así que
--                        pedir una copia concreta de otro sería pedir de más:
--                        obligaría a que siguiera libre justo esa, cuando
--                        cualquiera de las suyas vale igual. Se resuelve a una
--                        copia libre de verdad en el momento de aceptar.
--
-- CONTRAOFERTAS: `id_origen` apunta al anuncio del tablón al que responden.
-- El anuncio dice qué busca Y admite que le propongan otra cosa (las dos vías
-- sobre el mismo anuncio), así que una contraoferta es una oferta dirigida
-- normal —mismas tablas, mismo estado, mismo compromiso de copias— que además
-- recuerda de qué anuncio cuelga para poder enseñarlas agrupadas y para que al
-- aceptar una caigan las hermanas.
--
-- CADUCAN A LAS 48 HORAS. No es cosmético: las copias ofrecidas están
-- comprometidas, así que una oferta olvidada congela cartas de alguien que ya
-- no se acuerda. `vence` se guarda en la fila en vez de calcularse al vuelo
-- para que el plazo de una oferta ya hecha no cambie si mañana se cambia la
-- constante.
--
-- Aditiva y re-ejecutable.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. La oferta
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intercambios` (
  `id_intercambio`  INT(11) NOT NULL AUTO_INCREMENT,
  `id_emisor`       INT(11) NOT NULL,
  `id_destinatario` INT(11) DEFAULT NULL
    COMMENT 'NULL = anuncio del tablón, abierto a cualquiera. Con valor = oferta dirigida.',
  `id_origen`       INT(11) DEFAULT NULL
    COMMENT 'Anuncio del tablón al que contraoferta esta oferta. NULL = no es contraoferta.',
  `estado` ENUM('abierto','aceptado','rechazado','retirado','caducado')
    NOT NULL DEFAULT 'abierto',
  `id_cerro`        INT(11) DEFAULT NULL
    COMMENT 'Quién aceptó. En un anuncio del tablón no se sabe de antemano.',
  `creado`          DATETIME NOT NULL DEFAULT current_timestamp(),
  `vence`           DATETIME NOT NULL,
  `resuelto`        DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_intercambio`),
  KEY `idx_intercambios_emisor` (`id_emisor`),
  KEY `idx_intercambios_destinatario` (`id_destinatario`),
  KEY `idx_intercambios_origen` (`id_origen`),
  /* El tablón y la caducidad preguntan las dos por estado; el tablón además
     ordena por fecha. Un índice compuesto sirve a las dos consultas. */
  KEY `idx_intercambios_estado` (`estado`, `creado`),
  CONSTRAINT `fk_int_emisor` FOREIGN KEY (`id_emisor`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_int_destinatario` FOREIGN KEY (`id_destinatario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_int_cerro` FOREIGN KEY (`id_cerro`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  /* Si cae el anuncio, sus contraofertas se quedan sin sentido. */
  CONSTRAINT `fk_int_origen` FOREIGN KEY (`id_origen`)
    REFERENCES `intercambios` (`id_intercambio`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------------
-- 2. Lo que pone el emisor: copias concretas
--
-- Sin UNIQUE sobre `id_coleccion`, al contrario que `duelo_apuesta_cartas`.
-- Allí una copia no puede volver a apostarse nunca porque las filas no se
-- borran al resolver; aquí eso significaría que una carta ofrecida una vez y
-- rechazada ya no se puede volver a ofrecer. Quién tiene comprometida una
-- copia AHORA lo decide el estado de la oferta, no una restricción de tabla
-- (ver `SQL_COPIAS_EN_INTERCAMBIO` en `consultas.php`).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intercambio_da` (
  `id_intercambio` INT(11) NOT NULL,
  `id_coleccion`   INT(11) NOT NULL,
  PRIMARY KEY (`id_intercambio`, `id_coleccion`),
  KEY `idx_intda_coleccion` (`id_coleccion`),
  CONSTRAINT `fk_intda_intercambio` FOREIGN KEY (`id_intercambio`)
    REFERENCES `intercambios` (`id_intercambio`) ON DELETE CASCADE,
  CONSTRAINT `fk_intda_coleccion` FOREIGN KEY (`id_coleccion`)
    REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------------------------
-- 3. Lo que pide el emisor: cromos, con cuántas copias de cada uno
--
-- `cantidad` existe porque el tope es de diez cartas por lado y sin ella no se
-- podría pedir "tres copias de este común": la clave primaria ya impide
-- repetir el cromo en filas distintas.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intercambio_busca` (
  `id_intercambio` INT(11) NOT NULL,
  `id_cromo`       INT(11) NOT NULL,
  `cantidad`       TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_intercambio`, `id_cromo`),
  KEY `idx_intbusca_cromo` (`id_cromo`),
  CONSTRAINT `fk_intbusca_intercambio` FOREIGN KEY (`id_intercambio`)
    REFERENCES `intercambios` (`id_intercambio`) ON DELETE CASCADE,
  CONSTRAINT `fk_intbusca_cromo` FOREIGN KEY (`id_cromo`)
    REFERENCES `cromos` (`id_cromo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
