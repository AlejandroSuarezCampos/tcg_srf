-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-07-2026 a las 13:36:01
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

--
-- Volcado de datos para la tabla `coleccion`
--

INSERT INTO `coleccion` (`id_coleccion`, `id_usuario`, `id_cromo`, `obtenida`, `bloqueada`) VALUES
(1, 2, 22, '2026-07-22 09:06:55', 0),
(2, 2, 15, '2026-07-22 09:06:55', 0),
(3, 2, 24, '2026-07-22 09:06:55', 0),
(4, 2, 15, '2026-07-22 09:06:55', 0),
(5, 2, 21, '2026-07-22 09:06:55', 0),
(6, 2, 16, '2026-07-22 09:06:59', 0),
(7, 2, 15, '2026-07-22 09:06:59', 0),
(8, 2, 22, '2026-07-22 09:06:59', 0),
(9, 2, 16, '2026-07-22 09:06:59', 0),
(10, 2, 15, '2026-07-22 09:06:59', 0),
(11, 2, 14, '2026-07-22 09:07:04', 0),
(12, 2, 17, '2026-07-22 09:07:04', 0),
(13, 2, 21, '2026-07-22 09:07:04', 0),
(14, 2, 9, '2026-07-22 09:07:04', 0),
(15, 2, 20, '2026-07-22 09:07:04', 0),
(16, 2, 15, '2026-07-22 09:07:07', 0),
(17, 2, 23, '2026-07-22 09:07:07', 0),
(18, 2, 23, '2026-07-22 09:07:07', 0),
(19, 2, 17, '2026-07-22 09:07:07', 0),
(20, 2, 14, '2026-07-22 09:07:07', 0),
(21, 2, 15, '2026-07-22 09:07:11', 0),
(22, 2, 23, '2026-07-22 09:07:11', 0),
(23, 2, 19, '2026-07-22 09:07:11', 0),
(24, 2, 22, '2026-07-22 09:07:11', 0),
(25, 2, 21, '2026-07-22 09:07:11', 0),
(26, 2, 24, '2026-07-22 09:07:14', 0),
(27, 2, 15, '2026-07-22 09:07:14', 0),
(28, 2, 9, '2026-07-22 09:07:14', 0),
(29, 2, 14, '2026-07-22 09:07:14', 0),
(30, 2, 24, '2026-07-22 09:07:14', 0),
(31, 2, 9, '2026-07-22 09:07:16', 0),
(32, 2, 15, '2026-07-22 09:07:16', 0),
(33, 2, 14, '2026-07-22 09:07:16', 0),
(34, 2, 20, '2026-07-22 09:07:16', 0),
(35, 2, 16, '2026-07-22 09:07:16', 0),
(36, 2, 14, '2026-07-22 09:07:20', 0),
(37, 2, 22, '2026-07-22 09:07:20', 0),
(38, 2, 21, '2026-07-22 09:07:20', 0),
(39, 2, 20, '2026-07-22 09:07:20', 0),
(40, 2, 9, '2026-07-22 09:07:20', 0),
(41, 2, 24, '2026-07-22 09:07:22', 0),
(42, 2, 15, '2026-07-22 09:07:22', 0),
(43, 2, 14, '2026-07-22 09:07:22', 0),
(44, 2, 17, '2026-07-22 09:07:22', 0),
(45, 2, 17, '2026-07-22 09:07:22', 0),
(46, 2, 19, '2026-07-22 09:07:25', 0),
(47, 2, 14, '2026-07-22 09:07:25', 0),
(48, 2, 15, '2026-07-22 09:07:25', 0),
(49, 2, 19, '2026-07-22 09:07:25', 0),
(50, 2, 21, '2026-07-22 09:07:25', 0),
(51, 2, 15, '2026-07-22 09:11:49', 0),
(52, 2, 23, '2026-07-22 09:11:49', 0),
(53, 1, 22, '2026-07-22 09:19:37', 0),
(54, 2, 21, '2026-07-22 09:11:49', 0),
(55, 2, 19, '2026-07-22 09:11:49', 0),
(56, 2, 19, '2026-07-22 09:11:51', 0),
(57, 2, 11, '2026-07-22 09:11:51', 0),
(58, 2, 21, '2026-07-22 09:11:51', 0),
(59, 2, 21, '2026-07-22 09:11:51', 0),
(60, 2, 16, '2026-07-22 09:11:51', 0),
(61, 2, 14, '2026-07-22 09:11:54', 0),
(62, 2, 21, '2026-07-22 09:11:54', 0),
(63, 2, 22, '2026-07-22 09:11:54', 0),
(64, 2, 22, '2026-07-22 09:11:54', 0),
(65, 2, 20, '2026-07-22 09:11:54', 0),
(66, 2, 16, '2026-07-22 09:11:56', 0),
(67, 2, 21, '2026-07-22 09:11:56', 0),
(68, 2, 23, '2026-07-22 09:11:56', 0),
(69, 2, 19, '2026-07-22 09:11:56', 0),
(70, 2, 23, '2026-07-22 09:11:56', 0),
(71, 2, 19, '2026-07-22 09:11:57', 0),
(72, 2, 22, '2026-07-22 09:11:57', 0),
(73, 2, 18, '2026-07-22 09:11:57', 0),
(74, 2, 17, '2026-07-22 09:11:57', 0),
(75, 2, 19, '2026-07-22 09:11:57', 0),
(76, 2, 16, '2026-07-22 09:11:59', 0),
(77, 2, 11, '2026-07-22 09:11:59', 0),
(78, 2, 14, '2026-07-22 09:11:59', 0),
(79, 2, 23, '2026-07-22 09:11:59', 0),
(80, 2, 23, '2026-07-22 09:11:59', 0),
(81, 2, 18, '2026-07-22 09:12:01', 0),
(82, 2, 21, '2026-07-22 09:12:01', 0),
(83, 2, 19, '2026-07-22 09:12:01', 0),
(84, 2, 19, '2026-07-22 09:12:01', 0),
(85, 2, 14, '2026-07-22 09:12:01', 0),
(86, 2, 23, '2026-07-22 09:12:04', 0),
(87, 2, 21, '2026-07-22 09:12:04', 0),
(88, 2, 21, '2026-07-22 09:12:04', 0),
(89, 2, 23, '2026-07-22 09:12:04', 0),
(90, 2, 14, '2026-07-22 09:12:04', 0),
(91, 2, 16, '2026-07-22 09:12:06', 0),
(92, 2, 19, '2026-07-22 09:12:06', 0),
(93, 2, 22, '2026-07-22 09:12:06', 0),
(94, 2, 23, '2026-07-22 09:12:06', 0),
(95, 2, 22, '2026-07-22 09:12:06', 0),
(96, 2, 21, '2026-07-22 09:12:08', 0),
(97, 2, 17, '2026-07-22 09:12:08', 0),
(98, 2, 24, '2026-07-22 09:12:08', 0),
(99, 2, 23, '2026-07-22 09:12:08', 0),
(100, 2, 24, '2026-07-22 09:12:08', 0),
(101, 2, 22, '2026-07-22 09:12:09', 0),
(102, 2, 19, '2026-07-22 09:12:09', 0),
(103, 2, 22, '2026-07-22 09:12:09', 0),
(104, 2, 20, '2026-07-22 09:12:09', 0),
(105, 2, 21, '2026-07-22 09:12:09', 0),
(106, 2, 19, '2026-07-22 09:12:11', 0),
(107, 2, 19, '2026-07-22 09:12:11', 0),
(108, 2, 12, '2026-07-22 09:12:11', 0),
(109, 2, 16, '2026-07-22 09:12:11', 0),
(110, 2, 16, '2026-07-22 09:12:11', 0),
(111, 2, 12, '2026-07-22 09:12:13', 0),
(112, 2, 17, '2026-07-22 09:12:13', 0),
(113, 2, 24, '2026-07-22 09:12:13', 0),
(114, 2, 14, '2026-07-22 09:12:13', 0),
(115, 2, 19, '2026-07-22 09:12:13', 0),
(116, 2, 15, '2026-07-22 09:12:15', 0),
(117, 2, 16, '2026-07-22 09:12:15', 0),
(118, 2, 16, '2026-07-22 09:12:15', 0),
(119, 2, 9, '2026-07-22 09:12:15', 0),
(120, 2, 12, '2026-07-22 09:12:15', 0),
(121, 2, 22, '2026-07-22 09:12:17', 0),
(122, 2, 19, '2026-07-22 09:12:17', 0),
(123, 2, 19, '2026-07-22 09:12:17', 0),
(124, 2, 21, '2026-07-22 09:12:17', 0),
(125, 2, 14, '2026-07-22 09:12:17', 0),
(126, 2, 23, '2026-07-22 09:12:19', 0),
(127, 2, 22, '2026-07-22 09:12:19', 0),
(128, 2, 24, '2026-07-22 09:12:19', 0),
(129, 2, 19, '2026-07-22 09:12:19', 0),
(130, 2, 19, '2026-07-22 09:12:19', 0),
(131, 2, 16, '2026-07-22 09:12:20', 0),
(132, 2, 23, '2026-07-22 09:12:20', 0),
(133, 2, 23, '2026-07-22 09:12:20', 0),
(134, 2, 16, '2026-07-22 09:12:20', 0),
(135, 2, 16, '2026-07-22 09:12:20', 0),
(136, 2, 22, '2026-07-22 09:12:22', 0),
(137, 2, 24, '2026-07-22 09:12:22', 0),
(138, 2, 24, '2026-07-22 09:12:22', 0),
(139, 2, 7, '2026-07-22 09:12:22', 1),
(140, 2, 22, '2026-07-22 09:12:22', 0),
(141, 2, 15, '2026-07-22 09:13:51', 0),
(142, 2, 17, '2026-07-22 09:13:51', 0),
(143, 2, 21, '2026-07-22 09:13:51', 0),
(144, 2, 23, '2026-07-22 09:13:51', 0),
(145, 2, 21, '2026-07-22 09:13:51', 0),
(146, 2, 22, '2026-07-22 09:13:55', 0),
(147, 2, 22, '2026-07-22 09:13:55', 0),
(148, 2, 14, '2026-07-22 09:13:55', 0),
(149, 2, 19, '2026-07-22 09:13:55', 0),
(150, 2, 18, '2026-07-22 09:13:55', 0),
(151, 2, 19, '2026-07-22 09:54:35', 0),
(152, 2, 18, '2026-07-22 09:54:35', 0),
(153, 2, 16, '2026-07-22 09:54:35', 0),
(154, 2, 17, '2026-07-22 09:54:35', 0),
(155, 2, 19, '2026-07-22 09:54:35', 0),
(156, 2, 16, '2026-07-22 09:54:40', 0),
(157, 2, 21, '2026-07-22 09:54:40', 0),
(158, 2, 16, '2026-07-22 09:54:40', 0),
(159, 2, 22, '2026-07-22 09:54:40', 0),
(160, 2, 21, '2026-07-22 09:54:40', 0),
(161, 2, 15, '2026-07-22 09:54:40', 0),
(162, 2, 23, '2026-07-22 09:54:40', 0),
(163, 2, 21, '2026-07-22 09:54:40', 0),
(164, 2, 22, '2026-07-22 09:54:40', 0),
(165, 2, 21, '2026-07-22 09:54:40', 0),
(166, 2, 34, '2026-07-22 10:08:29', 0),
(167, 2, 17, '2026-07-22 10:08:29', 0),
(168, 2, 33, '2026-07-22 10:08:29', 0),
(169, 2, 18, '2026-07-22 10:08:29', 0),
(170, 2, 33, '2026-07-22 10:08:29', 0),
(171, 2, 22, '2026-07-22 10:08:29', 0),
(172, 2, 15, '2026-07-22 10:08:29', 0),
(173, 2, 35, '2026-07-22 10:08:29', 0),
(174, 2, 33, '2026-07-22 10:08:29', 0),
(175, 2, 22, '2026-07-22 10:08:29', 0),
(176, 2, 36, '2026-07-22 10:08:38', 0),
(177, 2, 15, '2026-07-22 10:08:38', 0),
(178, 2, 30, '2026-07-22 10:08:38', 0),
(179, 2, 36, '2026-07-22 10:08:38', 0),
(180, 2, 34, '2026-07-22 10:08:38', 0),
(181, 2, 22, '2026-07-22 10:08:38', 0),
(182, 2, 15, '2026-07-22 10:08:38', 0),
(183, 2, 35, '2026-07-22 10:08:38', 0),
(184, 2, 21, '2026-07-22 10:08:38', 0),
(185, 2, 33, '2026-07-22 10:08:38', 0),
(186, 2, 36, '2026-07-22 10:10:05', 0),
(187, 2, 22, '2026-07-22 10:10:05', 0),
(188, 2, 38, '2026-07-22 10:10:05', 0),
(189, 2, 39, '2026-07-22 10:10:05', 0),
(190, 2, 21, '2026-07-22 10:10:05', 0);

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
(28, 'Xokas', 'ENT', 'Entrenador y Streamer del Zeus', './assets/img/Cromos/Base Set/Instituto Zeus/xokas.png', 3, 13, 5, 3),
(29, 'Symeon Ayp', 'DC', '', '', 3, 6, 4, 1),
(30, 'Gaiel', 'MC', '', '', 3, 6, 3, 3),
(31, 'Kyon Canis', 'MC', '', '', 3, 6, 1, 1),
(32, 'Yuma Nosaka', 'MC', '', '', 3, 6, 4, 2),
(33, 'Pocus Sesame', 'MC', '', '', 3, 6, 1, 4),
(34, 'Ashley Sarpala', 'MC', '', '', 3, 6, 1, 4),
(35, 'Ichabod Stark', 'MC', '', '', 3, 6, 2, 1),
(36, 'In Chikita', 'DF', '', '', 3, 6, 1, 2),
(37, 'Bombasta Flamboyanzi', 'DF', '', '', 3, 6, 2, 2),
(38, 'Nick Woodgate', 'DF', '', '', 3, 6, 1, 3),
(39, 'Serina Shelby', 'POR', '', '', 3, 6, 2, 3),
(40, 'Nora Flexion', 'DC', '', '', 3, 6, 1, 4),
(41, 'Silvain Hache', 'DC', '', '', 3, 6, 3, 3),
(42, 'Strem Goozer', 'POR', '', '', 3, 6, 2, 2),
(43, 'Bala Gasgula', 'DC', '', '', 3, 6, 3, 2),
(44, 'Zed Kyu', 'MC', '', '', 3, 6, 4, 3),
(45, 'Escudo Academia Payilunio', 'ESCUDO', '', './assets/img/Cromos/Base Set/Academia Payilunio/Escudo.png', 3, 6, 5, 5),
(46, 'Gyan Cinquedea', 'ENT', '', './assets/img/Cromos/Base Set/Academia Payilunio/Cinquedea.png', 3, 6, 5, 2),
(47, 'Mila Simmering', 'GER', '', './assets/img/Cromos/Base Set/Academia Payilunio/Mila.png', 3, 6, 5, 1);

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

