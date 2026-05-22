-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-04-2026 a las 21:46:00
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
-- Base de datos: `sildemar`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id_auditoria` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(255) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id_auditoria`, `id_usuario`, `accion`, `fecha`) VALUES
(1, 1, 'Gerente del Sistema creó un nuevo usuario', '2026-04-30 19:44:43'),
(2, 2, 'Gerente Operacional actualizó el inventario', '2026-04-30 19:44:43'),
(3, 1, 'Gerente del Sistema eliminó un reporte obsoleto', '2026-04-30 19:44:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria`
--

CREATE TABLE `categoria` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categoria`
--

INSERT INTO `categoria` (`id_categoria`, `nombre_categoria`, `descripcion`) VALUES
(1, 'Motor', NULL),
(2, 'Frenos', NULL),
(3, 'Suspensión', NULL),
(4, 'Eléctrico', NULL),
(5, 'Filtros', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

CREATE TABLE `cliente` (
  `id_cliente` int(11) NOT NULL,
  `id_persona` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cliente`
--

INSERT INTO `cliente` (`id_cliente`, `id_persona`) VALUES
(5, 8),
(6, 9),
(7, 10),
(8, 11),
(9, 12);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL,
  `tasa_dolar` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `tasa_dolar`) VALUES
(0, 36.50),
(1, 483.80);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_solicitud`
--

CREATE TABLE `detalle_solicitud` (
  `id_detalle` int(11) NOT NULL,
  `id_solicitud` int(11) DEFAULT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_solicitud`
--

