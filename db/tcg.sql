-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-07-2026 a las 16:05:41
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `tcg`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `afinidad`
--

CREATE TABLE `afinidad` (
  `id` int(11) NOT NULL,
  `nombre` text NOT NULL,
  `imagen` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `afinidad`
--

INSERT INTO `afinidad` (`id`, `nombre`, `imagen`) VALUES
(1, 'Montaña', './assets/img/Afinidades/montaña.png'),
(2, 'Fuego', './assets/img/Afinidades/fuego.png'),
(3, 'Viento', './assets/img/Afinidades/aire.png'),
(4, 'Bosque', './assets/img/Afinidades/bosque.png'),
(5, 'no-afi', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `coleccion`
--

CREATE TABLE `coleccion` (
  `id_coleccion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_cromo` int(11) NOT NULL,
  `obtenida` datetime NOT NULL DEFAULT current_timestamp(),
  `bloqueada` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cromos`
--

CREATE TABLE `cromos` (
  `id_cromo` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `posicion` enum('POR','DF','MC','DC','ENT','GER','ESCUDO','PRESIDENTE') NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `id_expansion` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `id_rareza` int(11) NOT NULL,
  `id_afinidad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cromos`
--

INSERT INTO `cromos` (`id_cromo`, `nombre`, `posicion`, `descripcion`, `imagen`, `id_expansion`, `id_equipo`, `id_rareza`, `id_afinidad`) VALUES
(3, 'Gentian', 'DC', 'Máximo Goleador de la Primera Temporada', './assets/img/Cromos/ALL STARS/gentian.png', 3, 2, 6, 1),
(4, 'Shawn Froste', 'DC', 'Capitán del Alpino', './assets/img/Cromos/ALL STARS/GonzaloEse.png', 3, 3, 6, 3),
(5, 'Hector Helio', 'POR', 'Portero menos goleado', './assets/img/Cromos/ALL STARS/hector_helio.png', 3, 3, 6, 1),
(6, 'Maddie Moonlight', 'DC', 'Ganadora de Copa', './assets/img/Cromos/ALL STARS/maddie_moonlight.png', 3, 5, 6, 4),
(7, 'Lykan Moss', 'POR', 'Musgo', './assets/img/Cromos/ALL STARS/lykan_moss.png', 3, 4, 6, 4),
(8, 'Tom Skipper', 'DC', 'Capitán del Instituto Zeus', './assets/img/Cromos/Apuesta Segura/tom_skipper.png', 3, 13, 5, 3),
(9, 'Taiga west', 'POR', 'Portera Titular del Instituto Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/taiga_west.webp', 3, 13, 4, 1),
(10, 'Aiden Froste', 'DC', 'Delantero del Instituto Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/aiden_froste.webp', 3, 13, 4, 2),
(11, 'Tom Skipper', 'DC', 'Capitán del Instituto Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/tom_skipper.webp', 3, 13, 4, 3),
(12, 'Njord', 'DC', 'Delantero del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/njord.webp', 3, 13, 3, 3),
(13, 'Mystrene Callous', 'MC', 'Mediocentro del zeus', './assets/img/Cromos/Base Set/Instituto Zeus/callous.webp', 3, 13, 4, 1),
(14, 'Nikas Himmelstein', 'DC', 'Libero del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/nikas.webp', 3, 13, 2, 2),
(15, 'Folie Vora', 'MC', 'Mediocentro del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/folie_vora.webp', 3, 13, 2, 2),
(16, 'Plumian Whitlock', 'MC', 'Mediocentro del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/Plumian.webp', 3, 13, 1, 3),
(17, 'Keira', 'DF', 'Defensa del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/keira.webp', 3, 13, 2, 4),
(18, 'Jack Skipper', 'DF', 'Defensa central del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/jack_skipper.webp', 3, 13, 3, 2),
(19, 'Kazach', 'DF', 'Defensa del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/kazach.webp', 3, 13, 1, 1),
(20, 'Kia Tanner', 'MC', 'Mediocentro suplente del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/ponko.webp', 3, 13, 3, 4),
(21, 'Terri Ann Thrope', 'DC', 'Delantera suplente del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/terri.webp', 3, 13, 1, 4),
(22, 'Eidah', 'DF', 'Defensa suplente del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/eidah.webp', 3, 13, 1, 4),
(23, 'Boris', 'DF', 'Defensa suplente del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/boris.webp', 3, 13, 1, 4),
(24, 'Vozinha', 'POR', 'Portero suplente del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/vozinha.webp', 3, 13, 2, 1),
(26, 'Escudo Instituto Zeus', 'ESCUDO', 'Escudo del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/escudo_zeus.jpeg', 3, 13, 5, 5),
(27, 'Lanny Tern', 'GER', 'Gerente del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/lanny_tern.png', 3, 13, 5, 3),
(28, 'Xokas', 'ENT', 'Entrenador y Streamer del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/xokas.png', 3, 13, 5, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

CREATE TABLE `equipos` (
  `id_equipo` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `equipos`
--

INSERT INTO `equipos` (`id_equipo`, `nombre`) VALUES
(6, 'Academia Plenilunio'),
(3, 'Alpino'),
(15, 'Big Bang'),
(16, 'Cala Pirata'),
(9, 'Colegio Poderosa Fe'),
(8, 'Criaturas de la noche'),
(18, 'Épsilon'),
(10, 'Gar'),
(5, 'Inazuma Kids CF'),
(20, 'Instituto Farm'),
(22, 'Instituto Kikrwood'),
(21, 'Instituto Otaku'),
(13, 'Instituto Zeus'),
(14, 'Los Arions'),
(7, 'Mary Times'),
(12, 'Monte Olimpo'),
(19, 'Raimon'),
(17, 'Royal Academy'),
(11, 'Servicio Secreto'),
(4, 'Triple C'),
(2, 'Zanark Domain');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expansiones`
--

CREATE TABLE `expansiones` (
  `id_expansion` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `fecha_salida` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `activo` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `expansiones`
--

INSERT INTO `expansiones` (`id_expansion`, `nombre`, `fecha_salida`, `activo`) VALUES
(1, 'Prueba Pruebez', '2026-07-12 22:00:00', 0),
(3, 'Base Set - T2', '2026-07-13 10:32:30', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mercado`
--

CREATE TABLE `mercado` (
  `id_anuncio` int(11) NOT NULL,
  `id_coleccion` int(11) NOT NULL,
  `precio` int(11) NOT NULL,
  `fecha_publicacion` datetime NOT NULL DEFAULT current_timestamp(),
  `activa` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rarezas`
--

CREATE TABLE `rarezas` (
  `id_rareza` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `probabilidad` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rarezas`
--

INSERT INTO `rarezas` (`id_rareza`, `nombre`, `probabilidad`) VALUES
(1, 'Común', 60.00),
(2, 'Poco común', 25.00),
(3, 'Raro', 10.00),
(4, 'Épico', 3.50),
(5, 'Legendario', 1.00),
(6, 'SRF', 0.50);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `monedas` int(11) NOT NULL DEFAULT 500,
  `dictador` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `foto` text NOT NULL DEFAULT './assets/img/perfil/apple-icon-120x120.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `password_hash`, `monedas`, `dictador`, `fecha_registro`, `foto`) VALUES
(1, 'FranDictador', '$2y$10$JgLxZ.OiTE4r8jdrX9zdl.PeSwoiXKBczgSOtXvAhvxd3Sl/RNVnG', 1500, 1, '2026-07-21 09:46:33', './assets/img/perfil/apple-icon-120x120.png'),
(2, 'LuluLulez', '$2y$10$.D1gTDVfqcghmRlCRdwGTu1Yq12sOW9M9KtQ/1I53I6KeRYOBzm.C', 500, 0, '2026-07-21 15:32:54', './assets/img/perfil/apple-icon-120x120.png');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `afinidad`
--
ALTER TABLE `afinidad`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `coleccion`
--
ALTER TABLE `coleccion`
  ADD PRIMARY KEY (`id_coleccion`),
  ADD KEY `idx_coleccion_usuario` (`id_usuario`),
  ADD KEY `idx_coleccion_cromo` (`id_cromo`);

--
-- Indices de la tabla `cromos`
--
ALTER TABLE `cromos`
  ADD PRIMARY KEY (`id_cromo`),
  ADD KEY `idx_cromos_expansion` (`id_expansion`),
  ADD KEY `idx_cromos_equipo` (`id_equipo`),
  ADD KEY `idx_cromos_rareza` (`id_rareza`),
  ADD KEY `idx_cromos_afinidad` (`id_afinidad`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`id_equipo`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `expansiones`
--
ALTER TABLE `expansiones`
  ADD PRIMARY KEY (`id_expansion`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `mercado`
--
ALTER TABLE `mercado`
  ADD PRIMARY KEY (`id_anuncio`),
  ADD UNIQUE KEY `id_coleccion` (`id_coleccion`),
  ADD KEY `idx_mercado_precio` (`precio`),
  ADD KEY `idx_mercado_activa` (`activa`);

--
-- Indices de la tabla `rarezas`
--
ALTER TABLE `rarezas`
  ADD PRIMARY KEY (`id_rareza`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `nombre_2` (`nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `afinidad`
--
ALTER TABLE `afinidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `coleccion`
--
ALTER TABLE `coleccion`
  MODIFY `id_coleccion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cromos`
--
ALTER TABLE `cromos`
  MODIFY `id_cromo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `id_equipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `expansiones`
--
ALTER TABLE `expansiones`
  MODIFY `id_expansion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `mercado`
--
ALTER TABLE `mercado`
  MODIFY `id_anuncio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rarezas`
--
ALTER TABLE `rarezas`
  MODIFY `id_rareza` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `coleccion`
--
ALTER TABLE `coleccion`
  ADD CONSTRAINT `fk_coleccion_cromo` FOREIGN KEY (`id_cromo`) REFERENCES `cromos` (`id_cromo`),
  ADD CONSTRAINT `fk_coleccion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cromos`
--
ALTER TABLE `cromos`
  ADD CONSTRAINT `fk_cromo_afinidad` FOREIGN KEY (`id_afinidad`) REFERENCES `afinidad` (`id`),
  ADD CONSTRAINT `fk_cromo_equipo` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`),
  ADD CONSTRAINT `fk_cromo_expansion` FOREIGN KEY (`id_expansion`) REFERENCES `expansiones` (`id_expansion`),
  ADD CONSTRAINT `fk_cromo_rareza` FOREIGN KEY (`id_rareza`) REFERENCES `rarezas` (`id_rareza`);

--
-- Filtros para la tabla `mercado`
--
ALTER TABLE `mercado`
  ADD CONSTRAINT `fk_mercado_coleccion` FOREIGN KEY (`id_coleccion`) REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
