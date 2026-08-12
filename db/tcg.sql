-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-08-2026 a las 17:34:06
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
-- Estructura de tabla para la tabla `cadenas`
--

CREATE TABLE `cadenas` (
  `id_cadena` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `anfitrion` varchar(80) DEFAULT NULL,
  `orden` smallint(6) NOT NULL DEFAULT 0,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `formacion_recompensa` varchar(8) DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cadenas`
--

INSERT INTO `cadenas` (`id_cadena`, `nombre`, `descripcion`, `anfitrion`, `orden`, `activa`, `formacion_recompensa`, `fecha_fin`) VALUES
(5, 'Segunda división', 'Los nueve clásicos de la categoría de plata, uno detrás de otro.', NULL, 0, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_aristas`
--

CREATE TABLE `cadena_aristas` (
  `id_origen` int(11) NOT NULL,
  `id_destino` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cadena_aristas`
--

INSERT INTO `cadena_aristas` (`id_origen`, `id_destino`) VALUES
(25, 26),
(26, 27),
(27, 28),
(28, 29),
(29, 30),
(30, 31),
(31, 32),
(32, 33),
(33, 34);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_cofres`
--

CREATE TABLE `cadena_cofres` (
  `id_usuario` int(11) NOT NULL,
  `id_nodo` int(11) NOT NULL,
  `reclamado` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_drops`
--

CREATE TABLE `cadena_drops` (
  `id_drop` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_duelo` int(11) DEFAULT NULL,
  `id_nodo` int(11) NOT NULL,
  `tipo` enum('monedas','cromo','cromo_limitado','formacion') NOT NULL,
  `id_cromo` int(11) DEFAULT NULL,
  `numero_serie` int(11) UNSIGNED DEFAULT NULL,
  `monedas` int(11) UNSIGNED DEFAULT NULL,
  `formacion` varchar(8) DEFAULT NULL,
  `creado` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_loot`
--

CREATE TABLE `cadena_loot` (
  `id_loot` int(11) NOT NULL,
  `id_nodo` int(11) NOT NULL,
  `tipo` enum('monedas','cromo','cromo_limitado') NOT NULL,
  `id_cromo` int(11) DEFAULT NULL,
  `monedas` int(11) UNSIGNED DEFAULT NULL,
  `probabilidad` decimal(5,2) NOT NULL DEFAULT 100.00,
  `rango_minimo` char(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cadena_loot`
--

INSERT INTO `cadena_loot` (`id_loot`, `id_nodo`, `tipo`, `id_cromo`, `monedas`, `probabilidad`, `rango_minimo`) VALUES
(19, 34, 'monedas', NULL, 500, 100.00, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_nodos`
--

CREATE TABLE `cadena_nodos` (
  `id_nodo` int(11) NOT NULL,
  `id_cadena` int(11) NOT NULL,
  `id_rival` int(11) DEFAULT NULL,
  `tipo` enum('partido','cofre') NOT NULL DEFAULT 'partido',
  `nombre` varchar(80) DEFAULT NULL,
  `columna` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `fila` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `es_final` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cadena_nodos`
--

INSERT INTO `cadena_nodos` (`id_nodo`, `id_cadena`, `id_rival`, `tipo`, `nombre`, `columna`, `fila`, `es_final`) VALUES
(25, 5, 7, 'partido', NULL, 0, 0, 0),
(26, 5, 8, 'partido', NULL, 1, 0, 0),
(27, 5, 9, 'partido', NULL, 2, 0, 0),
(28, 5, 10, 'partido', NULL, 3, 0, 0),
(29, 5, 11, 'partido', NULL, 3, 1, 0),
(30, 5, 12, 'partido', NULL, 4, 1, 0),
(31, 5, 13, 'partido', NULL, 5, 1, 0),
(32, 5, 14, 'partido', NULL, 7, 1, 0),
(33, 5, 15, 'partido', NULL, 7, 0, 0),
(34, 5, NULL, 'cofre', NULL, 6, 0, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_numeracion`
--

CREATE TABLE `cadena_numeracion` (
  `id_numeracion` int(11) NOT NULL,
  `id_cromo` int(11) NOT NULL,
  `numero_serie` int(11) UNSIGNED NOT NULL,
  `id_coleccion` int(11) NOT NULL,
  `otorgado` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_progreso`
--

CREATE TABLE `cadena_progreso` (
  `id_progreso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_nodo` int(11) NOT NULL,
  `dificultad` enum('facil','medio','dificil','muy_dificil','extremo') NOT NULL,
  `veces` int(11) NOT NULL DEFAULT 0,
  `victorias` int(11) NOT NULL DEFAULT 0,
  `mejor_rango` char(1) DEFAULT NULL,
  `primera_victoria` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_requisitos`
--

CREATE TABLE `cadena_requisitos` (
  `id_requisito` int(11) NOT NULL,
  `id_cadena` int(11) NOT NULL,
  `tipo` enum('cadena','cromo') NOT NULL,
  `valor` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_rivales`
--

CREATE TABLE `cadena_rivales` (
  `id_rival` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `escudo` varchar(255) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cadena_rivales`
--

INSERT INTO `cadena_rivales` (`id_rival`, `nombre`, `escudo`, `descripcion`, `activo`) VALUES
(1, 'Escuadra Fantasma', NULL, 'Rival de pruebas del motor PvE.', 0),
(2, 'Brigada Cobalto', NULL, 'Rival de pruebas: bloque medio poblado.', 0),
(3, 'Guardia Carmesí', NULL, 'Rival de pruebas: sale a por el partido.', 0),
(4, 'Instituto Zeus', NULL, 'Línea de cinco atrás y disciplina de bloque. No regala espacios.', 1),
(5, 'Academia Plenilunio', NULL, 'Mediocampo con siete nombres propios. Juegan con el balón, no sin él.', 1),
(6, 'Selección Frontier', NULL, 'Ojeo across the liga: cinco fichas de una sola carta en el mismo campo.', 1),
(7, 'Big Bang', NULL, NULL, 1),
(8, 'Instituto Farm', NULL, NULL, 1),
(9, 'Cala Pirata', NULL, NULL, 1),
(10, 'Épsilon', NULL, NULL, 1),
(11, 'Royal Academy', NULL, NULL, 1),
(12, 'Instituto Kirkwood', NULL, NULL, 1),
(13, 'Instituto Occult', NULL, NULL, 1),
(14, 'Triple C', NULL, NULL, 1),
(15, 'Instituto Otaku', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_rival_cartas`
--

CREATE TABLE `cadena_rival_cartas` (
  `id_estilo` int(11) NOT NULL,
  `hueco` tinyint(3) UNSIGNED NOT NULL,
  `id_cromo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cadena_rival_cartas`
--

INSERT INTO `cadena_rival_cartas` (`id_estilo`, `hueco`, `id_cromo`) VALUES
(1, 1, 3),
(1, 5, 3),
(1, 9, 3),
(2, 1, 3),
(2, 5, 3),
(3, 1, 3),
(3, 5, 3),
(3, 9, 3),
(4, 3, 3),
(4, 7, 3),
(5, 1, 3),
(5, 5, 3),
(5, 9, 3),
(6, 3, 3),
(6, 7, 3),
(7, 1, 3),
(7, 5, 3),
(7, 10, 3),
(8, 4, 3),
(8, 8, 3),
(9, 2, 3),
(9, 6, 3),
(9, 10, 3),
(10, 4, 3),
(10, 8, 3),
(11, 2, 3),
(11, 6, 3),
(11, 8, 3),
(12, 3, 3),
(12, 7, 3),
(12, 9, 3),
(1, 2, 4),
(1, 6, 4),
(1, 10, 4),
(2, 2, 4),
(2, 6, 4),
(3, 2, 4),
(3, 6, 4),
(3, 10, 4),
(4, 4, 4),
(4, 8, 4),
(5, 2, 4),
(5, 6, 4),
(5, 10, 4),
(6, 4, 4),
(6, 8, 4),
(7, 2, 4),
(7, 6, 4),
(8, 1, 4),
(8, 5, 4),
(8, 9, 4),
(9, 3, 4),
(9, 7, 4),
(10, 1, 4),
(10, 5, 4),
(10, 9, 4),
(11, 3, 4),
(11, 7, 4),
(11, 9, 4),
(12, 4, 4),
(12, 8, 4),
(12, 10, 4),
(1, 0, 5),
(3, 0, 5),
(5, 0, 5),
(7, 0, 5),
(9, 0, 5),
(11, 0, 5),
(1, 3, 6),
(1, 7, 6),
(2, 3, 6),
(2, 7, 6),
(2, 8, 6),
(3, 3, 6),
(3, 7, 6),
(4, 1, 6),
(4, 5, 6),
(4, 9, 6),
(5, 3, 6),
(5, 7, 6),
(6, 1, 6),
(6, 5, 6),
(6, 9, 6),
(7, 3, 6),
(7, 7, 6),
(8, 2, 6),
(8, 6, 6),
(8, 10, 6),
(9, 4, 6),
(9, 8, 6),
(10, 2, 6),
(10, 6, 6),
(10, 10, 6),
(11, 4, 6),
(11, 10, 6),
(12, 1, 6),
(12, 5, 6),
(2, 0, 7),
(4, 0, 7),
(6, 0, 7),
(8, 0, 7),
(10, 0, 7),
(12, 0, 7),
(20, 0, 7),
(1, 4, 8),
(1, 8, 8),
(2, 4, 8),
(2, 9, 8),
(2, 10, 8),
(3, 4, 8),
(3, 8, 8),
(4, 2, 8),
(4, 6, 8),
(4, 10, 8),
(5, 4, 8),
(5, 8, 8),
(6, 2, 8),
(6, 6, 8),
(6, 10, 8),
(7, 4, 8),
(7, 8, 8),
(7, 9, 8),
(8, 3, 8),
(8, 7, 8),
(9, 1, 8),
(9, 5, 8),
(9, 9, 8),
(10, 3, 8),
(10, 7, 8),
(11, 1, 8),
(11, 5, 8),
(12, 2, 8),
(12, 6, 8),
(14, 0, 141),
(14, 1, 142),
(14, 3, 143),
(14, 2, 145),
(14, 5, 147),
(14, 6, 148),
(14, 9, 149),
(14, 7, 154),
(14, 4, 155),
(14, 10, 159),
(14, 8, 160),
(13, 0, 207),
(13, 1, 208),
(13, 3, 209),
(13, 7, 210),
(13, 6, 211),
(13, 10, 213),
(13, 4, 214),
(13, 9, 215),
(13, 5, 217),
(13, 8, 218),
(13, 2, 220),
(20, 4, 230),
(20, 10, 231),
(20, 9, 232),
(20, 1, 236),
(20, 2, 237),
(20, 3, 238),
(20, 7, 239),
(20, 8, 241),
(20, 5, 242),
(20, 6, 243),
(17, 0, 366),
(17, 1, 369),
(17, 2, 370),
(17, 3, 373),
(17, 5, 374),
(17, 4, 376),
(17, 7, 378),
(17, 6, 379),
(17, 9, 381),
(17, 10, 384),
(17, 8, 385),
(21, 0, 392),
(21, 1, 393),
(21, 2, 395),
(21, 3, 396),
(21, 6, 397),
(21, 5, 398),
(21, 4, 399),
(21, 10, 400),
(21, 8, 401),
(21, 7, 404),
(21, 9, 407),
(15, 0, 411),
(15, 5, 412),
(15, 9, 415),
(15, 1, 416),
(15, 8, 417),
(15, 2, 419),
(15, 4, 420),
(15, 6, 421),
(15, 10, 422),
(15, 3, 424),
(15, 7, 430),
(19, 2, 434),
(19, 8, 435),
(19, 0, 437),
(19, 1, 438),
(19, 3, 440),
(19, 5, 442),
(19, 4, 443),
(19, 9, 444),
(19, 10, 445),
(19, 6, 448),
(19, 7, 449),
(18, 1, 457),
(18, 2, 458),
(18, 6, 459),
(18, 9, 460),
(18, 8, 461),
(18, 0, 462),
(18, 3, 463),
(18, 5, 464),
(18, 4, 465),
(18, 10, 467),
(18, 7, 471),
(16, 3, 479),
(16, 0, 481),
(16, 1, 482),
(16, 2, 483),
(16, 5, 484),
(16, 6, 485),
(16, 10, 486),
(16, 9, 488),
(16, 8, 490),
(16, 7, 495),
(16, 4, 497);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cadena_rival_estilos`
--

CREATE TABLE `cadena_rival_estilos` (
  `id_estilo` int(11) NOT NULL,
  `id_rival` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `formacion` varchar(8) NOT NULL DEFAULT '442'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cadena_rival_estilos`
--

INSERT INTO `cadena_rival_estilos` (`id_estilo`, `id_rival`, `nombre`, `formacion`) VALUES
(1, 1, 'Bloque bajo', '532'),
(2, 1, 'Presión alta', '433'),
(3, 2, 'Equilibrio', '442'),
(4, 2, 'Centro poblado', '352'),
(5, 3, 'Tridente', '433'),
(6, 3, 'Muro y salida', '541'),
(7, 4, 'Bloque Zeus', '442'),
(8, 4, 'Muralla Zeus', '541'),
(9, 5, 'Recital Plenilunio', '352'),
(10, 5, 'Rueda Plenilunio', '361'),
(11, 6, 'Ofensiva Selección', '433'),
(12, 6, 'Contragolpe Selección', '352'),
(13, 7, 'Titular (1-3-5-2)', '352'),
(14, 8, 'Titular (1-4-5-1)', '451'),
(15, 9, 'Titular (1-3-4-3)', '343'),
(16, 10, 'Titular (1-4-3-3)', '433'),
(17, 11, 'Titular (1-3-4-3)', '343'),
(18, 12, 'Titular (1-3-4-3)', '343'),
(19, 13, 'Titular (1-3-4-3)', '343'),
(20, 14, 'Titular (1-3-4-3)', '343'),
(21, 15, 'Titular (1-3-4-3)', '343');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `codigos`
--

CREATE TABLE `codigos` (
  `id_codigo` int(11) UNSIGNED NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `tipo` enum('global','unico') NOT NULL,
  `monedas` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  `creado` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `codigos`
--

INSERT INTO `codigos` (`id_codigo`, `codigo`, `tipo`, `monedas`, `activo`, `creado`) VALUES
(1, 'BIENVENIDA2026', 'global', 100, 1, '2026-07-23 13:23:07'),
(2, 'EVENTOESPECIAL', 'unico', 500, 1, '2026-07-23 13:23:07'),
(3, 'PRUEBA1', 'global', 100, 1, '2026-07-23 14:48:37'),
(5, 'GONZALOESE', 'global', 500, 1, '2026-08-12 09:28:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `codigos_canjeados`
--

CREATE TABLE `codigos_canjeados` (
  `id_canje` int(11) UNSIGNED NOT NULL,
  `id_codigo` int(11) UNSIGNED NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_canje` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `codigos_canjeados`
--

INSERT INTO `codigos_canjeados` (`id_canje`, `id_codigo`, `id_usuario`, `fecha_canje`) VALUES
(1, 1, 2, '2026-08-03 15:56:01'),
(2, 5, 2, '2026-08-12 09:57:11');

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

--
-- Volcado de datos para la tabla `coleccion`
--

INSERT INTO `coleccion` (`id_coleccion`, `id_usuario`, `id_cromo`, `obtenida`, `bloqueada`) VALUES
(139, 2, 7, '2026-07-22 09:12:22', 0),
(268, 8, 46, '2026-08-05 08:59:44', 0),
(337, 8, 46, '2026-08-05 08:59:44', 0),
(386, 8, 28, '2026-08-05 08:59:44', 0),
(472, 8, 4, '2026-08-05 08:59:44', 0),
(627, 8, 46, '2026-08-05 08:59:44', 0),
(662, 8, 45, '2026-08-05 08:59:44', 0),
(981, 8, 4, '2026-08-05 09:00:19', 0),
(988, 8, 45, '2026-08-05 09:00:19', 0),
(993, 8, 46, '2026-08-05 09:00:19', 0),
(1034, 8, 45, '2026-08-05 09:00:19', 0),
(1191, 8, 28, '2026-08-05 09:00:19', 0),
(1253, 8, 28, '2026-08-05 09:00:19', 0),
(1411, 8, 3, '2026-08-05 09:00:22', 0),
(1475, 8, 45, '2026-08-05 09:00:22', 0),
(1476, 8, 47, '2026-08-05 09:00:22', 0),
(1540, 8, 4, '2026-08-05 09:00:22', 0),
(1603, 8, 47, '2026-08-05 09:00:22', 0),
(1636, 8, 8, '2026-08-05 09:00:22', 0),
(1647, 8, 45, '2026-08-05 09:00:22', 0),
(1767, 8, 8, '2026-08-05 09:00:22', 0),
(1776, 8, 6, '2026-08-05 09:00:22', 0),
(1925, 8, 28, '2026-08-05 09:00:22', 0),
(2012, 8, 4, '2026-08-05 09:01:55', 0),
(2157, 8, 7, '2026-08-05 09:01:55', 0),
(2206, 8, 26, '2026-08-05 09:01:55', 0),
(2214, 8, 26, '2026-08-05 09:01:55', 0),
(2304, 8, 27, '2026-08-05 09:01:55', 0),
(2325, 8, 46, '2026-08-05 09:01:55', 0),
(2631, 8, 7, '2026-08-05 09:01:57', 0),
(2673, 8, 4, '2026-08-05 09:01:57', 0),
(2695, 8, 6, '2026-08-05 09:01:57', 0),
(2773, 8, 5, '2026-08-05 09:01:57', 0),
(2920, 8, 45, '2026-08-05 09:01:57', 0),
(2991, 8, 27, '2026-08-05 09:01:57', 0),
(3238, 8, 8, '2026-08-05 09:35:31', 0),
(3260, 8, 47, '2026-08-05 09:35:31', 0),
(3300, 8, 45, '2026-08-05 09:35:31', 0),
(3330, 8, 3, '2026-08-05 09:35:32', 0),
(3446, 8, 8, '2026-08-05 09:35:32', 0),
(3476, 8, 4, '2026-08-05 09:35:32', 0),
(3526, 8, 8, '2026-08-05 09:35:32', 0),
(3543, 8, 28, '2026-08-05 09:35:32', 0),
(3570, 8, 27, '2026-08-05 09:35:32', 0),
(3596, 8, 26, '2026-08-05 09:35:32', 0),
(3619, 8, 27, '2026-08-05 09:35:32', 0),
(3834, 8, 45, '2026-08-05 09:56:08', 0),
(3860, 8, 45, '2026-08-05 09:56:08', 0),
(3893, 8, 26, '2026-08-05 09:56:08', 0),
(3975, 8, 27, '2026-08-05 09:56:08', 0),
(4133, 8, 28, '2026-08-05 09:56:08', 0),
(4348, 8, 5, '2026-08-05 09:56:08', 0),
(4354, 8, 4, '2026-08-05 09:56:08', 0),
(4532, 2, 46, '2026-08-06 09:01:11', 0),
(4559, 2, 5, '2026-08-06 09:01:11', 0),
(4585, 2, 26, '2026-08-06 09:01:11', 0),
(4939, 2, 46, '2026-08-06 09:01:11', 0),
(4983, 2, 26, '2026-08-06 09:01:11', 0),
(5133, 2, 47, '2026-08-06 09:17:52', 0),
(5223, 2, 47, '2026-08-06 09:17:52', 0),
(5400, 2, 6, '2026-08-06 09:17:52', 0),
(5424, 2, 8, '2026-08-06 09:17:52', 0),
(5454, 2, 8, '2026-08-06 09:17:52', 0),
(5625, 2, 8, '2026-08-06 09:17:53', 0),
(5627, 2, 47, '2026-08-06 09:17:53', 0),
(5641, 2, 47, '2026-08-06 09:18:12', 0),
(5718, 2, 7, '2026-08-06 09:18:12', 0),
(5739, 2, 5, '2026-08-06 09:18:12', 0),
(5889, 2, 4, '2026-08-06 09:18:12', 0),
(5909, 2, 7, '2026-08-06 09:18:12', 0),
(5931, 2, 3, '2026-08-06 09:18:12', 0),
(5975, 2, 4, '2026-08-06 09:18:12', 0),
(5992, 2, 7, '2026-08-06 09:18:12', 0),
(6007, 2, 5, '2026-08-06 09:18:12', 0),
(6016, 2, 5, '2026-08-06 09:18:12', 0),
(6076, 2, 46, '2026-08-06 09:18:12', 0),
(6157, 2, 47, '2026-08-06 09:18:12', 0),
(6196, 2, 6, '2026-08-06 09:18:12', 0),
(6200, 2, 46, '2026-08-06 09:18:12', 0),
(6286, 2, 26, '2026-08-06 09:18:15', 0),
(6456, 2, 46, '2026-08-06 09:18:15', 0),
(6463, 2, 4, '2026-08-06 09:18:15', 0),
(6732, 2, 8, '2026-08-06 09:18:15', 0),
(6750, 2, 5, '2026-08-06 09:18:15', 0),
(6830, 2, 47, '2026-08-06 09:18:15', 0),
(6972, 2, 26, '2026-08-06 09:18:17', 0),
(7078, 2, 3, '2026-08-06 09:18:17', 0),
(7088, 2, 28, '2026-08-06 09:18:17', 0),
(7142, 2, 5, '2026-08-06 09:18:17', 0),
(7163, 2, 7, '2026-08-06 09:18:17', 0),
(7181, 2, 47, '2026-08-06 09:18:17', 0),
(7221, 2, 4, '2026-08-06 09:18:17', 0),
(7275, 2, 47, '2026-08-06 09:18:17', 0),
(7369, 2, 8, '2026-08-06 09:18:17', 0),
(7429, 2, 46, '2026-08-06 09:18:17', 0),
(7522, 2, 27, '2026-08-06 09:18:49', 0),
(7551, 2, 27, '2026-08-06 09:18:49', 0),
(7553, 2, 26, '2026-08-06 09:18:49', 0),
(7689, 2, 26, '2026-08-06 09:18:49', 0),
(7691, 2, 8, '2026-08-06 09:18:49', 0),
(7712, 2, 6, '2026-08-06 09:18:49', 0),
(7722, 2, 6, '2026-08-06 09:18:49', 0),
(7770, 2, 45, '2026-08-06 09:18:49', 0),
(7814, 2, 4, '2026-08-06 09:18:49', 0),
(7932, 2, 8, '2026-08-06 09:18:49', 0),
(8215, 2, 3, '2026-08-06 09:18:52', 0),
(8284, 2, 45, '2026-08-06 09:18:52', 0),
(8417, 2, 45, '2026-08-06 09:18:52', 0),
(8460, 2, 7, '2026-08-06 09:18:52', 0),
(8488, 2, 45, '2026-08-06 09:18:52', 0),
(8504, 2, 3, '2026-08-06 09:18:52', 0),
(8718, 2, 28, '2026-08-06 09:18:54', 0),
(8807, 2, 27, '2026-08-06 09:18:54', 0),
(8808, 2, 45, '2026-08-06 09:18:54', 0),
(8845, 2, 28, '2026-08-06 09:18:54', 0),
(8879, 2, 46, '2026-08-06 09:18:54', 0),
(8934, 2, 3, '2026-08-06 09:18:54', 0),
(8948, 2, 4, '2026-08-06 09:18:54', 0),
(8992, 2, 7, '2026-08-06 09:18:54', 0),
(9075, 2, 27, '2026-08-06 09:18:54', 0),
(9099, 2, 47, '2026-08-06 09:18:54', 0),
(9148, 2, 8, '2026-08-06 09:18:54', 0),
(9255, 2, 45, '2026-08-06 09:18:57', 0),
(9282, 2, 8, '2026-08-06 09:18:57', 0),
(9408, 2, 27, '2026-08-06 09:18:57', 0),
(9441, 2, 27, '2026-08-06 09:18:57', 0),
(9478, 2, 27, '2026-08-06 09:18:57', 0),
(9580, 2, 8, '2026-08-06 09:18:57', 0),
(9596, 2, 28, '2026-08-06 09:18:57', 0),
(9642, 2, 45, '2026-08-06 09:18:57', 0),
(9704, 2, 6, '2026-08-06 09:18:57', 0),
(9738, 2, 6, '2026-08-06 09:18:57', 0),
(9763, 2, 45, '2026-08-06 09:18:57', 0),
(9776, 2, 26, '2026-08-06 09:18:57', 0),
(9828, 2, 4, '2026-08-06 09:18:57', 0),
(9830, 2, 5, '2026-08-06 09:18:57', 0),
(10129, 9, 5, '2026-08-07 09:30:00', 0),
(10361, 9, 6, '2026-08-07 09:30:00', 0),
(10470, 9, 6, '2026-08-07 09:33:48', 0),
(10511, 9, 46, '2026-08-07 09:33:48', 0),
(10606, 9, 45, '2026-08-07 09:33:48', 0),
(10687, 9, 8, '2026-08-07 09:33:48', 0),
(10806, 9, 28, '2026-08-07 09:33:48', 0),
(10809, 9, 28, '2026-08-07 09:33:48', 0),
(10876, 9, 5, '2026-08-07 09:33:48', 0),
(10898, 9, 7, '2026-08-07 09:33:48', 0),
(11036, 9, 5, '2026-08-07 09:33:48', 0),
(11051, 9, 5, '2026-08-07 09:33:48', 0),
(11127, 9, 28, '2026-08-07 09:53:07', 0),
(11229, 9, 4, '2026-08-07 09:53:07', 0),
(11272, 9, 6, '2026-08-07 09:53:07', 0),
(11389, 9, 3, '2026-08-07 09:53:07', 0),
(11395, 9, 27, '2026-08-07 09:53:07', 0),
(11448, 9, 46, '2026-08-07 09:53:07', 0),
(11518, 9, 5, '2026-08-07 09:53:07', 0),
(11667, 9, 27, '2026-08-07 09:54:39', 0),
(11857, 9, 26, '2026-08-07 09:54:39', 0),
(12006, 9, 8, '2026-08-07 09:54:39', 0),
(12063, 9, 8, '2026-08-07 09:54:39', 0),
(12094, 9, 45, '2026-08-07 09:54:39', 0),
(12107, 9, 27, '2026-08-07 09:54:39', 0),
(12124, 9, 8, '2026-08-07 09:54:39', 0),
(12189, 9, 5, '2026-08-07 09:54:39', 0),
(12272, 9, 46, '2026-08-07 09:56:15', 0),
(12314, 9, 4, '2026-08-07 09:56:15', 0),
(12425, 9, 6, '2026-08-07 09:56:15', 0),
(12607, 9, 8, '2026-08-07 09:56:15', 0),
(12672, 9, 46, '2026-08-07 09:56:15', 0),
(12708, 9, 4, '2026-08-07 09:56:15', 0),
(12793, 9, 3, '2026-08-07 09:56:15', 0),
(12898, 2, 121, '2026-08-12 12:36:49', 0),
(12899, 2, 457, '2026-08-12 12:36:49', 0),
(12900, 2, 253, '2026-08-12 12:36:49', 0),
(12901, 2, 170, '2026-08-12 12:36:49', 0),
(12902, 2, 258, '2026-08-12 12:36:49', 0),
(12903, 2, 76, '2026-08-12 12:36:49', 0),
(12904, 2, 326, '2026-08-12 12:36:49', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL,
  `descripcion` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
('ciclo_contra_afinidad_bonus', '5.5', 'Bonus (%) al total del equipo cuya afinidad dominante contra a la del rival. Ciclo canon: Fuego>Bosque>Viento>Montaña>Fuego. Neutro (empate de afinidad dominante) ni gana ni pierde. Valor elegido tras barrer 11 magnitudes con ~22M de duelos: 5,5 dio la me'),
('coherencia_malus_rate', '3.0', 'Cuánto se exige de compo_index por cada punto de rareza por encima del umbral libre. Subido desde 1,6 tras comprobar que el valor bajo dejaba un margen demasiado pequeño para que el malus se notara.'),
('coherencia_malus_tope', '18', 'Tope duro (%) del malus de coherencia. Con un equipo de rareza máxima y cero compos se llega a este tope, y eso basta para que pierda contra un equipo bien construido de rareza inferior.'),
('coherencia_umbral_libre', '2.5', 'Rareza media (Común=1 … SRF=6) por debajo de la cual no se exige ninguna coherencia de compos. 2,5 equivale a \"Raro\".'),
('compo_pesos_dr', '1.0,0.7,0.45,0.25', 'Rendimientos decrecientes cuando varios rasgos distintos empujan la MISMA línea: se ordenan de mayor a menor y se pesan así. El cuarto y siguientes usan el último peso.'),
('depuracion_forzar_empate', '0', 'PRUEBAS: fuerza 1-1 en todo partido PvP para llegar siempre a la tanda'),
('duelo_k', '400', 'K de la curva Elo de resolución. Menor = la diferencia de fuerza pesa más.'),
('duelo_latido_max', '45', 'Segundos sin latido del creador antes de dar la sala por abandonada y cancelarla.'),
('duelo_plazo_aumento', '30', 'Segundos para elegir aumento antes de que lo elija el sistema.'),
('duelo_p_max', '0.95', 'Probabilidad máxima de victoria. Nunca 1.'),
('duelo_p_min', '0.05', 'Probabilidad mínima de victoria. Nunca 0: el mazo débil siempre tiene opción.'),
('line_cap', '20', 'Tope máximo (%) del bonus acumulado de COMPOS sobre una sola línea, tras rendimientos decrecientes. Es la salvaguarda anti-exploit principal: se probó un escenario con 7 rasgos simultáneos sobre Ataque y contuvo el desborde sin romper el balance. No inclu'),
('partido_abandono_seg', '3600', 'Segundos tras los que un partido en juego se cierra por abandono'),
('partido_duracion_seg', '45', 'Segundos reales que dura el partido narrado de punta a punta. Los dos jugadores lo ven a la vez, así que subirlo alarga la espera de ambos.'),
('partido_espera_seg', '15', 'Segundos que se espera a que aparezcan los dos antes de arrancar igualmente. Quien no esté, se pierde el partido.'),
('partido_gol_base', '0.06', 'Base de la probabilidad de que una ocasión sea gol, antes del peligro'),
('partido_gol_sens', '0.30', 'Cuánto pesa el peligro de la ocasión en que sea gol: EL DIAL DEL EQUILIBRIO'),
('partido_latido_max', '12', 'Segundos sin latido para dar por ausente a un jugador y resolverle sus minijuegos con la opción segura.'),
('partido_minijuegos_max', '2', 'Decisiones que se ofrecen a CADA jugador por partido.'),
('partido_minijuegos_sin_impacto_max', '1', 'Cuantas de las decisiones de un partido pueden ser de impacto \"ninguno\" (arbitro y defensivas sin gol que mover). No sube el total, que lo fija partido_minijuegos_max.'),
('partido_minijuego_prob_gol', '0.70', 'Probabilidad de que un ACIERTO en un minijuego acabe moviendo el marcador. Antes era siempre 1. Fallar sigue sin castigar.'),
('partido_ocasion_base', '0.10', 'Base de la probabilidad de ocasión por tramo, antes del ratio de fuerzas'),
('partido_ocasion_factor', '0.62', 'Cuánto pesa el ratio de fuerzas en la probabilidad de ocasión'),
('partido_ocasion_max', '0.52', 'Techo de probabilidad de ocasión: lo que puede llegar a dominar el más fuerte'),
('partido_ocasion_min', '0.14', 'Suelo de probabilidad de ocasión: lo que pisa de área el mazo más flojo'),
('partido_penalti_prob_fallo', '0.018', 'Probabilidad de que una ocasion que NO acabo en gol se narre como penalti fallado. Sube el insignia del catalogo a costa de realismo.'),
('partido_penalti_prob_gol', '0.12', 'Probabilidad de que una ocasion que YA acabo en gol se narre como penalti. No cambia el marcador, solo el relato.'),
('partido_presupuesto_marcador', '1', 'Goles que puede mover cada jugador con sus minijuegos en un partido'),
('pve_compos_mult_dificil', '1.096', ''),
('pve_compos_mult_extremo', '1.339', ''),
('pve_compos_mult_facil', '1.031', ''),
('pve_compos_mult_medio', '1.037', ''),
('pve_compos_mult_muy_dificil', '1.273', ''),
('pve_goles_escala', '2.6', ''),
('pve_goles_exponente', '1.6', ''),
('pve_goles_max', '9', ''),
('pve_mult_dificil', '0.933', ''),
('pve_mult_extremo', '1.063', ''),
('pve_mult_facil', '0.742', ''),
('pve_mult_medio', '0.845', ''),
('pve_mult_muy_dificil', '1.027', ''),
('pve_rango_a_margen', '3', ''),
('pve_rango_s_goles', '2', ''),
('pve_rareza_max_dificil', '0', ''),
('pve_rareza_max_extremo', '0', ''),
('pve_rareza_max_facil', '4', ''),
('pve_rareza_max_medio', '0', ''),
('pve_rareza_max_muy_dificil', '0', ''),
('pve_recompensa_decrecimiento_piso', '0.15', ''),
('pve_recompensa_decrecimiento_tasa', '0.55', ''),
('pve_recompensa_dificil', '320', ''),
('pve_recompensa_extremo', '750', ''),
('pve_recompensa_facil', '80', ''),
('pve_recompensa_medio', '160', ''),
('pve_recompensa_mult_rango_a', '1.20', ''),
('pve_recompensa_mult_rango_b', '1.00', ''),
('pve_recompensa_mult_rango_s', '1.50', ''),
('pve_recompensa_muy_dificil', '520', ''),
('pve_tiers_dificil', '35,40,25', ''),
('pve_tiers_extremo', '0,0,100', ''),
('pve_tiers_facil', '60,30,10', ''),
('pve_tiers_medio', '55,31,14', ''),
('pve_tiers_muy_dificil', '20,40,40', ''),
('tanda_plazo_seg', '12', 'Segundos para elegir hueco en un penalti antes de que decida el sistema'),
('tension_tiers_0', '60,30,10', 'Probabilidades Plata/Oro/Prisma del Aumento sin Tensión (0-2 rasgos distintos). Es la tabla base del juego.'),
('tension_tiers_1', '55,31,14', 'Probabilidades Plata/Oro/Prisma con Tensión nivel 1 (3 rasgos distintos).'),
('tension_tiers_2', '50,33,17', 'Probabilidades Plata/Oro/Prisma con Tensión nivel 2 (5 rasgos distintos).'),
('tension_tiers_3', '43,36,21', 'Probabilidades Plata/Oro/Prisma con Tensión nivel 3 (7 rasgos distintos).');

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
  `id_afinidad` int(11) NOT NULL,
  `ataque` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `defensa` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `tecnica` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `cupo_numerado` int(11) UNSIGNED DEFAULT NULL,
  `origen_importacion` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cromos`
--

INSERT INTO `cromos` (`id_cromo`, `nombre`, `posicion`, `descripcion`, `imagen`, `id_expansion`, `id_equipo`, `id_rareza`, `id_afinidad`, `ataque`, `defensa`, `tecnica`, `cupo_numerado`, `origen_importacion`) VALUES
(3, 'Gentian', 'DC', 'Máximo Goleador de la Primera Temporada', './assets/img/Cromos/ALL STARS/gentian.webp', 3, 2, 6, 1, 99, 84, 95, NULL, 0),
(4, 'Shawn Froste', 'DC', 'Capitán del Alpino', './assets/img/Cromos/ALL STARS/GonzaloEse.webp', 3, 3, 6, 3, 99, 86, 99, 10, 0),
(5, 'Hector Helio', 'POR', 'Portero menos goleado', './assets/img/Cromos/ALL STARS/hector_helio.webp', 3, 3, 6, 1, 73, 99, 94, NULL, 0),
(6, 'Maddie Moonlight', 'DC', 'Ganadora de Copa', './assets/img/Cromos/ALL STARS/maddie_moonlight.webp', 3, 5, 6, 4, 99, 81, 98, NULL, 0),
(7, 'Lykan Moss', 'POR', 'Musgo', './assets/img/Cromos/ALL STARS/lykan_moss.webp', 3, 4, 6, 4, 69, 99, 93, NULL, 0),
(8, 'Tom Skipper', 'DC', 'Capitán del Instituto Zeus', './assets/img/Cromos/Apuesta Segura/tom_skipper.webp', 3, 13, 5, 3, 97, 78, 90, NULL, 0),
(26, 'Escudo Instituto Zeus', 'ESCUDO', 'Escudo del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/escudo_zeus.jpeg', 3, 13, 5, 5, 0, 0, 0, NULL, 0),
(27, 'Lanny Tern', 'GER', 'Gerente del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/lanny_tern.webp', 3, 13, 5, 3, 0, 0, 0, NULL, 0),
(28, 'Xokas', 'ENT', 'Entrenador y Streamer del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/xokas.webp', 3, 13, 5, 3, 0, 0, 0, NULL, 0),
(45, 'Escudo Academia Payilunio', 'ESCUDO', '', './assets/img/Cromos/Base Set/Academia Payilunio/Escudo.webp', 3, 6, 5, 5, 0, 0, 0, NULL, 0),
(46, 'Gyan Cinquedea', 'ENT', '', './assets/img/Cromos/Base Set/Academia Payilunio/Cinquedea.webp', 3, 6, 5, 2, 0, 0, 0, NULL, 0),
(47, 'Mila Simmering', 'GER', '', './assets/img/Cromos/Base Set/Academia Payilunio/Mila.webp', 3, 6, 5, 1, 0, 0, 0, NULL, 0),
(53, 'Joaquine Downtown', 'DF', '', './assets/img/Cromos/Importados/monte-olimpo/joaquine-downtown.webp', 3, 12, 2, 2, 56, 70, 61, NULL, 1),
(54, 'Soji Okita', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/soji-okita.webp', 3, 12, 3, 2, 88, 58, 76, NULL, 1),
(55, 'Basile Hardy', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/basile-hardy.webp', 3, 12, 2, 2, 77, 56, 64, NULL, 1),
(56, 'Mei Gongzhu', 'MC', '', './assets/img/Cromos/Importados/monte-olimpo/mei-gongzhu.webp', 3, 12, 2, 2, 60, 58, 69, NULL, 1),
(57, 'Navan', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/navan.webp', 3, 12, 1, 3, 69, 40, 64, NULL, 1),
(58, 'Lee Canthrup', 'POR', '', './assets/img/Cromos/Importados/monte-olimpo/lee-canthrup.webp', 3, 12, 1, 2, 33, 62, 57, NULL, 1),
(59, 'Aaron Gossamer', 'POR', '', './assets/img/Cromos/Importados/monte-olimpo/aaron-gossamer.webp', 3, 12, 2, 3, 38, 80, 64, NULL, 1),
(60, 'Isla Pullens', 'POR', '', './assets/img/Cromos/Importados/monte-olimpo/isla-pullens.webp', 3, 12, 1, 1, 33, 70, 62, NULL, 1),
(61, 'Joseph Yosemite', 'DF', '', './assets/img/Cromos/Importados/monte-olimpo/joseph-yosemite.webp', 3, 12, 2, 3, 51, 69, 65, NULL, 1),
(62, 'Gus Gamer', 'DF', '', './assets/img/Cromos/Importados/monte-olimpo/gus-gamer.webp', 3, 12, 2, 2, 51, 77, 58, NULL, 1),
(63, 'Sus Swihn', 'MC', '', './assets/img/Cromos/Importados/monte-olimpo/sus-swihn.webp', 3, 12, 2, 4, 69, 64, 79, NULL, 1),
(64, 'May Jarrett', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/may-jarrett.webp', 3, 12, 2, 4, 72, 48, 62, NULL, 1),
(65, 'Triton Nettuno', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/triton-nettuno.webp', 3, 12, 1, 3, 66, 43, 60, NULL, 1),
(66, 'Clay Modlin', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/clay-modlin.webp', 3, 12, 2, 1, 72, 48, 61, NULL, 1),
(67, 'Mahrsa', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/mahrsa.webp', 3, 12, 1, 3, 70, 47, 58, NULL, 1),
(68, 'Creepy', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/creepy.webp', 3, 12, 1, 4, 68, 44, 59, NULL, 1),
(69, 'Direm', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/direm.webp', 3, 12, 1, 1, 73, 51, 52, NULL, 1),
(70, 'Khefren Chepren', 'DC', '', './assets/img/Cromos/Importados/monte-olimpo/khefren-chepren.webp', 3, 12, 1, 2, 75, 50, 52, NULL, 1),
(71, 'Spencer Duskplay', 'MC', '', './assets/img/Cromos/Importados/monte-olimpo/spencer-duskplay.webp', 3, 12, 1, 3, 61, 49, 66, NULL, 1),
(72, 'Neith Venus', 'MC', '', './assets/img/Cromos/Importados/monte-olimpo/neith-venus.webp', 3, 12, 1, 4, 48, 60, 66, NULL, 1),
(73, 'Escudo Monte Olimpo', 'ESCUDO', '', '', 3, 12, 5, 5, 0, 0, 0, NULL, 1),
(74, 'Leão Samus', 'ENT', '', '', 3, 12, 5, 5, 0, 0, 0, NULL, 1),
(75, 'Hawke Ambrose', 'GER', '', '', 3, 12, 5, 5, 0, 0, 0, NULL, 1),
(76, 'Plink Powai', 'MC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/plink-powai.webp', 3, 8, 4, 3, 78, 80, 87, NULL, 1),
(77, 'Neil Turner', 'DC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/neil-turner.webp', 3, 8, 3, 2, 80, 57, 71, NULL, 1),
(78, 'Van Yamano', 'DC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/van-yamano.webp', 3, 8, 3, 1, 87, 62, 70, NULL, 1),
(79, 'Ashvina Bharani', 'POR', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/ashvina-bharani.webp', 3, 8, 2, 3, 33, 69, 70, NULL, 1),
(80, 'Jordan Greenway', 'MC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/jordan-greenway.webp', 3, 8, 3, 4, 75, 65, 86, NULL, 1),
(81, 'Beta', 'DC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/beta.webp', 3, 8, 4, 3, 96, 63, 87, NULL, 1),
(82, 'Brutus Nastion', 'POR', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/brutus-nastion.webp', 3, 8, 1, 2, 29, 66, 55, NULL, 1),
(83, 'Mimi Ceylon', 'POR', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/mimi-ceylon.webp', 3, 8, 1, 1, 25, 72, 64, NULL, 1),
(84, 'Van Tarel', 'POR', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/van-tarel.webp', 3, 8, 1, 4, 32, 74, 61, NULL, 1),
(85, 'Dart H. Cylon', 'DF', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/dart-h-cylon.webp', 3, 8, 2, 4, 54, 79, 58, NULL, 1),
(86, 'Gustav Weathervane', 'DF', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/gustav-weathervane.webp', 3, 8, 1, 3, 51, 62, 60, NULL, 1),
(87, 'Steven Nevets', 'DF', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/steven-nevets.webp', 3, 8, 2, 2, 52, 71, 65, NULL, 1),
(88, 'Ducksquiggan Tarboyle', 'DF', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/ducksquiggan-tarboyle.webp', 3, 8, 1, 2, 51, 68, 49, NULL, 1),
(89, 'Nai Swanson', 'DF', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/nai-swanson.webp', 3, 8, 1, 1, 49, 57, 52, NULL, 1),
(90, 'Tre Blackley', 'MC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/tre-blackley.webp', 3, 8, 1, 2, 48, 52, 70, NULL, 1),
(91, 'George Winters', 'DC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/george-winters.webp', 3, 8, 2, 4, 75, 48, 68, NULL, 1),
(92, 'Hilda Brunner', 'DC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/hilda-brunner.webp', 3, 8, 4, 4, 82, 74, 74, NULL, 1),
(93, 'Brom Hexine', 'DC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/brom-hexine.webp', 3, 8, 1, 2, 69, 51, 63, NULL, 1),
(94, 'Macha Green', 'DC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/macha-green.webp', 3, 8, 2, 1, 81, 45, 72, NULL, 1),
(95, 'Maneuver Gibbs', 'DC', '', './assets/img/Cromos/Importados/criaturas-de-la-noche/maneuver-gibbs.webp', 3, 8, 2, 3, 80, 51, 72, NULL, 1),
(96, 'Escudo Criaturas de la noche', 'ESCUDO', '', '', 3, 8, 5, 5, 0, 0, 0, NULL, 1),
(97, 'Fei Rune', 'ENT', '', '', 3, 8, 5, 5, 0, 0, 0, NULL, 1),
(98, 'Jade Greene', 'GER', '', '', 3, 8, 5, 5, 0, 0, 0, NULL, 1),
(99, 'Pocus Sesame', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/pocus-sesame.webp', 3, 6, 1, 4, 70, 49, 61, NULL, 1),
(100, 'Kyon Canis', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/kyon-canis.webp', 3, 6, 1, 1, 70, 50, 51, NULL, 1),
(101, 'Ichabod Stark', 'MC', '', './assets/img/Cromos/Importados/academia-plenilunio/ichabod-stark.webp', 3, 6, 2, 1, 67, 68, 79, NULL, 1),
(102, 'Heath Moore', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/heath-moore.webp', 3, 6, 3, 2, 79, 55, 72, NULL, 1),
(103, 'Simeon Ayp', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/simeon-ayp.webp', 3, 6, 4, 1, 85, 68, 83, NULL, 1),
(104, 'Bala Gasgula', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/bala-gasgula.webp', 3, 6, 1, 2, 65, 38, 57, NULL, 1),
(105, 'Nora Flexion', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/nora-flexion.webp', 3, 6, 1, 4, 68, 40, 64, NULL, 1),
(106, 'Silvain Hache', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/silvain-hache.webp', 3, 6, 4, 3, 95, 72, 75, NULL, 1),
(107, 'Orchid', 'MC', '', './assets/img/Cromos/Importados/academia-plenilunio/orchid.webp', 3, 6, 1, 3, 54, 49, 62, NULL, 1),
(108, 'Ceram Longicorn', 'POR', '', './assets/img/Cromos/Importados/academia-plenilunio/ceram-longicorn.webp', 3, 6, 1, 4, 36, 68, 58, NULL, 1),
(109, 'Serina Shelby', 'POR', '', './assets/img/Cromos/Importados/academia-plenilunio/serina-shelby.webp', 3, 6, 2, 3, 45, 74, 72, NULL, 1),
(110, 'Strem Goozer', 'POR', '', './assets/img/Cromos/Importados/academia-plenilunio/strem-goozer.webp', 3, 6, 1, 2, 28, 70, 52, NULL, 1),
(111, 'Bombasta Flamboyanzi', 'DF', '', './assets/img/Cromos/Importados/academia-plenilunio/bombasta-flamboyanzi.webp', 3, 6, 1, 2, 44, 67, 60, NULL, 1),
(112, 'In Chikita', 'DF', '', './assets/img/Cromos/Importados/academia-plenilunio/in-chikita.webp', 3, 6, 2, 2, 44, 69, 60, NULL, 1),
(113, 'Nick Woodgate', 'DF', '', './assets/img/Cromos/Importados/academia-plenilunio/nick-woodgate.webp', 3, 6, 2, 3, 51, 76, 66, NULL, 1),
(114, 'Ashley Sarpala', 'MC', '', './assets/img/Cromos/Importados/academia-plenilunio/ashley-sarpala.webp', 3, 6, 2, 4, 67, 65, 77, NULL, 1),
(115, 'Eggbert Heading', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/eggbert-heading.webp', 3, 6, 1, 4, 64, 46, 51, NULL, 1),
(116, 'Zed Kyu', 'MC', '', './assets/img/Cromos/Importados/academia-plenilunio/zed-kyu.webp', 3, 6, 3, 3, 75, 71, 76, NULL, 1),
(117, 'Sonny Wukong', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/sonny-wukong.webp', 3, 6, 1, 2, 71, 41, 51, NULL, 1),
(118, 'Gaiel', 'DC', '', './assets/img/Cromos/Importados/academia-plenilunio/gaiel.webp', 3, 6, 2, 3, 73, 54, 68, NULL, 1),
(120, 'Juana de Arco', 'DF', '', './assets/img/Cromos/Importados/alpino/juana-de-arco.webp', 3, 3, 3, 2, 60, 74, 70, NULL, 1),
(121, 'Gabriel García', 'DF', '', './assets/img/Cromos/Importados/alpino/gabriel-garcia.webp', 3, 3, 3, 4, 53, 85, 71, NULL, 1),
(122, 'Oni Triumvir', 'DF', '', './assets/img/Cromos/Importados/alpino/oni-triumvir.webp', 3, 3, 1, 4, 46, 60, 61, NULL, 1),
(123, 'Rex Remington', 'MC', '', './assets/img/Cromos/Importados/alpino/rex-remington.webp', 3, 3, 2, 1, 62, 71, 66, NULL, 1),
(124, 'Cronus Fourseasons', 'MC', '', './assets/img/Cromos/Importados/alpino/cronus-fourseasons.webp', 3, 3, 1, 2, 60, 56, 70, NULL, 1),
(125, 'Paolo Bianchi', 'DC', '', './assets/img/Cromos/Importados/alpino/paolo-bianchi.webp', 3, 3, 3, 4, 82, 55, 68, NULL, 1),
(126, 'Sael', 'MC', '', './assets/img/Cromos/Importados/alpino/sael.webp', 3, 3, 1, 4, 56, 58, 67, NULL, 1),
(127, 'Joan Asensi', 'DC', '', './assets/img/Cromos/Importados/alpino/joan-asensi.webp', 3, 3, 1, 2, 75, 41, 64, NULL, 1),
(128, 'Federigo Cinco', 'DF', '', './assets/img/Cromos/Importados/alpino/federigo-cinco.webp', 3, 3, 2, 4, 46, 65, 63, NULL, 1),
(129, 'Yule Hoarfrost', 'DC', '', './assets/img/Cromos/Importados/alpino/yule-hoarfrost.webp', 3, 3, 1, 4, 71, 43, 61, NULL, 1),
(130, 'Zetoh', 'DC', '', './assets/img/Cromos/Importados/alpino/zetoh.webp', 3, 3, 2, 2, 78, 50, 71, NULL, 1),
(131, 'Anorel', 'POR', '', './assets/img/Cromos/Importados/alpino/anorel.webp', 3, 3, 1, 4, 35, 67, 53, NULL, 1),
(132, 'Bradford Ash', 'DC', '', './assets/img/Cromos/Importados/alpino/bradford-ash.webp', 3, 3, 1, 1, 73, 41, 54, NULL, 1),
(133, 'Isaac César', 'DC', '', './assets/img/Cromos/Importados/alpino/isaac-cesar.webp', 3, 3, 1, 3, 77, 42, 59, NULL, 1),
(134, 'Crateris Mercury', 'DF', '', './assets/img/Cromos/Importados/alpino/crateris-mercury.webp', 3, 3, 1, 3, 47, 70, 59, NULL, 1),
(135, 'Roland Climbstein', 'DC', '', './assets/img/Cromos/Importados/alpino/roland-climbstein.webp', 3, 3, 1, 3, 65, 37, 56, NULL, 1),
(136, 'Ekadel', 'DF', '', './assets/img/Cromos/Importados/alpino/ekadel.webp', 3, 3, 1, 3, 38, 62, 55, NULL, 1),
(137, 'Mateo Bonachea', 'DC', '', './assets/img/Cromos/Importados/alpino/mateo-bonachea.webp', 3, 3, 1, 1, 65, 48, 56, NULL, 1),
(138, 'Escudo Alpino', 'ESCUDO', '', '', 3, 3, 5, 5, 0, 0, 0, NULL, 1),
(139, 'David Evans', 'ENT', '', '', 3, 3, 5, 5, 0, 0, 0, NULL, 1),
(140, 'Nelly Raimon', 'GER', '', '', 3, 3, 5, 5, 0, 0, 0, NULL, 1),
(141, 'Darren LaChance', 'POR', '', './assets/img/Cromos/Importados/instituto-farm/darren-lachance.webp', 3, 20, 3, 4, 43, 79, 69, NULL, 1),
(142, 'Fred Punch', 'DF', '', './assets/img/Cromos/Importados/instituto-farm/fred-punch.webp', 3, 20, 2, 2, 52, 77, 63, NULL, 1),
(143, 'Cee Eff', 'DF', '', './assets/img/Cromos/Importados/instituto-farm/cee-eff.webp', 3, 20, 2, 1, 52, 79, 58, NULL, 1),
(144, 'Peter Drent', 'DF', '', './assets/img/Cromos/Importados/instituto-farm/peter-drent.webp', 3, 20, 1, 1, 40, 60, 47, NULL, 1),
(145, 'Tod Ironside', 'DF', '', './assets/img/Cromos/Importados/instituto-farm/tod-ironside.webp', 3, 20, 3, 2, 58, 72, 60, NULL, 1),
(146, 'Howie Itzer', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/howie-itzer.webp', 3, 20, 1, 4, 55, 56, 58, NULL, 1),
(147, 'Sam Kincaid', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/sam-kincaid.webp', 3, 20, 3, 2, 72, 70, 80, NULL, 1),
(148, 'Fivier Saviola', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/fivier-saviola.webp', 3, 20, 2, 4, 68, 63, 70, NULL, 1),
(149, 'Diego Oro', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/diego-oro.webp', 3, 20, 1, 1, 62, 63, 58, NULL, 1),
(150, 'Adriano Donati', 'DC', '', './assets/img/Cromos/Importados/instituto-farm/adriano-donati.webp', 3, 20, 1, 2, 68, 42, 57, NULL, 1),
(151, 'Ben Evolent', 'DC', '', './assets/img/Cromos/Importados/instituto-farm/ben-evolent.webp', 3, 20, 1, 3, 64, 48, 57, NULL, 1),
(152, 'Raúl Parejo', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/raul-parejo.webp', 3, 20, 1, 3, 57, 60, 59, NULL, 1),
(153, 'Lorne Mower', 'POR', '', './assets/img/Cromos/Importados/instituto-farm/lorne-mower.webp', 3, 20, 1, 1, 35, 63, 65, NULL, 1),
(154, 'Ayt Bee', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/ayt-bee.webp', 3, 20, 2, 3, 57, 67, 74, NULL, 1),
(155, 'Conant Ó Briain', 'DF', '', './assets/img/Cromos/Importados/instituto-farm/conant-o-briain.webp', 3, 20, 2, 4, 54, 66, 65, NULL, 1),
(156, 'Pace Keeping', 'DF', '', './assets/img/Cromos/Importados/instituto-farm/pace-keeping.webp', 3, 20, 1, 1, 43, 60, 61, NULL, 1),
(157, 'Shaun Bonce', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/shaun-bonce.webp', 3, 20, 1, 3, 56, 60, 66, NULL, 1),
(158, 'Jack Sawyer', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/jack-sawyer.webp', 3, 20, 1, 3, 53, 52, 57, NULL, 1),
(159, 'Jagur Meister', 'DC', '', './assets/img/Cromos/Importados/instituto-farm/jagur-meister.webp', 3, 20, 1, 2, 74, 50, 59, NULL, 1),
(160, 'Horace Onlign', 'MC', '', './assets/img/Cromos/Importados/instituto-farm/horace-onlign.webp', 3, 20, 1, 4, 61, 57, 68, NULL, 1),
(161, 'Escudo Instituto Farm', 'ESCUDO', '', '', 3, 20, 5, 5, 0, 0, 0, NULL, 1),
(162, 'Robert Cottontail', 'ENT', '', '', 3, 20, 5, 5, 0, 0, 0, NULL, 1),
(163, 'Jim Trainer', 'GER', '', '', 3, 20, 5, 5, 0, 0, 0, NULL, 1),
(164, 'Jean-Pierre Lapin', 'POR', '', './assets/img/Cromos/Importados/inazuma-kids-fc/jeanpierre-lapin.webp', 3, 23, 3, 1, 48, 87, 66, NULL, 1),
(165, 'Maddie Moonlight', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/maddie-moonlight.webp', 3, 23, 3, 4, 81, 65, 74, NULL, 1),
(166, 'Nero', 'POR', '', './assets/img/Cromos/Importados/inazuma-kids-fc/nero.webp', 3, 23, 4, 3, 59, 90, 84, NULL, 1),
(167, 'Stuart Potty', 'DF', '', './assets/img/Cromos/Importados/inazuma-kids-fc/stuart-potty.webp', 3, 23, 1, 1, 42, 65, 51, NULL, 1),
(168, 'Nenel', 'DF', '', './assets/img/Cromos/Importados/inazuma-kids-fc/nenel.webp', 3, 23, 2, 2, 46, 75, 67, NULL, 1),
(169, 'Ewan Envoi', 'DF', '', './assets/img/Cromos/Importados/inazuma-kids-fc/ewan-envoi.webp', 3, 23, 2, 4, 45, 76, 54, NULL, 1),
(170, 'Scott Banyan', 'DF', '', './assets/img/Cromos/Importados/inazuma-kids-fc/scott-banyan.webp', 3, 23, 3, 4, 62, 80, 63, NULL, 1),
(171, 'Marjorette Pompom', 'MC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/marjorette-pompom.webp', 3, 23, 2, 4, 59, 68, 79, NULL, 1),
(172, 'Dora Delight', 'MC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/dora-delight.webp', 3, 23, 2, 2, 65, 71, 76, NULL, 1),
(173, 'Angelo Gabrini', 'MC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/angelo-gabrini.webp', 3, 23, 3, 3, 68, 66, 79, NULL, 1),
(174, 'Vier Whitely', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/vier-whitely.webp', 3, 23, 2, 3, 79, 59, 68, NULL, 1),
(175, 'Loughrey Mose', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/loughrey-mose.webp', 3, 23, 2, 3, 83, 48, 64, NULL, 1),
(176, 'Shiryu Hanabusa', 'POR', '', './assets/img/Cromos/Importados/inazuma-kids-fc/shiryu-hanabusa.webp', 3, 23, 1, 2, 30, 72, 54, NULL, 1),
(177, 'Barbara Bitt', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/barbara-bitt.webp', 3, 23, 1, 4, 74, 49, 61, NULL, 1),
(178, 'Finn Geld', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/finn-geld.webp', 3, 23, 1, 4, 67, 51, 64, NULL, 1),
(179, 'Reyes Cabedo', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/reyes-cabedo.webp', 3, 23, 1, 1, 71, 47, 55, NULL, 1),
(180, 'Rosa Welle', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/rosa-welle.webp', 3, 23, 1, 2, 70, 49, 55, NULL, 1),
(181, 'Lucia', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/lucia.webp', 3, 23, 1, 1, 65, 37, 51, NULL, 1),
(182, 'Corday Dremin', 'DC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/corday-dremin.webp', 3, 23, 1, 3, 72, 50, 53, NULL, 1),
(183, 'Aubrey McCoy', 'MC', '', './assets/img/Cromos/Importados/inazuma-kids-fc/aubrey-mccoy.webp', 3, 23, 1, 2, 49, 52, 70, NULL, 1),
(184, 'Escudo Inazuma Kids FC', 'ESCUDO', '', '', 3, 23, 5, 5, 0, 0, 0, NULL, 1),
(185, 'El Segundo', 'ENT', '', '', 3, 23, 5, 5, 0, 0, 0, NULL, 1),
(186, 'Lucy Wongfu', 'GER', '', '', 3, 23, 5, 5, 0, 0, 0, NULL, 1),
(187, 'Mystral Callous', 'DC', '', './assets/img/Cromos/Importados/instituto-zeus/mystral-callous.webp', 3, 13, 3, 1, 87, 67, 69, NULL, 1),
(188, 'Kia Tanner', 'MC', '', './assets/img/Cromos/Importados/instituto-zeus/kia-tanner.webp', 3, 13, 2, 4, 61, 58, 66, NULL, 1),
(189, 'Aiden Froste', 'DC', '', './assets/img/Cromos/Importados/instituto-zeus/aiden-froste.webp', 3, 13, 4, 2, 82, 70, 84, NULL, 1),
(190, 'Terri Ann Thrope', 'DC', '', './assets/img/Cromos/Importados/instituto-zeus/terri-ann-thrope.webp', 3, 13, 3, 4, 84, 60, 73, NULL, 1),
(191, 'Njord Snio', 'DC', '', './assets/img/Cromos/Importados/instituto-zeus/njord-snio.webp', 3, 13, 4, 3, 93, 62, 88, NULL, 1),
(192, 'Nikas Himmelstein', 'MC', '', './assets/img/Cromos/Importados/instituto-zeus/nikas-himmelstein.webp', 3, 13, 2, 2, 63, 70, 65, NULL, 1),
(193, 'Taiga West', 'POR', '', './assets/img/Cromos/Importados/instituto-zeus/taiga-west.webp', 3, 13, 3, 1, 52, 85, 79, NULL, 1),
(194, 'Keira Donnell', 'DF', '', './assets/img/Cromos/Importados/instituto-zeus/keira-donnell.webp', 3, 13, 2, 4, 43, 71, 62, NULL, 1),
(195, 'Jack Skipper', 'DF', '', './assets/img/Cromos/Importados/instituto-zeus/jack-skipper.webp', 3, 13, 3, 2, 57, 79, 67, NULL, 1),
(196, 'Kazach', 'DF', '', './assets/img/Cromos/Importados/instituto-zeus/kazach.webp', 3, 13, 1, 1, 44, 67, 59, NULL, 1),
(197, 'Plumian Whitlock', 'MC', '', './assets/img/Cromos/Importados/instituto-zeus/plumian-whitlock.webp', 3, 13, 1, 3, 61, 62, 64, NULL, 1),
(198, 'Folie Vora', 'DC', '', './assets/img/Cromos/Importados/instituto-zeus/folie-vora.webp', 3, 13, 2, 2, 83, 54, 60, NULL, 1),
(199, 'Anas Eidah', 'DF', '', './assets/img/Cromos/Importados/instituto-zeus/anas-eidah.webp', 3, 13, 1, 1, 50, 67, 56, NULL, 1),
(200, 'Boris Kaprichyov', 'DF', '', './assets/img/Cromos/Importados/instituto-zeus/boris-kaprichyov.webp', 3, 13, 1, 4, 39, 63, 60, NULL, 1),
(201, 'Vozinha', 'POR', '', './assets/img/Cromos/Importados/instituto-zeus/bear-zurka.webp', 3, 13, 2, 4, 36, 79, 66, NULL, 1),
(202, 'Tzeikh', 'MC', '', './assets/img/Cromos/Importados/instituto-zeus/tzeikh.webp', 3, 13, 1, 1, 50, 50, 58, NULL, 1),
(203, 'Uxley Allen', 'DC', '', './assets/img/Cromos/Importados/instituto-zeus/uxley-allen.webp', 3, 13, 1, 3, 69, 37, 55, NULL, 1),
(204, 'Warren Cool', 'DC', '', './assets/img/Cromos/Importados/instituto-zeus/warren-cool.webp', 3, 13, 1, 2, 64, 45, 55, NULL, 1),
(205, 'Zatang', 'MC', '', './assets/img/Cromos/Importados/instituto-zeus/zatang.webp', 3, 13, 1, 2, 58, 62, 60, NULL, 1),
(207, 'Hotel', 'POR', '', './assets/img/Cromos/Importados/big-bang/hotel.webp', 3, 15, 1, 1, 24, 68, 59, NULL, 1),
(208, 'Goldie Lemmon', 'DF', '', './assets/img/Cromos/Importados/big-bang/goldie-lemmon.webp', 3, 15, 2, 2, 57, 70, 54, NULL, 1),
(209, 'Mark Hillvalley', 'DF', '', './assets/img/Cromos/Importados/big-bang/mark-hillvalley.webp', 3, 15, 2, 1, 43, 70, 55, NULL, 1),
(210, 'Byron Love', 'MC', '', './assets/img/Cromos/Importados/big-bang/byron-love.webp', 3, 15, 3, 4, 71, 70, 76, NULL, 1),
(211, 'Baek Bull-wo', 'MC', '', './assets/img/Cromos/Importados/big-bang/baek-bullwo.webp', 3, 15, 3, 2, 68, 79, 73, NULL, 1),
(212, 'Keenan DiFortune', 'POR', '', './assets/img/Cromos/Importados/big-bang/keenan-difortune.webp', 3, 15, 1, 4, 23, 68, 54, NULL, 1),
(213, 'Zeke Valanche', 'DC', '', './assets/img/Cromos/Importados/big-bang/zeke-valanche.webp', 3, 15, 3, 3, 82, 67, 72, NULL, 1),
(214, 'Steve Grim', 'MC', '', './assets/img/Cromos/Importados/big-bang/steve-grim.webp', 3, 15, 3, 3, 65, 74, 82, NULL, 1),
(215, 'Circulus Corona', 'DC', '', './assets/img/Cromos/Importados/big-bang/circulus-corona.webp', 3, 15, 4, 2, 96, 68, 74, NULL, 1),
(216, 'Holly Summers', 'MC', '', './assets/img/Cromos/Importados/big-bang/holly-summers.webp', 3, 15, 2, 2, 66, 71, 68, NULL, 1),
(217, 'Cedric Freud', 'MC', '', './assets/img/Cromos/Importados/big-bang/cedric-freud.webp', 3, 15, 3, 4, 64, 69, 87, NULL, 1),
(218, 'Philip Star', 'MC', '', './assets/img/Cromos/Importados/big-bang/philip-star.webp', 3, 15, 3, 3, 70, 72, 75, NULL, 1),
(219, 'Hastar Savitar', 'DF', '', './assets/img/Cromos/Importados/big-bang/hastar-savitar.webp', 3, 15, 1, 3, 42, 64, 53, NULL, 1),
(220, 'Milly Vacey', 'DF', '', './assets/img/Cromos/Importados/big-bang/milly-vacey.webp', 3, 15, 2, 4, 56, 67, 55, NULL, 1),
(221, 'Mai Assamu', 'DF', '', './assets/img/Cromos/Importados/big-bang/mai-assamu.webp', 3, 15, 1, 3, 41, 64, 57, NULL, 1),
(222, 'Astro Justin', 'MC', '', './assets/img/Cromos/Importados/big-bang/astro-justin.webp', 3, 15, 2, 1, 56, 59, 71, NULL, 1),
(223, 'Hannah Breer', 'DC', '', './assets/img/Cromos/Importados/big-bang/hannah-breer.webp', 3, 15, 2, 3, 71, 58, 60, NULL, 1),
(224, 'Piero Mantisse', 'DC', '', './assets/img/Cromos/Importados/big-bang/piero-mantisse.webp', 3, 15, 2, 4, 78, 46, 62, NULL, 1),
(225, 'Duane Jerus', 'DC', '', './assets/img/Cromos/Importados/big-bang/duane-jerus.webp', 3, 15, 2, 3, 74, 47, 63, NULL, 1),
(226, 'Severin Volei', 'DC', '', './assets/img/Cromos/Importados/big-bang/severin-volei.webp', 3, 15, 1, 1, 72, 47, 57, NULL, 1),
(227, 'Escudo Big Bang', 'ESCUDO', '', '', 3, 15, 5, 5, 0, 0, 0, NULL, 1),
(228, 'Pepe Ballester', 'ENT', '', '', 3, 15, 5, 5, 0, 0, 0, NULL, 1),
(229, 'Anita Navarro', 'GER', '', '', 3, 15, 5, 5, 0, 0, 0, NULL, 1),
(230, 'Sierra', 'MC', '', './assets/img/Cromos/Importados/triple-c/sierra.webp', 3, 4, 3, 4, 77, 68, 77, NULL, 1),
(231, 'Sterne', 'DC', '', './assets/img/Cromos/Importados/triple-c/sterne.webp', 3, 4, 2, 4, 80, 50, 68, NULL, 1),
(232, 'Bash Lancer', 'DC', '', './assets/img/Cromos/Importados/triple-c/bash-lancer.webp', 3, 4, 3, 4, 78, 55, 67, NULL, 1),
(233, 'Orlando Pacelli', 'DC', '', './assets/img/Cromos/Importados/triple-c/orlando-pacelli.webp', 3, 4, 2, 1, 80, 53, 63, NULL, 1),
(234, 'Mercury', 'DC', '', './assets/img/Cromos/Importados/triple-c/mercury.webp', 3, 4, 1, 3, 65, 43, 51, NULL, 1),
(235, 'Theo Divine', 'POR', '', './assets/img/Cromos/Importados/triple-c/theo-divine.webp', 3, 4, 2, 1, 32, 74, 70, NULL, 1),
(236, 'Zyne Hanging', 'DF', '', './assets/img/Cromos/Importados/triple-c/zyne-hanging.webp', 3, 4, 2, 3, 52, 77, 63, NULL, 1),
(237, 'Sylvia Lagus', 'DF', '', './assets/img/Cromos/Importados/triple-c/sylvia-lagus.webp', 3, 4, 2, 4, 49, 75, 67, NULL, 1),
(238, 'Madison Barada', 'DF', '', './assets/img/Cromos/Importados/triple-c/madison-barada.webp', 3, 4, 1, 1, 42, 69, 60, NULL, 1),
(239, 'Dione Bolide', 'MC', '', './assets/img/Cromos/Importados/triple-c/dione-bolide.webp', 3, 4, 1, 1, 49, 52, 64, NULL, 1),
(240, 'Dylan Keats', 'DC', '', './assets/img/Cromos/Importados/triple-c/dylan-keats.webp', 3, 4, 2, 2, 82, 47, 65, NULL, 1),
(241, 'Queenie Blackcrown', 'DC', '', './assets/img/Cromos/Importados/triple-c/queenie-blackcrown.webp', 3, 4, 3, 2, 85, 67, 67, NULL, 1),
(242, 'Darius Cyrus', 'MC', '', './assets/img/Cromos/Importados/triple-c/darius-cyrus.webp', 3, 4, 1, 3, 58, 59, 64, NULL, 1),
(243, 'Rook Blackside', 'MC', '', './assets/img/Cromos/Importados/triple-c/rook-blackside.webp', 3, 4, 1, 2, 58, 49, 67, NULL, 1),
(244, 'Jack Tradesman', 'DC', '', './assets/img/Cromos/Importados/triple-c/jack-tradesman.webp', 3, 4, 1, 1, 77, 41, 60, NULL, 1),
(245, 'Cory Andish', 'DC', '', './assets/img/Cromos/Importados/triple-c/cory-andish.webp', 3, 4, 1, 4, 65, 40, 62, NULL, 1),
(246, 'Martin Sheller', 'DC', '', './assets/img/Cromos/Importados/triple-c/martin-sheller.webp', 3, 4, 1, 2, 77, 47, 57, NULL, 1),
(247, 'Kingley Blackcrown', 'POR', '', './assets/img/Cromos/Importados/triple-c/kingley-blackcrown.webp', 3, 4, 1, 2, 26, 68, 57, NULL, 1),
(248, 'Len Grayves', 'DC', '', './assets/img/Cromos/Importados/triple-c/len-grayves.webp', 3, 4, 1, 3, 77, 50, 51, NULL, 1),
(249, 'Escudo Triple C', 'ESCUDO', '', '', 3, 4, 5, 5, 0, 0, 0, NULL, 1),
(250, 'Marsilio Magno', 'ENT', '', '', 3, 4, 5, 5, 0, 0, 0, NULL, 1),
(251, 'Jethro Mackin', 'GER', '', '', 3, 4, 5, 5, 0, 0, 0, NULL, 1),
(252, 'Zander Warmington', 'POR', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/zander-warmington.webp', 3, 9, 3, 2, 39, 84, 72, NULL, 1),
(253, 'Hurley Kane', 'DF', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/hurley-kane.webp', 3, 9, 3, 3, 55, 83, 65, NULL, 1),
(254, 'Tom Gato', 'DF', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/tom-gato.webp', 3, 9, 1, 4, 48, 67, 56, NULL, 1),
(255, 'Wesley Knox', 'MC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/wesley-knox.webp', 3, 9, 1, 4, 58, 60, 67, NULL, 1),
(256, 'Nathan Swift', 'DC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/nathan-swift.webp', 3, 9, 3, 3, 77, 60, 67, NULL, 1),
(257, 'Charlie', 'MC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/charlie.webp', 3, 9, 3, 2, 69, 68, 77, NULL, 1),
(258, 'Thaddeus Bellefax', 'MC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/thaddeus-bellefax.webp', 3, 9, 4, 4, 76, 87, 88, NULL, 1),
(259, 'Sergi Hernández', 'DC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/sergi-hernandez.webp', 3, 9, 3, 1, 76, 65, 71, NULL, 1),
(260, 'Tai Richter', 'MC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/tai-richter.webp', 3, 9, 1, 3, 56, 55, 58, NULL, 1),
(261, 'Golgi Tai', 'DF', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/golgi-tai.webp', 3, 9, 2, 1, 54, 68, 54, NULL, 1),
(262, 'Radon Mons', 'DF', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/radon-mons.webp', 3, 9, 1, 2, 49, 60, 61, NULL, 1),
(263, 'Malcolm Shirker', 'MC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/malcolm-shirker.webp', 3, 9, 1, 3, 53, 60, 66, NULL, 1),
(264, 'Infinity Beyond', 'DC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/infinity-beyond.webp', 3, 9, 2, 2, 69, 58, 70, NULL, 1),
(265, 'Alex Copple', 'POR', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/alex-copple.webp', 3, 9, 1, 1, 35, 67, 59, NULL, 1),
(266, 'Robert Lee', 'DC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/robert-lee.webp', 3, 9, 1, 1, 71, 51, 60, NULL, 1),
(267, 'Grant Stodgell', 'DC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/grant-stodgell.webp', 3, 9, 1, 2, 69, 40, 55, NULL, 1),
(268, 'Xavier Shotwell', 'POR', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/xavier-shotwell.webp', 3, 9, 1, 4, 36, 76, 60, NULL, 1),
(269, 'Adora Shivers', 'MC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/adora-shivers.webp', 3, 9, 1, 3, 52, 49, 57, NULL, 1),
(270, 'Ian Flappable', 'DC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/ian-flappable.webp', 3, 9, 1, 4, 71, 39, 63, NULL, 1),
(271, 'Nobby Naga', 'DC', '', './assets/img/Cromos/Importados/colegio-poderosa-fe/nobby-naga.webp', 3, 9, 1, 3, 72, 43, 63, NULL, 1),
(272, 'Escudo Colegio Poderosa Fe', 'ESCUDO', '', '', 3, 9, 5, 5, 0, 0, 0, NULL, 1),
(273, 'Mister Yi', 'ENT', '', '', 3, 9, 5, 5, 0, 0, 0, NULL, 1),
(274, 'Regina Mulgrave', 'GER', '', '', 3, 9, 5, 5, 0, 0, 0, NULL, 1),
(275, 'Goldus Janque', 'POR', '', './assets/img/Cromos/Importados/gar/goldus-janque.webp', 3, 10, 1, 1, 29, 69, 57, NULL, 1),
(276, 'Rondula Flehl', 'MC', '', './assets/img/Cromos/Importados/gar/rondula-flehl.webp', 3, 10, 1, 3, 58, 52, 56, NULL, 1),
(277, 'Zhuge Liang', 'MC', '', './assets/img/Cromos/Importados/gar/zhuge-liang.webp', 3, 10, 2, 3, 70, 60, 70, NULL, 1),
(278, 'Sol Daystar', 'DC', '', './assets/img/Cromos/Importados/gar/sol-daystar.webp', 3, 10, 4, 2, 95, 65, 79, NULL, 1),
(279, 'Desmodus Drakul', 'DC', '', './assets/img/Cromos/Importados/gar/desmodus-drakul.webp', 3, 10, 3, 1, 89, 63, 70, NULL, 1),
(280, 'Hiro Hughes', 'DC', '', './assets/img/Cromos/Importados/gar/hiro-hughes.webp', 3, 10, 1, 3, 73, 48, 64, NULL, 1),
(281, 'Chitoh', 'POR', '', './assets/img/Cromos/Importados/gar/chitoh.webp', 3, 10, 2, 4, 45, 79, 64, NULL, 1),
(282, 'Damien Blackloch', 'DC', '', './assets/img/Cromos/Importados/gar/damien-blackloch.webp', 3, 10, 2, 3, 79, 49, 62, NULL, 1),
(283, 'Mintaka', 'DF', '', './assets/img/Cromos/Importados/gar/mintaka.webp', 3, 10, 1, 2, 51, 61, 51, NULL, 1),
(284, 'Yurkeh', 'DF', '', './assets/img/Cromos/Importados/gar/yurkeh.webp', 3, 10, 1, 4, 44, 69, 52, NULL, 1),
(285, 'Fei Rune', 'DC', '', './assets/img/Cromos/Importados/gar/fei-rune.webp', 3, 10, 4, 4, 91, 72, 79, NULL, 1),
(286, 'Avinash Chowdhury', 'DF', '', './assets/img/Cromos/Importados/gar/avinash-chowdhury.webp', 3, 10, 2, 3, 49, 77, 66, NULL, 1),
(287, 'Iro Armussel', 'DC', '', './assets/img/Cromos/Importados/gar/iro-armussel.webp', 3, 10, 2, 3, 70, 54, 65, NULL, 1),
(288, 'Jerome Knight', 'DC', '', './assets/img/Cromos/Importados/gar/jerome-knight.webp', 3, 10, 1, 2, 76, 45, 64, NULL, 1),
(289, 'Romica Glimmerly', 'DC', '', './assets/img/Cromos/Importados/gar/romica-glimmerly.webp', 3, 10, 1, 1, 70, 50, 63, NULL, 1),
(290, 'Gruff Dorman', 'DC', '', './assets/img/Cromos/Importados/gar/gruff-dorman.webp', 3, 10, 1, 2, 73, 39, 59, NULL, 1),
(291, 'Ryuun Cho', 'DC', '', './assets/img/Cromos/Importados/gar/ryuun-cho.webp', 3, 10, 1, 1, 67, 48, 60, NULL, 1),
(292, 'Alan McCallum', 'DC', '', './assets/img/Cromos/Importados/gar/alan-mccallum.webp', 3, 10, 1, 4, 69, 45, 52, NULL, 1),
(293, 'Trane Levitt', 'DC', '', './assets/img/Cromos/Importados/gar/trane-levitt.webp', 3, 10, 1, 3, 63, 39, 50, NULL, 1),
(294, 'Donovan Brasswagon', 'DC', '', './assets/img/Cromos/Importados/gar/donovan-brasswagon.webp', 3, 10, 1, 3, 67, 37, 62, NULL, 1),
(295, 'Escudo Gar', 'ESCUDO', '', '', 3, 10, 5, 5, 0, 0, 0, NULL, 1),
(296, 'Seymour Hillman', 'ENT', '', '', 3, 10, 5, 5, 0, 0, 0, NULL, 1),
(297, 'Silvia Woods', 'GER', '', '', 3, 10, 5, 5, 0, 0, 0, NULL, 1),
(298, 'Gareth Flare', 'DC', '', './assets/img/Cromos/Importados/servicio-secreto/gareth-flare.webp', 3, 11, 3, 2, 77, 59, 79, NULL, 1),
(299, 'Sonny Raimon', 'MC', '', './assets/img/Cromos/Importados/servicio-secreto/sonny-raimon.webp', 3, 11, 1, 1, 61, 55, 57, NULL, 1),
(300, 'Dekih', 'MC', '', './assets/img/Cromos/Importados/servicio-secreto/dekih.webp', 3, 11, 2, 4, 61, 68, 72, NULL, 1),
(301, 'Zanos', 'DC', '', './assets/img/Cromos/Importados/servicio-secreto/zanos.webp', 3, 11, 1, 4, 72, 40, 52, NULL, 1),
(302, 'Soldado de Terracota 1', 'POR', '', './assets/img/Cromos/Importados/servicio-secreto/soldado-de-terracota-1.webp', 3, 11, 3, 3, 49, 77, 71, NULL, 1),
(303, 'Jimmy Mach', 'DF', '', './assets/img/Cromos/Importados/servicio-secreto/jimmy-mach.webp', 3, 11, 1, 3, 38, 65, 60, NULL, 1),
(304, 'Marv Errick', 'DF', '', './assets/img/Cromos/Importados/servicio-secreto/marv-errick.webp', 3, 11, 1, 2, 43, 67, 61, NULL, 1),
(305, 'Knight Reader', 'DF', '', './assets/img/Cromos/Importados/servicio-secreto/knight-reader.webp', 3, 11, 2, 1, 44, 78, 65, NULL, 1),
(306, 'Dawson Little', 'DF', '', './assets/img/Cromos/Importados/servicio-secreto/dawson-little.webp', 3, 11, 2, 3, 47, 72, 64, NULL, 1),
(307, 'Banda Crix Junior', 'DF', '', './assets/img/Cromos/Importados/servicio-secreto/banda-crix-junior.webp', 3, 11, 1, 3, 40, 58, 50, NULL, 1),
(308, 'Jade Beor', 'DC', '', './assets/img/Cromos/Importados/servicio-secreto/jade-beor.webp', 3, 11, 3, 4, 80, 61, 68, NULL, 1),
(309, 'Seth Bael', 'DC', '', './assets/img/Cromos/Importados/servicio-secreto/seth-bael.webp', 3, 11, 3, 1, 88, 65, 80, NULL, 1),
(310, 'Choen Ryo', 'DC', '', './assets/img/Cromos/Importados/servicio-secreto/choen-ryo.webp', 3, 11, 1, 1, 74, 51, 54, NULL, 1),
(311, 'Claria Leone', 'DC', '', './assets/img/Cromos/Importados/servicio-secreto/claria-leone.webp', 3, 11, 1, 3, 63, 43, 64, NULL, 1),
(312, 'Stan Roller', 'DC', '', './assets/img/Cromos/Importados/servicio-secreto/stan-roller.webp', 3, 11, 1, 3, 66, 39, 59, NULL, 1),
(313, 'Dryden Zephyr', 'MC', '', './assets/img/Cromos/Importados/servicio-secreto/dryden-zephyr.webp', 3, 11, 1, 1, 53, 56, 60, NULL, 1),
(314, 'Émile Lazare', 'POR', '', './assets/img/Cromos/Importados/servicio-secreto/emile-lazare.webp', 3, 11, 1, 1, 32, 75, 59, NULL, 1),
(315, 'Auberon Ouranos', 'DF', '', './assets/img/Cromos/Importados/servicio-secreto/auberon-ouranos.webp', 3, 11, 1, 4, 50, 69, 60, NULL, 1),
(316, 'Ms QT', 'MC', '', './assets/img/Cromos/Importados/servicio-secreto/ms-qt.webp', 3, 11, 1, 1, 56, 62, 56, NULL, 1),
(317, 'Tosh Coach', 'DF', '', './assets/img/Cromos/Importados/servicio-secreto/tosh-coach.webp', 3, 11, 1, 1, 50, 64, 51, NULL, 1),
(318, 'Escudo Servicio Secreto', 'ESCUDO', '', '', 3, 11, 5, 5, 0, 0, 0, NULL, 1),
(319, 'Soyogi Soyogi', 'ENT', '', '', 3, 11, 5, 5, 0, 0, 0, NULL, 1),
(320, 'Silica Fieltour', 'GER', '', '', 3, 11, 5, 5, 0, 0, 0, NULL, 1),
(321, 'Nero Night', 'POR', '', './assets/img/Cromos/Importados/zanark-domain/nero-night.webp', 3, 2, 2, 2, 36, 70, 68, NULL, 1),
(322, 'Scaris Cowler', 'DC', '', './assets/img/Cromos/Importados/zanark-domain/scaris-cowler.webp', 3, 2, 2, 2, 78, 54, 61, NULL, 1),
(323, 'Raleigh Greenstreet', 'DC', '', './assets/img/Cromos/Importados/zanark-domain/raleigh-greenstreet.webp', 3, 2, 4, 4, 85, 74, 86, NULL, 1),
(324, 'Lotus', 'POR', '', './assets/img/Cromos/Importados/zanark-domain/lotus.webp', 3, 2, 1, 1, 27, 64, 53, NULL, 1),
(325, 'Sonny Wright', 'MC', '', './assets/img/Cromos/Importados/zanark-domain/sonny-wright.webp', 3, 2, 3, 2, 77, 75, 81, NULL, 1),
(326, 'Jude Sharp', 'MC', '', './assets/img/Cromos/Importados/zanark-domain/jude-sharp.webp', 3, 2, 3, 3, 67, 79, 86, NULL, 1),
(327, 'Zack Avalon', 'DC', '', './assets/img/Cromos/Importados/zanark-domain/zack-avalon.webp', 3, 2, 3, 4, 87, 67, 67, NULL, 1),
(328, 'Zanark Avalonic', 'DC', '', './assets/img/Cromos/Importados/zanark-domain/zanark-avalonic.webp', 3, 2, 4, 1, 94, 68, 88, NULL, 1),
(329, 'David Samford', 'DC', '', './assets/img/Cromos/Importados/zanark-domain/david-samford.webp', 3, 2, 3, 4, 87, 65, 69, NULL, 1),
(330, 'Raider Takkel', 'DF', '', './assets/img/Cromos/Importados/zanark-domain/raider-takkel.webp', 3, 2, 1, 4, 50, 58, 56, NULL, 1),
(331, 'Tasuke', 'DF', '', './assets/img/Cromos/Importados/zanark-domain/tasuke.webp', 3, 2, 2, 4, 44, 68, 56, NULL, 1),
(332, 'Maia', 'DF', '', './assets/img/Cromos/Importados/zanark-domain/maia.webp', 3, 2, 1, 4, 41, 64, 61, NULL, 1),
(333, 'Lou Beigh', 'POR', '', './assets/img/Cromos/Importados/zanark-domain/lou-beigh.webp', 3, 2, 1, 4, 23, 71, 57, NULL, 1),
(334, 'Hilda Bronski', 'DC', '', './assets/img/Cromos/Importados/zanark-domain/hilda-bronski.webp', 3, 2, 1, 2, 76, 39, 59, NULL, 1),
(335, 'Carys Chaparon', 'DC', '', './assets/img/Cromos/Importados/zanark-domain/carys-chaparon.webp', 3, 2, 1, 3, 69, 46, 60, NULL, 1),
(336, 'Ton Nino', 'MC', '', './assets/img/Cromos/Importados/zanark-domain/ton-nino.webp', 3, 2, 1, 3, 56, 63, 66, NULL, 1),
(337, 'Tim Roubadour', 'MC', '', './assets/img/Cromos/Importados/zanark-domain/tim-roubadour.webp', 3, 2, 1, 4, 56, 53, 56, NULL, 1),
(338, 'Reggie Alldark', 'MC', '', './assets/img/Cromos/Importados/zanark-domain/reggie-alldark.webp', 3, 2, 1, 2, 52, 61, 66, NULL, 1),
(339, 'Gary Baldi', 'DF', '', './assets/img/Cromos/Importados/zanark-domain/gary-baldi.webp', 3, 2, 1, 2, 42, 70, 54, NULL, 1),
(340, 'Escudo Zanark Domain', 'ESCUDO', '', '', 3, 2, 5, 5, 0, 0, 0, NULL, 1),
(341, 'Astero Black', 'ENT', '', '', 3, 2, 5, 5, 0, 0, 0, NULL, 1),
(342, 'Meina Maiden', 'GER', '', '', 3, 2, 5, 5, 0, 0, 0, NULL, 1),
(343, 'Salvador Castell', 'POR', '', './assets/img/Cromos/Importados/mary-times/salvador-castell.webp', 3, 7, 1, 1, 35, 73, 55, NULL, 1),
(344, 'Mark Gambling', 'MC', '', './assets/img/Cromos/Importados/mary-times/mark-gambling.webp', 3, 7, 3, 3, 69, 69, 79, NULL, 1),
(345, 'Said Ashraf', 'DC', '', './assets/img/Cromos/Importados/mary-times/said-ashraf.webp', 3, 7, 1, 1, 68, 43, 61, NULL, 1),
(346, 'Ricky Regomen', 'POR', '', './assets/img/Cromos/Importados/mary-times/ricky-regomen.webp', 3, 7, 1, 4, 35, 70, 57, NULL, 1),
(347, 'Aimé Quintet', 'DF', '', './assets/img/Cromos/Importados/mary-times/aime-quintet.webp', 3, 7, 1, 3, 51, 68, 52, NULL, 1),
(348, 'Alan Master', 'MC', '', './assets/img/Cromos/Importados/mary-times/alan-master.webp', 3, 7, 2, 3, 56, 68, 75, NULL, 1),
(349, 'Jay Yuu', 'POR', '', './assets/img/Cromos/Importados/mary-times/jay-yuu.webp', 3, 7, 1, 2, 28, 71, 63, NULL, 1),
(350, 'Alegre', 'DC', '', './assets/img/Cromos/Importados/mary-times/alegre.webp', 3, 7, 1, 4, 73, 38, 57, NULL, 1),
(351, 'Doug McArthur', 'DC', '', './assets/img/Cromos/Importados/mary-times/doug-mcarthur.webp', 3, 7, 1, 3, 64, 42, 51, NULL, 1),
(352, 'Helena Harwood', 'POR', '', './assets/img/Cromos/Importados/mary-times/helena-harwood.webp', 3, 7, 1, 3, 29, 76, 61, NULL, 1),
(353, 'Rocco Shale', 'DF', '', './assets/img/Cromos/Importados/mary-times/rocco-shale.webp', 3, 7, 2, 2, 48, 70, 54, NULL, 1),
(354, 'Howard Robin', 'DF', '', './assets/img/Cromos/Importados/mary-times/howard-robin.webp', 3, 7, 2, 1, 57, 79, 66, NULL, 1),
(355, 'Carlos Arroyo', 'DF', '', './assets/img/Cromos/Importados/mary-times/carlos-arroyo.webp', 3, 7, 2, 1, 50, 76, 62, NULL, 1),
(356, 'Slim Pointer', 'MC', '', './assets/img/Cromos/Importados/mary-times/slim-pointer.webp', 3, 7, 1, 1, 61, 63, 64, NULL, 1),
(357, 'Gary Primo', 'DC', '', './assets/img/Cromos/Importados/mary-times/gary-primo.webp', 3, 7, 2, 2, 80, 54, 71, NULL, 1),
(358, 'Georgio Plumber', 'DC', '', './assets/img/Cromos/Importados/mary-times/georgio-plumber.webp', 3, 7, 2, 3, 78, 47, 66, NULL, 1),
(359, 'Keines Elvain', 'DC', '', './assets/img/Cromos/Importados/mary-times/keines-elvain.webp', 3, 7, 1, 4, 72, 38, 62, NULL, 1),
(360, 'Stan Treece', 'MC', '', './assets/img/Cromos/Importados/mary-times/stan-treece.webp', 3, 7, 1, 3, 52, 49, 57, NULL, 1),
(361, 'Noel Taurius', 'DC', '', './assets/img/Cromos/Importados/mary-times/noel-taurius.webp', 3, 7, 1, 2, 64, 48, 62, NULL, 1),
(362, 'Dolph Hensen', 'MC', '', './assets/img/Cromos/Importados/mary-times/dolph-hensen.webp', 3, 7, 1, 3, 50, 53, 66, NULL, 1),
(363, 'Escudo Mary Times', 'ESCUDO', '', '', 3, 7, 5, 5, 0, 0, 0, NULL, 1),
(364, 'Axel Blaze', 'ENT', '', '', 3, 7, 5, 5, 0, 0, 0, NULL, 1),
(365, 'Emperio N. Guinn', 'GER', '', '', 3, 7, 5, 5, 0, 0, 0, NULL, 1),
(366, 'Martin Fleet', 'POR', '', './assets/img/Cromos/Importados/royal-academy/martin-fleet.webp', 3, 17, 2, 4, 40, 70, 62, NULL, 1),
(367, 'Perrick Ambin', 'POR', '', './assets/img/Cromos/Importados/royal-academy/perrick-ambin.webp', 3, 17, 1, 2, 26, 70, 65, NULL, 1),
(368, 'Arthur Easley', 'DF', '', './assets/img/Cromos/Importados/royal-academy/arthur-easley.webp', 3, 17, 1, 1, 41, 60, 51, NULL, 1),
(369, 'Kagraro Ephemera', 'DF', '', './assets/img/Cromos/Importados/royal-academy/kagraro-ephemera.webp', 3, 17, 2, 4, 56, 73, 62, NULL, 1),
(370, 'Tau Rin', 'DF', '', './assets/img/Cromos/Importados/royal-academy/tau-rin.webp', 3, 17, 2, 3, 47, 78, 60, NULL, 1),
(371, 'Toya Boyde', 'DF', '', './assets/img/Cromos/Importados/royal-academy/toya-boyde.webp', 3, 17, 1, 2, 37, 58, 47, NULL, 1),
(372, 'Adam Hesive', 'DF', '', './assets/img/Cromos/Importados/royal-academy/adam-hesive.webp', 3, 17, 1, 2, 37, 71, 49, NULL, 1),
(373, 'Keshia Readingplace', 'DF', '', './assets/img/Cromos/Importados/royal-academy/keshia-readingplace.webp', 3, 17, 1, 4, 45, 60, 53, NULL, 1),
(374, 'Domenico Barberini', 'MC', '', './assets/img/Cromos/Importados/royal-academy/domenico-barberini.webp', 3, 17, 2, 3, 62, 58, 66, NULL, 1),
(375, 'Bindal Hermit', 'MC', '', './assets/img/Cromos/Importados/royal-academy/bindal-hermit.webp', 3, 17, 1, 2, 52, 61, 67, NULL, 1),
(376, 'Brandimarte Ruffo', 'MC', '', './assets/img/Cromos/Importados/royal-academy/brandimarte-ruffo.webp', 3, 17, 2, 1, 57, 63, 75, NULL, 1),
(377, 'Phineas Hudd', 'MC', '', './assets/img/Cromos/Importados/royal-academy/phineas-hudd.webp', 3, 17, 1, 3, 51, 55, 57, NULL, 1),
(378, 'Pinkie Harte', 'MC', '', './assets/img/Cromos/Importados/royal-academy/pinkie-harte.webp', 3, 17, 1, 1, 57, 58, 66, NULL, 1),
(379, 'Nahrje', 'MC', '', './assets/img/Cromos/Importados/royal-academy/nahrje.webp', 3, 17, 1, 2, 55, 63, 66, NULL, 1),
(380, 'Bountine', 'MC', '', './assets/img/Cromos/Importados/royal-academy/bountine.webp', 3, 17, 1, 4, 52, 51, 69, NULL, 1),
(381, 'Alfa', 'DC', '', './assets/img/Cromos/Importados/royal-academy/alfa.webp', 3, 17, 3, 2, 81, 53, 68, NULL, 1),
(382, 'Mike', 'DC', '', './assets/img/Cromos/Importados/royal-academy/mike.webp', 3, 17, 2, 4, 70, 50, 58, NULL, 1),
(383, 'Wittz', 'DC', '', './assets/img/Cromos/Importados/royal-academy/wittz.webp', 3, 17, 1, 4, 75, 37, 57, NULL, 1),
(384, 'Roland Lex', 'DC', '', './assets/img/Cromos/Importados/royal-academy/roland-lex.webp', 3, 17, 1, 3, 74, 48, 63, NULL, 1),
(385, 'Bailong', 'DC', '', './assets/img/Cromos/Importados/royal-academy/bailong.webp', 3, 17, 4, 3, 88, 70, 76, NULL, 1),
(386, 'Escudo Royal Academy', 'ESCUDO', '', '', 3, 17, 5, 5, 0, 0, 0, NULL, 1),
(387, 'Clint Loggan', 'ENT', '', '', 3, 17, 5, 5, 0, 0, 0, NULL, 1),
(388, 'Lynn Tambelle', 'GER', '', '', 3, 17, 5, 5, 0, 0, 0, NULL, 1),
(389, 'Meg Imai', 'POR', '', './assets/img/Cromos/Importados/instituto-otaku/meg-imai.webp', 3, 21, 1, 4, 35, 68, 58, NULL, 1),
(390, 'Marge Fielding', 'DF', '', './assets/img/Cromos/Importados/instituto-otaku/marge-fielding.webp', 3, 21, 1, 3, 46, 68, 53, NULL, 1),
(391, 'Terry Pinn', 'POR', '', './assets/img/Cromos/Importados/instituto-otaku/terry-pinn.webp', 3, 21, 1, 4, 35, 68, 63, NULL, 1),
(392, 'Karl Kappa', 'POR', '', './assets/img/Cromos/Importados/instituto-otaku/karl-kappa.webp', 3, 21, 3, 3, 40, 77, 74, NULL, 1),
(393, 'Silas Kaye', 'DF', '', './assets/img/Cromos/Importados/instituto-otaku/silas-kaye.webp', 3, 21, 2, 2, 51, 79, 58, NULL, 1),
(394, 'Radd Ischer', 'DF', '', './assets/img/Cromos/Importados/instituto-otaku/radd-ischer.webp', 3, 21, 1, 4, 51, 60, 53, NULL, 1),
(395, 'Reina de los Dragones', 'DF', '', './assets/img/Cromos/Importados/instituto-otaku/reina-de-los-dragones.webp', 3, 21, 2, 2, 55, 78, 54, NULL, 1),
(396, 'Cherrie Menjikko', 'DF', '', './assets/img/Cromos/Importados/instituto-otaku/cherrie-menjikko.webp', 3, 21, 2, 1, 43, 67, 58, NULL, 1),
(397, 'Dave Lung', 'MC', '', './assets/img/Cromos/Importados/instituto-otaku/dave-lung.webp', 3, 21, 2, 3, 64, 63, 65, NULL, 1),
(398, 'Argento Salvage', 'MC', '', './assets/img/Cromos/Importados/instituto-otaku/argento-salvage.webp', 3, 21, 2, 2, 67, 62, 75, NULL, 1),
(399, 'Duff Dooley', 'MC', '', './assets/img/Cromos/Importados/instituto-otaku/duff-dooley.webp', 3, 21, 2, 1, 64, 69, 72, NULL, 1),
(400, 'Frankie Boulder', 'DC', '', './assets/img/Cromos/Importados/instituto-otaku/frankie-boulder.webp', 3, 21, 2, 4, 75, 46, 63, NULL, 1),
(401, 'Marvin Murdock', 'DC', '', './assets/img/Cromos/Importados/instituto-otaku/marvin-murdock.webp', 3, 21, 3, 2, 79, 64, 77, NULL, 1),
(402, 'Kit Cymbal', 'DF', '', './assets/img/Cromos/Importados/instituto-otaku/kit-cymbal.webp', 3, 21, 1, 2, 48, 58, 47, NULL, 1),
(403, 'Buster Clout', 'DF', '', './assets/img/Cromos/Importados/instituto-otaku/buster-clout.webp', 3, 21, 1, 2, 45, 61, 48, NULL, 1),
(404, 'Maston Color', 'MC', '', './assets/img/Cromos/Importados/instituto-otaku/maston-color.webp', 3, 21, 1, 1, 48, 62, 68, NULL, 1),
(405, 'Barney Snellman', 'POR', '', './assets/img/Cromos/Importados/instituto-otaku/barney-snellman.webp', 3, 21, 1, 2, 25, 75, 64, NULL, 1),
(406, 'Lucien Rarey', 'MC', '', './assets/img/Cromos/Importados/instituto-otaku/lucien-rarey.webp', 3, 21, 1, 3, 48, 52, 67, NULL, 1),
(407, 'Bigsby Pigford', 'DC', '', './assets/img/Cromos/Importados/instituto-otaku/bigsby-pigford.webp', 3, 21, 3, 3, 78, 54, 80, NULL, 1),
(408, 'Escudo Instituto Otaku', 'ESCUDO', '', '', 3, 21, 5, 5, 0, 0, 0, NULL, 1),
(409, 'Clifford Chillard', 'ENT', '', '', 3, 21, 5, 5, 0, 0, 0, NULL, 1),
(410, 'Raz Pond', 'GER', '', '', 3, 21, 5, 5, 0, 0, 0, NULL, 1),
(411, 'Ringo Saturn', 'POR', '', './assets/img/Cromos/Importados/cala-pirata/ringo-saturn.webp', 3, 16, 2, 1, 38, 77, 59, NULL, 1),
(412, 'Fantaghirò Petrucci', 'MC', '', './assets/img/Cromos/Importados/cala-pirata/fantaghiro-petrucci.webp', 3, 16, 1, 3, 62, 58, 62, NULL, 1),
(413, 'Marisol Cavallo', 'DF', '', './assets/img/Cromos/Importados/cala-pirata/marisol-cavallo.webp', 3, 16, 1, 2, 46, 69, 48, NULL, 1),
(414, 'Dracon Yale', 'DF', '', './assets/img/Cromos/Importados/cala-pirata/dracon-yale.webp', 3, 16, 2, 3, 43, 65, 53, NULL, 1),
(415, 'Harper Evans', 'DC', '', './assets/img/Cromos/Importados/cala-pirata/harper-evans.webp', 3, 16, 3, 1, 78, 58, 72, NULL, 1),
(416, 'Acker Reese', 'DF', '', './assets/img/Cromos/Importados/cala-pirata/acker-reese.webp', 3, 16, 3, 3, 61, 73, 61, NULL, 1),
(417, 'Gandares Baran', 'DC', '', './assets/img/Cromos/Importados/cala-pirata/gandares-baran.webp', 3, 16, 3, 4, 86, 59, 70, NULL, 1),
(418, 'Alix La Fontaine', 'POR', '', './assets/img/Cromos/Importados/cala-pirata/alix-la-fontaine.webp', 3, 16, 1, 3, 37, 65, 63, NULL, 1),
(419, 'Stu Hoofer', 'DF', '', './assets/img/Cromos/Importados/cala-pirata/stu-hoofer.webp', 3, 16, 2, 4, 49, 77, 60, NULL, 1),
(420, 'Julien Rousseau', 'MC', '', './assets/img/Cromos/Importados/cala-pirata/julien-rousseau.webp', 3, 16, 1, 1, 57, 57, 70, NULL, 1),
(421, 'Miguel Pereira', 'MC', '', './assets/img/Cromos/Importados/cala-pirata/miguel-pereira.webp', 3, 16, 1, 3, 55, 51, 67, NULL, 1),
(422, 'Chucky Cardaway', 'DC', '', './assets/img/Cromos/Importados/cala-pirata/chucky-cardaway.webp', 3, 16, 2, 2, 71, 53, 61, NULL, 1),
(423, 'Oliviero Albizzi', 'POR', '', './assets/img/Cromos/Importados/cala-pirata/oliviero-albizzi.webp', 3, 16, 1, 4, 36, 67, 54, NULL, 1),
(424, 'Tiny Forester', 'DF', '', './assets/img/Cromos/Importados/cala-pirata/tiny-forester.webp', 3, 16, 1, 1, 47, 66, 59, NULL, 1),
(425, 'Plato Mail', 'DF', '', './assets/img/Cromos/Importados/cala-pirata/plato-mail.webp', 3, 16, 1, 2, 48, 60, 57, NULL, 1),
(426, 'Phil Noir', 'DC', '', './assets/img/Cromos/Importados/cala-pirata/phil-noir.webp', 3, 16, 1, 4, 69, 42, 56, NULL, 1),
(427, 'Aila Pavey', 'DC', '', './assets/img/Cromos/Importados/cala-pirata/aila-pavey.webp', 3, 16, 1, 3, 67, 41, 51, NULL, 1),
(428, 'Alfonso Íñigo', 'POR', '', './assets/img/Cromos/Importados/cala-pirata/alfonso-inigo.webp', 3, 16, 1, 2, 32, 71, 57, NULL, 1),
(429, 'Miguel Jiménez', 'DC', '', './assets/img/Cromos/Importados/cala-pirata/miguel-jimenez.webp', 3, 16, 1, 4, 65, 51, 53, NULL, 1),
(430, 'Kuhrach', 'MC', '', './assets/img/Cromos/Importados/cala-pirata/kuhrach.webp', 3, 16, 1, 2, 57, 49, 61, NULL, 1),
(431, 'Escudo Cala Pirata', 'ESCUDO', '', '', 3, 16, 5, 5, 0, 0, 0, NULL, 1),
(432, 'Clark von Wunderbar', 'ENT', '', '', 3, 16, 5, 5, 0, 0, 0, NULL, 1),
(433, 'Celeste Fairbourne', 'GER', '', '', 3, 16, 5, 5, 0, 0, 0, NULL, 1),
(434, 'Lump Trungus', 'DF', '', './assets/img/Cromos/Importados/instituto-occult/lump-trungus.webp', 3, 24, 2, 2, 56, 68, 60, NULL, 1),
(435, 'Flora', 'DC', '', './assets/img/Cromos/Importados/instituto-occult/flora.webp', 3, 24, 4, 3, 93, 71, 79, NULL, 1),
(436, 'Johan Tassman', 'DC', '', './assets/img/Cromos/Importados/instituto-occult/johan-tassman.webp', 3, 24, 2, 4, 78, 45, 63, NULL, 1),
(437, 'Ted Autumn', 'POR', '', './assets/img/Cromos/Importados/instituto-occult/ted-autumn.webp', 3, 24, 2, 3, 40, 75, 63, NULL, 1),
(438, 'A\'ddah', 'DF', '', './assets/img/Cromos/Importados/instituto-occult/addah.webp', 3, 24, 2, 1, 43, 76, 67, NULL, 1),
(439, 'Guy Kogan', 'DF', '', './assets/img/Cromos/Importados/instituto-occult/guy-kogan.webp', 3, 24, 1, 3, 45, 68, 57, NULL, 1),
(440, 'Cinqua Lightning', 'DF', '', './assets/img/Cromos/Importados/instituto-occult/cinqua-lightning.webp', 3, 24, 2, 4, 46, 67, 60, NULL, 1),
(441, 'Selenia Mund', 'MC', '', './assets/img/Cromos/Importados/instituto-occult/selenia-mund.webp', 3, 24, 1, 2, 54, 49, 60, NULL, 1),
(442, 'Hairy', 'MC', '', './assets/img/Cromos/Importados/instituto-occult/hairy.webp', 3, 24, 2, 4, 60, 57, 75, NULL, 1),
(443, 'Malphas', 'MC', '', './assets/img/Cromos/Importados/instituto-occult/malphas.webp', 3, 24, 2, 3, 60, 62, 77, NULL, 1),
(444, 'Fenric Wulfgang', 'DC', '', './assets/img/Cromos/Importados/instituto-occult/fenric-wulfgang.webp', 3, 24, 2, 2, 81, 58, 68, NULL, 1),
(445, 'Niyuta', 'DC', '', './assets/img/Cromos/Importados/instituto-occult/niyuta.webp', 3, 24, 2, 4, 79, 45, 65, NULL, 1);
INSERT INTO `cromos` (`id_cromo`, `nombre`, `posicion`, `descripcion`, `imagen`, `id_expansion`, `id_equipo`, `id_rareza`, `id_afinidad`, `ataque`, `defensa`, `tecnica`, `cupo_numerado`, `origen_importacion`) VALUES
(446, 'Marvin Reading', 'POR', '', './assets/img/Cromos/Importados/instituto-occult/marvin-reading.webp', 3, 24, 1, 2, 33, 72, 52, NULL, 1),
(447, 'Sammy Salter', 'POR', '', './assets/img/Cromos/Importados/instituto-occult/sammy-salter.webp', 3, 24, 1, 4, 30, 63, 53, NULL, 1),
(448, 'Troy Moon', 'MC', '', './assets/img/Cromos/Importados/instituto-occult/troy-moon.webp', 3, 24, 1, 2, 62, 53, 67, NULL, 1),
(449, 'Banda Crix', 'MC', '', './assets/img/Cromos/Importados/instituto-occult/banda-crix.webp', 3, 24, 1, 3, 52, 62, 66, NULL, 1),
(450, 'Denzel Harvest', 'DC', '', './assets/img/Cromos/Importados/instituto-occult/denzel-harvest.webp', 3, 24, 1, 2, 76, 38, 57, NULL, 1),
(451, 'Kreaton Coco', 'MC', '', './assets/img/Cromos/Importados/instituto-occult/kreaton-coco.webp', 3, 24, 1, 2, 56, 58, 58, NULL, 1),
(452, 'Hunter Wanabi', 'POR', '', './assets/img/Cromos/Importados/instituto-occult/hunter-wanabi.webp', 3, 24, 1, 3, 24, 76, 57, NULL, 1),
(453, 'Zayn Pinegrove', 'DF', '', './assets/img/Cromos/Importados/instituto-occult/zayn-pinegrove.webp', 3, 24, 1, 3, 46, 59, 55, NULL, 1),
(454, 'Escudo Instituto Occult', 'ESCUDO', '', '', 3, 24, 5, 5, 0, 0, 0, NULL, 1),
(455, 'Zanark Avalonic', 'ENT', '', '', 3, 24, 5, 5, 0, 0, 0, NULL, 1),
(456, 'Juno Hundertmark', 'GER', '', '', 3, 24, 5, 5, 0, 0, 0, NULL, 1),
(457, 'Victoria Vanguard', 'DF', '', './assets/img/Cromos/Importados/instituto-kirkwood/victoria-vanguard.webp', 3, 25, 3, 3, 64, 80, 71, NULL, 1),
(458, 'Hortensia Raintree', 'DF', '', './assets/img/Cromos/Importados/instituto-kirkwood/hortensia-raintree.webp', 3, 25, 2, 3, 57, 70, 65, NULL, 1),
(459, 'Bay Laurel', 'MC', '', './assets/img/Cromos/Importados/instituto-kirkwood/bay-laurel.webp', 3, 25, 2, 1, 56, 67, 66, NULL, 1),
(460, 'Torch', 'DC', '', './assets/img/Cromos/Importados/instituto-kirkwood/torch.webp', 3, 25, 4, 2, 83, 68, 76, NULL, 1),
(461, 'Edgar Partinus', 'DC', '', './assets/img/Cromos/Importados/instituto-kirkwood/edgar-partinus.webp', 3, 25, 4, 4, 85, 74, 88, NULL, 1),
(462, 'Bruce Lau', 'POR', '', './assets/img/Cromos/Importados/instituto-kirkwood/bruce-lau.webp', 3, 25, 2, 2, 35, 69, 63, NULL, 1),
(463, 'Morris Moore', 'DF', '', './assets/img/Cromos/Importados/instituto-kirkwood/morris-moore.webp', 3, 25, 1, 4, 37, 69, 60, NULL, 1),
(464, 'Lean', 'MC', '', './assets/img/Cromos/Importados/instituto-kirkwood/lean.webp', 3, 25, 2, 2, 56, 70, 68, NULL, 1),
(465, 'Rodh', 'MC', '', './assets/img/Cromos/Importados/instituto-kirkwood/rodh.webp', 3, 25, 2, 3, 62, 64, 69, NULL, 1),
(466, 'Kaien Spiradawn', 'DC', '', './assets/img/Cromos/Importados/instituto-kirkwood/kaien-spiradawn.webp', 3, 25, 2, 3, 78, 47, 70, NULL, 1),
(467, 'Millie Terry', 'DC', '', './assets/img/Cromos/Importados/instituto-kirkwood/millie-terry.webp', 3, 25, 2, 1, 70, 56, 72, NULL, 1),
(468, 'Chip Flashman', 'DC', '', './assets/img/Cromos/Importados/instituto-kirkwood/chip-flashman.webp', 3, 25, 1, 3, 67, 43, 60, NULL, 1),
(469, 'Morgan Sanders', 'POR', '', './assets/img/Cromos/Importados/instituto-kirkwood/morgan-sanders.webp', 3, 25, 1, 4, 23, 70, 62, NULL, 1),
(470, 'Grigham', 'MC', '', './assets/img/Cromos/Importados/instituto-kirkwood/grigham.webp', 3, 25, 1, 3, 60, 56, 64, NULL, 1),
(471, 'Hampton Harvard', 'MC', '', './assets/img/Cromos/Importados/instituto-kirkwood/hampton-harvard.webp', 3, 25, 1, 2, 60, 56, 70, NULL, 1),
(472, 'Eva Chatton', 'DC', '', './assets/img/Cromos/Importados/instituto-kirkwood/eva-chatton.webp', 3, 25, 1, 3, 74, 49, 52, NULL, 1),
(473, 'Elinor Maven', 'DC', '', './assets/img/Cromos/Importados/instituto-kirkwood/elinor-maven.webp', 3, 25, 1, 4, 76, 46, 63, NULL, 1),
(474, 'Larry Pogue', 'DC', '', './assets/img/Cromos/Importados/instituto-kirkwood/larry-pogue.webp', 3, 25, 1, 4, 72, 43, 60, NULL, 1),
(475, 'Escudo Instituto Kirkwood', 'ESCUDO', '', '', 3, 25, 5, 5, 0, 0, 0, NULL, 1),
(476, 'Destin Billows', 'ENT', '', '', 3, 25, 5, 5, 0, 0, 0, NULL, 1),
(477, 'Camellia Travis', 'GER', '', '', 3, 25, 5, 5, 0, 0, 0, NULL, 1),
(478, 'Dvalin', 'POR', '', './assets/img/Cromos/Importados/epsilon/dvalin.webp', 3, 18, 3, 2, 40, 87, 65, NULL, 1),
(479, 'Darehn', 'DF', '', './assets/img/Cromos/Importados/epsilon/darehn.webp', 3, 18, 2, 3, 53, 75, 58, NULL, 1),
(480, 'Mecamark', 'POR', '', './assets/img/Cromos/Importados/epsilon/mecamark.webp', 3, 18, 3, 1, 45, 80, 71, NULL, 1),
(481, 'Preston Princeton', 'POR', '', './assets/img/Cromos/Importados/epsilon/preston-princeton.webp', 3, 18, 3, 4, 53, 82, 70, NULL, 1),
(482, 'Locke Saflowe', 'DF', '', './assets/img/Cromos/Importados/epsilon/locke-saflowe.webp', 3, 18, 2, 2, 54, 70, 65, NULL, 1),
(483, 'Cliff Parker', 'DF', '', './assets/img/Cromos/Importados/epsilon/cliff-parker.webp', 3, 18, 2, 1, 50, 79, 57, NULL, 1),
(484, 'Lepidoptera Flutterby', 'MC', '', './assets/img/Cromos/Importados/epsilon/lepidoptera-flutterby.webp', 3, 18, 2, 3, 64, 66, 65, NULL, 1),
(485, 'Miles Ryan', 'MC', '', './assets/img/Cromos/Importados/epsilon/miles-ryan.webp', 3, 18, 2, 3, 59, 62, 65, NULL, 1),
(486, 'Rio Sands', 'DC', '', './assets/img/Cromos/Importados/epsilon/rio-sands.webp', 3, 18, 2, 1, 81, 45, 64, NULL, 1),
(487, 'Alexander Brave', 'DC', '', './assets/img/Cromos/Importados/epsilon/alexander-brave.webp', 3, 18, 2, 2, 72, 55, 59, NULL, 1),
(488, 'Smiley', 'DC', '', './assets/img/Cromos/Importados/epsilon/smiley.webp', 3, 18, 2, 4, 76, 50, 65, NULL, 1),
(489, 'Shunsuke Aoyama', 'DC', '', './assets/img/Cromos/Importados/epsilon/shunsuke-aoyama.webp', 3, 18, 2, 4, 70, 52, 60, NULL, 1),
(490, 'Eugene Conwell', 'DC', '', './assets/img/Cromos/Importados/epsilon/eugene-conwell.webp', 3, 18, 4, 4, 94, 65, 85, NULL, 1),
(491, 'Gwyn Penn', 'POR', '', './assets/img/Cromos/Importados/epsilon/gwyn-penn.webp', 3, 18, 1, 3, 29, 71, 54, NULL, 1),
(492, 'Steve Ingham', 'DC', '', './assets/img/Cromos/Importados/epsilon/steve-ingham.webp', 3, 18, 1, 2, 74, 40, 52, NULL, 1),
(493, 'Vashan Morell', 'DC', '', './assets/img/Cromos/Importados/epsilon/vashan-morell.webp', 3, 18, 1, 2, 65, 50, 57, NULL, 1),
(494, 'Tango Ahmedov', 'DC', '', './assets/img/Cromos/Importados/epsilon/tango-ahmedov.webp', 3, 18, 1, 3, 63, 39, 60, NULL, 1),
(495, 'Steve Eagle', 'MC', '', './assets/img/Cromos/Importados/epsilon/steve-eagle.webp', 3, 18, 1, 3, 55, 55, 66, NULL, 1),
(496, 'Anton Graziuso', 'DF', '', './assets/img/Cromos/Importados/epsilon/anton-graziuso.webp', 3, 18, 1, 2, 49, 60, 51, NULL, 1),
(497, 'Tytan', 'DF', '', './assets/img/Cromos/Importados/epsilon/tytan.webp', 3, 18, 1, 1, 41, 70, 57, NULL, 1),
(498, 'Escudo Épsilon', 'ESCUDO', '', '', 3, 18, 5, 5, 0, 0, 0, NULL, 1),
(499, 'Steve Grim', 'ENT', '', '', 3, 18, 5, 5, 0, 0, 0, NULL, 1),
(500, 'Misty', 'GER', '', '', 3, 18, 5, 5, 0, 0, 0, NULL, 1),
(501, 'Hector Helio', 'POR', '', './assets/img/Cromos/Importados/alpino/hector-helio.webp', 3, 3, 4, 1, 47, 87, 72, NULL, 1),
(502, 'Shawn Froste', 'DC', '', './assets/img/Cromos/Importados/alpino/shawn-froste.webp', 3, 3, 4, 3, 91, 62, 88, NULL, 1),
(503, 'Tom Skipper', 'DC', '', './assets/img/Cromos/Importados/instituto-zeus/tom-skipper.webp', 3, 13, 4, 3, 85, 60, 84, NULL, 1),
(504, 'Lykan Moss', 'POR', '', './assets/img/Cromos/Importados/triple-c/lykan-moss.webp', 3, 4, 4, 4, 60, 80, 74, NULL, 1),
(505, 'Gentian', 'DC', '', './assets/img/Cromos/Importados/zanark-domain/gentian.webp', 3, 2, 3, 1, 84, 54, 66, NULL, 1),
(506, 'Sol Daystar', 'DC', '', './assets/img/Cromos/Apuesta Segura/sol_daystar.webp', 3, 10, 5, 2, 98, 81, 94, NULL, 0),
(507, 'Neil Truner', 'DC', '', './assets/img/Cromos/Apuesta Segura/neil_turner.webp', 3, 8, 5, 2, 90, 69, 90, NULL, 0),
(508, 'Raleigh Greenstreet', 'DC', '', './assets/img/Cromos/Apuesta Segura/raleigh_greenstreet.webp', 3, 2, 5, 3, 91, 70, 86, NULL, 0),
(509, 'Payo Aguao', 'PRESIDENTE', 'Super Ruinero Fronteras', './assets/img/Cromos/Apuesta Segura/payo_agua.webp', 3, 6, 6, 5, 0, 0, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cromo_rasgos`
--

CREATE TABLE `cromo_rasgos` (
  `id_cromo_rasgo` int(11) NOT NULL,
  `id_cromo` int(11) NOT NULL,
  `id_rasgo` int(11) NOT NULL,
  `manual` tinyint(1) NOT NULL DEFAULT 0,
  `asignado` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cromo_rasgos`
--

INSERT INTO `cromo_rasgos` (`id_cromo_rasgo`, `id_cromo`, `id_rasgo`, `manual`, `asignado`) VALUES
(26251, 3, 8, 0, '2026-08-12 12:00:33'),
(26252, 5, 5, 0, '2026-08-12 12:00:33'),
(26253, 60, 5, 0, '2026-08-12 12:00:33'),
(26254, 66, 8, 0, '2026-08-12 12:00:33'),
(26255, 69, 8, 0, '2026-08-12 12:00:33'),
(26256, 78, 8, 0, '2026-08-12 12:00:33'),
(26257, 83, 5, 0, '2026-08-12 12:00:33'),
(26258, 89, 7, 0, '2026-08-12 12:00:33'),
(26259, 94, 8, 0, '2026-08-12 12:00:33'),
(26260, 100, 8, 0, '2026-08-12 12:00:33'),
(26261, 101, 6, 0, '2026-08-12 12:00:33'),
(26262, 103, 8, 0, '2026-08-12 12:00:33'),
(26263, 123, 6, 0, '2026-08-12 12:00:33'),
(26264, 132, 8, 0, '2026-08-12 12:00:33'),
(26265, 137, 8, 0, '2026-08-12 12:00:33'),
(26266, 143, 7, 0, '2026-08-12 12:00:33'),
(26267, 144, 7, 0, '2026-08-12 12:00:33'),
(26268, 149, 6, 0, '2026-08-12 12:00:33'),
(26269, 153, 5, 0, '2026-08-12 12:00:33'),
(26270, 156, 7, 0, '2026-08-12 12:00:33'),
(26271, 164, 5, 0, '2026-08-12 12:00:33'),
(26272, 167, 7, 0, '2026-08-12 12:00:33'),
(26273, 179, 8, 0, '2026-08-12 12:00:33'),
(26274, 181, 8, 0, '2026-08-12 12:00:33'),
(26275, 187, 8, 0, '2026-08-12 12:00:33'),
(26276, 193, 5, 0, '2026-08-12 12:00:33'),
(26277, 196, 7, 0, '2026-08-12 12:00:33'),
(26278, 199, 7, 0, '2026-08-12 12:00:33'),
(26279, 202, 6, 0, '2026-08-12 12:00:33'),
(26280, 207, 5, 0, '2026-08-12 12:00:33'),
(26281, 209, 7, 0, '2026-08-12 12:00:33'),
(26282, 222, 6, 0, '2026-08-12 12:00:33'),
(26283, 226, 8, 0, '2026-08-12 12:00:33'),
(26284, 233, 8, 0, '2026-08-12 12:00:33'),
(26285, 235, 5, 0, '2026-08-12 12:00:33'),
(26286, 238, 7, 0, '2026-08-12 12:00:33'),
(26287, 239, 6, 0, '2026-08-12 12:00:33'),
(26288, 244, 8, 0, '2026-08-12 12:00:33'),
(26289, 259, 8, 0, '2026-08-12 12:00:33'),
(26290, 261, 7, 0, '2026-08-12 12:00:33'),
(26291, 265, 5, 0, '2026-08-12 12:00:33'),
(26292, 266, 8, 0, '2026-08-12 12:00:33'),
(26293, 275, 5, 0, '2026-08-12 12:00:33'),
(26294, 279, 8, 0, '2026-08-12 12:00:33'),
(26295, 289, 8, 0, '2026-08-12 12:00:33'),
(26296, 291, 8, 0, '2026-08-12 12:00:33'),
(26297, 299, 6, 0, '2026-08-12 12:00:33'),
(26298, 305, 7, 0, '2026-08-12 12:00:33'),
(26299, 309, 8, 0, '2026-08-12 12:00:33'),
(26300, 310, 8, 0, '2026-08-12 12:00:33'),
(26301, 313, 6, 0, '2026-08-12 12:00:33'),
(26302, 314, 5, 0, '2026-08-12 12:00:33'),
(26303, 316, 6, 0, '2026-08-12 12:00:33'),
(26304, 317, 7, 0, '2026-08-12 12:00:33'),
(26305, 324, 5, 0, '2026-08-12 12:00:33'),
(26306, 328, 8, 0, '2026-08-12 12:00:33'),
(26307, 343, 5, 0, '2026-08-12 12:00:33'),
(26308, 345, 8, 0, '2026-08-12 12:00:33'),
(26309, 354, 7, 0, '2026-08-12 12:00:33'),
(26310, 355, 7, 0, '2026-08-12 12:00:33'),
(26311, 356, 6, 0, '2026-08-12 12:00:33'),
(26312, 368, 7, 0, '2026-08-12 12:00:33'),
(26313, 376, 6, 0, '2026-08-12 12:00:33'),
(26314, 378, 6, 0, '2026-08-12 12:00:33'),
(26315, 396, 7, 0, '2026-08-12 12:00:33'),
(26316, 399, 6, 0, '2026-08-12 12:00:33'),
(26317, 404, 6, 0, '2026-08-12 12:00:33'),
(26318, 411, 5, 0, '2026-08-12 12:00:33'),
(26319, 415, 8, 0, '2026-08-12 12:00:33'),
(26320, 420, 6, 0, '2026-08-12 12:00:33'),
(26321, 424, 7, 0, '2026-08-12 12:00:33'),
(26322, 438, 7, 0, '2026-08-12 12:00:33'),
(26323, 459, 6, 0, '2026-08-12 12:00:33'),
(26324, 467, 8, 0, '2026-08-12 12:00:33'),
(26325, 480, 5, 0, '2026-08-12 12:00:33'),
(26326, 483, 7, 0, '2026-08-12 12:00:33'),
(26327, 486, 8, 0, '2026-08-12 12:00:33'),
(26328, 497, 7, 0, '2026-08-12 12:00:33'),
(26329, 501, 5, 0, '2026-08-12 12:00:33'),
(26330, 505, 8, 0, '2026-08-12 12:00:33'),
(26331, 53, 6, 0, '2026-08-12 12:00:33'),
(26332, 54, 5, 0, '2026-08-12 12:00:33'),
(26333, 55, 5, 0, '2026-08-12 12:00:33'),
(26334, 56, 8, 0, '2026-08-12 12:00:33'),
(26335, 58, 7, 0, '2026-08-12 12:00:33'),
(26336, 62, 6, 0, '2026-08-12 12:00:33'),
(26337, 70, 5, 0, '2026-08-12 12:00:33'),
(26338, 77, 5, 0, '2026-08-12 12:00:33'),
(26339, 82, 7, 0, '2026-08-12 12:00:33'),
(26340, 87, 6, 0, '2026-08-12 12:00:33'),
(26341, 88, 6, 0, '2026-08-12 12:00:33'),
(26342, 90, 8, 0, '2026-08-12 12:00:33'),
(26343, 93, 5, 0, '2026-08-12 12:00:33'),
(26344, 102, 5, 0, '2026-08-12 12:00:33'),
(26345, 104, 5, 0, '2026-08-12 12:00:33'),
(26346, 110, 7, 0, '2026-08-12 12:00:33'),
(26347, 111, 6, 0, '2026-08-12 12:00:33'),
(26348, 112, 6, 0, '2026-08-12 12:00:33'),
(26349, 117, 5, 0, '2026-08-12 12:00:33'),
(26350, 120, 6, 0, '2026-08-12 12:00:33'),
(26351, 124, 8, 0, '2026-08-12 12:00:33'),
(26352, 127, 5, 0, '2026-08-12 12:00:33'),
(26353, 130, 5, 0, '2026-08-12 12:00:33'),
(26354, 142, 6, 0, '2026-08-12 12:00:33'),
(26355, 145, 6, 0, '2026-08-12 12:00:33'),
(26356, 147, 8, 0, '2026-08-12 12:00:33'),
(26357, 150, 5, 0, '2026-08-12 12:00:33'),
(26358, 159, 5, 0, '2026-08-12 12:00:33'),
(26359, 168, 6, 0, '2026-08-12 12:00:33'),
(26360, 172, 8, 0, '2026-08-12 12:00:33'),
(26361, 176, 7, 0, '2026-08-12 12:00:33'),
(26362, 180, 5, 0, '2026-08-12 12:00:33'),
(26363, 183, 8, 0, '2026-08-12 12:00:33'),
(26364, 189, 5, 0, '2026-08-12 12:00:33'),
(26365, 192, 8, 0, '2026-08-12 12:00:33'),
(26366, 195, 6, 0, '2026-08-12 12:00:33'),
(26367, 198, 5, 0, '2026-08-12 12:00:33'),
(26368, 204, 5, 0, '2026-08-12 12:00:33'),
(26369, 205, 8, 0, '2026-08-12 12:00:33'),
(26370, 208, 6, 0, '2026-08-12 12:00:33'),
(26371, 211, 8, 0, '2026-08-12 12:00:33'),
(26372, 215, 5, 0, '2026-08-12 12:00:33'),
(26373, 216, 8, 0, '2026-08-12 12:00:33'),
(26374, 240, 5, 0, '2026-08-12 12:00:33'),
(26375, 241, 5, 0, '2026-08-12 12:00:33'),
(26376, 243, 8, 0, '2026-08-12 12:00:33'),
(26377, 246, 5, 0, '2026-08-12 12:00:33'),
(26378, 247, 7, 0, '2026-08-12 12:00:33'),
(26379, 252, 7, 0, '2026-08-12 12:00:33'),
(26380, 257, 8, 0, '2026-08-12 12:00:33'),
(26381, 262, 6, 0, '2026-08-12 12:00:33'),
(26382, 264, 5, 0, '2026-08-12 12:00:33'),
(26383, 267, 5, 0, '2026-08-12 12:00:33'),
(26384, 278, 5, 0, '2026-08-12 12:00:33'),
(26385, 283, 6, 0, '2026-08-12 12:00:33'),
(26386, 288, 5, 0, '2026-08-12 12:00:33'),
(26387, 290, 5, 0, '2026-08-12 12:00:33'),
(26388, 298, 5, 0, '2026-08-12 12:00:33'),
(26389, 304, 6, 0, '2026-08-12 12:00:33'),
(26390, 321, 7, 0, '2026-08-12 12:00:33'),
(26391, 322, 5, 0, '2026-08-12 12:00:33'),
(26392, 325, 8, 0, '2026-08-12 12:00:33'),
(26393, 334, 5, 0, '2026-08-12 12:00:33'),
(26394, 338, 8, 0, '2026-08-12 12:00:33'),
(26395, 339, 6, 0, '2026-08-12 12:00:33'),
(26396, 349, 7, 0, '2026-08-12 12:00:33'),
(26397, 353, 6, 0, '2026-08-12 12:00:33'),
(26398, 357, 5, 0, '2026-08-12 12:00:33'),
(26399, 361, 5, 0, '2026-08-12 12:00:33'),
(26400, 367, 7, 0, '2026-08-12 12:00:33'),
(26401, 371, 6, 0, '2026-08-12 12:00:33'),
(26402, 372, 6, 0, '2026-08-12 12:00:33'),
(26403, 375, 8, 0, '2026-08-12 12:00:33'),
(26404, 379, 8, 0, '2026-08-12 12:00:33'),
(26405, 381, 5, 0, '2026-08-12 12:00:33'),
(26406, 393, 6, 0, '2026-08-12 12:00:33'),
(26407, 395, 6, 0, '2026-08-12 12:00:33'),
(26408, 398, 8, 0, '2026-08-12 12:00:33'),
(26409, 401, 5, 0, '2026-08-12 12:00:33'),
(26410, 402, 6, 0, '2026-08-12 12:00:33'),
(26411, 403, 6, 0, '2026-08-12 12:00:33'),
(26412, 405, 7, 0, '2026-08-12 12:00:33'),
(26413, 413, 6, 0, '2026-08-12 12:00:33'),
(26414, 422, 5, 0, '2026-08-12 12:00:33'),
(26415, 425, 6, 0, '2026-08-12 12:00:33'),
(26416, 428, 7, 0, '2026-08-12 12:00:33'),
(26417, 430, 8, 0, '2026-08-12 12:00:33'),
(26418, 434, 6, 0, '2026-08-12 12:00:33'),
(26419, 441, 8, 0, '2026-08-12 12:00:33'),
(26420, 444, 5, 0, '2026-08-12 12:00:33'),
(26421, 446, 7, 0, '2026-08-12 12:00:33'),
(26422, 448, 8, 0, '2026-08-12 12:00:33'),
(26423, 450, 5, 0, '2026-08-12 12:00:33'),
(26424, 451, 8, 0, '2026-08-12 12:00:33'),
(26425, 460, 5, 0, '2026-08-12 12:00:33'),
(26426, 462, 7, 0, '2026-08-12 12:00:33'),
(26427, 464, 8, 0, '2026-08-12 12:00:33'),
(26428, 471, 8, 0, '2026-08-12 12:00:33'),
(26429, 478, 7, 0, '2026-08-12 12:00:33'),
(26430, 482, 6, 0, '2026-08-12 12:00:33'),
(26431, 487, 5, 0, '2026-08-12 12:00:33'),
(26432, 492, 5, 0, '2026-08-12 12:00:33'),
(26433, 493, 5, 0, '2026-08-12 12:00:33'),
(26434, 496, 6, 0, '2026-08-12 12:00:33'),
(26435, 506, 5, 0, '2026-08-12 12:00:33'),
(26436, 507, 5, 0, '2026-08-12 12:00:33'),
(26437, 4, 6, 0, '2026-08-12 12:00:33'),
(26438, 8, 6, 0, '2026-08-12 12:00:33'),
(26439, 57, 6, 0, '2026-08-12 12:00:33'),
(26440, 59, 8, 0, '2026-08-12 12:00:33'),
(26441, 61, 5, 0, '2026-08-12 12:00:33'),
(26442, 65, 6, 0, '2026-08-12 12:00:33'),
(26443, 67, 6, 0, '2026-08-12 12:00:33'),
(26444, 71, 7, 0, '2026-08-12 12:00:33'),
(26445, 76, 7, 0, '2026-08-12 12:00:33'),
(26446, 79, 8, 0, '2026-08-12 12:00:33'),
(26447, 81, 6, 0, '2026-08-12 12:00:33'),
(26448, 86, 5, 0, '2026-08-12 12:00:33'),
(26449, 95, 6, 0, '2026-08-12 12:00:33'),
(26450, 106, 6, 0, '2026-08-12 12:00:33'),
(26451, 107, 7, 0, '2026-08-12 12:00:33'),
(26452, 109, 8, 0, '2026-08-12 12:00:33'),
(26453, 113, 5, 0, '2026-08-12 12:00:33'),
(26454, 116, 7, 0, '2026-08-12 12:00:33'),
(26455, 118, 6, 0, '2026-08-12 12:00:33'),
(26456, 133, 6, 0, '2026-08-12 12:00:33'),
(26457, 134, 5, 0, '2026-08-12 12:00:33'),
(26458, 135, 6, 0, '2026-08-12 12:00:33'),
(26459, 136, 5, 0, '2026-08-12 12:00:33'),
(26460, 151, 6, 0, '2026-08-12 12:00:33'),
(26461, 152, 7, 0, '2026-08-12 12:00:33'),
(26462, 154, 7, 0, '2026-08-12 12:00:33'),
(26463, 157, 7, 0, '2026-08-12 12:00:33'),
(26464, 158, 7, 0, '2026-08-12 12:00:33'),
(26465, 166, 8, 0, '2026-08-12 12:00:33'),
(26466, 173, 7, 0, '2026-08-12 12:00:33'),
(26467, 174, 6, 0, '2026-08-12 12:00:33'),
(26468, 175, 6, 0, '2026-08-12 12:00:33'),
(26469, 182, 6, 0, '2026-08-12 12:00:33'),
(26470, 191, 6, 0, '2026-08-12 12:00:33'),
(26471, 197, 7, 0, '2026-08-12 12:00:33'),
(26472, 203, 6, 0, '2026-08-12 12:00:33'),
(26473, 213, 6, 0, '2026-08-12 12:00:33'),
(26474, 214, 7, 0, '2026-08-12 12:00:33'),
(26475, 218, 7, 0, '2026-08-12 12:00:33'),
(26476, 219, 5, 0, '2026-08-12 12:00:33'),
(26477, 221, 5, 0, '2026-08-12 12:00:33'),
(26478, 223, 6, 0, '2026-08-12 12:00:33'),
(26479, 225, 6, 0, '2026-08-12 12:00:33'),
(26480, 234, 6, 0, '2026-08-12 12:00:33'),
(26481, 236, 5, 0, '2026-08-12 12:00:33'),
(26482, 242, 7, 0, '2026-08-12 12:00:33'),
(26483, 248, 6, 0, '2026-08-12 12:00:33'),
(26484, 253, 5, 0, '2026-08-12 12:00:33'),
(26485, 256, 6, 0, '2026-08-12 12:00:33'),
(26486, 260, 7, 0, '2026-08-12 12:00:33'),
(26487, 263, 7, 0, '2026-08-12 12:00:33'),
(26488, 269, 7, 0, '2026-08-12 12:00:33'),
(26489, 271, 6, 0, '2026-08-12 12:00:33'),
(26490, 276, 7, 0, '2026-08-12 12:00:33'),
(26491, 277, 7, 0, '2026-08-12 12:00:33'),
(26492, 280, 6, 0, '2026-08-12 12:00:33'),
(26493, 282, 6, 0, '2026-08-12 12:00:33'),
(26494, 286, 5, 0, '2026-08-12 12:00:33'),
(26495, 287, 6, 0, '2026-08-12 12:00:33'),
(26496, 293, 6, 0, '2026-08-12 12:00:33'),
(26497, 294, 6, 0, '2026-08-12 12:00:33'),
(26498, 302, 8, 0, '2026-08-12 12:00:33'),
(26499, 303, 5, 0, '2026-08-12 12:00:33'),
(26500, 306, 5, 0, '2026-08-12 12:00:33'),
(26501, 307, 5, 0, '2026-08-12 12:00:33'),
(26502, 311, 6, 0, '2026-08-12 12:00:33'),
(26503, 312, 6, 0, '2026-08-12 12:00:33'),
(26504, 326, 7, 0, '2026-08-12 12:00:33'),
(26505, 335, 6, 0, '2026-08-12 12:00:33'),
(26506, 336, 7, 0, '2026-08-12 12:00:33'),
(26507, 344, 7, 0, '2026-08-12 12:00:33'),
(26508, 347, 5, 0, '2026-08-12 12:00:33'),
(26509, 348, 7, 0, '2026-08-12 12:00:33'),
(26510, 351, 6, 0, '2026-08-12 12:00:33'),
(26511, 352, 8, 0, '2026-08-12 12:00:33'),
(26512, 358, 6, 0, '2026-08-12 12:00:33'),
(26513, 360, 7, 0, '2026-08-12 12:00:33'),
(26514, 362, 7, 0, '2026-08-12 12:00:33'),
(26515, 370, 5, 0, '2026-08-12 12:00:33'),
(26516, 374, 7, 0, '2026-08-12 12:00:33'),
(26517, 377, 7, 0, '2026-08-12 12:00:33'),
(26518, 384, 6, 0, '2026-08-12 12:00:33'),
(26519, 385, 6, 0, '2026-08-12 12:00:33'),
(26520, 390, 5, 0, '2026-08-12 12:00:33'),
(26521, 392, 8, 0, '2026-08-12 12:00:33'),
(26522, 397, 7, 0, '2026-08-12 12:00:33'),
(26523, 406, 7, 0, '2026-08-12 12:00:33'),
(26524, 407, 6, 0, '2026-08-12 12:00:33'),
(26525, 412, 7, 0, '2026-08-12 12:00:33'),
(26526, 414, 5, 0, '2026-08-12 12:00:33'),
(26527, 416, 5, 0, '2026-08-12 12:00:33'),
(26528, 418, 8, 0, '2026-08-12 12:00:33'),
(26529, 421, 7, 0, '2026-08-12 12:00:33'),
(26530, 427, 6, 0, '2026-08-12 12:00:33'),
(26531, 435, 6, 0, '2026-08-12 12:00:33'),
(26532, 437, 8, 0, '2026-08-12 12:00:33'),
(26533, 439, 5, 0, '2026-08-12 12:00:33'),
(26534, 443, 7, 0, '2026-08-12 12:00:33'),
(26535, 449, 7, 0, '2026-08-12 12:00:33'),
(26536, 452, 8, 0, '2026-08-12 12:00:33'),
(26537, 453, 5, 0, '2026-08-12 12:00:33'),
(26538, 457, 5, 0, '2026-08-12 12:00:33'),
(26539, 458, 5, 0, '2026-08-12 12:00:33'),
(26540, 465, 7, 0, '2026-08-12 12:00:33'),
(26541, 466, 6, 0, '2026-08-12 12:00:33'),
(26542, 468, 6, 0, '2026-08-12 12:00:33'),
(26543, 470, 7, 0, '2026-08-12 12:00:33'),
(26544, 472, 6, 0, '2026-08-12 12:00:33'),
(26545, 479, 5, 0, '2026-08-12 12:00:33'),
(26546, 484, 7, 0, '2026-08-12 12:00:33'),
(26547, 485, 7, 0, '2026-08-12 12:00:33'),
(26548, 491, 8, 0, '2026-08-12 12:00:33'),
(26549, 494, 6, 0, '2026-08-12 12:00:33'),
(26550, 495, 7, 0, '2026-08-12 12:00:33'),
(26551, 502, 6, 0, '2026-08-12 12:00:33'),
(26552, 503, 6, 0, '2026-08-12 12:00:33'),
(26553, 508, 6, 0, '2026-08-12 12:00:33'),
(26554, 6, 7, 0, '2026-08-12 12:00:33'),
(26555, 7, 6, 0, '2026-08-12 12:00:33'),
(26556, 63, 5, 0, '2026-08-12 12:00:33'),
(26557, 64, 7, 0, '2026-08-12 12:00:33'),
(26558, 68, 7, 0, '2026-08-12 12:00:33'),
(26559, 72, 5, 0, '2026-08-12 12:00:33'),
(26560, 80, 5, 0, '2026-08-12 12:00:33'),
(26561, 84, 6, 0, '2026-08-12 12:00:33'),
(26562, 85, 8, 0, '2026-08-12 12:00:33'),
(26563, 91, 7, 0, '2026-08-12 12:00:33'),
(26564, 92, 7, 0, '2026-08-12 12:00:33'),
(26565, 99, 7, 0, '2026-08-12 12:00:33'),
(26566, 105, 7, 0, '2026-08-12 12:00:33'),
(26567, 108, 6, 0, '2026-08-12 12:00:33'),
(26568, 114, 5, 0, '2026-08-12 12:00:33'),
(26569, 115, 7, 0, '2026-08-12 12:00:33'),
(26570, 121, 8, 0, '2026-08-12 12:00:33'),
(26571, 122, 8, 0, '2026-08-12 12:00:33'),
(26572, 125, 7, 0, '2026-08-12 12:00:33'),
(26573, 126, 5, 0, '2026-08-12 12:00:33'),
(26574, 128, 8, 0, '2026-08-12 12:00:33'),
(26575, 129, 7, 0, '2026-08-12 12:00:33'),
(26576, 131, 6, 0, '2026-08-12 12:00:33'),
(26577, 141, 6, 0, '2026-08-12 12:00:33'),
(26578, 146, 5, 0, '2026-08-12 12:00:33'),
(26579, 148, 5, 0, '2026-08-12 12:00:33'),
(26580, 155, 8, 0, '2026-08-12 12:00:33'),
(26581, 160, 5, 0, '2026-08-12 12:00:33'),
(26582, 165, 7, 0, '2026-08-12 12:00:33'),
(26583, 169, 8, 0, '2026-08-12 12:00:33'),
(26584, 170, 8, 0, '2026-08-12 12:00:33'),
(26585, 171, 5, 0, '2026-08-12 12:00:33'),
(26586, 177, 7, 0, '2026-08-12 12:00:33'),
(26587, 178, 7, 0, '2026-08-12 12:00:33'),
(26588, 188, 5, 0, '2026-08-12 12:00:33'),
(26589, 190, 7, 0, '2026-08-12 12:00:33'),
(26590, 194, 8, 0, '2026-08-12 12:00:33'),
(26591, 200, 8, 0, '2026-08-12 12:00:33'),
(26592, 201, 6, 0, '2026-08-12 12:00:33'),
(26593, 210, 5, 0, '2026-08-12 12:00:33'),
(26594, 212, 6, 0, '2026-08-12 12:00:33'),
(26595, 217, 5, 0, '2026-08-12 12:00:33'),
(26596, 220, 8, 0, '2026-08-12 12:00:33'),
(26597, 224, 7, 0, '2026-08-12 12:00:33'),
(26598, 230, 5, 0, '2026-08-12 12:00:33'),
(26599, 231, 7, 0, '2026-08-12 12:00:33'),
(26600, 232, 7, 0, '2026-08-12 12:00:33'),
(26601, 237, 8, 0, '2026-08-12 12:00:33'),
(26602, 245, 7, 0, '2026-08-12 12:00:33'),
(26603, 254, 8, 0, '2026-08-12 12:00:33'),
(26604, 255, 5, 0, '2026-08-12 12:00:33'),
(26605, 258, 5, 0, '2026-08-12 12:00:33'),
(26606, 268, 6, 0, '2026-08-12 12:00:33'),
(26607, 270, 7, 0, '2026-08-12 12:00:33'),
(26608, 281, 6, 0, '2026-08-12 12:00:33'),
(26609, 284, 8, 0, '2026-08-12 12:00:33'),
(26610, 285, 7, 0, '2026-08-12 12:00:33'),
(26611, 292, 7, 0, '2026-08-12 12:00:33'),
(26612, 300, 5, 0, '2026-08-12 12:00:33'),
(26613, 301, 7, 0, '2026-08-12 12:00:33'),
(26614, 308, 7, 0, '2026-08-12 12:00:33'),
(26615, 315, 8, 0, '2026-08-12 12:00:33'),
(26616, 323, 7, 0, '2026-08-12 12:00:33'),
(26617, 327, 7, 0, '2026-08-12 12:00:33'),
(26618, 329, 7, 0, '2026-08-12 12:00:33'),
(26619, 330, 8, 0, '2026-08-12 12:00:33'),
(26620, 331, 8, 0, '2026-08-12 12:00:33'),
(26621, 332, 8, 0, '2026-08-12 12:00:33'),
(26622, 333, 6, 0, '2026-08-12 12:00:34'),
(26623, 337, 5, 0, '2026-08-12 12:00:34'),
(26624, 346, 6, 0, '2026-08-12 12:00:34'),
(26625, 350, 7, 0, '2026-08-12 12:00:34'),
(26626, 359, 7, 0, '2026-08-12 12:00:34'),
(26627, 366, 6, 0, '2026-08-12 12:00:34'),
(26628, 369, 8, 0, '2026-08-12 12:00:34'),
(26629, 373, 8, 0, '2026-08-12 12:00:34'),
(26630, 380, 5, 0, '2026-08-12 12:00:34'),
(26631, 382, 7, 0, '2026-08-12 12:00:34'),
(26632, 383, 7, 0, '2026-08-12 12:00:34'),
(26633, 389, 6, 0, '2026-08-12 12:00:34'),
(26634, 391, 6, 0, '2026-08-12 12:00:34'),
(26635, 394, 8, 0, '2026-08-12 12:00:34'),
(26636, 400, 7, 0, '2026-08-12 12:00:34'),
(26637, 417, 7, 0, '2026-08-12 12:00:34'),
(26638, 419, 8, 0, '2026-08-12 12:00:34'),
(26639, 423, 6, 0, '2026-08-12 12:00:34'),
(26640, 426, 7, 0, '2026-08-12 12:00:34'),
(26641, 429, 7, 0, '2026-08-12 12:00:34'),
(26642, 436, 7, 0, '2026-08-12 12:00:34'),
(26643, 440, 8, 0, '2026-08-12 12:00:34'),
(26644, 442, 5, 0, '2026-08-12 12:00:34'),
(26645, 445, 7, 0, '2026-08-12 12:00:34'),
(26646, 447, 6, 0, '2026-08-12 12:00:34'),
(26647, 461, 7, 0, '2026-08-12 12:00:34'),
(26648, 463, 8, 0, '2026-08-12 12:00:34'),
(26649, 469, 6, 0, '2026-08-12 12:00:34'),
(26650, 473, 7, 0, '2026-08-12 12:00:34'),
(26651, 474, 7, 0, '2026-08-12 12:00:34'),
(26652, 481, 6, 0, '2026-08-12 12:00:34'),
(26653, 488, 7, 0, '2026-08-12 12:00:34'),
(26654, 489, 7, 0, '2026-08-12 12:00:34'),
(26655, 490, 7, 0, '2026-08-12 12:00:34'),
(26656, 504, 6, 0, '2026-08-12 12:00:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `duelos`
--

CREATE TABLE `duelos` (
  `id_duelo` int(11) NOT NULL,
  `id_creador` int(11) NOT NULL,
  `id_rival` int(11) DEFAULT NULL,
  `id_mazo_creador` int(11) NOT NULL,
  `id_mazo_rival` int(11) DEFAULT NULL,
  `formacion_creador` varchar(8) DEFAULT NULL,
  `formacion_rival` varchar(8) DEFAULT NULL,
  `tipo_apuesta` enum('monedas','carta') NOT NULL,
  `monedas` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `id_rareza_apuesta` int(11) DEFAULT NULL,
  `total_bruto_creador` decimal(12,4) DEFAULT NULL,
  `total_bruto_rival` decimal(12,4) DEFAULT NULL,
  `total_final_creador` decimal(12,4) DEFAULT NULL,
  `total_final_rival` decimal(12,4) DEFAULT NULL,
  `probabilidad_victoria_creador` decimal(12,4) DEFAULT NULL,
  `valor_sorteo` decimal(12,4) DEFAULT NULL,
  `k_utilizado` decimal(12,4) DEFAULT NULL,
  `afinidad_dom_creador` varchar(20) DEFAULT NULL,
  `afinidad_dom_rival` varchar(20) DEFAULT NULL,
  `ciclo_bonus_creador` decimal(6,3) DEFAULT NULL,
  `ciclo_bonus_rival` decimal(6,3) DEFAULT NULL,
  `malus_coh_creador` decimal(6,3) DEFAULT NULL,
  `malus_coh_rival` decimal(6,3) DEFAULT NULL,
  `tension_creador` tinyint(3) UNSIGNED DEFAULT NULL,
  `tension_rival` tinyint(3) UNSIGNED DEFAULT NULL,
  `aumento_vence` datetime DEFAULT NULL,
  `ultimo_latido` datetime DEFAULT NULL,
  `estado` enum('creado','aceptado','aumento_pendiente','listo_para_resolver','en_juego','resuelto','cancelado') NOT NULL DEFAULT 'creado',
  `dificultad` enum('facil','medio','dificil','muy_dificil','extremo') DEFAULT NULL,
  `id_estilo_rival` int(11) DEFAULT NULL,
  `id_nodo` int(11) DEFAULT NULL,
  `rango` char(1) DEFAULT NULL,
  `id_ganador` int(11) DEFAULT NULL,
  `goles_creador` tinyint(3) UNSIGNED DEFAULT NULL,
  `goles_rival` tinyint(3) UNSIGNED DEFAULT NULL,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  `resuelto` datetime DEFAULT NULL,
  `partido_inicio` datetime DEFAULT NULL COMMENT 'Cuándo arrancó el partido narrado. NULL = todavía no ha empezado.',
  `partido_pausado_en` datetime DEFAULT NULL COMMENT 'Desde cuándo está detenido por un minijuego. NULL = corriendo.',
  `partido_pausa_seg` int(11) NOT NULL DEFAULT 0 COMMENT 'Segundos acumulados de pausa, que no cuentan para el minuto.',
  `latido_creador` datetime DEFAULT NULL,
  `latido_rival` datetime DEFAULT NULL,
  `resuelto_por_tanda` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'El empate se rompio en la tanda de penaltis'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `duelos`
--

INSERT INTO `duelos` (`id_duelo`, `id_creador`, `id_rival`, `id_mazo_creador`, `id_mazo_rival`, `formacion_creador`, `formacion_rival`, `tipo_apuesta`, `monedas`, `id_rareza_apuesta`, `total_bruto_creador`, `total_bruto_rival`, `total_final_creador`, `total_final_rival`, `probabilidad_victoria_creador`, `valor_sorteo`, `k_utilizado`, `afinidad_dom_creador`, `afinidad_dom_rival`, `ciclo_bonus_creador`, `ciclo_bonus_rival`, `malus_coh_creador`, `malus_coh_rival`, `tension_creador`, `tension_rival`, `aumento_vence`, `ultimo_latido`, `estado`, `dificultad`, `id_estilo_rival`, `id_nodo`, `rango`, `id_ganador`, `goles_creador`, `goles_rival`, `creado`, `resuelto`, `partido_inicio`, `partido_pausado_en`, `partido_pausa_seg`, `latido_creador`, `latido_rival`, `resuelto_por_tanda`) VALUES
(1047, 2, 10, 8, NULL, '442', '442', 'monedas', 0, NULL, 1720.5500, 1637.5973, 1809.8513, 1753.4503, 0.5805, 0.1815, 400.0000, 'fuego', 'viento', 0.000, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'extremo', 3, 5, 'B', 2, 5, 3, '2026-08-06 15:00:33', '2026-08-06 15:00:34', NULL, NULL, 0, NULL, NULL, 0),
(1049, 2, 10, 8, NULL, '442', '433', 'monedas', 0, NULL, 1720.5500, 1552.3466, 1806.7369, 1625.2819, 0.7397, 0.3935, 400.0000, 'fuego', 'fuego', 0.000, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'dificil', 2, 1, 'B', 2, 3, 2, '2026-08-06 15:05:54', '2026-08-06 15:05:56', NULL, NULL, 0, NULL, NULL, 0),
(1050, 2, 10, 8, NULL, '442', '433', 'monedas', 0, NULL, 1720.5500, 1552.3466, 1794.5573, 1636.1400, 0.7134, 0.3281, 400.0000, 'fuego', 'fuego', 0.000, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'dificil', 2, 1, 'B', 2, 4, 2, '2026-08-06 15:06:28', '2026-08-06 15:06:35', NULL, NULL, 0, NULL, NULL, 0),
(1052, 2, 10, 8, NULL, '442', '433', 'monedas', 0, NULL, 1720.5500, 1552.3466, 1815.2243, 1638.1546, 0.7348, 0.3265, 400.0000, 'fuego', 'fuego', 0.000, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'dificil', 2, 1, 'B', 2, 6, 4, '2026-08-06 15:11:00', '2026-08-06 15:11:01', NULL, NULL, 0, NULL, NULL, 0),
(1055, 2, 10, 8, NULL, '442', '532', 'monedas', 0, NULL, 1720.5500, 1501.8028, 1797.6971, 1651.6008, 0.6987, 0.4641, 400.0000, 'fuego', 'montana', 0.000, 5.500, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'dificil', 1, 1, 'B', 2, 1, 0, '2026-08-06 15:16:45', '2026-08-06 15:16:47', NULL, NULL, 0, NULL, NULL, 0),
(1056, 2, 10, 8, NULL, '442', '532', 'monedas', 0, NULL, 1720.5500, 1501.8028, 1803.4497, 1674.3543, 0.6777, 0.3376, 400.0000, 'fuego', 'montana', 0.000, 5.500, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'dificil', 1, 1, 'A', 2, 6, 2, '2026-08-06 15:17:01', '2026-08-06 15:17:05', NULL, NULL, 0, NULL, NULL, 0),
(1315, 2, 10, 8, NULL, '442', '433', 'monedas', 0, NULL, 1720.5500, 1354.0113, 1895.2058, 1412.2748, 0.9416, 0.2506, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'dificil', 5, 7, 'S', 2, 9, 0, '2026-08-06 15:33:50', '2026-08-06 15:33:53', NULL, NULL, 0, NULL, NULL, 0),
(1316, 2, 10, 8, NULL, '442', '433', 'monedas', 0, NULL, 1720.5500, 1482.8925, 1897.9090, 1570.6707, 0.8680, 0.2477, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'muy_dificil', 5, 7, 'B', 2, 5, 3, '2026-08-06 15:34:23', '2026-08-06 15:34:25', NULL, NULL, 0, NULL, NULL, 0),
(1317, 2, 10, 8, NULL, '442', '541', 'monedas', 0, NULL, 1720.5500, 1462.5990, 1795.2461, 1552.4685, 0.8018, 0.0347, 400.0000, 'fuego', 'neutro', 0.000, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'muy_dificil', 6, 7, 'B', 2, 4, 3, '2026-08-06 15:34:34', '2026-08-06 15:34:37', NULL, NULL, 0, NULL, NULL, 0),
(1318, 2, 10, 8, NULL, '442', '541', 'monedas', 0, NULL, 1720.5500, 1462.5990, 1810.6111, 1528.2661, 0.8355, 0.8672, 400.0000, 'fuego', 'neutro', 0.000, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'muy_dificil', 6, 7, NULL, 10, 1, 2, '2026-08-06 15:34:45', '2026-08-06 15:34:49', NULL, NULL, 0, NULL, NULL, 0),
(1569, 2, 10, 8, NULL, '442', '361', 'monedas', 0, NULL, 1777.7500, 1500.2520, 1860.3902, 1559.8880, 0.8494, 0.4739, 400.0000, 'fuego', 'fuego', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'muy_dificil', 10, 7, 'A', 2, 9, 2, '2026-08-06 15:44:54', '2026-08-06 15:44:56', NULL, NULL, 0, NULL, NULL, 0),
(1570, 2, 10, 8, NULL, '442', '361', 'monedas', 0, NULL, 1777.7500, 1500.2520, 1861.8766, 1557.5123, 0.8522, 0.6221, 400.0000, 'fuego', 'fuego', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'muy_dificil', 10, 7, 'B', 2, 4, 3, '2026-08-06 15:45:08', '2026-08-06 15:45:11', NULL, NULL, 0, NULL, NULL, 0),
(1571, 2, 10, 8, NULL, '442', '361', 'monedas', 0, NULL, 1777.7500, 1500.2520, 1874.9057, 1595.1130, 0.8335, 0.7864, 400.0000, 'fuego', 'fuego', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'muy_dificil', 10, 7, 'A', 2, 4, 1, '2026-08-06 15:45:18', '2026-08-06 15:45:20', NULL, NULL, 0, NULL, NULL, 0),
(1572, 2, 10, 8, NULL, '442', '352', 'monedas', 0, NULL, 1777.7500, 1638.6023, 1860.4369, 1743.2315, 0.6626, 0.4871, 400.0000, 'fuego', 'viento', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'extremo', 9, 7, 'B', 2, 7, 5, '2026-08-06 15:45:50', '2026-08-06 15:45:51', NULL, NULL, 0, NULL, NULL, 0),
(1573, 2, 10, 8, NULL, '442', '352', 'monedas', 0, NULL, 1777.7500, 1594.5801, 1856.8486, 1694.1826, 0.7184, 0.8272, 400.0000, 'fuego', 'viento', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'muy_dificil', 9, 7, NULL, 10, 2, 3, '2026-08-06 15:46:00', '2026-08-06 15:46:02', NULL, NULL, 0, NULL, NULL, 0),
(1574, 2, 10, 8, NULL, '442', '352', 'monedas', 0, NULL, 1777.7500, 1594.5801, 1869.5443, 1688.2105, 0.7396, 0.7342, 400.0000, 'fuego', 'viento', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'muy_dificil', 9, 7, 'A', 2, 9, 5, '2026-08-06 15:46:09', '2026-08-06 15:46:10', NULL, NULL, 0, NULL, NULL, 0),
(1575, 2, 10, 8, NULL, '442', '361', 'monedas', 0, NULL, 1777.7500, 1541.6700, 1867.9730, 1622.1358, 0.8046, 0.7076, 400.0000, 'fuego', 'fuego', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'extremo', 10, 7, 'B', 2, 4, 2, '2026-08-06 15:46:16', '2026-08-06 15:46:19', NULL, NULL, 0, NULL, NULL, 0),
(1846, 2, 10, 8, NULL, '442', '541', 'monedas', 0, NULL, 1777.7500, 1427.0702, 1959.1078, 1484.7613, 0.9388, 0.8250, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 3, NULL, NULL, 'resuelto', 'dificil', 8, 1, 'B', 2, 7, 6, '2026-08-06 15:53:30', '2026-08-06 15:53:31', NULL, NULL, 0, NULL, NULL, 0),
(1847, 2, 10, 8, NULL, '442', '352', 'monedas', 0, NULL, 1777.7500, 1674.4722, 1874.0695, 1775.8539, 0.6377, 0.5589, 400.0000, 'fuego', 'viento', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'muy_dificil', 9, 2, 'B', 2, 2, 0, '2026-08-06 15:54:24', '2026-08-06 15:54:26', NULL, NULL, 0, NULL, NULL, 0),
(1848, 9, 10, 1, NULL, '442', '442', 'monedas', 0, NULL, 1486.4500, 1770.9049, 1535.8604, 1885.1197, 0.1181, 0.3451, 400.0000, 'bosque', 'neutro', 0.000, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'extremo', 7, 1, NULL, 10, 4, 8, '2026-08-07 11:21:48', '2026-08-07 11:23:31', NULL, NULL, 0, NULL, NULL, 0),
(1849, 9, 2, 1, 8, '442', '442', 'monedas', 100, NULL, 1486.4500, 1777.7500, 1539.5235, 1966.6927, 0.0788, 0.3297, 400.0000, 'bosque', 'fuego', 0.000, 5.500, 0.000, 0.000, 2, 3, '2026-08-07 11:44:43', '2026-08-07 11:44:12', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 4, '2026-08-07 11:44:08', '2026-08-07 11:44:16', NULL, NULL, 0, NULL, NULL, 0),
(1850, 9, 2, 1, 8, '442', '442', 'monedas', 100, NULL, 1486.4500, 1777.7500, 1539.3122, 1961.4754, 0.0809, 0.1094, 400.0000, 'bosque', 'fuego', 0.000, 5.500, 0.000, 0.000, 2, 3, '2026-08-07 11:55:46', '2026-08-07 11:54:34', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 4, '2026-08-07 11:53:12', '2026-08-07 11:55:20', NULL, NULL, 0, NULL, NULL, 0),
(1851, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1966.0759, 1541.8960, 0.9200, 0.8949, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-07 12:06:31', '2026-08-07 12:05:59', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 0, '2026-08-07 12:05:55', '2026-08-07 12:06:06', NULL, NULL, 0, NULL, NULL, 0),
(1852, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1965.4932, 1546.1973, 0.9179, 0.1313, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-07 12:31:47', '2026-08-07 12:31:16', 'resuelto', NULL, NULL, NULL, NULL, 2, 1, 0, '2026-08-07 12:31:09', '2026-08-07 12:31:20', NULL, NULL, 0, NULL, NULL, 0),
(1853, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1960.7655, 1533.9495, 0.9211, 0.9051, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-07 12:32:29', '2026-08-07 12:31:58', 'resuelto', NULL, NULL, NULL, NULL, 2, 3, 2, '2026-08-07 12:31:54', '2026-08-07 12:32:02', NULL, NULL, 0, NULL, NULL, 0),
(1854, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1962.0253, 1556.3870, 0.9117, 0.1541, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-07 12:33:51', '2026-08-07 12:33:18', 'resuelto', NULL, NULL, NULL, NULL, 2, 1, 0, '2026-08-07 12:33:15', '2026-08-07 12:33:24', NULL, NULL, 0, NULL, NULL, 0),
(1855, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1965.6905, 1535.7679, 0.9224, 0.8638, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-07 12:34:23', '2026-08-07 12:33:51', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 1, '2026-08-07 12:33:47', '2026-08-07 12:33:56', NULL, NULL, 0, NULL, NULL, 0),
(1856, 9, NULL, 1, NULL, '442', NULL, 'monedas', 100, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-07 12:55:05', 'cancelado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-07 12:55:05', NULL, NULL, NULL, 0, NULL, NULL, 0),
(1857, 9, 2, 1, 8, '442', '442', 'monedas', 100, NULL, 1486.4500, 1777.7500, 1557.1051, 1960.8335, 0.0892, 0.1558, 400.0000, 'bosque', 'fuego', 0.000, 5.500, 0.000, 0.000, 2, 3, '2026-08-07 12:55:43', '2026-08-07 12:55:11', 'resuelto', NULL, NULL, NULL, NULL, 2, 0, 1, '2026-08-07 12:55:07', '2026-08-07 12:55:17', '2026-08-07 12:55:18', NULL, 0, '2026-08-07 12:56:33', '2026-08-07 12:56:33', 0),
(1858, 9, 2, 1, 8, '442', '442', 'monedas', 100, NULL, 1486.4500, 1777.7500, 1542.6239, 1961.2961, 0.0824, 0.7619, 400.0000, 'bosque', 'fuego', 0.000, 5.500, 0.000, 0.000, 2, 3, '2026-08-07 13:29:48', '2026-08-07 13:29:16', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 4, '2026-08-07 13:29:12', '2026-08-07 13:29:21', '2026-08-07 13:29:23', NULL, 34, '2026-08-07 14:02:32', '2026-08-07 13:30:24', 0),
(1859, 9, 2, 1, 8, '442', '442', 'monedas', 100, NULL, 1486.4500, 1777.7500, 1532.7167, 1964.8424, 0.0767, 0.2025, 400.0000, 'bosque', 'fuego', 0.000, 5.500, 0.000, 0.000, 2, 3, '2026-08-10 11:47:47', '2026-08-10 11:47:15', 'resuelto', NULL, NULL, NULL, NULL, 2, 0, 1, '2026-08-10 11:47:11', '2026-08-10 11:47:25', '2026-08-10 11:47:27', '2026-08-10 11:47:51', 12, '2026-08-10 11:48:35', '2026-08-10 11:48:35', 0),
(1860, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1980.8780, 1536.1994, 0.9282, 0.4737, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-10 11:55:01', '2026-08-10 11:54:28', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 0, '2026-08-10 11:54:22', '2026-08-10 11:54:38', '2026-08-10 11:54:39', '2026-08-10 11:54:42', 0, '2026-08-10 11:55:16', '2026-08-10 11:55:20', 0),
(1861, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1971.0871, 1528.9567, 0.9272, 0.1380, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-10 12:11:22', '2026-08-10 12:10:50', 'resuelto', NULL, NULL, NULL, NULL, 2, 3, 2, '2026-08-10 12:10:47', '2026-08-10 12:10:55', '2026-08-10 12:10:57', NULL, 27, '2026-08-10 12:12:09', '2026-08-10 12:12:09', 0),
(1862, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1958.6056, 1531.7921, 0.9211, 0.7785, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-10 12:13:47', '2026-08-10 12:13:16', 'resuelto', NULL, NULL, NULL, NULL, 2, 3, 0, '2026-08-10 12:13:10', '2026-08-10 12:13:21', '2026-08-10 12:13:23', NULL, 23, '2026-08-10 12:14:31', '2026-08-10 12:14:31', 0),
(1863, 9, NULL, 1, NULL, '442', NULL, 'monedas', 100, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-10 12:13:11', 'cancelado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-10 12:13:11', NULL, NULL, NULL, 0, NULL, NULL, 0),
(1864, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1953.5723, 1551.5049, 0.9101, 0.1163, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-10 12:59:15', '2026-08-10 12:58:43', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 0, '2026-08-10 12:58:37', '2026-08-10 12:58:50', '2026-08-10 12:58:51', NULL, 15, '2026-08-10 12:59:51', '2026-08-10 12:59:51', 0),
(1865, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1970.7787, 1535.2748, 0.9246, 0.4113, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-11 08:26:24', '2026-08-11 08:25:51', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 1, '2026-08-11 08:25:42', '2026-08-11 08:25:58', '2026-08-11 08:26:00', NULL, 16, '2026-08-11 08:27:01', '2026-08-11 08:27:01', 0),
(1866, 9, NULL, 1, NULL, '442', NULL, 'monedas', 100, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-11 08:25:49', 'cancelado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-11 08:25:49', NULL, NULL, NULL, 0, NULL, NULL, 0),
(1867, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1974.2921, 1539.6512, 0.9243, 0.8434, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-11 08:28:03', '2026-08-11 08:27:32', 'resuelto', NULL, NULL, NULL, NULL, 9, 0, 2, '2026-08-11 08:27:26', '2026-08-11 08:27:41', '2026-08-11 08:27:43', NULL, 21, '2026-08-11 08:28:49', '2026-08-11 08:28:49', 0),
(1868, 9, 2, 1, 8, '442', '442', 'monedas', 10, NULL, 1486.4500, 1777.7500, 1532.9016, 1970.4463, 0.0746, 0.5130, 400.0000, 'bosque', 'fuego', 0.000, 5.500, 0.000, 0.000, 2, 3, '2026-08-11 08:32:33', '2026-08-11 08:32:01', 'resuelto', NULL, NULL, NULL, NULL, 2, 0, 1, '2026-08-11 08:31:54', '2026-08-11 08:32:32', '2026-08-11 08:27:42', '2026-08-11 08:33:36', 16, '2026-08-11 08:33:01', '2026-08-11 08:33:36', 0),
(1869, 9, 2, 1, 8, '442', '442', 'monedas', 10, NULL, 1486.4500, 1777.7500, 1541.5043, 1961.2988, 0.0819, 0.2066, 400.0000, 'bosque', 'fuego', 0.000, 5.500, 0.000, 0.000, 2, 3, '2026-08-11 08:43:52', '2026-08-11 08:43:21', 'resuelto', NULL, NULL, NULL, NULL, 9, 1, 1, '2026-08-11 08:43:14', '2026-08-11 08:43:31', '2026-08-11 08:38:39', NULL, 25, '2026-08-11 08:46:11', '2026-08-11 08:45:47', 1),
(1870, 9, 10, 1, NULL, '442', '442', 'monedas', 0, NULL, 1486.4500, 1236.1349, 1547.8360, 1297.1356, 0.8089, 0.7149, 400.0000, 'bosque', 'neutro', 0.000, 0.000, 0.000, 0.000, 2, 3, NULL, NULL, 'resuelto', 'facil', 7, 1, 'B', 9, 1, 0, '2026-08-11 11:52:24', '2026-08-11 11:52:41', '2026-08-11 11:52:41', NULL, 20, '2026-08-11 11:53:46', NULL, 0),
(1871, 9, 10, 1, NULL, '442', '352', 'monedas', 0, NULL, 1486.4500, 1209.7939, 1616.0262, 1279.8170, 0.8738, 0.7351, 400.0000, 'bosque', 'viento', 5.500, 0.000, 0.000, 0.000, 2, 2, NULL, NULL, 'resuelto', 'facil', 9, 2, 'B', 9, 1, 1, '2026-08-11 12:40:15', '2026-08-11 12:40:19', '2026-08-11 12:40:19', NULL, 21, '2026-08-11 12:42:29', NULL, 1),
(1872, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1972.3029, 1548.5095, 0.9198, 0.6728, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-12 08:07:04', '2026-08-12 08:06:33', 'resuelto', NULL, NULL, NULL, NULL, 2, 2, 0, '2026-08-12 08:05:46', '2026-08-12 08:06:39', '2026-08-12 08:06:41', NULL, 19, '2026-08-12 08:07:45', '2026-08-12 08:07:45', 0),
(1873, 2, 9, 8, 1, '442', '442', 'monedas', 100, NULL, 1777.7500, 1486.4500, 1971.2412, 1558.6064, 0.9149, 0.9713, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 2, '2026-08-12 08:08:57', '2026-08-12 08:08:27', 'resuelto', NULL, NULL, NULL, NULL, 2, 1, 0, '2026-08-12 08:08:18', '2026-08-12 08:08:34', '2026-08-12 08:08:37', NULL, 19, '2026-08-12 08:09:41', '2026-08-12 08:09:41', 0),
(1874, 9, NULL, 1, NULL, '442', NULL, 'monedas', 100, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-12 08:08:23', 'cancelado', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-12 08:08:23', NULL, NULL, NULL, 0, NULL, NULL, 0),
(1875, 2, 10, 8, NULL, '442', '541', 'monedas', 0, NULL, 1777.7500, 1570.8479, 1957.1486, 1658.7963, 0.8478, 0.6044, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 3, NULL, NULL, 'resuelto', 'muy_dificil', 8, 1, 'B', 2, 2, 1, '2026-08-12 08:10:27', '2026-08-12 08:10:31', '2026-08-12 08:10:31', NULL, 8, '2026-08-12 08:11:24', NULL, 0),
(1876, 2, 10, 8, NULL, '442', '442', 'monedas', 0, NULL, 1777.7500, 1710.9307, 1862.5246, 1803.9011, 0.5836, 0.3610, 400.0000, 'fuego', 'neutro', 0.000, 0.000, 0.000, 0.000, 3, 3, NULL, NULL, 'resuelto', 'muy_dificil', 7, 1, 'B', 2, 2, 0, '2026-08-12 08:11:31', '2026-08-12 08:11:42', '2026-08-12 08:11:42', NULL, 12, '2026-08-12 08:12:39', NULL, 0),
(1877, 2, 10, 8, NULL, '442', '442', 'monedas', 0, NULL, 1777.7500, 1710.9307, 1869.0415, 1814.0423, 0.5785, 0.2435, 400.0000, 'fuego', 'neutro', 0.000, 0.000, 0.000, 0.000, 3, 3, NULL, NULL, 'resuelto', 'muy_dificil', 7, 1, 'B', 2, 1, 0, '2026-08-12 08:12:48', '2026-08-12 08:12:50', '2026-08-12 08:12:50', NULL, 11, '2026-08-12 08:13:46', NULL, 0),
(1878, 2, 10, 8, NULL, '442', '541', 'monedas', 0, NULL, 1777.7500, 1570.8479, 1947.3715, 1655.3321, 0.8431, 0.3102, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 3, NULL, NULL, 'resuelto', 'muy_dificil', 8, 1, NULL, 10, 0, 1, '2026-08-12 08:14:47', '2026-08-12 08:14:50', '2026-08-12 08:14:50', NULL, 3, '2026-08-12 08:15:38', NULL, 0),
(1879, 2, 10, 8, NULL, '442', '541', 'monedas', 0, NULL, 1777.7500, 1570.8479, 1959.9084, 1641.8448, 0.8619, 0.8603, 400.0000, 'fuego', 'bosque', 5.500, 0.000, 0.000, 0.000, 3, 3, NULL, NULL, 'resuelto', 'muy_dificil', 8, 1, NULL, 10, 0, 0, '2026-08-12 08:15:46', '2026-08-12 08:15:49', '2026-08-12 08:15:49', NULL, 8, '2026-08-12 08:17:05', NULL, 1),
(1880, 2, 10, 8, NULL, '442', '361', 'monedas', 0, NULL, 1777.7500, 1575.4180, 1869.3520, 1641.5429, 0.7877, 0.6412, 400.0000, 'fuego', 'fuego', 0.000, 0.000, 0.000, 0.000, 3, 2, NULL, NULL, 'resuelto', 'muy_dificil', 10, 11, 'B', 2, 1, 0, '2026-08-12 09:55:04', '2026-08-12 09:55:06', '2026-08-12 09:55:06', NULL, 8, '2026-08-12 09:55:59', NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `duelo_alineaciones`
--

CREATE TABLE `duelo_alineaciones` (
  `id_alineacion` int(11) NOT NULL,
  `id_duelo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `hueco` tinyint(3) UNSIGNED NOT NULL,
  `id_cromo` int(11) NOT NULL,
  `ataque` tinyint(3) UNSIGNED NOT NULL,
  `defensa` tinyint(3) UNSIGNED NOT NULL,
  `tecnica` tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `duelo_alineaciones`
--

INSERT INTO `duelo_alineaciones` (`id_alineacion`, `id_duelo`, `id_usuario`, `hueco`, `id_cromo`, `ataque`, `defensa`, `tecnica`) VALUES
(22936, 1047, 2, 0, 5, 73, 99, 94),
(22980, 1049, 2, 0, 5, 73, 99, 94),
(22991, 1049, 10, 0, 7, 69, 99, 93),
(22999, 1049, 10, 8, 6, 99, 81, 98),
(23000, 1049, 10, 9, 8, 97, 78, 90),
(23002, 1050, 2, 0, 5, 73, 99, 94),
(23013, 1050, 10, 0, 7, 69, 99, 93),
(23021, 1050, 10, 8, 6, 99, 81, 98),
(23022, 1050, 10, 9, 8, 97, 78, 90),
(23046, 1052, 2, 0, 5, 73, 99, 94),
(23057, 1052, 10, 0, 7, 69, 99, 93),
(23065, 1052, 10, 8, 6, 99, 81, 98),
(23066, 1052, 10, 9, 8, 97, 78, 90),
(23101, 1055, 2, 0, 5, 73, 99, 94),
(23112, 1055, 10, 0, 5, 73, 99, 94),
(23121, 1055, 10, 9, 3, 99, 84, 95),
(23122, 1055, 10, 10, 4, 99, 86, 99),
(23123, 1056, 2, 0, 5, 73, 99, 94),
(23134, 1056, 10, 0, 5, 73, 99, 94),
(23143, 1056, 10, 9, 3, 99, 84, 95),
(23144, 1056, 10, 10, 4, 99, 86, 99),
(28821, 1315, 2, 0, 5, 73, 99, 94),
(28843, 1316, 2, 0, 5, 73, 99, 94),
(28865, 1317, 2, 0, 5, 73, 99, 94),
(28887, 1318, 2, 0, 5, 73, 99, 94),
(34409, 1569, 2, 0, 5, 73, 99, 94),
(34414, 1569, 2, 5, 6, 99, 81, 98),
(34417, 1569, 2, 8, 8, 97, 78, 90),
(34418, 1569, 2, 9, 3, 99, 84, 95),
(34419, 1569, 2, 10, 4, 99, 86, 99),
(34431, 1570, 2, 0, 5, 73, 99, 94),
(34436, 1570, 2, 5, 6, 99, 81, 98),
(34439, 1570, 2, 8, 8, 97, 78, 90),
(34440, 1570, 2, 9, 3, 99, 84, 95),
(34441, 1570, 2, 10, 4, 99, 86, 99),
(34453, 1571, 2, 0, 5, 73, 99, 94),
(34458, 1571, 2, 5, 6, 99, 81, 98),
(34461, 1571, 2, 8, 8, 97, 78, 90),
(34462, 1571, 2, 9, 3, 99, 84, 95),
(34463, 1571, 2, 10, 4, 99, 86, 99),
(34475, 1572, 2, 0, 5, 73, 99, 94),
(34480, 1572, 2, 5, 6, 99, 81, 98),
(34483, 1572, 2, 8, 8, 97, 78, 90),
(34484, 1572, 2, 9, 3, 99, 84, 95),
(34485, 1572, 2, 10, 4, 99, 86, 99),
(34497, 1573, 2, 0, 5, 73, 99, 94),
(34502, 1573, 2, 5, 6, 99, 81, 98),
(34505, 1573, 2, 8, 8, 97, 78, 90),
(34506, 1573, 2, 9, 3, 99, 84, 95),
(34507, 1573, 2, 10, 4, 99, 86, 99),
(34519, 1574, 2, 0, 5, 73, 99, 94),
(34524, 1574, 2, 5, 6, 99, 81, 98),
(34527, 1574, 2, 8, 8, 97, 78, 90),
(34528, 1574, 2, 9, 3, 99, 84, 95),
(34529, 1574, 2, 10, 4, 99, 86, 99),
(34541, 1575, 2, 0, 5, 73, 99, 94),
(34546, 1575, 2, 5, 6, 99, 81, 98),
(34549, 1575, 2, 8, 8, 97, 78, 90),
(34550, 1575, 2, 9, 3, 99, 84, 95),
(34551, 1575, 2, 10, 4, 99, 86, 99),
(40503, 1846, 2, 0, 5, 73, 99, 94),
(40508, 1846, 2, 5, 6, 99, 81, 98),
(40511, 1846, 2, 8, 8, 97, 78, 90),
(40512, 1846, 2, 9, 3, 99, 84, 95),
(40513, 1846, 2, 10, 4, 99, 86, 99),
(40525, 1847, 2, 0, 5, 73, 99, 94),
(40530, 1847, 2, 5, 6, 99, 81, 98),
(40533, 1847, 2, 8, 8, 97, 78, 90),
(40534, 1847, 2, 9, 3, 99, 84, 95),
(40535, 1847, 2, 10, 4, 99, 86, 99),
(40567, 1848, 10, 9, 8, 97, 78, 90),
(40580, 1849, 2, 0, 5, 73, 99, 94),
(40585, 1849, 2, 5, 6, 99, 81, 98),
(40588, 1849, 2, 8, 8, 97, 78, 90),
(40589, 1849, 2, 9, 3, 99, 84, 95),
(40590, 1849, 2, 10, 4, 99, 86, 99),
(40602, 1850, 2, 0, 5, 73, 99, 94),
(40607, 1850, 2, 5, 6, 99, 81, 98),
(40610, 1850, 2, 8, 8, 97, 78, 90),
(40611, 1850, 2, 9, 3, 99, 84, 95),
(40612, 1850, 2, 10, 4, 99, 86, 99),
(40613, 1851, 2, 0, 5, 73, 99, 94),
(40618, 1851, 2, 5, 6, 99, 81, 98),
(40621, 1851, 2, 8, 8, 97, 78, 90),
(40622, 1851, 2, 9, 3, 99, 84, 95),
(40623, 1851, 2, 10, 4, 99, 86, 99),
(40635, 1852, 2, 0, 5, 73, 99, 94),
(40640, 1852, 2, 5, 6, 99, 81, 98),
(40643, 1852, 2, 8, 8, 97, 78, 90),
(40644, 1852, 2, 9, 3, 99, 84, 95),
(40645, 1852, 2, 10, 4, 99, 86, 99),
(40657, 1853, 2, 0, 5, 73, 99, 94),
(40662, 1853, 2, 5, 6, 99, 81, 98),
(40665, 1853, 2, 8, 8, 97, 78, 90),
(40666, 1853, 2, 9, 3, 99, 84, 95),
(40667, 1853, 2, 10, 4, 99, 86, 99),
(40679, 1854, 2, 0, 5, 73, 99, 94),
(40684, 1854, 2, 5, 6, 99, 81, 98),
(40687, 1854, 2, 8, 8, 97, 78, 90),
(40688, 1854, 2, 9, 3, 99, 84, 95),
(40689, 1854, 2, 10, 4, 99, 86, 99),
(40701, 1855, 2, 0, 5, 73, 99, 94),
(40706, 1855, 2, 5, 6, 99, 81, 98),
(40709, 1855, 2, 8, 8, 97, 78, 90),
(40710, 1855, 2, 9, 3, 99, 84, 95),
(40711, 1855, 2, 10, 4, 99, 86, 99),
(40745, 1857, 2, 0, 5, 73, 99, 94),
(40750, 1857, 2, 5, 6, 99, 81, 98),
(40753, 1857, 2, 8, 8, 97, 78, 90),
(40754, 1857, 2, 9, 3, 99, 84, 95),
(40755, 1857, 2, 10, 4, 99, 86, 99),
(40767, 1858, 2, 0, 5, 73, 99, 94),
(40772, 1858, 2, 5, 6, 99, 81, 98),
(40775, 1858, 2, 8, 8, 97, 78, 90),
(40776, 1858, 2, 9, 3, 99, 84, 95),
(40777, 1858, 2, 10, 4, 99, 86, 99),
(40789, 1859, 2, 0, 5, 73, 99, 94),
(40794, 1859, 2, 5, 6, 99, 81, 98),
(40797, 1859, 2, 8, 8, 97, 78, 90),
(40798, 1859, 2, 9, 3, 99, 84, 95),
(40799, 1859, 2, 10, 4, 99, 86, 99),
(40800, 1860, 2, 0, 5, 73, 99, 94),
(40805, 1860, 2, 5, 6, 99, 81, 98),
(40808, 1860, 2, 8, 8, 97, 78, 90),
(40809, 1860, 2, 9, 3, 99, 84, 95),
(40810, 1860, 2, 10, 4, 99, 86, 99),
(40822, 1861, 2, 0, 5, 73, 99, 94),
(40827, 1861, 2, 5, 6, 99, 81, 98),
(40830, 1861, 2, 8, 8, 97, 78, 90),
(40831, 1861, 2, 9, 3, 99, 84, 95),
(40832, 1861, 2, 10, 4, 99, 86, 99),
(40844, 1862, 2, 0, 5, 73, 99, 94),
(40849, 1862, 2, 5, 6, 99, 81, 98),
(40852, 1862, 2, 8, 8, 97, 78, 90),
(40853, 1862, 2, 9, 3, 99, 84, 95),
(40854, 1862, 2, 10, 4, 99, 86, 99),
(40877, 1864, 2, 0, 5, 73, 99, 94),
(40882, 1864, 2, 5, 6, 99, 81, 98),
(40885, 1864, 2, 8, 8, 97, 78, 90),
(40886, 1864, 2, 9, 3, 99, 84, 95),
(40887, 1864, 2, 10, 4, 99, 86, 99),
(40899, 1865, 2, 0, 5, 73, 99, 94),
(40904, 1865, 2, 5, 6, 99, 81, 98),
(40907, 1865, 2, 8, 8, 97, 78, 90),
(40908, 1865, 2, 9, 3, 99, 84, 95),
(40909, 1865, 2, 10, 4, 99, 86, 99),
(40932, 1867, 2, 0, 5, 73, 99, 94),
(40937, 1867, 2, 5, 6, 99, 81, 98),
(40940, 1867, 2, 8, 8, 97, 78, 90),
(40941, 1867, 2, 9, 3, 99, 84, 95),
(40942, 1867, 2, 10, 4, 99, 86, 99),
(40965, 1868, 2, 0, 5, 73, 99, 94),
(40970, 1868, 2, 5, 6, 99, 81, 98),
(40973, 1868, 2, 8, 8, 97, 78, 90),
(40974, 1868, 2, 9, 3, 99, 84, 95),
(40975, 1868, 2, 10, 4, 99, 86, 99),
(40987, 1869, 2, 0, 5, 73, 99, 94),
(40992, 1869, 2, 5, 6, 99, 81, 98),
(40995, 1869, 2, 8, 8, 97, 78, 90),
(40996, 1869, 2, 9, 3, 99, 84, 95),
(40997, 1869, 2, 10, 4, 99, 86, 99),
(41018, 1870, 10, 9, 8, 97, 78, 90),
(41042, 1872, 2, 0, 5, 73, 99, 94),
(41047, 1872, 2, 5, 6, 99, 81, 98),
(41050, 1872, 2, 8, 8, 97, 78, 90),
(41051, 1872, 2, 9, 3, 99, 84, 95),
(41052, 1872, 2, 10, 4, 99, 86, 99),
(41064, 1873, 2, 0, 5, 73, 99, 94),
(41069, 1873, 2, 5, 6, 99, 81, 98),
(41072, 1873, 2, 8, 8, 97, 78, 90),
(41073, 1873, 2, 9, 3, 99, 84, 95),
(41074, 1873, 2, 10, 4, 99, 86, 99),
(41097, 1875, 2, 0, 5, 73, 99, 94),
(41102, 1875, 2, 5, 6, 99, 81, 98),
(41105, 1875, 2, 8, 8, 97, 78, 90),
(41106, 1875, 2, 9, 3, 99, 84, 95),
(41107, 1875, 2, 10, 4, 99, 86, 99),
(41119, 1876, 2, 0, 5, 73, 99, 94),
(41124, 1876, 2, 5, 6, 99, 81, 98),
(41127, 1876, 2, 8, 8, 97, 78, 90),
(41128, 1876, 2, 9, 3, 99, 84, 95),
(41129, 1876, 2, 10, 4, 99, 86, 99),
(41139, 1876, 10, 9, 8, 97, 78, 90),
(41141, 1877, 2, 0, 5, 73, 99, 94),
(41146, 1877, 2, 5, 6, 99, 81, 98),
(41149, 1877, 2, 8, 8, 97, 78, 90),
(41150, 1877, 2, 9, 3, 99, 84, 95),
(41151, 1877, 2, 10, 4, 99, 86, 99),
(41161, 1877, 10, 9, 8, 97, 78, 90),
(41163, 1878, 2, 0, 5, 73, 99, 94),
(41168, 1878, 2, 5, 6, 99, 81, 98),
(41171, 1878, 2, 8, 8, 97, 78, 90),
(41172, 1878, 2, 9, 3, 99, 84, 95),
(41173, 1878, 2, 10, 4, 99, 86, 99),
(41185, 1879, 2, 0, 5, 73, 99, 94),
(41190, 1879, 2, 5, 6, 99, 81, 98),
(41193, 1879, 2, 8, 8, 97, 78, 90),
(41194, 1879, 2, 9, 3, 99, 84, 95),
(41195, 1879, 2, 10, 4, 99, 86, 99),
(41207, 1880, 2, 0, 5, 73, 99, 94),
(41212, 1880, 2, 5, 6, 99, 81, 98),
(41215, 1880, 2, 8, 8, 97, 78, 90),
(41216, 1880, 2, 9, 3, 99, 84, 95),
(41217, 1880, 2, 10, 4, 99, 86, 99);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `duelo_apuestas`
--

CREATE TABLE `duelo_apuestas` (
  `id_apuesta` int(11) NOT NULL,
  `id_duelo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `monedas` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `id_coleccion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `duelo_apuestas`
--

INSERT INTO `duelo_apuestas` (`id_apuesta`, `id_duelo`, `id_usuario`, `monedas`, `id_coleccion`) VALUES
(27, 1849, 9, 100, NULL),
(28, 1849, 2, 100, NULL),
(29, 1850, 9, 100, NULL),
(30, 1850, 2, 100, NULL),
(31, 1851, 2, 100, NULL),
(32, 1851, 9, 100, NULL),
(33, 1852, 2, 100, NULL),
(34, 1852, 9, 100, NULL),
(35, 1853, 2, 100, NULL),
(36, 1853, 9, 100, NULL),
(37, 1854, 2, 100, NULL),
(38, 1854, 9, 100, NULL),
(39, 1855, 2, 100, NULL),
(40, 1855, 9, 100, NULL),
(41, 1856, 9, 100, NULL),
(42, 1857, 9, 100, NULL),
(43, 1857, 2, 100, NULL),
(44, 1858, 9, 100, NULL),
(45, 1858, 2, 100, NULL),
(46, 1859, 9, 100, NULL),
(47, 1859, 2, 100, NULL),
(48, 1860, 2, 100, NULL),
(49, 1860, 9, 100, NULL),
(50, 1861, 2, 100, NULL),
(51, 1861, 9, 100, NULL),
(52, 1862, 2, 100, NULL),
(53, 1863, 9, 100, NULL),
(54, 1862, 9, 100, NULL),
(55, 1864, 2, 100, NULL),
(56, 1864, 9, 100, NULL),
(57, 1865, 2, 100, NULL),
(58, 1866, 9, 100, NULL),
(59, 1865, 9, 100, NULL),
(60, 1867, 2, 100, NULL),
(61, 1867, 9, 100, NULL),
(62, 1868, 9, 10, NULL),
(63, 1868, 2, 10, NULL),
(64, 1869, 9, 10, NULL),
(65, 1869, 2, 10, NULL),
(66, 1872, 2, 100, NULL),
(67, 1872, 9, 100, NULL),
(68, 1873, 2, 100, NULL),
(69, 1874, 9, 100, NULL),
(70, 1873, 9, 100, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `duelo_aumentos`
--

CREATE TABLE `duelo_aumentos` (
  `id_aumento` int(11) NOT NULL,
  `id_duelo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `opcion` tinyint(3) UNSIGNED NOT NULL,
  `stat` enum('ataque','defensa','tecnica') NOT NULL,
  `tier` enum('plata','oro','prisma') NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL,
  `tension_nivel` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `elegida` tinyint(1) NOT NULL DEFAULT 0,
  `por_defecto` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `duelo_aumentos`
--

INSERT INTO `duelo_aumentos` (`id_aumento`, `id_duelo`, `id_usuario`, `opcion`, `stat`, `tier`, `porcentaje`, `tension_nivel`, `elegida`, `por_defecto`) VALUES
(6211, 1047, 2, 1, 'defensa', 'prisma', 4.26, 2, 0, 0),
(6212, 1047, 2, 2, 'defensa', 'oro', 3.46, 2, 1, 0),
(6213, 1047, 2, 3, 'defensa', 'plata', 1.19, 2, 0, 0),
(6214, 1047, 10, 1, 'tecnica', 'prisma', 4.85, 0, 0, 0),
(6215, 1047, 10, 2, 'ataque', 'prisma', 4.75, 0, 0, 0),
(6216, 1047, 10, 3, 'defensa', 'prisma', 3.95, 0, 1, 0),
(6223, 1049, 2, 1, 'tecnica', 'prisma', 3.93, 2, 1, 0),
(6224, 1049, 2, 2, 'ataque', 'oro', 3.31, 2, 0, 0),
(6225, 1049, 2, 3, 'ataque', 'plata', 0.82, 2, 0, 0),
(6226, 1049, 10, 1, 'ataque', 'prisma', 4.77, 0, 0, 0),
(6227, 1049, 10, 2, 'defensa', 'prisma', 4.65, 0, 0, 0),
(6228, 1049, 10, 3, 'ataque', 'plata', 1.61, 0, 1, 0),
(6229, 1050, 2, 1, 'tecnica', 'plata', 0.93, 2, 0, 0),
(6230, 1050, 2, 2, 'ataque', 'oro', 2.89, 2, 1, 0),
(6231, 1050, 2, 3, 'ataque', 'plata', 0.17, 2, 0, 0),
(6232, 1050, 10, 1, 'ataque', 'oro', 3.40, 0, 0, 0),
(6233, 1050, 10, 2, 'defensa', 'oro', 3.09, 0, 1, 0),
(6234, 1050, 10, 3, 'ataque', 'prisma', 4.85, 0, 0, 0),
(6241, 1052, 2, 1, 'defensa', 'prisma', 4.17, 2, 1, 0),
(6242, 1052, 2, 2, 'defensa', 'plata', 0.36, 2, 0, 0),
(6243, 1052, 2, 3, 'defensa', 'plata', 1.30, 2, 0, 0),
(6244, 1052, 10, 1, 'ataque', 'plata', 1.22, 0, 0, 0),
(6245, 1052, 10, 2, 'tecnica', 'prisma', 4.90, 0, 0, 0),
(6246, 1052, 10, 3, 'defensa', 'oro', 3.41, 0, 1, 0),
(6253, 1055, 2, 1, 'tecnica', 'oro', 2.39, 2, 1, 0),
(6254, 1055, 2, 2, 'ataque', 'plata', 0.20, 2, 0, 0),
(6255, 1055, 2, 3, 'ataque', 'plata', 0.20, 2, 0, 0),
(6256, 1055, 10, 1, 'tecnica', 'plata', 1.08, 0, 1, 0),
(6257, 1055, 10, 2, 'ataque', 'plata', 0.88, 0, 0, 0),
(6258, 1055, 10, 3, 'defensa', 'prisma', 4.16, 0, 0, 0),
(6259, 1056, 2, 1, 'tecnica', 'oro', 3.37, 2, 1, 0),
(6260, 1056, 2, 2, 'tecnica', 'plata', 1.21, 2, 0, 0),
(6261, 1056, 2, 3, 'defensa', 'oro', 3.07, 2, 0, 0),
(6262, 1056, 10, 1, 'tecnica', 'plata', 1.23, 0, 0, 0),
(6263, 1056, 10, 2, 'defensa', 'oro', 3.41, 0, 1, 0),
(6264, 1056, 10, 3, 'ataque', 'prisma', 3.74, 0, 0, 0),
(7813, 1315, 2, 1, 'ataque', 'oro', 3.38, 2, 1, 0),
(7814, 1315, 2, 2, 'ataque', 'plata', 1.73, 2, 0, 0),
(7815, 1315, 2, 3, 'defensa', 'plata', 0.20, 2, 0, 0),
(7816, 1315, 10, 1, 'tecnica', 'plata', 0.58, 0, 1, 0),
(7817, 1315, 10, 2, 'ataque', 'plata', 0.39, 0, 0, 0),
(7818, 1315, 10, 3, 'tecnica', 'oro', 3.34, 0, 0, 0),
(7819, 1316, 2, 1, 'defensa', 'plata', 0.57, 2, 0, 0),
(7820, 1316, 2, 2, 'ataque', 'prisma', 4.06, 2, 1, 0),
(7821, 1316, 2, 3, 'tecnica', 'oro', 3.36, 2, 0, 0),
(7822, 1316, 10, 1, 'tecnica', 'prisma', 4.35, 0, 1, 0),
(7823, 1316, 10, 2, 'tecnica', 'oro', 3.45, 0, 0, 0),
(7824, 1316, 10, 3, 'ataque', 'plata', 0.35, 0, 0, 0),
(7825, 1317, 2, 1, 'defensa', 'plata', 1.53, 2, 1, 0),
(7826, 1317, 2, 2, 'ataque', 'plata', 0.22, 2, 0, 0),
(7827, 1317, 2, 3, 'defensa', 'plata', 0.00, 2, 0, 0),
(7828, 1317, 10, 1, 'tecnica', 'plata', 0.44, 0, 0, 0),
(7829, 1317, 10, 2, 'defensa', 'prisma', 4.04, 0, 1, 0),
(7830, 1317, 10, 3, 'tecnica', 'prisma', 4.08, 0, 0, 0),
(7831, 1318, 2, 1, 'tecnica', 'prisma', 4.59, 2, 1, 0),
(7832, 1318, 2, 2, 'defensa', 'oro', 3.13, 2, 0, 0),
(7833, 1318, 2, 3, 'tecnica', 'prisma', 4.36, 2, 0, 0),
(7834, 1318, 10, 1, 'defensa', 'prisma', 4.69, 0, 0, 0),
(7835, 1318, 10, 2, 'defensa', 'plata', 0.69, 0, 0, 0),
(7836, 1318, 10, 3, 'ataque', 'prisma', 3.66, 0, 1, 0),
(9337, 1569, 2, 1, 'ataque', 'oro', 2.15, 3, 0, 0),
(9338, 1569, 2, 2, 'tecnica', 'oro', 3.06, 3, 0, 0),
(9339, 1569, 2, 3, 'ataque', 'prisma', 3.97, 3, 1, 0),
(9340, 1569, 10, 1, 'ataque', 'oro', 2.20, 0, 0, 0),
(9341, 1569, 10, 2, 'defensa', 'plata', 0.85, 0, 0, 0),
(9342, 1569, 10, 3, 'ataque', 'oro', 2.27, 0, 1, 0),
(9343, 1570, 2, 1, 'ataque', 'plata', 0.93, 3, 0, 0),
(9344, 1570, 2, 2, 'ataque', 'oro', 2.52, 3, 0, 0),
(9345, 1570, 2, 3, 'tecnica', 'oro', 2.88, 3, 1, 0),
(9346, 1570, 10, 1, 'ataque', 'prisma', 4.14, 0, 0, 0),
(9347, 1570, 10, 2, 'ataque', 'oro', 2.68, 0, 0, 0),
(9348, 1570, 10, 3, 'ataque', 'plata', 0.80, 0, 1, 0),
(9349, 1571, 2, 1, 'ataque', 'plata', 0.72, 3, 0, 0),
(9350, 1571, 2, 2, 'defensa', 'prisma', 4.26, 3, 1, 0),
(9351, 1571, 2, 3, 'tecnica', 'plata', 1.96, 3, 0, 0),
(9352, 1571, 10, 1, 'defensa', 'oro', 2.59, 0, 0, 0),
(9353, 1571, 10, 2, 'tecnica', 'prisma', 4.74, 0, 1, 0),
(9354, 1571, 10, 3, 'ataque', 'prisma', 3.91, 0, 0, 0),
(9355, 1572, 2, 1, 'defensa', 'plata', 0.37, 3, 0, 0),
(9356, 1572, 2, 2, 'defensa', 'oro', 2.28, 3, 1, 0),
(9357, 1572, 2, 3, 'defensa', 'plata', 0.67, 3, 0, 0),
(9358, 1572, 10, 1, 'ataque', 'prisma', 3.88, 0, 1, 0),
(9359, 1572, 10, 2, 'tecnica', 'prisma', 4.58, 0, 0, 0),
(9360, 1572, 10, 3, 'defensa', 'prisma', 4.92, 0, 0, 0),
(9361, 1573, 2, 1, 'ataque', 'plata', 1.01, 3, 0, 0),
(9362, 1573, 2, 2, 'tecnica', 'plata', 1.26, 3, 0, 0),
(9363, 1573, 2, 3, 'tecnica', 'oro', 2.08, 3, 1, 0),
(9364, 1573, 10, 1, 'ataque', 'prisma', 4.32, 0, 1, 0),
(9365, 1573, 10, 2, 'ataque', 'oro', 2.56, 0, 0, 0),
(9366, 1573, 10, 3, 'tecnica', 'plata', 0.57, 0, 0, 0),
(9367, 1574, 2, 1, 'tecnica', 'plata', 1.70, 3, 0, 0),
(9368, 1574, 2, 2, 'tecnica', 'prisma', 4.10, 3, 1, 0),
(9369, 1574, 2, 3, 'tecnica', 'plata', 0.77, 3, 0, 0),
(9370, 1574, 10, 1, 'defensa', 'oro', 2.72, 0, 0, 0),
(9371, 1574, 10, 2, 'ataque', 'oro', 3.40, 0, 0, 0),
(9372, 1574, 10, 3, 'ataque', 'oro', 2.62, 0, 1, 0),
(9373, 1575, 2, 1, 'tecnica', 'prisma', 3.85, 3, 1, 0),
(9374, 1575, 2, 2, 'defensa', 'oro', 2.41, 3, 0, 0),
(9375, 1575, 2, 3, 'tecnica', 'oro', 2.62, 3, 0, 0),
(9376, 1575, 10, 1, 'defensa', 'prisma', 4.91, 0, 0, 0),
(9377, 1575, 10, 2, 'defensa', 'prisma', 3.83, 0, 1, 0),
(9378, 1575, 10, 3, 'defensa', 'prisma', 4.01, 0, 0, 0),
(10999, 1846, 2, 1, 'defensa', 'plata', 0.29, 3, 0, 0),
(11000, 1846, 2, 2, 'tecnica', 'oro', 2.10, 3, 1, 0),
(11001, 1846, 2, 3, 'defensa', 'plata', 1.39, 3, 0, 0),
(11002, 1846, 10, 1, 'defensa', 'plata', 0.64, 0, 1, 0),
(11003, 1846, 10, 2, 'tecnica', 'oro', 3.39, 0, 0, 0),
(11004, 1846, 10, 3, 'ataque', 'plata', 0.99, 0, 0, 0),
(11005, 1847, 2, 1, 'tecnica', 'prisma', 4.82, 3, 1, 0),
(11006, 1847, 2, 2, 'defensa', 'oro', 2.03, 3, 0, 0),
(11007, 1847, 2, 3, 'ataque', 'plata', 1.36, 3, 0, 0),
(11008, 1847, 10, 1, 'tecnica', 'plata', 0.89, 0, 0, 0),
(11009, 1847, 10, 2, 'ataque', 'oro', 2.24, 0, 1, 0),
(11010, 1847, 10, 3, 'tecnica', 'prisma', 4.31, 0, 0, 0),
(11011, 1848, 9, 1, 'tecnica', 'plata', 1.16, 2, 0, 0),
(11012, 1848, 9, 2, 'ataque', 'oro', 3.04, 2, 1, 0),
(11013, 1848, 9, 3, 'ataque', 'plata', 0.71, 2, 0, 0),
(11014, 1848, 10, 1, 'tecnica', 'prisma', 4.39, 0, 1, 0),
(11015, 1848, 10, 2, 'ataque', 'prisma', 4.55, 0, 0, 0),
(11016, 1848, 10, 3, 'tecnica', 'prisma', 4.42, 0, 0, 0),
(11017, 1849, 9, 1, 'tecnica', 'plata', 1.64, 2, 0, 0),
(11018, 1849, 9, 2, 'tecnica', 'oro', 2.48, 2, 1, 0),
(11019, 1849, 9, 3, 'ataque', 'plata', 1.55, 2, 0, 0),
(11020, 1849, 2, 1, 'defensa', 'oro', 2.79, 3, 1, 0),
(11021, 1849, 2, 2, 'ataque', 'plata', 0.13, 3, 0, 0),
(11022, 1849, 2, 3, 'defensa', 'plata', 1.14, 3, 0, 0),
(11023, 1850, 9, 1, 'defensa', 'plata', 1.67, 2, 0, 0),
(11024, 1850, 9, 2, 'tecnica', 'plata', 0.65, 2, 0, 0),
(11025, 1850, 9, 3, 'ataque', 'prisma', 4.16, 2, 1, 0),
(11026, 1850, 2, 1, 'ataque', 'prisma', 3.69, 3, 1, 0),
(11027, 1850, 2, 2, 'tecnica', 'oro', 2.06, 3, 0, 0),
(11028, 1850, 2, 3, 'ataque', 'oro', 2.58, 3, 0, 0),
(11029, 1851, 2, 1, 'defensa', 'oro', 2.71, 3, 1, 0),
(11030, 1851, 2, 2, 'defensa', 'plata', 1.26, 3, 0, 0),
(11031, 1851, 2, 3, 'defensa', 'oro', 2.68, 3, 0, 0),
(11032, 1851, 9, 1, 'defensa', 'plata', 0.14, 2, 0, 0),
(11033, 1851, 9, 2, 'defensa', 'oro', 2.13, 2, 0, 0),
(11034, 1851, 9, 3, 'defensa', 'oro', 2.36, 2, 1, 0),
(11035, 1852, 2, 1, 'tecnica', 'oro', 3.38, 3, 0, 0),
(11036, 1852, 2, 2, 'ataque', 'prisma', 4.60, 3, 1, 0),
(11037, 1852, 2, 3, 'tecnica', 'plata', 1.08, 3, 0, 0),
(11038, 1852, 9, 1, 'tecnica', 'prisma', 3.75, 2, 1, 0),
(11039, 1852, 9, 2, 'defensa', 'plata', 0.96, 2, 0, 0),
(11040, 1852, 9, 3, 'ataque', 'oro', 2.41, 2, 0, 0),
(11041, 1853, 2, 1, 'tecnica', 'oro', 2.35, 3, 1, 0),
(11042, 1853, 2, 2, 'defensa', 'plata', 0.42, 3, 0, 0),
(11043, 1853, 2, 3, 'defensa', 'plata', 0.91, 3, 0, 0),
(11044, 1853, 9, 1, 'ataque', 'oro', 2.42, 2, 1, 0),
(11045, 1853, 9, 2, 'defensa', 'plata', 0.24, 2, 0, 0),
(11046, 1853, 9, 3, 'tecnica', 'oro', 2.77, 2, 0, 0),
(11047, 1854, 2, 1, 'tecnica', 'plata', 1.01, 3, 0, 0),
(11048, 1854, 2, 2, 'tecnica', 'oro', 2.54, 3, 1, 0),
(11049, 1854, 2, 3, 'ataque', 'plata', 1.46, 3, 0, 0),
(11050, 1854, 9, 1, 'defensa', 'oro', 2.98, 2, 0, 0),
(11051, 1854, 9, 2, 'defensa', 'prisma', 4.61, 2, 0, 0),
(11052, 1854, 9, 3, 'defensa', 'prisma', 4.58, 2, 1, 0),
(11053, 1855, 2, 1, 'tecnica', 'plata', 1.16, 3, 0, 0),
(11054, 1855, 2, 2, 'defensa', 'oro', 2.66, 3, 1, 0),
(11055, 1855, 2, 3, 'tecnica', 'plata', 1.11, 3, 0, 0),
(11056, 1855, 9, 1, 'tecnica', 'plata', 0.29, 2, 0, 0),
(11057, 1855, 9, 2, 'tecnica', 'plata', 0.49, 2, 0, 0),
(11058, 1855, 9, 3, 'ataque', 'oro', 3.01, 2, 1, 0),
(11059, 1857, 9, 1, 'ataque', 'oro', 2.37, 2, 0, 0),
(11060, 1857, 9, 2, 'defensa', 'prisma', 4.69, 2, 1, 0),
(11061, 1857, 9, 3, 'ataque', 'prisma', 4.73, 2, 0, 0),
(11062, 1857, 2, 1, 'ataque', 'oro', 3.15, 3, 0, 0),
(11063, 1857, 2, 2, 'defensa', 'oro', 2.75, 3, 0, 0),
(11064, 1857, 2, 3, 'defensa', 'oro', 2.03, 3, 1, 0),
(11065, 1858, 9, 1, 'tecnica', 'oro', 3.07, 2, 1, 0),
(11066, 1858, 9, 2, 'defensa', 'plata', 0.19, 2, 0, 0),
(11067, 1858, 9, 3, 'defensa', 'plata', 0.56, 2, 0, 0),
(11068, 1858, 2, 1, 'defensa', 'plata', 0.00, 3, 0, 0),
(11069, 1858, 2, 2, 'defensa', 'plata', 0.80, 3, 0, 0),
(11070, 1858, 2, 3, 'defensa', 'oro', 2.09, 3, 1, 0),
(11071, 1859, 9, 1, 'tecnica', 'plata', 1.80, 2, 0, 0),
(11072, 1859, 9, 2, 'ataque', 'oro', 2.02, 2, 1, 0),
(11073, 1859, 9, 3, 'ataque', 'oro', 2.42, 2, 0, 0),
(11074, 1859, 2, 1, 'defensa', 'oro', 2.55, 3, 1, 0),
(11075, 1859, 2, 2, 'ataque', 'plata', 0.79, 3, 0, 0),
(11076, 1859, 2, 3, 'defensa', 'oro', 2.73, 3, 0, 0),
(11077, 1860, 2, 1, 'defensa', 'prisma', 4.19, 3, 0, 0),
(11078, 1860, 2, 2, 'ataque', 'prisma', 3.68, 3, 0, 0),
(11079, 1860, 2, 3, 'defensa', 'prisma', 4.63, 3, 1, 0),
(11080, 1860, 9, 1, 'ataque', 'oro', 2.21, 2, 0, 0),
(11081, 1860, 9, 2, 'ataque', 'oro', 3.15, 2, 1, 0),
(11082, 1860, 9, 3, 'ataque', 'oro', 2.42, 2, 0, 0),
(11083, 1861, 2, 1, 'tecnica', 'plata', 1.01, 3, 0, 0),
(11084, 1861, 2, 2, 'defensa', 'plata', 0.33, 3, 0, 0),
(11085, 1861, 2, 3, 'defensa', 'oro', 3.36, 3, 1, 0),
(11086, 1861, 9, 1, 'ataque', 'plata', 0.80, 2, 1, 0),
(11087, 1861, 9, 2, 'tecnica', 'plata', 1.63, 2, 0, 0),
(11088, 1861, 9, 3, 'defensa', 'plata', 0.49, 2, 0, 0),
(11089, 1862, 2, 1, 'ataque', 'oro', 3.04, 3, 1, 0),
(11090, 1862, 2, 2, 'defensa', 'oro', 2.18, 3, 0, 0),
(11091, 1862, 2, 3, 'ataque', 'oro', 2.90, 3, 0, 0),
(11092, 1862, 9, 1, 'ataque', 'plata', 1.72, 2, 1, 0),
(11093, 1862, 9, 2, 'ataque', 'plata', 0.78, 2, 0, 0),
(11094, 1862, 9, 3, 'defensa', 'plata', 0.65, 2, 0, 0),
(11095, 1864, 2, 1, 'ataque', 'plata', 1.05, 3, 0, 0),
(11096, 1864, 2, 2, 'ataque', 'plata', 1.90, 3, 1, 0),
(11097, 1864, 2, 3, 'tecnica', 'plata', 1.05, 3, 0, 0),
(11098, 1864, 9, 1, 'defensa', 'oro', 3.34, 2, 0, 0),
(11099, 1864, 9, 2, 'tecnica', 'plata', 1.87, 2, 0, 0),
(11100, 1864, 9, 3, 'tecnica', 'prisma', 4.76, 2, 1, 0),
(11101, 1865, 2, 1, 'ataque', 'plata', 1.86, 3, 0, 0),
(11102, 1865, 2, 2, 'defensa', 'oro', 3.32, 3, 1, 0),
(11103, 1865, 2, 3, 'ataque', 'plata', 1.81, 3, 0, 0),
(11104, 1865, 9, 1, 'tecnica', 'oro', 2.75, 2, 0, 0),
(11105, 1865, 9, 2, 'ataque', 'oro', 2.85, 2, 1, 0),
(11106, 1865, 9, 3, 'defensa', 'plata', 0.08, 2, 0, 0),
(11107, 1867, 2, 1, 'defensa', 'oro', 2.15, 3, 0, 0),
(11108, 1867, 2, 2, 'defensa', 'oro', 2.20, 3, 0, 0),
(11109, 1867, 2, 3, 'tecnica', 'prisma', 4.39, 3, 1, 0),
(11110, 1867, 9, 1, 'defensa', 'oro', 2.53, 2, 0, 0),
(11111, 1867, 9, 2, 'ataque', 'prisma', 4.27, 2, 1, 0),
(11112, 1867, 9, 3, 'defensa', 'oro', 3.49, 2, 0, 0),
(11113, 1868, 9, 1, 'ataque', 'oro', 2.08, 2, 1, 0),
(11114, 1868, 9, 2, 'defensa', 'oro', 2.96, 2, 0, 0),
(11115, 1868, 9, 3, 'tecnica', 'oro', 3.12, 2, 0, 0),
(11116, 1868, 2, 1, 'tecnica', 'prisma', 3.81, 3, 1, 0),
(11117, 1868, 2, 2, 'tecnica', 'oro', 2.21, 3, 0, 0),
(11118, 1868, 2, 3, 'ataque', 'plata', 1.97, 3, 0, 0),
(11119, 1869, 9, 1, 'defensa', 'oro', 2.30, 2, 1, 0),
(11120, 1869, 9, 2, 'ataque', 'oro', 2.81, 2, 0, 0),
(11121, 1869, 9, 3, 'ataque', 'plata', 1.66, 2, 0, 0),
(11122, 1869, 2, 1, 'ataque', 'prisma', 3.65, 3, 1, 0),
(11123, 1869, 2, 2, 'ataque', 'oro', 2.46, 3, 0, 0),
(11124, 1869, 2, 3, 'ataque', 'plata', 1.63, 3, 0, 0),
(11125, 1870, 9, 1, 'ataque', 'plata', 0.77, 2, 0, 0),
(11126, 1870, 9, 2, 'defensa', 'oro', 3.27, 2, 1, 0),
(11127, 1870, 9, 3, 'tecnica', 'plata', 0.51, 2, 0, 0),
(11128, 1870, 10, 1, 'tecnica', 'oro', 3.29, 0, 1, 0),
(11129, 1870, 10, 2, 'defensa', 'oro', 2.08, 0, 0, 0),
(11130, 1870, 10, 3, 'defensa', 'oro', 2.91, 0, 0, 0),
(11131, 1871, 9, 1, 'defensa', 'plata', 0.25, 2, 0, 0),
(11132, 1871, 9, 2, 'defensa', 'plata', 0.81, 2, 1, 0),
(11133, 1871, 9, 3, 'tecnica', 'oro', 2.41, 2, 0, 0),
(11134, 1871, 10, 1, 'ataque', 'oro', 2.63, 0, 0, 0),
(11135, 1871, 10, 2, 'ataque', 'prisma', 4.60, 0, 0, 0),
(11136, 1871, 10, 3, 'defensa', 'prisma', 3.93, 0, 1, 0),
(11137, 1872, 2, 1, 'tecnica', 'plata', 1.64, 3, 0, 0),
(11138, 1872, 2, 2, 'defensa', 'plata', 0.70, 3, 0, 0),
(11139, 1872, 2, 3, 'tecnica', 'prisma', 4.09, 3, 1, 0),
(11140, 1872, 9, 1, 'tecnica', 'prisma', 4.19, 2, 1, 0),
(11141, 1872, 9, 2, 'tecnica', 'plata', 0.91, 2, 0, 0),
(11142, 1872, 9, 3, 'defensa', 'plata', 1.26, 2, 0, 0),
(11143, 1873, 2, 1, 'tecnica', 'plata', 0.25, 3, 0, 0),
(11144, 1873, 2, 2, 'defensa', 'oro', 3.38, 3, 1, 0),
(11145, 1873, 2, 3, 'defensa', 'oro', 2.03, 3, 0, 0),
(11146, 1873, 9, 1, 'ataque', 'plata', 0.27, 2, 0, 0),
(11147, 1873, 9, 2, 'tecnica', 'oro', 2.47, 2, 0, 0),
(11148, 1873, 9, 3, 'defensa', 'prisma', 4.92, 2, 1, 0),
(11149, 1875, 2, 1, 'ataque', 'oro', 2.71, 3, 1, 0),
(11150, 1875, 2, 2, 'ataque', 'plata', 0.03, 3, 0, 0),
(11151, 1875, 2, 3, 'defensa', 'oro', 2.36, 3, 0, 0),
(11152, 1875, 10, 1, 'defensa', 'oro', 2.55, 0, 1, 0),
(11153, 1875, 10, 2, 'defensa', 'plata', 0.54, 0, 0, 0),
(11154, 1875, 10, 3, 'tecnica', 'oro', 2.38, 0, 0, 0),
(11155, 1876, 2, 1, 'defensa', 'prisma', 4.02, 3, 0, 0),
(11156, 1876, 2, 2, 'ataque', 'plata', 0.29, 3, 0, 0),
(11157, 1876, 2, 3, 'ataque', 'prisma', 4.48, 3, 1, 0),
(11158, 1876, 10, 1, 'defensa', 'plata', 1.59, 0, 0, 0),
(11159, 1876, 10, 2, 'defensa', 'prisma', 4.19, 0, 0, 0),
(11160, 1876, 10, 3, 'defensa', 'plata', 1.77, 0, 1, 0),
(11161, 1877, 2, 1, 'tecnica', 'prisma', 4.02, 3, 1, 0),
(11162, 1877, 2, 2, 'tecnica', 'oro', 3.08, 3, 0, 0),
(11163, 1877, 2, 3, 'ataque', 'plata', 1.87, 3, 0, 0),
(11164, 1877, 10, 1, 'tecnica', 'oro', 3.40, 0, 0, 0),
(11165, 1877, 10, 2, 'defensa', 'prisma', 4.75, 0, 0, 0),
(11166, 1877, 10, 3, 'tecnica', 'prisma', 3.87, 0, 1, 0),
(11167, 1878, 2, 1, 'defensa', 'oro', 3.35, 3, 0, 0),
(11168, 1878, 2, 2, 'tecnica', 'plata', 0.33, 3, 1, 0),
(11169, 1878, 2, 3, 'defensa', 'plata', 0.35, 3, 0, 0),
(11170, 1878, 10, 1, 'defensa', 'oro', 3.06, 0, 0, 0),
(11171, 1878, 10, 2, 'defensa', 'plata', 1.30, 0, 0, 0),
(11172, 1878, 10, 3, 'tecnica', 'oro', 2.80, 0, 1, 0),
(11173, 1879, 2, 1, 'tecnica', 'plata', 1.23, 3, 0, 0),
(11174, 1879, 2, 2, 'tecnica', 'plata', 1.40, 3, 0, 0),
(11175, 1879, 2, 3, 'defensa', 'plata', 1.91, 3, 1, 0),
(11176, 1879, 10, 1, 'tecnica', 'prisma', 3.88, 0, 0, 0),
(11177, 1879, 10, 2, 'tecnica', 'oro', 2.16, 0, 0, 0),
(11178, 1879, 10, 3, 'tecnica', 'plata', 0.52, 0, 1, 0),
(11179, 1880, 2, 1, 'defensa', 'plata', 0.92, 3, 0, 0),
(11180, 1880, 2, 2, 'defensa', 'prisma', 3.50, 3, 1, 0),
(11181, 1880, 2, 3, 'tecnica', 'plata', 1.59, 3, 0, 0),
(11182, 1880, 10, 1, 'tecnica', 'prisma', 3.64, 0, 0, 0),
(11183, 1880, 10, 2, 'ataque', 'oro', 2.59, 0, 1, 0),
(11184, 1880, 10, 3, 'tecnica', 'prisma', 4.47, 0, 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `duelo_compos`
--

CREATE TABLE `duelo_compos` (
  `id_duelo_compo` int(11) NOT NULL,
  `id_duelo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_rasgo` int(11) NOT NULL,
  `copias` tinyint(3) UNSIGNED NOT NULL,
  `nivel` tinyint(3) UNSIGNED NOT NULL,
  `pct_nominal` decimal(6,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `duelo_compos`
--

INSERT INTO `duelo_compos` (`id_duelo_compo`, `id_duelo`, `id_usuario`, `id_rasgo`, `copias`, `nivel`, `pct_nominal`) VALUES
(15950, 1047, 2, 4, 2, 1, 3.880),
(15951, 1047, 2, 5, 4, 1, 2.700),
(15952, 1047, 2, 2, 2, 1, 1.700),
(15953, 1047, 2, 8, 3, 1, 1.060),
(15954, 1047, 2, 1, 6, 2, 6.300),
(15955, 1047, 2, 6, 4, 1, 1.700),
(15956, 1047, 2, 9, 6, 2, 0.000),
(15957, 1047, 10, 4, 2, 1, 3.880),
(15958, 1047, 10, 5, 3, 1, 2.700),
(15959, 1047, 10, 1, 3, 1, 2.700),
(15960, 1047, 10, 6, 5, 2, 3.970),
(15961, 1047, 10, 3, 4, 1, 2.230),
(15962, 1047, 10, 2, 2, 1, 1.700),
(15963, 1047, 10, 8, 2, 1, 1.060),
(15964, 1047, 10, 9, 7, 3, 0.000),
(15980, 1049, 2, 4, 2, 1, 3.880),
(15981, 1049, 2, 5, 4, 1, 2.700),
(15982, 1049, 2, 2, 2, 1, 1.700),
(15983, 1049, 2, 8, 3, 1, 1.060),
(15984, 1049, 2, 1, 6, 2, 6.300),
(15985, 1049, 2, 6, 4, 1, 1.700),
(15986, 1049, 2, 9, 6, 2, 0.000),
(15987, 1049, 10, 2, 3, 1, 1.700),
(15988, 1049, 10, 6, 5, 2, 3.970),
(15989, 1049, 10, 1, 4, 1, 2.700),
(15990, 1049, 10, 3, 3, 1, 2.230),
(15991, 1049, 10, 5, 2, 1, 2.700),
(15992, 1049, 10, 8, 2, 1, 1.060),
(15993, 1049, 10, 7, 2, 1, 0.820),
(15994, 1049, 10, 9, 7, 3, 0.000),
(15995, 1050, 2, 4, 2, 1, 3.880),
(15996, 1050, 2, 5, 4, 1, 2.700),
(15997, 1050, 2, 2, 2, 1, 1.700),
(15998, 1050, 2, 8, 3, 1, 1.060),
(15999, 1050, 2, 1, 6, 2, 6.300),
(16000, 1050, 2, 6, 4, 1, 1.700),
(16001, 1050, 2, 9, 6, 2, 0.000),
(16002, 1050, 10, 2, 3, 1, 1.700),
(16003, 1050, 10, 6, 5, 2, 3.970),
(16004, 1050, 10, 1, 4, 1, 2.700),
(16005, 1050, 10, 3, 3, 1, 2.230),
(16006, 1050, 10, 5, 2, 1, 2.700),
(16007, 1050, 10, 8, 2, 1, 1.060),
(16008, 1050, 10, 7, 2, 1, 0.820),
(16009, 1050, 10, 9, 7, 3, 0.000),
(16024, 1052, 2, 4, 2, 1, 3.880),
(16025, 1052, 2, 5, 4, 1, 2.700),
(16026, 1052, 2, 2, 2, 1, 1.700),
(16027, 1052, 2, 8, 3, 1, 1.060),
(16028, 1052, 2, 1, 6, 2, 6.300),
(16029, 1052, 2, 6, 4, 1, 1.700),
(16030, 1052, 2, 9, 6, 2, 0.000),
(16031, 1052, 10, 2, 3, 1, 1.700),
(16032, 1052, 10, 6, 5, 2, 3.970),
(16033, 1052, 10, 1, 4, 1, 2.700),
(16034, 1052, 10, 3, 3, 1, 2.230),
(16035, 1052, 10, 5, 2, 1, 2.700),
(16036, 1052, 10, 8, 2, 1, 1.060),
(16037, 1052, 10, 7, 2, 1, 0.820),
(16038, 1052, 10, 9, 7, 3, 0.000),
(16054, 1055, 2, 4, 2, 1, 3.880),
(16055, 1055, 2, 5, 4, 1, 2.700),
(16056, 1055, 2, 2, 2, 1, 1.700),
(16057, 1055, 2, 8, 3, 1, 1.060),
(16058, 1055, 2, 1, 6, 2, 6.300),
(16059, 1055, 2, 6, 4, 1, 1.700),
(16060, 1055, 2, 9, 6, 2, 0.000),
(16061, 1055, 10, 4, 4, 1, 3.880),
(16062, 1055, 10, 2, 3, 1, 1.700),
(16063, 1055, 10, 8, 5, 2, 2.390),
(16064, 1055, 10, 1, 2, 1, 2.700),
(16065, 1055, 10, 6, 3, 1, 1.700),
(16066, 1055, 10, 7, 2, 1, 0.820),
(16067, 1055, 10, 3, 2, 1, 2.230),
(16068, 1055, 10, 9, 7, 3, 0.000),
(16069, 1056, 2, 4, 2, 1, 3.880),
(16070, 1056, 2, 5, 4, 1, 2.700),
(16071, 1056, 2, 2, 2, 1, 1.700),
(16072, 1056, 2, 8, 3, 1, 1.060),
(16073, 1056, 2, 1, 6, 2, 6.300),
(16074, 1056, 2, 6, 4, 1, 1.700),
(16075, 1056, 2, 9, 6, 2, 0.000),
(16076, 1056, 10, 4, 4, 1, 3.880),
(16077, 1056, 10, 2, 3, 1, 1.700),
(16078, 1056, 10, 8, 5, 2, 2.390),
(16079, 1056, 10, 1, 2, 1, 2.700),
(16080, 1056, 10, 6, 3, 1, 1.700),
(16081, 1056, 10, 7, 2, 1, 0.820),
(16082, 1056, 10, 3, 2, 1, 2.230),
(16083, 1056, 10, 9, 7, 3, 0.000),
(19954, 1315, 2, 4, 2, 1, 3.880),
(19955, 1315, 2, 5, 4, 1, 2.700),
(19956, 1315, 2, 2, 2, 1, 1.700),
(19957, 1315, 2, 8, 3, 1, 1.060),
(19958, 1315, 2, 1, 6, 2, 6.300),
(19959, 1315, 2, 6, 4, 1, 1.700),
(19960, 1315, 2, 9, 6, 2, 0.000),
(19961, 1315, 10, 3, 3, 1, 2.230),
(19962, 1315, 10, 8, 4, 1, 1.060),
(19963, 1315, 10, 2, 5, 2, 3.970),
(19964, 1315, 10, 1, 2, 1, 2.700),
(19965, 1315, 10, 6, 3, 1, 1.700),
(19966, 1315, 10, 7, 2, 1, 0.820),
(19967, 1315, 10, 5, 2, 1, 2.700),
(19968, 1315, 10, 9, 7, 3, 0.000),
(19969, 1316, 2, 4, 2, 1, 3.880),
(19970, 1316, 2, 5, 4, 1, 2.700),
(19971, 1316, 2, 2, 2, 1, 1.700),
(19972, 1316, 2, 8, 3, 1, 1.060),
(19973, 1316, 2, 1, 6, 2, 6.300),
(19974, 1316, 2, 6, 4, 1, 1.700),
(19975, 1316, 2, 9, 6, 2, 0.000),
(19976, 1316, 10, 3, 3, 1, 2.230),
(19977, 1316, 10, 8, 4, 1, 1.060),
(19978, 1316, 10, 2, 5, 2, 3.970),
(19979, 1316, 10, 1, 2, 1, 2.700),
(19980, 1316, 10, 6, 3, 1, 1.700),
(19981, 1316, 10, 7, 2, 1, 0.820),
(19982, 1316, 10, 5, 2, 1, 2.700),
(19983, 1316, 10, 9, 7, 3, 0.000),
(19984, 1317, 2, 4, 2, 1, 3.880),
(19985, 1317, 2, 5, 4, 1, 2.700),
(19986, 1317, 2, 2, 2, 1, 1.700),
(19987, 1317, 2, 8, 3, 1, 1.060),
(19988, 1317, 2, 1, 6, 2, 6.300),
(19989, 1317, 2, 6, 4, 1, 1.700),
(19990, 1317, 2, 9, 6, 2, 0.000),
(19991, 1317, 10, 1, 3, 1, 2.700),
(19992, 1317, 10, 7, 4, 1, 0.820),
(19993, 1317, 10, 2, 3, 1, 1.700),
(19994, 1317, 10, 8, 2, 1, 1.060),
(19995, 1317, 10, 6, 2, 1, 1.700),
(19996, 1317, 10, 4, 2, 1, 3.880),
(19997, 1317, 10, 3, 3, 1, 2.230),
(19998, 1317, 10, 5, 3, 1, 2.700),
(19999, 1317, 10, 9, 8, 3, 0.000),
(20000, 1318, 2, 4, 2, 1, 3.880),
(20001, 1318, 2, 5, 4, 1, 2.700),
(20002, 1318, 2, 2, 2, 1, 1.700),
(20003, 1318, 2, 8, 3, 1, 1.060),
(20004, 1318, 2, 1, 6, 2, 6.300),
(20005, 1318, 2, 6, 4, 1, 1.700),
(20006, 1318, 2, 9, 6, 2, 0.000),
(20007, 1318, 10, 1, 3, 1, 2.700),
(20008, 1318, 10, 7, 4, 1, 0.820),
(20009, 1318, 10, 2, 3, 1, 1.700),
(20010, 1318, 10, 8, 2, 1, 1.060),
(20011, 1318, 10, 6, 2, 1, 1.700),
(20012, 1318, 10, 4, 2, 1, 3.880),
(20013, 1318, 10, 3, 3, 1, 2.230),
(20014, 1318, 10, 5, 3, 1, 2.700),
(20015, 1318, 10, 9, 8, 3, 0.000),
(24016, 1569, 2, 4, 3, 1, 3.880),
(24017, 1569, 2, 5, 2, 1, 2.700),
(24018, 1569, 2, 2, 2, 1, 1.700),
(24019, 1569, 2, 8, 3, 1, 1.060),
(24020, 1569, 2, 1, 4, 1, 2.700),
(24021, 1569, 2, 6, 4, 1, 1.700),
(24022, 1569, 2, 7, 2, 1, 0.820),
(24023, 1569, 2, 3, 2, 1, 2.230),
(24024, 1569, 2, 9, 8, 3, 0.000),
(24025, 1569, 10, 1, 5, 2, 6.300),
(24026, 1569, 10, 7, 3, 1, 0.820),
(24027, 1569, 10, 6, 3, 1, 1.700),
(24028, 1569, 10, 3, 3, 1, 2.230),
(24029, 1569, 10, 5, 4, 1, 2.700),
(24030, 1569, 10, 2, 2, 1, 1.700),
(24031, 1569, 10, 9, 6, 2, 0.000),
(24032, 1570, 2, 4, 3, 1, 3.880),
(24033, 1570, 2, 5, 2, 1, 2.700),
(24034, 1570, 2, 2, 2, 1, 1.700),
(24035, 1570, 2, 8, 3, 1, 1.060),
(24036, 1570, 2, 1, 4, 1, 2.700),
(24037, 1570, 2, 6, 4, 1, 1.700),
(24038, 1570, 2, 7, 2, 1, 0.820),
(24039, 1570, 2, 3, 2, 1, 2.230),
(24040, 1570, 2, 9, 8, 3, 0.000),
(24041, 1570, 10, 1, 5, 2, 6.300),
(24042, 1570, 10, 7, 3, 1, 0.820),
(24043, 1570, 10, 6, 3, 1, 1.700),
(24044, 1570, 10, 3, 3, 1, 2.230),
(24045, 1570, 10, 5, 4, 1, 2.700),
(24046, 1570, 10, 2, 2, 1, 1.700),
(24047, 1570, 10, 9, 6, 2, 0.000),
(24048, 1571, 2, 4, 3, 1, 3.880),
(24049, 1571, 2, 5, 2, 1, 2.700),
(24050, 1571, 2, 2, 2, 1, 1.700),
(24051, 1571, 2, 8, 3, 1, 1.060),
(24052, 1571, 2, 1, 4, 1, 2.700),
(24053, 1571, 2, 6, 4, 1, 1.700),
(24054, 1571, 2, 7, 2, 1, 0.820),
(24055, 1571, 2, 3, 2, 1, 2.230),
(24056, 1571, 2, 9, 8, 3, 0.000),
(24057, 1571, 10, 1, 5, 2, 6.300),
(24058, 1571, 10, 7, 3, 1, 0.820),
(24059, 1571, 10, 6, 3, 1, 1.700),
(24060, 1571, 10, 3, 3, 1, 2.230),
(24061, 1571, 10, 5, 4, 1, 2.700),
(24062, 1571, 10, 2, 2, 1, 1.700),
(24063, 1571, 10, 9, 6, 2, 0.000),
(24064, 1572, 2, 4, 3, 1, 3.880),
(24065, 1572, 2, 5, 2, 1, 2.700),
(24066, 1572, 2, 2, 2, 1, 1.700),
(24067, 1572, 2, 8, 3, 1, 1.060),
(24068, 1572, 2, 1, 4, 1, 2.700),
(24069, 1572, 2, 6, 4, 1, 1.700),
(24070, 1572, 2, 7, 2, 1, 0.820),
(24071, 1572, 2, 3, 2, 1, 2.230),
(24072, 1572, 2, 9, 8, 3, 0.000),
(24073, 1572, 10, 3, 5, 2, 5.190),
(24074, 1572, 10, 8, 3, 1, 1.060),
(24075, 1572, 10, 1, 3, 1, 2.700),
(24076, 1572, 10, 6, 5, 2, 3.970),
(24077, 1572, 10, 7, 2, 1, 0.820),
(24078, 1572, 10, 4, 3, 1, 3.880),
(24079, 1572, 10, 9, 6, 2, 0.000),
(24080, 1573, 2, 4, 3, 1, 3.880),
(24081, 1573, 2, 5, 2, 1, 2.700),
(24082, 1573, 2, 2, 2, 1, 1.700),
(24083, 1573, 2, 8, 3, 1, 1.060),
(24084, 1573, 2, 1, 4, 1, 2.700),
(24085, 1573, 2, 6, 4, 1, 1.700),
(24086, 1573, 2, 7, 2, 1, 0.820),
(24087, 1573, 2, 3, 2, 1, 2.230),
(24088, 1573, 2, 9, 8, 3, 0.000),
(24089, 1573, 10, 3, 5, 2, 5.190),
(24090, 1573, 10, 8, 3, 1, 1.060),
(24091, 1573, 10, 1, 3, 1, 2.700),
(24092, 1573, 10, 6, 5, 2, 3.970),
(24093, 1573, 10, 7, 2, 1, 0.820),
(24094, 1573, 10, 4, 3, 1, 3.880),
(24095, 1573, 10, 9, 6, 2, 0.000),
(24096, 1574, 2, 4, 3, 1, 3.880),
(24097, 1574, 2, 5, 2, 1, 2.700),
(24098, 1574, 2, 2, 2, 1, 1.700),
(24099, 1574, 2, 8, 3, 1, 1.060),
(24100, 1574, 2, 1, 4, 1, 2.700),
(24101, 1574, 2, 6, 4, 1, 1.700),
(24102, 1574, 2, 7, 2, 1, 0.820),
(24103, 1574, 2, 3, 2, 1, 2.230),
(24104, 1574, 2, 9, 8, 3, 0.000),
(24105, 1574, 10, 3, 5, 2, 5.190),
(24106, 1574, 10, 8, 3, 1, 1.060),
(24107, 1574, 10, 1, 3, 1, 2.700),
(24108, 1574, 10, 6, 5, 2, 3.970),
(24109, 1574, 10, 7, 2, 1, 0.820),
(24110, 1574, 10, 4, 3, 1, 3.880),
(24111, 1574, 10, 9, 6, 2, 0.000),
(24112, 1575, 2, 4, 3, 1, 3.880),
(24113, 1575, 2, 5, 2, 1, 2.700),
(24114, 1575, 2, 2, 2, 1, 1.700),
(24115, 1575, 2, 8, 3, 1, 1.060),
(24116, 1575, 2, 1, 4, 1, 2.700),
(24117, 1575, 2, 6, 4, 1, 1.700),
(24118, 1575, 2, 7, 2, 1, 0.820),
(24119, 1575, 2, 3, 2, 1, 2.230),
(24120, 1575, 2, 9, 8, 3, 0.000),
(24121, 1575, 10, 1, 5, 2, 6.300),
(24122, 1575, 10, 7, 3, 1, 0.820),
(24123, 1575, 10, 6, 3, 1, 1.700),
(24124, 1575, 10, 3, 3, 1, 2.230),
(24125, 1575, 10, 5, 4, 1, 2.700),
(24126, 1575, 10, 2, 2, 1, 1.700),
(24127, 1575, 10, 9, 6, 2, 0.000),
(28378, 1846, 2, 4, 3, 1, 3.880),
(28379, 1846, 2, 5, 2, 1, 2.700),
(28380, 1846, 2, 2, 2, 1, 1.700),
(28381, 1846, 2, 8, 3, 1, 1.060),
(28382, 1846, 2, 1, 4, 1, 2.700),
(28383, 1846, 2, 6, 4, 1, 1.700),
(28384, 1846, 2, 7, 2, 1, 0.820),
(28385, 1846, 2, 3, 2, 1, 2.230),
(28386, 1846, 2, 9, 8, 3, 0.000),
(28387, 1846, 10, 4, 3, 1, 3.880),
(28388, 1846, 10, 5, 2, 1, 2.700),
(28389, 1846, 10, 2, 4, 1, 1.700),
(28390, 1846, 10, 8, 4, 1, 1.060),
(28391, 1846, 10, 1, 2, 1, 2.700),
(28392, 1846, 10, 6, 3, 1, 1.700),
(28393, 1846, 10, 7, 2, 1, 0.820),
(28394, 1846, 10, 3, 2, 1, 2.230),
(28395, 1846, 10, 9, 8, 3, 0.000),
(28396, 1847, 2, 4, 3, 1, 3.880),
(28397, 1847, 2, 5, 2, 1, 2.700),
(28398, 1847, 2, 2, 2, 1, 1.700),
(28399, 1847, 2, 8, 3, 1, 1.060),
(28400, 1847, 2, 1, 4, 1, 2.700),
(28401, 1847, 2, 6, 4, 1, 1.700),
(28402, 1847, 2, 7, 2, 1, 0.820),
(28403, 1847, 2, 3, 2, 1, 2.230),
(28404, 1847, 2, 9, 8, 3, 0.000),
(28405, 1847, 10, 3, 5, 2, 5.190),
(28406, 1847, 10, 8, 3, 1, 1.060),
(28407, 1847, 10, 1, 3, 1, 2.700),
(28408, 1847, 10, 6, 5, 2, 3.970),
(28409, 1847, 10, 7, 2, 1, 0.820),
(28410, 1847, 10, 4, 3, 1, 3.880),
(28411, 1847, 10, 9, 6, 2, 0.000),
(28412, 1848, 9, 4, 3, 1, 3.880),
(28413, 1848, 9, 6, 4, 1, 1.700),
(28414, 1848, 9, 1, 3, 1, 2.700),
(28415, 1848, 9, 2, 4, 1, 1.700),
(28416, 1848, 9, 8, 4, 1, 1.060),
(28417, 1848, 9, 7, 2, 1, 0.820),
(28418, 1848, 9, 9, 6, 2, 0.000),
(28419, 1848, 10, 4, 3, 1, 3.880),
(28420, 1848, 10, 5, 3, 1, 2.700),
(28421, 1848, 10, 2, 3, 1, 1.700),
(28422, 1848, 10, 8, 3, 1, 1.060),
(28423, 1848, 10, 1, 3, 1, 2.700),
(28424, 1848, 10, 6, 3, 1, 1.700),
(28425, 1848, 10, 7, 2, 1, 0.820),
(28426, 1848, 10, 3, 2, 1, 2.230),
(28427, 1848, 10, 9, 8, 3, 0.000),
(28428, 1849, 9, 4, 3, 1, 3.880),
(28429, 1849, 9, 6, 4, 1, 1.700),
(28430, 1849, 9, 1, 3, 1, 2.700),
(28431, 1849, 9, 2, 4, 1, 1.700),
(28432, 1849, 9, 8, 4, 1, 1.060),
(28433, 1849, 9, 7, 2, 1, 0.820),
(28434, 1849, 9, 9, 6, 2, 0.000),
(28435, 1849, 2, 4, 3, 1, 3.880),
(28436, 1849, 2, 5, 2, 1, 2.700),
(28437, 1849, 2, 2, 2, 1, 1.700),
(28438, 1849, 2, 8, 3, 1, 1.060),
(28439, 1849, 2, 1, 4, 1, 2.700),
(28440, 1849, 2, 6, 4, 1, 1.700),
(28441, 1849, 2, 7, 2, 1, 0.820),
(28442, 1849, 2, 3, 2, 1, 2.230),
(28443, 1849, 2, 9, 8, 3, 0.000),
(28444, 1850, 9, 4, 3, 1, 3.880),
(28445, 1850, 9, 6, 4, 1, 1.700),
(28446, 1850, 9, 1, 3, 1, 2.700),
(28447, 1850, 9, 2, 4, 1, 1.700),
(28448, 1850, 9, 8, 4, 1, 1.060),
(28449, 1850, 9, 7, 2, 1, 0.820),
(28450, 1850, 9, 9, 6, 2, 0.000),
(28451, 1850, 2, 4, 3, 1, 3.880),
(28452, 1850, 2, 5, 2, 1, 2.700),
(28453, 1850, 2, 2, 2, 1, 1.700),
(28454, 1850, 2, 8, 3, 1, 1.060),
(28455, 1850, 2, 1, 4, 1, 2.700),
(28456, 1850, 2, 6, 4, 1, 1.700),
(28457, 1850, 2, 7, 2, 1, 0.820),
(28458, 1850, 2, 3, 2, 1, 2.230),
(28459, 1850, 2, 9, 8, 3, 0.000),
(28460, 1851, 2, 4, 3, 1, 3.880),
(28461, 1851, 2, 5, 2, 1, 2.700),
(28462, 1851, 2, 2, 2, 1, 1.700),
(28463, 1851, 2, 8, 3, 1, 1.060),
(28464, 1851, 2, 1, 4, 1, 2.700),
(28465, 1851, 2, 6, 4, 1, 1.700),
(28466, 1851, 2, 7, 2, 1, 0.820),
(28467, 1851, 2, 3, 2, 1, 2.230),
(28468, 1851, 2, 9, 8, 3, 0.000),
(28469, 1851, 9, 4, 3, 1, 3.880),
(28470, 1851, 9, 6, 4, 1, 1.700),
(28471, 1851, 9, 1, 3, 1, 2.700),
(28472, 1851, 9, 2, 4, 1, 1.700),
(28473, 1851, 9, 8, 4, 1, 1.060),
(28474, 1851, 9, 7, 2, 1, 0.820),
(28475, 1851, 9, 9, 6, 2, 0.000),
(28476, 1852, 2, 4, 3, 1, 3.880),
(28477, 1852, 2, 5, 2, 1, 2.700),
(28478, 1852, 2, 2, 2, 1, 1.700),
(28479, 1852, 2, 8, 3, 1, 1.060),
(28480, 1852, 2, 1, 4, 1, 2.700),
(28481, 1852, 2, 6, 4, 1, 1.700),
(28482, 1852, 2, 7, 2, 1, 0.820),
(28483, 1852, 2, 3, 2, 1, 2.230),
(28484, 1852, 2, 9, 8, 3, 0.000),
(28485, 1852, 9, 4, 3, 1, 3.880),
(28486, 1852, 9, 6, 4, 1, 1.700),
(28487, 1852, 9, 1, 3, 1, 2.700),
(28488, 1852, 9, 2, 4, 1, 1.700),
(28489, 1852, 9, 8, 4, 1, 1.060),
(28490, 1852, 9, 7, 2, 1, 0.820),
(28491, 1852, 9, 9, 6, 2, 0.000),
(28492, 1853, 2, 4, 3, 1, 3.880),
(28493, 1853, 2, 5, 2, 1, 2.700),
(28494, 1853, 2, 2, 2, 1, 1.700),
(28495, 1853, 2, 8, 3, 1, 1.060),
(28496, 1853, 2, 1, 4, 1, 2.700),
(28497, 1853, 2, 6, 4, 1, 1.700),
(28498, 1853, 2, 7, 2, 1, 0.820),
(28499, 1853, 2, 3, 2, 1, 2.230),
(28500, 1853, 2, 9, 8, 3, 0.000),
(28501, 1853, 9, 4, 3, 1, 3.880),
(28502, 1853, 9, 6, 4, 1, 1.700),
(28503, 1853, 9, 1, 3, 1, 2.700),
(28504, 1853, 9, 2, 4, 1, 1.700),
(28505, 1853, 9, 8, 4, 1, 1.060),
(28506, 1853, 9, 7, 2, 1, 0.820),
(28507, 1853, 9, 9, 6, 2, 0.000),
(28508, 1854, 2, 4, 3, 1, 3.880),
(28509, 1854, 2, 5, 2, 1, 2.700),
(28510, 1854, 2, 2, 2, 1, 1.700),
(28511, 1854, 2, 8, 3, 1, 1.060),
(28512, 1854, 2, 1, 4, 1, 2.700),
(28513, 1854, 2, 6, 4, 1, 1.700),
(28514, 1854, 2, 7, 2, 1, 0.820),
(28515, 1854, 2, 3, 2, 1, 2.230),
(28516, 1854, 2, 9, 8, 3, 0.000),
(28517, 1854, 9, 4, 3, 1, 3.880),
(28518, 1854, 9, 6, 4, 1, 1.700),
(28519, 1854, 9, 1, 3, 1, 2.700),
(28520, 1854, 9, 2, 4, 1, 1.700),
(28521, 1854, 9, 8, 4, 1, 1.060),
(28522, 1854, 9, 7, 2, 1, 0.820),
(28523, 1854, 9, 9, 6, 2, 0.000),
(28524, 1855, 2, 4, 3, 1, 3.880),
(28525, 1855, 2, 5, 2, 1, 2.700),
(28526, 1855, 2, 2, 2, 1, 1.700),
(28527, 1855, 2, 8, 3, 1, 1.060),
(28528, 1855, 2, 1, 4, 1, 2.700),
(28529, 1855, 2, 6, 4, 1, 1.700),
(28530, 1855, 2, 7, 2, 1, 0.820),
(28531, 1855, 2, 3, 2, 1, 2.230),
(28532, 1855, 2, 9, 8, 3, 0.000),
(28533, 1855, 9, 4, 3, 1, 3.880),
(28534, 1855, 9, 6, 4, 1, 1.700),
(28535, 1855, 9, 1, 3, 1, 2.700),
(28536, 1855, 9, 2, 4, 1, 1.700),
(28537, 1855, 9, 8, 4, 1, 1.060),
(28538, 1855, 9, 7, 2, 1, 0.820),
(28539, 1855, 9, 9, 6, 2, 0.000),
(28540, 1857, 9, 4, 3, 1, 3.880),
(28541, 1857, 9, 6, 4, 1, 1.700),
(28542, 1857, 9, 1, 3, 1, 2.700),
(28543, 1857, 9, 2, 4, 1, 1.700),
(28544, 1857, 9, 8, 4, 1, 1.060),
(28545, 1857, 9, 7, 2, 1, 0.820),
(28546, 1857, 9, 9, 6, 2, 0.000),
(28547, 1857, 2, 4, 3, 1, 3.880),
(28548, 1857, 2, 5, 2, 1, 2.700),
(28549, 1857, 2, 2, 2, 1, 1.700),
(28550, 1857, 2, 8, 3, 1, 1.060),
(28551, 1857, 2, 1, 4, 1, 2.700),
(28552, 1857, 2, 6, 4, 1, 1.700),
(28553, 1857, 2, 7, 2, 1, 0.820),
(28554, 1857, 2, 3, 2, 1, 2.230),
(28555, 1857, 2, 9, 8, 3, 0.000),
(28556, 1858, 9, 4, 3, 1, 3.880),
(28557, 1858, 9, 6, 4, 1, 1.700),
(28558, 1858, 9, 1, 3, 1, 2.700),
(28559, 1858, 9, 2, 4, 1, 1.700),
(28560, 1858, 9, 8, 4, 1, 1.060),
(28561, 1858, 9, 7, 2, 1, 0.820),
(28562, 1858, 9, 9, 6, 2, 0.000),
(28563, 1858, 2, 4, 3, 1, 3.880),
(28564, 1858, 2, 5, 2, 1, 2.700),
(28565, 1858, 2, 2, 2, 1, 1.700),
(28566, 1858, 2, 8, 3, 1, 1.060),
(28567, 1858, 2, 1, 4, 1, 2.700),
(28568, 1858, 2, 6, 4, 1, 1.700),
(28569, 1858, 2, 7, 2, 1, 0.820),
(28570, 1858, 2, 3, 2, 1, 2.230),
(28571, 1858, 2, 9, 8, 3, 0.000),
(28572, 1859, 9, 4, 3, 1, 3.880),
(28573, 1859, 9, 6, 4, 1, 1.700),
(28574, 1859, 9, 1, 3, 1, 2.700),
(28575, 1859, 9, 2, 4, 1, 1.700),
(28576, 1859, 9, 8, 4, 1, 1.060),
(28577, 1859, 9, 7, 2, 1, 0.820),
(28578, 1859, 9, 9, 6, 2, 0.000),
(28579, 1859, 2, 4, 3, 1, 3.880),
(28580, 1859, 2, 5, 2, 1, 2.700),
(28581, 1859, 2, 2, 2, 1, 1.700),
(28582, 1859, 2, 8, 3, 1, 1.060),
(28583, 1859, 2, 1, 4, 1, 2.700),
(28584, 1859, 2, 6, 4, 1, 1.700),
(28585, 1859, 2, 7, 2, 1, 0.820),
(28586, 1859, 2, 3, 2, 1, 2.230),
(28587, 1859, 2, 9, 8, 3, 0.000),
(28588, 1860, 2, 4, 3, 1, 3.880),
(28589, 1860, 2, 5, 2, 1, 2.700),
(28590, 1860, 2, 2, 2, 1, 1.700),
(28591, 1860, 2, 8, 3, 1, 1.060),
(28592, 1860, 2, 1, 4, 1, 2.700),
(28593, 1860, 2, 6, 4, 1, 1.700),
(28594, 1860, 2, 7, 2, 1, 0.820),
(28595, 1860, 2, 3, 2, 1, 2.230),
(28596, 1860, 2, 9, 8, 3, 0.000),
(28597, 1860, 9, 4, 3, 1, 3.880),
(28598, 1860, 9, 6, 4, 1, 1.700),
(28599, 1860, 9, 1, 3, 1, 2.700),
(28600, 1860, 9, 2, 4, 1, 1.700),
(28601, 1860, 9, 8, 4, 1, 1.060),
(28602, 1860, 9, 7, 2, 1, 0.820),
(28603, 1860, 9, 9, 6, 2, 0.000),
(28604, 1861, 2, 4, 3, 1, 3.880),
(28605, 1861, 2, 5, 2, 1, 2.700),
(28606, 1861, 2, 2, 2, 1, 1.700),
(28607, 1861, 2, 8, 3, 1, 1.060),
(28608, 1861, 2, 1, 4, 1, 2.700),
(28609, 1861, 2, 6, 4, 1, 1.700),
(28610, 1861, 2, 7, 2, 1, 0.820),
(28611, 1861, 2, 3, 2, 1, 2.230),
(28612, 1861, 2, 9, 8, 3, 0.000),
(28613, 1861, 9, 4, 3, 1, 3.880),
(28614, 1861, 9, 6, 4, 1, 1.700),
(28615, 1861, 9, 1, 3, 1, 2.700),
(28616, 1861, 9, 2, 4, 1, 1.700),
(28617, 1861, 9, 8, 4, 1, 1.060),
(28618, 1861, 9, 7, 2, 1, 0.820),
(28619, 1861, 9, 9, 6, 2, 0.000),
(28620, 1862, 2, 4, 3, 1, 3.880),
(28621, 1862, 2, 5, 2, 1, 2.700),
(28622, 1862, 2, 2, 2, 1, 1.700),
(28623, 1862, 2, 8, 3, 1, 1.060),
(28624, 1862, 2, 1, 4, 1, 2.700),
(28625, 1862, 2, 6, 4, 1, 1.700),
(28626, 1862, 2, 7, 2, 1, 0.820),
(28627, 1862, 2, 3, 2, 1, 2.230),
(28628, 1862, 2, 9, 8, 3, 0.000),
(28629, 1862, 9, 4, 3, 1, 3.880),
(28630, 1862, 9, 6, 4, 1, 1.700),
(28631, 1862, 9, 1, 3, 1, 2.700),
(28632, 1862, 9, 2, 4, 1, 1.700),
(28633, 1862, 9, 8, 4, 1, 1.060),
(28634, 1862, 9, 7, 2, 1, 0.820),
(28635, 1862, 9, 9, 6, 2, 0.000),
(28636, 1864, 2, 4, 3, 1, 3.880),
(28637, 1864, 2, 5, 2, 1, 2.700),
(28638, 1864, 2, 2, 2, 1, 1.700),
(28639, 1864, 2, 8, 3, 1, 1.060),
(28640, 1864, 2, 1, 4, 1, 2.700),
(28641, 1864, 2, 6, 4, 1, 1.700),
(28642, 1864, 2, 7, 2, 1, 0.820),
(28643, 1864, 2, 3, 2, 1, 2.230),
(28644, 1864, 2, 9, 8, 3, 0.000),
(28645, 1864, 9, 4, 3, 1, 3.880),
(28646, 1864, 9, 6, 4, 1, 1.700),
(28647, 1864, 9, 1, 3, 1, 2.700),
(28648, 1864, 9, 2, 4, 1, 1.700),
(28649, 1864, 9, 8, 4, 1, 1.060),
(28650, 1864, 9, 7, 2, 1, 0.820),
(28651, 1864, 9, 9, 6, 2, 0.000),
(28652, 1865, 2, 4, 3, 1, 3.880),
(28653, 1865, 2, 5, 2, 1, 2.700),
(28654, 1865, 2, 2, 2, 1, 1.700),
(28655, 1865, 2, 8, 3, 1, 1.060),
(28656, 1865, 2, 1, 4, 1, 2.700),
(28657, 1865, 2, 6, 4, 1, 1.700),
(28658, 1865, 2, 7, 2, 1, 0.820),
(28659, 1865, 2, 3, 2, 1, 2.230),
(28660, 1865, 2, 9, 8, 3, 0.000),
(28661, 1865, 9, 4, 3, 1, 3.880),
(28662, 1865, 9, 6, 4, 1, 1.700),
(28663, 1865, 9, 1, 3, 1, 2.700),
(28664, 1865, 9, 2, 4, 1, 1.700),
(28665, 1865, 9, 8, 4, 1, 1.060),
(28666, 1865, 9, 7, 2, 1, 0.820),
(28667, 1865, 9, 9, 6, 2, 0.000),
(28668, 1867, 2, 4, 3, 1, 3.880),
(28669, 1867, 2, 5, 2, 1, 2.700),
(28670, 1867, 2, 2, 2, 1, 1.700),
(28671, 1867, 2, 8, 3, 1, 1.060),
(28672, 1867, 2, 1, 4, 1, 2.700),
(28673, 1867, 2, 6, 4, 1, 1.700),
(28674, 1867, 2, 7, 2, 1, 0.820),
(28675, 1867, 2, 3, 2, 1, 2.230),
(28676, 1867, 2, 9, 8, 3, 0.000),
(28677, 1867, 9, 4, 3, 1, 3.880),
(28678, 1867, 9, 6, 4, 1, 1.700),
(28679, 1867, 9, 1, 3, 1, 2.700),
(28680, 1867, 9, 2, 4, 1, 1.700),
(28681, 1867, 9, 8, 4, 1, 1.060),
(28682, 1867, 9, 7, 2, 1, 0.820),
(28683, 1867, 9, 9, 6, 2, 0.000),
(28684, 1868, 9, 4, 3, 1, 3.880),
(28685, 1868, 9, 6, 4, 1, 1.700),
(28686, 1868, 9, 1, 3, 1, 2.700),
(28687, 1868, 9, 2, 4, 1, 1.700),
(28688, 1868, 9, 8, 4, 1, 1.060),
(28689, 1868, 9, 7, 2, 1, 0.820),
(28690, 1868, 9, 9, 6, 2, 0.000),
(28691, 1868, 2, 4, 3, 1, 3.880),
(28692, 1868, 2, 5, 2, 1, 2.700),
(28693, 1868, 2, 2, 2, 1, 1.700),
(28694, 1868, 2, 8, 3, 1, 1.060),
(28695, 1868, 2, 1, 4, 1, 2.700),
(28696, 1868, 2, 6, 4, 1, 1.700),
(28697, 1868, 2, 7, 2, 1, 0.820),
(28698, 1868, 2, 3, 2, 1, 2.230),
(28699, 1868, 2, 9, 8, 3, 0.000),
(28700, 1869, 9, 4, 3, 1, 3.880),
(28701, 1869, 9, 6, 4, 1, 1.700),
(28702, 1869, 9, 1, 3, 1, 2.700),
(28703, 1869, 9, 2, 4, 1, 1.700),
(28704, 1869, 9, 8, 4, 1, 1.060),
(28705, 1869, 9, 7, 2, 1, 0.820),
(28706, 1869, 9, 9, 6, 2, 0.000),
(28707, 1869, 2, 4, 3, 1, 3.880),
(28708, 1869, 2, 5, 2, 1, 2.700),
(28709, 1869, 2, 2, 2, 1, 1.700),
(28710, 1869, 2, 8, 3, 1, 1.060),
(28711, 1869, 2, 1, 4, 1, 2.700),
(28712, 1869, 2, 6, 4, 1, 1.700),
(28713, 1869, 2, 7, 2, 1, 0.820),
(28714, 1869, 2, 3, 2, 1, 2.230),
(28715, 1869, 2, 9, 8, 3, 0.000),
(28716, 1870, 9, 4, 3, 1, 3.880),
(28717, 1870, 9, 6, 4, 1, 1.700),
(28718, 1870, 9, 1, 3, 1, 2.700),
(28719, 1870, 9, 2, 4, 1, 1.700),
(28720, 1870, 9, 8, 4, 1, 1.060),
(28721, 1870, 9, 7, 2, 1, 0.820),
(28722, 1870, 9, 9, 6, 2, 0.000),
(28723, 1870, 10, 4, 3, 1, 3.880),
(28724, 1870, 10, 5, 3, 1, 2.700),
(28725, 1870, 10, 2, 3, 1, 1.700),
(28726, 1870, 10, 8, 3, 1, 1.060),
(28727, 1870, 10, 1, 3, 1, 2.700),
(28728, 1870, 10, 6, 3, 1, 1.700),
(28729, 1870, 10, 7, 2, 1, 0.820),
(28730, 1870, 10, 3, 2, 1, 2.230),
(28731, 1870, 10, 9, 8, 3, 0.000),
(28732, 1871, 9, 4, 3, 1, 3.880),
(28733, 1871, 9, 6, 4, 1, 1.700),
(28734, 1871, 9, 1, 3, 1, 2.700),
(28735, 1871, 9, 2, 4, 1, 1.700),
(28736, 1871, 9, 8, 4, 1, 1.060),
(28737, 1871, 9, 7, 2, 1, 0.820),
(28738, 1871, 9, 9, 6, 2, 0.000),
(28739, 1871, 10, 3, 5, 2, 5.190),
(28740, 1871, 10, 8, 3, 1, 1.060),
(28741, 1871, 10, 1, 3, 1, 2.700),
(28742, 1871, 10, 6, 5, 2, 3.970),
(28743, 1871, 10, 7, 2, 1, 0.820),
(28744, 1871, 10, 4, 3, 1, 3.880),
(28745, 1871, 10, 9, 6, 2, 0.000),
(28746, 1872, 2, 4, 3, 1, 3.880),
(28747, 1872, 2, 5, 2, 1, 2.700),
(28748, 1872, 2, 2, 2, 1, 1.700),
(28749, 1872, 2, 8, 3, 1, 1.060),
(28750, 1872, 2, 1, 4, 1, 2.700),
(28751, 1872, 2, 6, 4, 1, 1.700),
(28752, 1872, 2, 7, 2, 1, 0.820),
(28753, 1872, 2, 3, 2, 1, 2.230),
(28754, 1872, 2, 9, 8, 3, 0.000),
(28755, 1872, 9, 4, 3, 1, 3.880),
(28756, 1872, 9, 6, 4, 1, 1.700),
(28757, 1872, 9, 1, 3, 1, 2.700),
(28758, 1872, 9, 2, 4, 1, 1.700),
(28759, 1872, 9, 8, 4, 1, 1.060),
(28760, 1872, 9, 7, 2, 1, 0.820),
(28761, 1872, 9, 9, 6, 2, 0.000),
(28762, 1873, 2, 4, 3, 1, 3.880),
(28763, 1873, 2, 5, 2, 1, 2.700),
(28764, 1873, 2, 2, 2, 1, 1.700),
(28765, 1873, 2, 8, 3, 1, 1.060),
(28766, 1873, 2, 1, 4, 1, 2.700),
(28767, 1873, 2, 6, 4, 1, 1.700),
(28768, 1873, 2, 7, 2, 1, 0.820),
(28769, 1873, 2, 3, 2, 1, 2.230),
(28770, 1873, 2, 9, 8, 3, 0.000),
(28771, 1873, 9, 4, 3, 1, 3.880),
(28772, 1873, 9, 6, 4, 1, 1.700),
(28773, 1873, 9, 1, 3, 1, 2.700),
(28774, 1873, 9, 2, 4, 1, 1.700),
(28775, 1873, 9, 8, 4, 1, 1.060),
(28776, 1873, 9, 7, 2, 1, 0.820),
(28777, 1873, 9, 9, 6, 2, 0.000),
(28778, 1875, 2, 4, 3, 1, 3.880),
(28779, 1875, 2, 5, 2, 1, 2.700),
(28780, 1875, 2, 2, 2, 1, 1.700),
(28781, 1875, 2, 8, 3, 1, 1.060),
(28782, 1875, 2, 1, 4, 1, 2.700),
(28783, 1875, 2, 6, 4, 1, 1.700),
(28784, 1875, 2, 7, 2, 1, 0.820),
(28785, 1875, 2, 3, 2, 1, 2.230),
(28786, 1875, 2, 9, 8, 3, 0.000),
(28787, 1875, 10, 4, 3, 1, 3.880),
(28788, 1875, 10, 5, 2, 1, 2.700),
(28789, 1875, 10, 2, 4, 1, 1.700),
(28790, 1875, 10, 8, 4, 1, 1.060),
(28791, 1875, 10, 1, 2, 1, 2.700),
(28792, 1875, 10, 6, 3, 1, 1.700),
(28793, 1875, 10, 7, 2, 1, 0.820),
(28794, 1875, 10, 3, 2, 1, 2.230),
(28795, 1875, 10, 9, 8, 3, 0.000),
(28796, 1876, 2, 4, 3, 1, 3.880),
(28797, 1876, 2, 5, 2, 1, 2.700),
(28798, 1876, 2, 2, 2, 1, 1.700),
(28799, 1876, 2, 8, 3, 1, 1.060),
(28800, 1876, 2, 1, 4, 1, 2.700),
(28801, 1876, 2, 6, 4, 1, 1.700),
(28802, 1876, 2, 7, 2, 1, 0.820),
(28803, 1876, 2, 3, 2, 1, 2.230),
(28804, 1876, 2, 9, 8, 3, 0.000),
(28805, 1876, 10, 4, 3, 1, 3.880),
(28806, 1876, 10, 5, 3, 1, 2.700),
(28807, 1876, 10, 2, 3, 1, 1.700),
(28808, 1876, 10, 8, 3, 1, 1.060),
(28809, 1876, 10, 1, 3, 1, 2.700),
(28810, 1876, 10, 6, 3, 1, 1.700),
(28811, 1876, 10, 7, 2, 1, 0.820),
(28812, 1876, 10, 3, 2, 1, 2.230),
(28813, 1876, 10, 9, 8, 3, 0.000),
(28814, 1877, 2, 4, 3, 1, 3.880),
(28815, 1877, 2, 5, 2, 1, 2.700),
(28816, 1877, 2, 2, 2, 1, 1.700),
(28817, 1877, 2, 8, 3, 1, 1.060),
(28818, 1877, 2, 1, 4, 1, 2.700),
(28819, 1877, 2, 6, 4, 1, 1.700),
(28820, 1877, 2, 7, 2, 1, 0.820),
(28821, 1877, 2, 3, 2, 1, 2.230),
(28822, 1877, 2, 9, 8, 3, 0.000),
(28823, 1877, 10, 4, 3, 1, 3.880),
(28824, 1877, 10, 5, 3, 1, 2.700),
(28825, 1877, 10, 2, 3, 1, 1.700),
(28826, 1877, 10, 8, 3, 1, 1.060),
(28827, 1877, 10, 1, 3, 1, 2.700),
(28828, 1877, 10, 6, 3, 1, 1.700),
(28829, 1877, 10, 7, 2, 1, 0.820),
(28830, 1877, 10, 3, 2, 1, 2.230),
(28831, 1877, 10, 9, 8, 3, 0.000),
(28832, 1878, 2, 4, 3, 1, 3.880),
(28833, 1878, 2, 5, 2, 1, 2.700),
(28834, 1878, 2, 2, 2, 1, 1.700),
(28835, 1878, 2, 8, 3, 1, 1.060),
(28836, 1878, 2, 1, 4, 1, 2.700),
(28837, 1878, 2, 6, 4, 1, 1.700),
(28838, 1878, 2, 7, 2, 1, 0.820),
(28839, 1878, 2, 3, 2, 1, 2.230),
(28840, 1878, 2, 9, 8, 3, 0.000),
(28841, 1878, 10, 4, 3, 1, 3.880),
(28842, 1878, 10, 5, 2, 1, 2.700),
(28843, 1878, 10, 2, 4, 1, 1.700),
(28844, 1878, 10, 8, 4, 1, 1.060),
(28845, 1878, 10, 1, 2, 1, 2.700),
(28846, 1878, 10, 6, 3, 1, 1.700),
(28847, 1878, 10, 7, 2, 1, 0.820),
(28848, 1878, 10, 3, 2, 1, 2.230),
(28849, 1878, 10, 9, 8, 3, 0.000),
(28850, 1879, 2, 4, 3, 1, 3.880),
(28851, 1879, 2, 5, 2, 1, 2.700),
(28852, 1879, 2, 2, 2, 1, 1.700),
(28853, 1879, 2, 8, 3, 1, 1.060),
(28854, 1879, 2, 1, 4, 1, 2.700),
(28855, 1879, 2, 6, 4, 1, 1.700),
(28856, 1879, 2, 7, 2, 1, 0.820),
(28857, 1879, 2, 3, 2, 1, 2.230),
(28858, 1879, 2, 9, 8, 3, 0.000),
(28859, 1879, 10, 4, 3, 1, 3.880),
(28860, 1879, 10, 5, 2, 1, 2.700),
(28861, 1879, 10, 2, 4, 1, 1.700),
(28862, 1879, 10, 8, 4, 1, 1.060),
(28863, 1879, 10, 1, 2, 1, 2.700),
(28864, 1879, 10, 6, 3, 1, 1.700),
(28865, 1879, 10, 7, 2, 1, 0.820),
(28866, 1879, 10, 3, 2, 1, 2.230),
(28867, 1879, 10, 9, 8, 3, 0.000),
(28868, 1880, 2, 4, 3, 1, 3.880),
(28869, 1880, 2, 5, 2, 1, 2.700),
(28870, 1880, 2, 2, 2, 1, 1.700),
(28871, 1880, 2, 8, 3, 1, 1.060),
(28872, 1880, 2, 1, 4, 1, 2.700),
(28873, 1880, 2, 6, 4, 1, 1.700),
(28874, 1880, 2, 7, 2, 1, 0.820),
(28875, 1880, 2, 3, 2, 1, 2.230),
(28876, 1880, 2, 9, 8, 3, 0.000),
(28877, 1880, 10, 1, 5, 2, 6.300),
(28878, 1880, 10, 7, 3, 1, 0.820),
(28879, 1880, 10, 6, 3, 1, 1.700),
(28880, 1880, 10, 3, 3, 1, 2.230),
(28881, 1880, 10, 5, 4, 1, 2.700),
(28882, 1880, 10, 2, 2, 1, 1.700),
(28883, 1880, 10, 9, 6, 2, 0.000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `duelo_minijuegos`
--

CREATE TABLE `duelo_minijuegos` (
  `id_duelo` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL COMMENT 'id del evento dentro de la narración de ese duelo',
  `id_usuario` int(11) NOT NULL,
  `minijuego` varchar(40) NOT NULL COMMENT 'clave del catálogo (db/minijuegos.php)',
  `opcion` varchar(40) NOT NULL COMMENT 'vacío = se agotó el plazo y se aplicó la segura',
  `resultado` enum('acierto','fallo') NOT NULL,
  `aplicado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 si llegó a mover el marcador',
  `resuelto_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `duelo_minijuegos`
--

INSERT INTO `duelo_minijuegos` (`id_duelo`, `id_evento`, `id_usuario`, `minijuego`, `opcion`, `resultado`, `aplicado`, `resuelto_en`) VALUES
(1858, 2, 2, 'elige_tu_veneno', 'cruzado', 'fallo', 0, '2026-08-07 11:29:33'),
(1858, 3, 2, 'muralla_humana', 'estirada', 'acierto', 1, '2026-08-07 11:29:40'),
(1858, 3, 9, 'elige_tu_veneno', 'picada', 'fallo', 0, '2026-08-07 11:29:46'),
(1858, 5, 9, 'muralla_humana', 'cuerpo_a_tierra', 'fallo', 0, '2026-08-07 11:29:58'),
(1858, 6, 9, 'muralla_humana', 'estirada', 'fallo', 0, '2026-08-07 11:30:10'),
(1858, 7, 2, 'muralla_humana', 'adelantarse', 'fallo', 0, '2026-08-07 11:30:20'),
(1859, 2, 2, 'primer_toque', 'empalmar_arriba', 'fallo', 0, '2026-08-10 09:47:37'),
(1859, 3, 2, 'elige_tu_veneno', 'picada', 'fallo', 0, '2026-08-10 09:47:41'),
(1859, 5, 9, 'elige_tu_veneno', 'cruzado', 'acierto', 0, '2026-08-10 09:47:51'),
(1861, 6, 2, 'lectura_de_cadera', 'aguantar_de_pie', 'fallo', 0, '2026-08-10 10:11:18'),
(1861, 9, 9, 'desde_la_frontal', 'rosca_al_palo', 'fallo', 0, '2026-08-10 10:11:29'),
(1861, 12, 9, 'la_pizarra', 'centro_medido', 'fallo', 0, '2026-08-10 10:11:45'),
(1861, 14, 2, 'jugada_laboratorio', 'atras_para_el_tiro', 'fallo', 0, '2026-08-10 10:12:00'),
(1862, 3, 2, 'efecto_imposible', 'rosca_larga', 'fallo', 0, '2026-08-10 10:13:35'),
(1862, 5, 9, 'efecto_imposible', 'sin_rosca_arriba', 'fallo', 0, '2026-08-10 10:13:45'),
(1862, 10, 2, 'salto_depredador', 'abajo_al_palo', 'acierto', 1, '2026-08-10 10:13:58'),
(1862, 10, 9, 'el_ultimo_palmo', 'ganar_el_palo', 'fallo', 0, '2026-08-10 10:14:05'),
(1862, 14, 2, 'bombardeo_aereo', 'colgarla_al_area', 'acierto', 1, '2026-08-10 10:14:22'),
(1862, 15, 2, 'la_barrera', 'al_hueco', 'fallo', 0, '2026-08-10 10:14:27'),
(1864, 2, 9, 'elige_tu_veneno', 'picada', 'fallo', 0, '2026-08-10 10:58:54'),
(1864, 4, 2, 'corner_de_bolsillo', 'cerrarla_al_area_chica', 'fallo', 0, '2026-08-10 10:59:01'),
(1864, 14, 9, 'cazador_de_rebotes', 'por_encima_del_portero', 'acierto', 0, '2026-08-10 10:59:31'),
(1864, 15, 2, 'escudo_humano', 'el_tiempo_justo', 'acierto', 1, '2026-08-10 10:59:37'),
(1864, 18, 2, 'vigilancia_aerea', 'poner_gente_en_el_borde', 'fallo', 0, '2026-08-10 10:59:46'),
(1865, 2, 2, 'locura_acrobatica', 'chilena', 'fallo', 0, '2026-08-11 06:26:08'),
(1865, 5, 9, 'disparo_guiado', 'trazo_alto_cruzado', 'fallo', 0, '2026-08-11 06:26:18'),
(1865, 14, 9, 'salto_depredador', 'abajo_al_palo', 'fallo', 0, '2026-08-11 06:26:46'),
(1865, 15, 9, 'centro_al_area', 'tensa_al_primero', 'acierto', 1, '2026-08-11 06:26:42'),
(1867, 2, 9, 'furia_del_clima', 'esperar_el_error', 'fallo', 0, '2026-08-11 06:27:55'),
(1867, 4, 2, 'conduccion_serpenteante', 'ruta_recta_al_area', 'fallo', 0, '2026-08-11 06:28:06'),
(1867, 10, 9, 'el_latigazo', 'cambio_al_punto', 'acierto', 1, '2026-08-11 06:28:46'),
(1867, 12, 2, 'francotirador', 'un_misil_al_hueco', 'fallo', 0, '2026-08-11 06:28:34'),
(1867, 13, 9, 'segunda_jugada', 'filtrarla', 'acierto', 1, '2026-08-11 06:28:42'),
(1868, 5, 9, 'efecto_imposible', 'rosca_larga', 'fallo', 0, '2026-08-11 06:32:49'),
(1868, 7, 2, 'lectura_de_cadera', 'aguantar_de_pie', 'acierto', 1, '2026-08-11 06:33:01'),
(1869, 6, 2, 'efecto_imposible', 'rosca_larga', 'acierto', 0, '2026-08-11 06:43:51'),
(1869, 10, 9, 'el_paso_adelante', 'un_paso_corto', 'fallo', 0, '2026-08-11 06:43:59'),
(1869, 14, 2, 'el_regate_previo', 'tocarla_pronto', 'acierto', 0, '2026-08-11 06:44:09'),
(1870, 3, 9, 'cara_a_cara', 'definirla_cruzada', 'acierto', 0, '2026-08-11 09:52:56'),
(1870, 18, 9, 'elige_tu_veneno', 'cruzado', 'acierto', 1, '2026-08-11 09:53:46'),
(1871, 2, 9, 'dormir_el_partido', 'seguir_igual_de_intenso', 'fallo', 0, '2026-08-11 10:40:36'),
(1871, 16, 9, 'golpe_de_primeras', 'al_punto', 'fallo', 0, '2026-08-11 10:41:21'),
(1872, 4, 2, 'el_paso_adelante', 'un_paso_corto', 'acierto', 1, '2026-08-12 06:06:54'),
(1872, 5, 2, 'francotirador', 'un_misil_al_hueco', 'fallo', 0, '2026-08-12 06:06:58'),
(1872, 6, 9, 'francotirador', 'rosca_baja', 'fallo', 0, '2026-08-12 06:07:06'),
(1872, 15, 9, 'desde_la_frontal', 'rosca_al_palo', 'fallo', 0, '2026-08-12 06:07:26'),
(1872, 16, 2, 'el_regate_previo', 'regatear', 'fallo', 0, '2026-08-12 06:07:31'),
(1873, 2, 9, 'el_regate_previo', 'regatear', 'acierto', 0, '2026-08-12 06:08:43'),
(1873, 4, 2, 'pase_filtrado', 'filtrarla_al_hueco', 'acierto', 1, '2026-08-12 06:08:59'),
(1873, 5, 2, 'disparo_guiado', 'trazo_recto_arriba', 'fallo', 0, '2026-08-12 06:09:07'),
(1873, 10, 2, 'control_magico', 'dejarla_correr', 'fallo', 0, '2026-08-12 06:09:24'),
(1873, 14, 9, 'conduccion_serpenteante', 'ruta_por_el_costado', 'fallo', 0, '2026-08-12 06:09:40'),
(1875, 11, 2, 'lectura_de_cadera', 'aguantar_de_pie', 'acierto', 0, '2026-08-12 06:11:00'),
(1875, 13, 2, 'el_sacrificio_final', 'barrerle_el_balon', 'fallo', 0, '2026-08-12 06:11:13'),
(1876, 4, 2, 'desde_la_frontal', 'rosca_al_palo', 'acierto', 1, '2026-08-12 06:11:55'),
(1876, 7, 2, 'el_punto_debil', 'a_la_frontal', 'fallo', 0, '2026-08-12 06:12:07'),
(1876, 14, 2, 'locura_acrobatica', 'bajarla_y_girar', 'fallo', 0, '2026-08-12 06:12:26'),
(1877, 2, 2, 'disparo_guiado', 'trazo_alto_cruzado', 'acierto', 0, '2026-08-12 06:12:58'),
(1877, 9, 2, 'ultima_trinchera', 'aguantar_la_linea_atras', 'fallo', 0, '2026-08-12 06:13:44'),
(1877, 12, 2, 'primer_toque', 'empalmar_arriba', 'acierto', 1, '2026-08-12 06:13:40'),
(1878, 3, 2, 'detective_del_area', 'apuntar_que_se_adelanta', 'fallo', 0, '2026-08-12 06:14:53'),
(1878, 11, 2, 'cara_a_cara', 'recortarle', 'fallo', 0, '2026-08-12 06:15:22'),
(1879, 2, 2, 'cazador_de_rebotes', 'al_palo_lejano', 'fallo', 0, '2026-08-12 06:15:54'),
(1879, 13, 2, 'sigo_o_suelto', 'soltarla_ya', 'fallo', 0, '2026-08-12 06:16:27'),
(1880, 5, 2, 'el_farol', 'esperar_el_bote', 'fallo', 0, '2026-08-12 07:55:24'),
(1880, 10, 2, 'pase_de_prudencia', 'atras_al_portero', 'fallo', 0, '2026-08-12 07:55:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `duelo_penaltis`
--

CREATE TABLE `duelo_penaltis` (
  `id_duelo` int(11) NOT NULL,
  `ronda` tinyint(3) UNSIGNED NOT NULL,
  `turno` tinyint(3) UNSIGNED NOT NULL,
  `zona_tirador` varchar(16) DEFAULT NULL,
  `zona_portero` varchar(16) DEFAULT NULL,
  `auto_tirador` tinyint(1) NOT NULL DEFAULT 0,
  `auto_portero` tinyint(1) NOT NULL DEFAULT 0,
  `gol` tinyint(1) DEFAULT NULL,
  `abierto` datetime NOT NULL DEFAULT current_timestamp(),
  `resuelto` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `duelo_penaltis`
--

INSERT INTO `duelo_penaltis` (`id_duelo`, `ronda`, `turno`, `zona_tirador`, `zona_portero`, `auto_tirador`, `auto_portero`, `gol`, `abierto`, `resuelto`) VALUES
(1869, 1, 0, 'abajo_der', 'arriba_izq', 0, 0, 1, '2026-08-11 08:43:51', '2026-08-11 08:44:31'),
(1869, 1, 1, 'arriba_izq', 'arriba_izq', 0, 0, 0, '2026-08-11 08:44:31', '2026-08-11 08:45:23'),
(1869, 2, 0, 'abajo_der', 'abajo_izq', 0, 0, 1, '2026-08-11 08:45:25', '2026-08-11 08:46:11'),
(1869, 2, 1, 'arriba_izq', 'arriba_izq', 0, 0, 0, '2026-08-11 08:46:11', '2026-08-11 08:46:11'),
(1869, 3, 0, 'abajo_der', 'arriba_izq', 0, 0, 1, '2026-08-11 08:46:11', '2026-08-11 08:46:11'),
(1869, 3, 1, 'arriba_izq', 'arriba_izq', 0, 0, 0, '2026-08-11 08:46:11', '2026-08-11 08:46:11'),
(1871, 1, 0, 'arriba_der', 'abajo_der', 1, 1, 1, '2026-08-11 12:41:25', '2026-08-11 12:41:38'),
(1871, 1, 1, 'abajo_izq', 'arriba_der', 1, 1, 1, '2026-08-11 12:41:38', '2026-08-11 12:41:50'),
(1871, 2, 0, 'arriba_der', 'abajo_der', 1, 1, 1, '2026-08-11 12:41:50', '2026-08-11 12:42:02'),
(1871, 2, 1, 'abajo_izq', 'arriba_der', 0, 1, 1, '2026-08-11 12:42:02', '2026-08-11 12:42:08'),
(1871, 3, 0, 'arriba_der', 'arriba_izq', 1, 0, 1, '2026-08-11 12:42:09', '2026-08-11 12:42:20'),
(1871, 3, 1, 'abajo_izq', 'abajo_der', 0, 1, 1, '2026-08-11 12:42:21', '2026-08-11 12:42:21'),
(1871, 4, 0, 'arriba_der', 'abajo_der', 1, 0, 1, '2026-08-11 12:42:22', '2026-08-11 12:42:22'),
(1871, 4, 1, 'arriba_der', 'arriba_izq', 0, 1, 1, '2026-08-11 12:42:23', '2026-08-11 12:42:24'),
(1871, 5, 0, 'arriba_der', 'abajo_izq', 1, 0, 1, '2026-08-11 12:42:24', '2026-08-11 12:42:25'),
(1871, 5, 1, 'abajo_der', 'arriba_der', 0, 1, 1, '2026-08-11 12:42:25', '2026-08-11 12:42:26'),
(1871, 6, 0, 'arriba_der', 'arriba_der', 1, 0, 0, '2026-08-11 12:42:27', '2026-08-11 12:42:28'),
(1871, 6, 1, 'abajo_izq', 'abajo_der', 0, 1, 1, '2026-08-11 12:42:28', '2026-08-11 12:42:29'),
(1879, 1, 0, 'arriba_izq', 'arriba_der', 1, 0, 1, '2026-08-12 08:16:42', '2026-08-12 08:16:44'),
(1879, 1, 1, 'abajo_izq', 'arriba_der', 0, 1, 1, '2026-08-12 08:16:45', '2026-08-12 08:16:49'),
(1879, 2, 0, 'arriba_der', 'arriba_izq', 1, 0, 1, '2026-08-12 08:16:50', '2026-08-12 08:16:53'),
(1879, 2, 1, 'abajo_der', 'abajo_izq', 0, 1, 1, '2026-08-12 08:16:53', '2026-08-12 08:16:54'),
(1879, 3, 0, 'abajo_izq', 'arriba_der', 1, 0, 1, '2026-08-12 08:16:55', '2026-08-12 08:16:56'),
(1879, 3, 1, 'abajo_izq', 'arriba_der', 0, 1, 1, '2026-08-12 08:16:57', '2026-08-12 08:16:59'),
(1879, 4, 0, 'arriba_izq', 'abajo_der', 1, 0, 1, '2026-08-12 08:17:00', '2026-08-12 08:17:01'),
(1879, 4, 1, 'arriba_der', 'arriba_der', 0, 1, 0, '2026-08-12 08:17:02', '2026-08-12 08:17:03'),
(1879, 5, 0, 'abajo_der', 'abajo_izq', 1, 0, 1, '2026-08-12 08:17:03', '2026-08-12 08:17:04');

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
(23, 'Inazuma Kids FC'),
(20, 'Instituto Farm'),
(22, 'Instituto Kikrwood'),
(25, 'Instituto Kirkwood'),
(24, 'Instituto Occult'),
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
-- Estructura de tabla para la tabla `formaciones_usuario`
--

CREATE TABLE `formaciones_usuario` (
  `id_formacion_usuario` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `formacion` varchar(8) NOT NULL,
  `obtenida` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `formaciones_usuario`
--

INSERT INTO `formaciones_usuario` (`id_formacion_usuario`, `id_usuario`, `formacion`, `obtenida`) VALUES
(4, 2, '352', '2026-08-06 14:50:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mazos`
--

CREATE TABLE `mazos` (
  `id_mazo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `formacion` varchar(8) NOT NULL DEFAULT '442',
  `titular` tinyint(1) NOT NULL DEFAULT 0,
  `creado` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mazos`
--

INSERT INTO `mazos` (`id_mazo`, `id_usuario`, `nombre`, `formacion`, `titular`, `creado`) VALUES
(1, 9, 'Once titular', '442', 1, '2026-08-05 10:52:10'),
(8, 2, 'LuluLulez', '433', 1, '2026-08-06 09:01:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mazo_cartas`
--

CREATE TABLE `mazo_cartas` (
  `id_mazo_carta` int(11) NOT NULL,
  `id_mazo` int(11) NOT NULL,
  `id_coleccion` int(11) NOT NULL,
  `hueco` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mazo_cartas`
--

INSERT INTO `mazo_cartas` (`id_mazo_carta`, `id_mazo`, `id_coleccion`, `hueco`) VALUES
(232, 8, 4559, 0),
(233, 8, 12898, 1),
(234, 8, 12899, 2),
(235, 8, 12900, 3),
(236, 8, 12901, 4),
(237, 8, 12902, 5),
(238, 8, 12903, 6),
(239, 8, 12904, 7),
(240, 8, 5889, 8),
(241, 8, 5400, 9),
(242, 8, 5931, 10);

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

--
-- Volcado de datos para la tabla `mercado`
--

INSERT INTO `mercado` (`id_anuncio`, `id_coleccion`, `precio`, `fecha_publicacion`, `activa`) VALUES
(4, 5424, 250, '2026-08-06 09:47:00', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `minijuegos_partidas`
--

CREATE TABLE `minijuegos_partidas` (
  `id_partida` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `juego` varchar(40) NOT NULL,
  `puntuacion` int(11) NOT NULL DEFAULT 0,
  `recompensa_monedas` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `jugado` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `misiones`
--

CREATE TABLE `misiones` (
  `id_mision` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `tipo` enum('cartas_distintas','copias_totales','duelos_jugados','duelos_ganados','expansiones_completas','mazos_creados') NOT NULL,
  `ciclo` enum('unica','diaria','semanal') NOT NULL DEFAULT 'unica',
  `objetivo` int(11) UNSIGNED NOT NULL,
  `recompensa_monedas` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `misiones`
--

INSERT INTO `misiones` (`id_mision`, `nombre`, `descripcion`, `tipo`, `ciclo`, `objetivo`, `recompensa_monedas`, `activo`) VALUES
(1, 'Primeras fichas', 'Consigue 10 cromos distintos.', 'cartas_distintas', 'diaria', 10, 250, 1),
(2, 'Plantilla amplia', 'Consigue 25 cromos distintos.', 'cartas_distintas', 'unica', 25, 600, 1),
(3, 'Archivo completo', 'Consigue 40 cromos distintos.', 'cartas_distintas', 'unica', 40, 1500, 1),
(4, 'Fondo de armario', 'Acumula 100 cromos contando repetidos.', 'copias_totales', 'unica', 100, 400, 1),
(5, 'Alineación inscrita', 'Crea tu primer mazo de 11 jugadores.', 'mazos_creados', 'unica', 1, 300, 1),
(6, 'Debut en competición', 'Disputa tu primer duelo.', 'duelos_jugados', 'unica', 1, 250, 1),
(7, 'Racha de temporada', 'Gana 5 duelos.', 'duelos_ganados', 'unica', 5, 900, 1),
(8, 'Expansión al día', 'Completa todas las cartas de una expansión.', 'expansiones_completas', 'unica', 1, 1200, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `misiones_progreso`
--

CREATE TABLE `misiones_progreso` (
  `id_progreso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_mision` int(11) NOT NULL,
  `periodo` varchar(10) NOT NULL DEFAULT '',
  `fecha_reclamada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `misiones_progreso`
--

INSERT INTO `misiones_progreso` (`id_progreso`, `id_usuario`, `id_mision`, `periodo`, `fecha_reclamada`) VALUES
(1, 9, 1, '', '2026-08-06 08:58:14'),
(2, 2, 1, '', '2026-08-06 09:00:44'),
(3, 2, 4, '', '2026-08-06 09:00:46'),
(4, 2, 2, '', '2026-08-06 09:17:57'),
(5, 2, 5, '', '2026-08-06 09:18:00'),
(6, 2, 3, '', '2026-08-06 09:18:23'),
(7, 2, 8, '', '2026-08-06 09:40:38'),
(8, 2, 6, '', '2026-08-06 15:35:25'),
(9, 2, 7, '', '2026-08-06 15:35:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plantillas_3d`
--

CREATE TABLE `plantillas_3d` (
  `id_plantilla` int(11) NOT NULL,
  `tipo` enum('caja_expansion','caja_sobre','sobre') NOT NULL,
  `id_referencia` int(11) NOT NULL,
  `ruta_original` varchar(255) NOT NULL,
  `rutas_recortadas` text NOT NULL COMMENT 'JSON: {"front":"...","top":"...","side":"..."} o {"frente":"...","reverso":"..."}',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `plantillas_3d`
--

INSERT INTO `plantillas_3d` (`id_plantilla`, `tipo`, `id_referencia`, `ruta_original`, `rutas_recortadas`, `actualizado_en`) VALUES
(1, 'caja_expansion', 3, './assets/img/plantillas/caja_expansion_3/original.png?v=1786058614', '{\"front\":\"./assets/img/plantillas/caja_expansion_3/front.png?v=1786058614\",\"side\":\"./assets/img/plantillas/caja_expansion_3/side.png?v=1786058614\",\"top\":\"./assets/img/plantillas/caja_expansion_3/top.png?v=1786058614\",\"lid\":\"./assets/img/plantillas/caja_expansion_3/lid.png?v=1786058614\",\"interior\":\"./assets/img/plantillas/caja_expansion_3/interior.png?v=1786058614\"}', '2026-08-07 07:27:17'),
(2, 'caja_sobre', 2, './assets/img/plantillas/caja_sobre_2/original.png?v=1786058645', '{\"front\":\"./assets/img/plantillas/caja_sobre_2/front.png?v=1786058645\",\"side\":\"./assets/img/plantillas/caja_sobre_2/side.png?v=1786058645\",\"top\":\"./assets/img/plantillas/caja_sobre_2/top.png?v=1786058645\",\"lid\":\"./assets/img/plantillas/caja_sobre_2/lid.png?v=1786058645\",\"interior\":\"./assets/img/plantillas/caja_sobre_2/interior.png?v=1786058645\"}', '2026-08-07 07:27:17'),
(3, 'sobre', 2, './assets/img/plantillas/sobre_2/original.png?v=1786058819', '{\"frente\":\"./assets/img/plantillas/sobre_2/frente.png?v=1786058819\",\"reverso\":\"./assets/img/plantillas/sobre_2/reverso.png?v=1786058819\"}', '2026-08-07 07:27:17');

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
-- Estructura de tabla para la tabla `rasgos`
--

CREATE TABLE `rasgos` (
  `id_rasgo` int(11) NOT NULL,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `tipo` enum('afinidad','configuracion','derivado') NOT NULL,
  `linea_1` enum('POR','DF','MC','DC') DEFAULT NULL,
  `linea_2` enum('POR','DF','MC','DC') DEFAULT NULL,
  `umbral_1` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `umbral_2` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `umbral_3` tinyint(3) UNSIGNED NOT NULL DEFAULT 11,
  `pct_1` decimal(6,3) NOT NULL DEFAULT 0.000,
  `pct_2` decimal(6,3) NOT NULL DEFAULT 0.000,
  `pct_3` decimal(6,3) NOT NULL DEFAULT 0.000,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rasgos`
--

INSERT INTO `rasgos` (`id_rasgo`, `clave`, `nombre`, `tipo`, `linea_1`, `linea_2`, `umbral_1`, `umbral_2`, `umbral_3`, `pct_1`, `pct_2`, `pct_3`, `descripcion`) VALUES
(1, 'fuego', 'Fuego', 'afinidad', 'DC', NULL, 2, 5, 11, 2.700, 6.300, 12.590, 'Refuerza la línea de Ataque.'),
(2, 'bosque', 'Bosque', 'afinidad', 'MC', NULL, 2, 5, 11, 1.700, 3.970, 7.940, 'Refuerza la línea de Medio.'),
(3, 'viento', 'Viento', 'afinidad', 'DF', NULL, 2, 5, 11, 2.230, 5.190, 10.360, 'Refuerza la línea de Defensa.'),
(4, 'montana', 'Montaña', 'afinidad', 'POR', NULL, 2, 5, 11, 3.880, 9.060, 18.130, 'Refuerza la línea de Portería.'),
(5, 'contraataque', 'Contraataque', 'configuracion', 'DC', NULL, 2, 5, 11, 2.700, 6.300, 12.590, 'Refuerza la línea de Ataque.'),
(6, 'vinculo', 'Vínculo', 'configuracion', 'MC', NULL, 2, 5, 11, 1.700, 3.970, 7.940, 'Refuerza la línea de Medio.'),
(7, 'justicia', 'Justicia', 'configuracion', 'DC', 'DF', 2, 5, 11, 0.820, 1.830, 3.650, 'Refuerza Ataque y Defensa por igual.'),
(8, 'brecha', 'Brecha', 'configuracion', 'DC', 'POR', 2, 5, 11, 1.060, 2.390, 4.770, 'Refuerza Ataque y Portería por igual.'),
(9, 'tension', 'Tensión', 'derivado', NULL, NULL, 3, 5, 7, 0.000, 0.000, 0.000, 'No da fuerza: mejora tus probabilidades de tier del Aumento pre-partido. Se activa por número de rasgos DISTINTOS activos.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sobre`
--

CREATE TABLE `sobre` (
  `id_sobre` int(11) UNSIGNED NOT NULL,
  `id_expansion` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `cantidad` int(11) UNSIGNED NOT NULL,
  `precio` int(11) UNSIGNED NOT NULL,
  `imagen` varchar(255) NOT NULL,
  `activo` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sobre`
--

INSERT INTO `sobre` (`id_sobre`, `id_expansion`, `nombre`, `cantidad`, `precio`, `imagen`, `activo`) VALUES
(1, 3, 'Sobre Doble', 10, 50, './assets/img/Sobres/base_set_x10.png', 1),
(2, 3, 'Sobre Básico', 5, 25, './assets/img/Sobres/base_set_x10.png', 1),
(3, 3, 'sobre prueba', 600, 10, './assets/img/Sobres/base_set_x10.png', 1);

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
(1, 'FranDictador', '$2y$10$JgLxZ.OiTE4r8jdrX9zdl.PeSwoiXKBczgSOtXvAhvxd3Sl/RNVnG', 1400, 1, '2026-07-21 09:46:33', './assets/img/perfil/apple-icon-120x120.png'),
(2, 'LuluLulez', '$2y$10$xzIHik9TczfeLmwbHoBqZeXNnXVNMLtMn9aHm/btOpqKHlehl.MN.', 114517, 1, '2026-07-21 15:32:54', './assets/img/perfil/usuario_2_24464568.png'),
(4, 'Prueba1', '$2y$10$94UVoMMlhe10ZzqR1Apt5OkqkmEdzXP6qhODkm68XkTBleCHtQpIm', 100000, 1, '2026-07-22 12:01:22', './assets/img/perfil/apple-icon-120x120.png'),
(5, 'Prueba2', '$2y$10$N36PYPCuZ4TwlB3L9d23G.A6lhWeNajIGCtqy6lef2vNBZDNTnpI6', 100000, 0, '2026-07-22 12:01:42', './assets/img/perfil/apple-icon-120x120.png'),
(7, 'Prueba3', '$2y$10$ZBhiS8mxITHzpoOxOeCsl.rKm/ETWOBGtrHflZxlThhbQtRXtvtoC', 100000, 1, '2026-07-22 12:02:30', './assets/img/perfil/apple-icon-120x120.png'),
(8, 'Payo Water', '$2y$10$Exodaf.7tWHhPHJwEz8K.eSqsBzm43Zl7BaPNS5jLcInRg2kGZBk.', 99905, 0, '2026-08-05 08:58:57', './assets/img/perfil/apple-icon-120x120.png'),
(9, 'Claude', '$2y$10$FLD./fFwK34785/71L5fS.rFHbkn1OssXUgIdDGJGIMWG9gEbnWc2', 998570, 1, '2026-08-05 10:30:31', './assets/img/perfil/apple-icon-120x120.png'),
(10, 'CPU', '*', 0, 0, '2026-08-06 11:10:56', './assets/img/perfil/apple-icon-120x120.png');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `afinidad`
--
ALTER TABLE `afinidad`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cadenas`
--
ALTER TABLE `cadenas`
  ADD PRIMARY KEY (`id_cadena`);

--
-- Indices de la tabla `cadena_aristas`
--
ALTER TABLE `cadena_aristas`
  ADD PRIMARY KEY (`id_origen`,`id_destino`),
  ADD KEY `idx_arista_destino` (`id_destino`);

--
-- Indices de la tabla `cadena_cofres`
--
ALTER TABLE `cadena_cofres`
  ADD PRIMARY KEY (`id_usuario`,`id_nodo`),
  ADD KEY `fk_cofre_nodo` (`id_nodo`);

--
-- Indices de la tabla `cadena_drops`
--
ALTER TABLE `cadena_drops`
  ADD PRIMARY KEY (`id_drop`),
  ADD KEY `idx_drop_duelo` (`id_duelo`),
  ADD KEY `idx_drop_usuario` (`id_usuario`),
  ADD KEY `fk_drop_nodo` (`id_nodo`);

--
-- Indices de la tabla `cadena_loot`
--
ALTER TABLE `cadena_loot`
  ADD PRIMARY KEY (`id_loot`),
  ADD KEY `idx_loot_nodo` (`id_nodo`),
  ADD KEY `fk_loot_cromo` (`id_cromo`);

--
-- Indices de la tabla `cadena_nodos`
--
ALTER TABLE `cadena_nodos`
  ADD PRIMARY KEY (`id_nodo`),
  ADD KEY `idx_nodo_cadena` (`id_cadena`),
  ADD KEY `idx_nodo_rival` (`id_rival`);

--
-- Indices de la tabla `cadena_numeracion`
--
ALTER TABLE `cadena_numeracion`
  ADD PRIMARY KEY (`id_numeracion`),
  ADD UNIQUE KEY `uq_numeracion_serie` (`id_cromo`,`numero_serie`),
  ADD UNIQUE KEY `uq_numeracion_coleccion` (`id_coleccion`);

--
-- Indices de la tabla `cadena_progreso`
--
ALTER TABLE `cadena_progreso`
  ADD PRIMARY KEY (`id_progreso`),
  ADD UNIQUE KEY `uq_progreso_nodo` (`id_usuario`,`id_nodo`,`dificultad`),
  ADD KEY `idx_progreso_nodo` (`id_nodo`);

--
-- Indices de la tabla `cadena_requisitos`
--
ALTER TABLE `cadena_requisitos`
  ADD PRIMARY KEY (`id_requisito`),
  ADD KEY `idx_req_cadena` (`id_cadena`);

--
-- Indices de la tabla `cadena_rivales`
--
ALTER TABLE `cadena_rivales`
  ADD PRIMARY KEY (`id_rival`);

--
-- Indices de la tabla `cadena_rival_cartas`
--
ALTER TABLE `cadena_rival_cartas`
  ADD PRIMARY KEY (`id_estilo`,`hueco`),
  ADD KEY `idx_rival_carta_cromo` (`id_cromo`);

--
-- Indices de la tabla `cadena_rival_estilos`
--
ALTER TABLE `cadena_rival_estilos`
  ADD PRIMARY KEY (`id_estilo`),
  ADD KEY `idx_estilo_rival` (`id_rival`);

--
-- Indices de la tabla `codigos`
--
ALTER TABLE `codigos`
  ADD PRIMARY KEY (`id_codigo`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `codigos_canjeados`
--
ALTER TABLE `codigos_canjeados`
  ADD PRIMARY KEY (`id_canje`),
  ADD UNIQUE KEY `uq_codigo_usuario` (`id_codigo`,`id_usuario`),
  ADD KEY `fk_canje_usuario` (`id_usuario`);

--
-- Indices de la tabla `coleccion`
--
ALTER TABLE `coleccion`
  ADD PRIMARY KEY (`id_coleccion`),
  ADD KEY `idx_coleccion_usuario` (`id_usuario`),
  ADD KEY `idx_coleccion_cromo` (`id_cromo`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`clave`);

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
-- Indices de la tabla `cromo_rasgos`
--
ALTER TABLE `cromo_rasgos`
  ADD PRIMARY KEY (`id_cromo_rasgo`),
  ADD UNIQUE KEY `uq_cromo_rasgo` (`id_cromo`,`id_rasgo`),
  ADD KEY `idx_cromo` (`id_cromo`),
  ADD KEY `idx_rasgo` (`id_rasgo`);

--
-- Indices de la tabla `duelos`
--
ALTER TABLE `duelos`
  ADD PRIMARY KEY (`id_duelo`),
  ADD KEY `idx_duelos_estado` (`estado`),
  ADD KEY `idx_duelos_creador` (`id_creador`),
  ADD KEY `idx_duelos_rival` (`id_rival`),
  ADD KEY `fk_duelos_rareza` (`id_rareza_apuesta`);

--
-- Indices de la tabla `duelo_alineaciones`
--
ALTER TABLE `duelo_alineaciones`
  ADD PRIMARY KEY (`id_alineacion`),
  ADD UNIQUE KEY `uq_alineacion_duelo_usuario_hueco` (`id_duelo`,`id_usuario`,`hueco`),
  ADD KEY `idx_alineacion_cromo` (`id_cromo`),
  ADD KEY `fk_alineacion_usuario` (`id_usuario`);

--
-- Indices de la tabla `duelo_apuestas`
--
ALTER TABLE `duelo_apuestas`
  ADD PRIMARY KEY (`id_apuesta`),
  ADD UNIQUE KEY `uq_apuesta_duelo_usuario` (`id_duelo`,`id_usuario`),
  ADD KEY `idx_apuestas_usuario` (`id_usuario`),
  ADD KEY `idx_apuestas_coleccion` (`id_coleccion`);

--
-- Indices de la tabla `duelo_aumentos`
--
ALTER TABLE `duelo_aumentos`
  ADD PRIMARY KEY (`id_aumento`),
  ADD UNIQUE KEY `uq_aumento_duelo_usuario_opcion` (`id_duelo`,`id_usuario`,`opcion`),
  ADD KEY `idx_aumentos_usuario` (`id_usuario`);

--
-- Indices de la tabla `duelo_compos`
--
ALTER TABLE `duelo_compos`
  ADD PRIMARY KEY (`id_duelo_compo`),
  ADD UNIQUE KEY `uq_duelo_usuario_rasgo` (`id_duelo`,`id_usuario`,`id_rasgo`),
  ADD KEY `idx_duelo_usuario` (`id_duelo`,`id_usuario`),
  ADD KEY `fk_dc_rasgo` (`id_rasgo`);

--
-- Indices de la tabla `duelo_minijuegos`
--
ALTER TABLE `duelo_minijuegos`
  ADD PRIMARY KEY (`id_duelo`,`id_evento`,`id_usuario`),
  ADD KEY `duelo` (`id_duelo`);

--
-- Indices de la tabla `duelo_penaltis`
--
ALTER TABLE `duelo_penaltis`
  ADD PRIMARY KEY (`id_duelo`,`ronda`,`turno`);

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
-- Indices de la tabla `formaciones_usuario`
--
ALTER TABLE `formaciones_usuario`
  ADD PRIMARY KEY (`id_formacion_usuario`),
  ADD UNIQUE KEY `uq_formacion_usuario` (`id_usuario`,`formacion`);

--
-- Indices de la tabla `mazos`
--
ALTER TABLE `mazos`
  ADD PRIMARY KEY (`id_mazo`),
  ADD KEY `idx_mazos_usuario` (`id_usuario`);

--
-- Indices de la tabla `mazo_cartas`
--
ALTER TABLE `mazo_cartas`
  ADD PRIMARY KEY (`id_mazo_carta`),
  ADD UNIQUE KEY `uq_mazo_copia` (`id_mazo`,`id_coleccion`),
  ADD UNIQUE KEY `uq_mazo_hueco` (`id_mazo`,`hueco`),
  ADD KEY `idx_mazocartas_coleccion` (`id_coleccion`);

--
-- Indices de la tabla `mercado`
--
ALTER TABLE `mercado`
  ADD PRIMARY KEY (`id_anuncio`),
  ADD UNIQUE KEY `id_coleccion` (`id_coleccion`),
  ADD KEY `idx_mercado_precio` (`precio`),
  ADD KEY `idx_mercado_activa` (`activa`);

--
-- Indices de la tabla `minijuegos_partidas`
--
ALTER TABLE `minijuegos_partidas`
  ADD PRIMARY KEY (`id_partida`),
  ADD KEY `idx_partidas_usuario` (`id_usuario`),
  ADD KEY `idx_partidas_juego` (`juego`);

--
-- Indices de la tabla `misiones`
--
ALTER TABLE `misiones`
  ADD PRIMARY KEY (`id_mision`);

--
-- Indices de la tabla `misiones_progreso`
--
ALTER TABLE `misiones_progreso`
  ADD PRIMARY KEY (`id_progreso`),
  ADD UNIQUE KEY `uq_progreso_usuario_mision_periodo` (`id_usuario`,`id_mision`,`periodo`),
  ADD KEY `idx_progreso_mision` (`id_mision`);

--
-- Indices de la tabla `plantillas_3d`
--
ALTER TABLE `plantillas_3d`
  ADD PRIMARY KEY (`id_plantilla`),
  ADD UNIQUE KEY `tipo_referencia` (`tipo`,`id_referencia`);

--
-- Indices de la tabla `rarezas`
--
ALTER TABLE `rarezas`
  ADD PRIMARY KEY (`id_rareza`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `rasgos`
--
ALTER TABLE `rasgos`
  ADD PRIMARY KEY (`id_rasgo`),
  ADD UNIQUE KEY `uq_rasgo_clave` (`clave`);

--
-- Indices de la tabla `sobre`
--
ALTER TABLE `sobre`
  ADD PRIMARY KEY (`id_sobre`),
  ADD KEY `fk_sobre_expansion` (`id_expansion`);

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
-- AUTO_INCREMENT de la tabla `cadenas`
--
ALTER TABLE `cadenas`
  MODIFY `id_cadena` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cadena_drops`
--
ALTER TABLE `cadena_drops`
  MODIFY `id_drop` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=494;

--
-- AUTO_INCREMENT de la tabla `cadena_loot`
--
ALTER TABLE `cadena_loot`
  MODIFY `id_loot` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `cadena_nodos`
--
ALTER TABLE `cadena_nodos`
  MODIFY `id_nodo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de la tabla `cadena_numeracion`
--
ALTER TABLE `cadena_numeracion`
  MODIFY `id_numeracion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `cadena_progreso`
--
ALTER TABLE `cadena_progreso`
  MODIFY `id_progreso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=829;

--
-- AUTO_INCREMENT de la tabla `cadena_requisitos`
--
ALTER TABLE `cadena_requisitos`
  MODIFY `id_requisito` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cadena_rivales`
--
ALTER TABLE `cadena_rivales`
  MODIFY `id_rival` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `cadena_rival_estilos`
--
ALTER TABLE `cadena_rival_estilos`
  MODIFY `id_estilo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `codigos`
--
ALTER TABLE `codigos`
  MODIFY `id_codigo` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `codigos_canjeados`
--
ALTER TABLE `codigos_canjeados`
  MODIFY `id_canje` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `coleccion`
--
ALTER TABLE `coleccion`
  MODIFY `id_coleccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12905;

--
-- AUTO_INCREMENT de la tabla `cromos`
--
ALTER TABLE `cromos`
  MODIFY `id_cromo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=510;

--
-- AUTO_INCREMENT de la tabla `cromo_rasgos`
--
ALTER TABLE `cromo_rasgos`
  MODIFY `id_cromo_rasgo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26657;

--
-- AUTO_INCREMENT de la tabla `duelos`
--
ALTER TABLE `duelos`
  MODIFY `id_duelo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1881;

--
-- AUTO_INCREMENT de la tabla `duelo_alineaciones`
--
ALTER TABLE `duelo_alineaciones`
  MODIFY `id_alineacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41229;

--
-- AUTO_INCREMENT de la tabla `duelo_apuestas`
--
ALTER TABLE `duelo_apuestas`
  MODIFY `id_apuesta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT de la tabla `duelo_aumentos`
--
ALTER TABLE `duelo_aumentos`
  MODIFY `id_aumento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11185;

--
-- AUTO_INCREMENT de la tabla `duelo_compos`
--
ALTER TABLE `duelo_compos`
  MODIFY `id_duelo_compo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28884;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `id_equipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `expansiones`
--
ALTER TABLE `expansiones`
  MODIFY `id_expansion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `formaciones_usuario`
--
ALTER TABLE `formaciones_usuario`
  MODIFY `id_formacion_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `mazos`
--
ALTER TABLE `mazos`
  MODIFY `id_mazo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `mazo_cartas`
--
ALTER TABLE `mazo_cartas`
  MODIFY `id_mazo_carta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=243;

--
-- AUTO_INCREMENT de la tabla `mercado`
--
ALTER TABLE `mercado`
  MODIFY `id_anuncio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `minijuegos_partidas`
--
ALTER TABLE `minijuegos_partidas`
  MODIFY `id_partida` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `misiones`
--
ALTER TABLE `misiones`
  MODIFY `id_mision` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `misiones_progreso`
--
ALTER TABLE `misiones_progreso`
  MODIFY `id_progreso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `plantillas_3d`
--
ALTER TABLE `plantillas_3d`
  MODIFY `id_plantilla` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `rarezas`
--
ALTER TABLE `rarezas`
  MODIFY `id_rareza` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `rasgos`
--
ALTER TABLE `rasgos`
  MODIFY `id_rasgo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `sobre`
--
ALTER TABLE `sobre`
  MODIFY `id_sobre` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cadena_aristas`
--
ALTER TABLE `cadena_aristas`
  ADD CONSTRAINT `fk_arista_destino` FOREIGN KEY (`id_destino`) REFERENCES `cadena_nodos` (`id_nodo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_arista_origen` FOREIGN KEY (`id_origen`) REFERENCES `cadena_nodos` (`id_nodo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cadena_cofres`
--
ALTER TABLE `cadena_cofres`
  ADD CONSTRAINT `fk_cofre_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `cadena_nodos` (`id_nodo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cofre_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cadena_drops`
--
ALTER TABLE `cadena_drops`
  ADD CONSTRAINT `fk_drop_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `cadena_nodos` (`id_nodo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_drop_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cadena_loot`
--
ALTER TABLE `cadena_loot`
  ADD CONSTRAINT `fk_loot_cromo` FOREIGN KEY (`id_cromo`) REFERENCES `cromos` (`id_cromo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loot_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `cadena_nodos` (`id_nodo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cadena_nodos`
--
ALTER TABLE `cadena_nodos`
  ADD CONSTRAINT `fk_nodo_cadena` FOREIGN KEY (`id_cadena`) REFERENCES `cadenas` (`id_cadena`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_nodo_rival` FOREIGN KEY (`id_rival`) REFERENCES `cadena_rivales` (`id_rival`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cadena_numeracion`
--
ALTER TABLE `cadena_numeracion`
  ADD CONSTRAINT `fk_numeracion_coleccion` FOREIGN KEY (`id_coleccion`) REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_numeracion_cromo` FOREIGN KEY (`id_cromo`) REFERENCES `cromos` (`id_cromo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cadena_progreso`
--
ALTER TABLE `cadena_progreso`
  ADD CONSTRAINT `fk_progreso_cadena_nodo` FOREIGN KEY (`id_nodo`) REFERENCES `cadena_nodos` (`id_nodo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_progreso_cadena_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cadena_requisitos`
--
ALTER TABLE `cadena_requisitos`
  ADD CONSTRAINT `fk_req_cadena` FOREIGN KEY (`id_cadena`) REFERENCES `cadenas` (`id_cadena`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cadena_rival_cartas`
--
ALTER TABLE `cadena_rival_cartas`
  ADD CONSTRAINT `fk_rival_carta_cromo` FOREIGN KEY (`id_cromo`) REFERENCES `cromos` (`id_cromo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rival_carta_estilo` FOREIGN KEY (`id_estilo`) REFERENCES `cadena_rival_estilos` (`id_estilo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cadena_rival_estilos`
--
ALTER TABLE `cadena_rival_estilos`
  ADD CONSTRAINT `fk_estilo_rival` FOREIGN KEY (`id_rival`) REFERENCES `cadena_rivales` (`id_rival`) ON DELETE CASCADE;

--
-- Filtros para la tabla `codigos_canjeados`
--
ALTER TABLE `codigos_canjeados`
  ADD CONSTRAINT `fk_canje_codigo` FOREIGN KEY (`id_codigo`) REFERENCES `codigos` (`id_codigo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_canje_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

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
-- Filtros para la tabla `cromo_rasgos`
--
ALTER TABLE `cromo_rasgos`
  ADD CONSTRAINT `fk_cr_cromo` FOREIGN KEY (`id_cromo`) REFERENCES `cromos` (`id_cromo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cr_rasgo` FOREIGN KEY (`id_rasgo`) REFERENCES `rasgos` (`id_rasgo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `duelos`
--
ALTER TABLE `duelos`
  ADD CONSTRAINT `fk_duelos_creador` FOREIGN KEY (`id_creador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_duelos_rareza` FOREIGN KEY (`id_rareza_apuesta`) REFERENCES `rarezas` (`id_rareza`),
  ADD CONSTRAINT `fk_duelos_rival` FOREIGN KEY (`id_rival`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `duelo_alineaciones`
--
ALTER TABLE `duelo_alineaciones`
  ADD CONSTRAINT `fk_alineacion_cromo` FOREIGN KEY (`id_cromo`) REFERENCES `cromos` (`id_cromo`),
  ADD CONSTRAINT `fk_alineacion_duelo` FOREIGN KEY (`id_duelo`) REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alineacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `duelo_apuestas`
--
ALTER TABLE `duelo_apuestas`
  ADD CONSTRAINT `fk_apuestas_coleccion` FOREIGN KEY (`id_coleccion`) REFERENCES `coleccion` (`id_coleccion`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_apuestas_duelo` FOREIGN KEY (`id_duelo`) REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_apuestas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `duelo_aumentos`
--
ALTER TABLE `duelo_aumentos`
  ADD CONSTRAINT `fk_aumentos_duelo` FOREIGN KEY (`id_duelo`) REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aumentos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `duelo_compos`
--
ALTER TABLE `duelo_compos`
  ADD CONSTRAINT `fk_dc_duelo` FOREIGN KEY (`id_duelo`) REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dc_rasgo` FOREIGN KEY (`id_rasgo`) REFERENCES `rasgos` (`id_rasgo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `duelo_penaltis`
--
ALTER TABLE `duelo_penaltis`
  ADD CONSTRAINT `fk_penaltis_duelo` FOREIGN KEY (`id_duelo`) REFERENCES `duelos` (`id_duelo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `formaciones_usuario`
--
ALTER TABLE `formaciones_usuario`
  ADD CONSTRAINT `fk_formacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mazos`
--
ALTER TABLE `mazos`
  ADD CONSTRAINT `fk_mazos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mazo_cartas`
--
ALTER TABLE `mazo_cartas`
  ADD CONSTRAINT `fk_mazocartas_coleccion` FOREIGN KEY (`id_coleccion`) REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mazocartas_mazo` FOREIGN KEY (`id_mazo`) REFERENCES `mazos` (`id_mazo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mercado`
--
ALTER TABLE `mercado`
  ADD CONSTRAINT `fk_mercado_coleccion` FOREIGN KEY (`id_coleccion`) REFERENCES `coleccion` (`id_coleccion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `minijuegos_partidas`
--
ALTER TABLE `minijuegos_partidas`
  ADD CONSTRAINT `fk_partidas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `misiones_progreso`
--
ALTER TABLE `misiones_progreso`
  ADD CONSTRAINT `fk_progreso_mision` FOREIGN KEY (`id_mision`) REFERENCES `misiones` (`id_mision`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_progreso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sobre`
--
ALTER TABLE `sobre`
  ADD CONSTRAINT `fk_sobre_expansion` FOREIGN KEY (`id_expansion`) REFERENCES `expansiones` (`id_expansion`);

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `dar_monedas_cada_minuto` ON SCHEDULE EVERY 1 MINUTE STARTS '2026-08-03 16:08:16' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    UPDATE usuarios
    SET monedas = monedas + 100
    WHERE monedas < 1000;
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