--
-- Volcado de datos para la tabla `mercado`
--

INSERT INTO `mercado` (`id_anuncio`, `id_coleccion`, `precio`, `fecha_publicacion`, `activa`) VALUES
(1, 53, 100, '2026-07-22 09:18:07', 0),
(2, 57, 300, '2026-07-22 09:20:31', 1);

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
(2, 3, 'Sobre Básico', 5, 25, './assets/img/Sobres/base_set_x10.png', 1);

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
(2, 'LuluLulez', '$2y$10$.D1gTDVfqcghmRlCRdwGTu1Yq12sOW9M9KtQ/1I53I6KeRYOBzm.C', 98900, 1, '2026-07-21 15:32:54', './assets/img/perfil/apple-icon-120x120.png'),
(3, 'Payo Water', '$2y$10$Czkfj7624fF1IWqvqE/Qq.70y.aM7kM2VzGDcbmdgQa3zf1LM/xRe', 500, 0, '2026-07-22 09:19:06', './assets/img/perfil/apple-icon-120x120.png'),
(4, 'Prueba1', '$2y$10$94UVoMMlhe10ZzqR1Apt5OkqkmEdzXP6qhODkm68XkTBleCHtQpIm', 100000, 1, '2026-07-22 12:01:22', './assets/img/perfil/apple-icon-120x120.png'),
(5, 'Prueba2', '$2y$10$N36PYPCuZ4TwlB3L9d23G.A6lhWeNajIGCtqy6lef2vNBZDNTnpI6', 100000, 0, '2026-07-22 12:01:42', './assets/img/perfil/apple-icon-120x120.png'),
(7, 'Prueba3', '$2y$10$ZBhiS8mxITHzpoOxOeCsl.rKm/ETWOBGtrHflZxlThhbQtRXtvtoC', 100000, 1, '2026-07-22 12:02:30', './assets/img/perfil/apple-icon-120x120.png');

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
-- AUTO_INCREMENT de la tabla `coleccion`
--
ALTER TABLE `coleccion`
  MODIFY `id_coleccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- AUTO_INCREMENT de la tabla `cromos`
--
ALTER TABLE `cromos`
  MODIFY `id_cromo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

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
  MODIFY `id_anuncio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `rarezas`
--
ALTER TABLE `rarezas`
  MODIFY `id_rareza` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `sobre`
--
ALTER TABLE `sobre`
  MODIFY `id_sobre` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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

--
-- Filtros para la tabla `sobre`
--
ALTER TABLE `sobre`
  ADD CONSTRAINT `fk_sobre_expansion` FOREIGN KEY (`id_expansion`) REFERENCES `expansiones` (`id_expansion`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
