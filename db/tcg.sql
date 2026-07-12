-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-07-2026 a las 23:09:22
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
(3, 'Viento', './assets/img/Afinidades/viento.png'),
(4, 'Bosque', './assets/img/Afinidades/bosque.png');

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
(2, 'Payo Agua', 'PRESIDENTE', 'Agua de Noticias', './assets/img/Cromos/Prueba Pruebez/PayoAguaLudao.png', 1, 1, 6, 2),
(3, 'Gentian', 'DC', 'Máximo Goleador de la Primera Temporada', './assets/img/Cromos/ALL STARS/Gentian.webp', 2, 2, 6, 1);

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
(1, 'Noticias de ultima hora'),
(2, 'Zanark Domain');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expansiones`
--

CREATE TABLE `expansiones` (
  `id_expansion` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `fecha_salida` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `expansiones`
--

INSERT INTO `expansiones` (`id_expansion`, `nombre`, `fecha_salida`) VALUES
(1, 'Prueba Pruebez\r\n', '2026-07-11 22:00:00'),
(2, 'ALL STARS', '2026-07-12 17:56:04');

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
  `monedas` int(11) NOT NULL DEFAULT 0,
  `dictador` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `foto` text DEFAULT './assets/img/perfil'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `afinidad`
--
ALTER TABLE `afinidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `coleccion`
--
ALTER TABLE `coleccion`
  MODIFY `id_coleccion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cromos`
--
ALTER TABLE `cromos`
  MODIFY `id_cromo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `id_equipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `expansiones`
--
ALTER TABLE `expansiones`
  MODIFY `id_expansion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

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
