-- Plantillas 3D de cajas y sobres (prompt-claude-code-sobres-3d.md, Fase 5).
-- Una fila por elemento con plantilla subida: la caja grande de una expansión
-- (tipo='caja_expansion', id_referencia=id_expansion), la caja pequeña de un
-- tipo de sobre (tipo='caja_sobre', id_referencia=id_sobre) y el propio sobre
-- (tipo='sobre', id_referencia=id_sobre). Sin plantilla subida, no hay fila:
-- el render cae al degradado por defecto (ver Tcg::rutasPlantilla()).
CREATE TABLE IF NOT EXISTS `plantillas_3d` (
  `id_plantilla` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('caja_expansion','caja_sobre','sobre') NOT NULL,
  `id_referencia` int(11) NOT NULL,
  `ruta_original` varchar(255) NOT NULL,
  `rutas_recortadas` text NOT NULL COMMENT 'JSON: {"front":"...","top":"...","side":"..."} o {"frente":"...","reverso":"..."}',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_plantilla`),
  UNIQUE KEY `tipo_referencia` (`tipo`, `id_referencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