INSERT INTO `detalle_solicitud` (`id_detalle`, `id_solicitud`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(7, 5, 5, 1, 32.00, 32.00),
(8, 6, 3, 1, 6.75, 6.75),
(9, 7, 2, 1, 120.00, 120.00),
(10, 8, 3, 1, 6.75, 6.75),
(11, 9, 3, 1, 6.75, 6.75),
(12, 10, 3, 1, 6.75, 6.75),
(13, 11, 2, 1, 120.00, 120.00),
(14, 12, 3, 1, 6.75, 6.75),
(15, 13, 2, 1, 120.00, 120.00),
(16, 14, 2, 1, 120.00, 120.00),
(17, 15, 2, 1, 120.00, 120.00),
(18, 16, 2, 1, 120.00, 120.00),
(19, 17, 1, 1, 95.00, 95.00),
(20, 18, 2, 1, 120.00, 120.00),
(21, 19, 2, 1, 120.00, 120.00),
(22, 20, 2, 1, 120.00, 120.00),
(23, 21, 3, 1, 6.75, 6.75),
(24, 22, 2, 1, 120.00, 120.00),
(25, 23, 2, 1, 120.00, 120.00),
(26, 24, 2, 1, 120.00, 120.00),
(27, 25, 3, 1, 6.75, 6.75),
(28, 26, 3, 1, 6.75, 6.75),
(29, 27, 4, 1, 35.00, 35.00),
(30, 28, 4, 1, 35.00, 35.00),
(31, 29, 4, 1, 35.00, 35.00),
(32, 30, 4, 1, 35.00, 35.00);

--
-- Disparadores `detalle_solicitud`
--
DELIMITER $$
CREATE TRIGGER `trg_subtotal_solicitud` BEFORE INSERT ON `detalle_solicitud` FOR EACH ROW BEGIN

    SET NEW.subtotal = NEW.cantidad * NEW.precio_unitario;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_total_solicitud` AFTER INSERT ON `detalle_solicitud` FOR EACH ROW BEGIN

    DECLARE suma_total DECIMAL(10,2);

    

    -- Calcular la suma real de todos los detalles de esta solicitud

    SELECT COALESCE(SUM(subtotal), 0) INTO suma_total 

    FROM detalle_solicitud 

    WHERE id_solicitud = NEW.id_solicitud;

    

    -- Actualizar con la suma real, no acumulando

    UPDATE solicitud 

    SET total = suma_total 

    WHERE id_solicitud = NEW.id_solicitud;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleado`
--

CREATE TABLE `empleado` (
  `id_empleado` int(11) NOT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empleado`
--

INSERT INTO `empleado` (`id_empleado`, `cargo`, `id_usuario`) VALUES
(1, 'Operador', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `persona`
--

CREATE TABLE `persona` (
  `id_persona` int(11) NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `persona`
--

INSERT INTO `persona` (`id_persona`, `cedula`, `nombre`, `direccion`, `telefono`, `correo`) VALUES
(1, '11111111', 'German Figuera', 'Oficina', '04120000001', NULL),
(2, '33333333', 'Santiago Perez', 'Los Teques', '04120000003', NULL),
(3, '12345', 'Pedro Perez', NULL, NULL, NULL),
(8, '4678932', 'Pedro', 'ezequiel zamora', '04128285541', NULL),
(9, 'V30457651', 'Santiago Perez', 'Los Teques', '04142149796', NULL),
(10, 'V30457', 'Lerrins Velasquez', 'Caracas', '04128285541', NULL),
(11, 'V3045', 'Moises Perez', 'Caracas', '04141158980', NULL),
(12, 'V304', 'Santiago Perez', 'Caracas', '04128285541', NULL),
(999, '12345678', 'Gerente Operacional Prueba', 'Local Sildemar', '04140000000', 'gerente_op@sildemar.com');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `id_producto` int(11) NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre_producto` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `compra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_actual` int(11) DEFAULT 0,
  `marca_repuesto` varchar(100) DEFAULT NULL,
  `marca_carro` varchar(100) DEFAULT NULL,
  `modelo_vehiculo` varchar(100) DEFAULT NULL,
  `categoria` varchar(100) DEFAULT 'General',
  `ano` varchar(10) DEFAULT NULL,
  `transmision` varchar(50) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT 'default.jpg',
  `id_categoria` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`id_producto`, `codigo`, `nombre_producto`, `descripcion`, `precio`, `compra`, `stock_actual`, `marca_repuesto`, `marca_carro`, `categoria`, `ano`, `transmision`, `imagen`, `id_categoria`) VALUES
(1, 'PROD-01', 'Amortiguador delantero', NULL, 95.00, 70.00, 23, 'Monroe', 'Toyota', 'General', NULL, NULL, 'amortiguador.jpg', 3),
(2, 'PROD-02', 'Bater├¡a 12V', NULL, 120.00, 85.00, 0, 'Bosch', 'Universal', 'General', NULL, NULL, 'bateria.jpg', 4),
(3, 'PROD-03', 'Filtro de aceite', NULL, 6.75, 4.50, 191, 'ACDelco', 'Universal', 'General', NULL, NULL, 'filtro.jpg', 5),
(4, 'PROD-04', 'Pastillas de freno', NULL, 35.00, 22.00, 56, 'Bosch', 'Ford', 'General', NULL, NULL, 'frenos.jpg', 2),
(5, 'PROD-05', 'Bieleta', NULL, 32.00, 20.00, 10, 'MHW', 'Chevrolet', 'General', NULL, NULL, 'bieleta.jpg', 3),
(6, 'PROD-06', 'Juego de Buj├¡as', NULL, 15.50, 10.00, 40, 'NGK', 'Universal', 'General', NULL, NULL, 'bujia.jpg', 4),
(7, 'PROD-07', 'Kit de Embrague', NULL, 110.00, 75.00, 15, 'Valeo', 'Toyota', 'General', NULL, 'Sincr├│nica', 'embrague.jpg', 1),
(8, 'PROD-08', 'Filtro de Aire', NULL, 8.00, 5.00, 80, 'Wix', 'Chevrolet', 'General', NULL, NULL, 'Filtro-aire.jpg', 5),
(9, 'PROD-09', 'Disco de Freno', NULL, 45.00, 30.00, 20, 'Bosch', 'Ford', 'General', NULL, NULL, 'Disco-freno.jpg', 2),
(10, 'PROD-10', 'Bobina de Encendido', NULL, 30.00, 18.00, 25, 'Delphi', 'Toyota', 'General', NULL, NULL, 'bobina.jpg', 4),
(11, 'PROD-11', 'Bomba de Agua', NULL, 40.00, 25.00, 18, 'ACDelco', 'Chevrolet', 'General', NULL, NULL, 'bomba-gua.jpg', 1),
(12, 'PROD-12', 'Correa de Tiempo', NULL, 22.00, 12.00, 30, 'Gates', 'Ford', 'General', NULL, NULL, 'correa-tiempo.jpg', 1),
(13, 'PROD-13', 'Rotula', NULL, 18.00, 10.00, 50, 'Moog', 'Toyota', 'General', NULL, NULL, 'rotula.jpg', 3),
(14, 'PROD-14', 'Radiador', NULL, 130.00, 90.00, 5, 'Denso', 'Chevrolet', 'General', NULL, 'Autom├ítica', 'radiador.jpg', 1),
(15, 'PROD-15', 'Aceite Sint├®tico 10W-30', NULL, 35.00, 25.00, 100, 'Castrol', 'Universal', 'General', NULL, NULL, 'aceite.jpg', 1),
(16, '', 'Rotula', '{\"marca_repuesto\":\"AYD\",\"marca_vehiculo\":\"Hyundai\",\"transmision\":\"Autom\\u00e1tica\",\"precio_compra\":\"2\",\"codigo\":\"123456\"}', 32.00, 0.00, 234, NULL, NULL, 'General', NULL, NULL, 'Ima/1776956925_alitaa.jpg', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'Gerente del Sistema'),
(2, 'Gerente Operacional'),
(3, 'Empleado'),
(4, 'Cliente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud`
--

CREATE TABLE `solicitud` (
  `id_solicitud` int(11) NOT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('Pendiente','Aprobada','Rechazada','Entregada') DEFAULT 'Pendiente',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_cliente` int(11) DEFAULT NULL,
  `id_vendedor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `solicitud`
--

INSERT INTO `solicitud` (`id_solicitud`, `fecha_solicitud`, `estado`, `total`, `id_cliente`, `id_vendedor`) VALUES
(5, '2026-04-13 16:30:17', 'Rechazada', 32.00, 5, NULL),
(6, '2026-04-23 13:21:18', 'Entregada', 6.75, 6, NULL),
(7, '2026-04-23 13:25:51', 'Pendiente', 120.00, 6, NULL),
(8, '2026-04-23 13:32:03', 'Pendiente', 6.75, 6, NULL),
(9, '2026-04-23 13:38:11', 'Pendiente', 6.75, 6, NULL),
(10, '2026-04-23 13:42:19', 'Pendiente', 6.75, 6, NULL),
(11, '2026-04-28 16:33:47', 'Pendiente', 120.00, 7, NULL),
(12, '2026-04-28 16:37:26', 'Pendiente', 6.75, 8, NULL),
(13, '2026-04-28 17:01:42', 'Pendiente', 120.00, 6, NULL),
(14, '2026-04-28 17:02:01', 'Pendiente', 120.00, 9, NULL),
(15, '2026-04-28 17:05:32', 'Pendiente', 120.00, 6, NULL),
(16, '2026-04-28 17:05:40', 'Pendiente', 120.00, 8, NULL),
(17, '2026-04-28 17:06:10', 'Pendiente', 95.00, 6, NULL),
(18, '2026-04-28 17:10:33', 'Pendiente', 120.00, 6, NULL),
(19, '2026-04-28 17:12:18', 'Pendiente', 120.00, 6, NULL),
(20, '2026-04-28 17:13:41', 'Pendiente', 120.00, 6, NULL),
(21, '2026-04-28 17:14:21', 'Pendiente', 6.75, 6, NULL),
(22, '2026-04-28 17:14:55', 'Pendiente', 120.00, 6, NULL),
(23, '2026-04-28 17:20:27', 'Pendiente', 120.00, 6, NULL),
(24, '2026-04-28 17:28:38', 'Pendiente', 120.00, 6, NULL),
(25, '2026-04-28 17:30:56', 'Pendiente', 6.75, 6, NULL),
(26, '2026-04-28 17:36:19', 'Pendiente', 6.75, 6, NULL),
(27, '2026-04-29 11:58:31', 'Pendiente', 35.00, 6, NULL),
(28, '2026-04-29 11:59:10', 'Pendiente', 35.00, 6, NULL),
(29, '2026-04-29 12:28:20', 'Pendiente', 35.00, 6, NULL),
(30, '2026-04-29 12:29:30', 'Pendiente', 35.00, 6, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `correo` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `codigo_recuperacion` varchar(6) DEFAULT NULL,
  `expiracion_codigo` datetime DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_persona` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `primer_login` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `correo`, `password`, `fecha_registro`, `id_persona`, `id_rol`) VALUES
(1, 'sildemar2010@gmail.com', '$2y$10$W/ZJ5E8bg6nb.wkQllhhxuJ2puqif/gjHnzAwDDZ6ulifl43WFoJe', '2026-04-13 13:53:26', 1, 1),
(2, 'empleado@sildemar.com', '$2y$10$H0CI7LL4WiLX9wMUAhz6QOwmtUqYJ5SNHh5vgaOiMf8Jp3rm5ZPSy', '2026-04-13 13:54:34', 3, 3),
(3, 'gerente_op@sildemar.com', '$2y$10$W/ZJ5E8bg6nb.wkQllhhxuJ2puqif/gjHnzAwDDZ6ulifl43WFoJe', '2026-04-30 18:53:11', 999, 2);

--
-- Estructura de tabla para la tabla `recuperacion_intentos`
--

CREATE TABLE `recuperacion_intentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `correo` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `action` varchar(20) NOT NULL DEFAULT 'generate',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id_auditoria`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `id_persona` (`id_persona`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `detalle_solicitud`
--
ALTER TABLE `detalle_solicitud`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_solicitud` (`id_solicitud`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `empleado`
--
ALTER TABLE `empleado`
  ADD PRIMARY KEY (`id_empleado`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `persona`
--
ALTER TABLE `persona`
  ADD PRIMARY KEY (`id_persona`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `solicitud`
--
ALTER TABLE `solicitud`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `idx_vendedor` (`id_vendedor`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `id_persona` (`id_persona`),
  ADD KEY `fk_usuario_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id_auditoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cliente`
--
ALTER TABLE `cliente`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `detalle_solicitud`
--
ALTER TABLE `detalle_solicitud`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `empleado`
--
ALTER TABLE `empleado`
  MODIFY `id_empleado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `persona`
--
ALTER TABLE `persona`
  MODIFY `id_persona` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `solicitud`
--
ALTER TABLE `solicitud`
  MODIFY `id_solicitud` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD CONSTRAINT `cliente_ibfk_1` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_solicitud`
--
ALTER TABLE `detalle_solicitud`
  ADD CONSTRAINT `detalle_solicitud_ibfk_1` FOREIGN KEY (`id_solicitud`) REFERENCES `solicitud` (`id_solicitud`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_solicitud_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON DELETE CASCADE;

--
-- Filtros para la tabla `empleado`
--
ALTER TABLE `empleado`
  ADD CONSTRAINT `empleado_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `producto`
--
ALTER TABLE `producto`
  ADD CONSTRAINT `producto_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`) ON DELETE SET NULL;

--
-- Filtros para la tabla `solicitud`
--
ALTER TABLE `solicitud`
  ADD CONSTRAINT `solicitud_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
