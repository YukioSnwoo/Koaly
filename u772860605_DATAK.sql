-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 05-09-2026 a las 23:16:12
-- Versión del servidor: 11.8.8-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u772860605_DATAK`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Categorias`
--

CREATE TABLE `Categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Categorias`
--

INSERT INTO `Categorias` (`id_categoria`, `nombre_categoria`) VALUES
(1, 'Electronica'),
(2, 'Hogar'),
(3, 'Ropa');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Detalle_Venta`
--

CREATE TABLE `Detalle_Venta` (
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL CHECK (`cantidad` > 0),
  `precio_unitario` decimal(10,2) NOT NULL CHECK (`precio_unitario` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Inventario_Sucursal`
--

CREATE TABLE `Inventario_Sucursal` (
  `id_sucursal` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad_disponible` int(11) NOT NULL DEFAULT 0 CHECK (`cantidad_disponible` >= 0),
  `precio_venta` decimal(10,2) NOT NULL CHECK (`precio_venta` >= 0),
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `modificado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Inventario_Sucursal`
--

INSERT INTO `Inventario_Sucursal` (`id_sucursal`, `id_producto`, `cantidad_disponible`, `precio_venta`, `creado_en`, `modificado_en`) VALUES
(1, 1, 20, 1200.00, '2026-09-03 02:58:21', '2026-09-03 02:58:21'),
(1, 2, 50, 25.00, '2026-09-03 02:58:21', '2026-09-03 02:58:21'),
(1, 3, 30, 45.00, '2026-09-03 02:58:21', '2026-09-03 02:58:21'),
(2, 1, 15, 1150.00, '2026-09-03 02:58:21', '2026-09-03 02:58:21'),
(2, 2, 40, 27.00, '2026-09-03 02:58:21', '2026-09-03 02:58:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Metodos_Pago`
--

CREATE TABLE `Metodos_Pago` (
  `id_metodo_pago` int(11) NOT NULL,
  `nombre_metodo` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Metodos_Pago`
--

INSERT INTO `Metodos_Pago` (`id_metodo_pago`, `nombre_metodo`) VALUES
(1, 'Efectivo'),
(2, 'Tarjeta Credito'),
(3, 'Transferencia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Movimientos_Inventario`
--

CREATE TABLE `Movimientos_Inventario` (
  `id_movimiento` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `tipo_movimiento` enum('VENTA','COMPRA_PROVEEDOR','AJUSTE_MERMA','AJUSTE_ROBO','DEVOLUCION_CLIENTE','TRASLADO') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `existencia_posterior` int(11) NOT NULL CHECK (`existencia_posterior` >= 0),
  `referencia_id` int(11) DEFAULT NULL,
  `motivo_detalle` text DEFAULT NULL,
  `realizado_por` int(11) NOT NULL,
  `fecha_hora` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Productos`
--

CREATE TABLE `Productos` (
  `id_producto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_categoria` int(11) NOT NULL,
  `estado` enum('Activo','Descontinuado','Proximamente') DEFAULT 'Activo',
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `modificado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Productos`
--

INSERT INTO `Productos` (`id_producto`, `nombre`, `descripcion`, `id_categoria`, `estado`, `creado_en`, `modificado_en`) VALUES
(1, 'Laptop Gamer', '16GB RAM, 1TB SSD, RTX 4060', 1, 'Activo', '2026-09-03 02:58:21', '2026-09-03 02:58:21'),
(2, 'Mouse Inalambrico', 'Logitech MX Master 3', 1, 'Activo', '2026-09-03 02:58:21', '2026-09-03 02:58:21'),
(3, 'Sarten Antiadherente', '26 cm, ceramica', 2, 'Activo', '2026-09-03 02:58:21', '2026-09-03 02:58:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Roles`
--

CREATE TABLE `Roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Roles`
--

INSERT INTO `Roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'Administrador'),
(3, 'Cajero'),
(2, 'Gerente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Sucursales`
--

CREATE TABLE `Sucursales` (
  `id_sucursal` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email_contacto` varchar(100) DEFAULT NULL,
  `estado` enum('Activa','Mantenimiento','Cierre_Temporal','Cierre_Definitivo') DEFAULT 'Activa',
  `fecha_inicio_estado` date NOT NULL,
  `fecha_fin_estado` date DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `modificado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Sucursales`
--

INSERT INTO `Sucursales` (`id_sucursal`, `nombre`, `direccion`, `telefono`, `email_contacto`, `estado`, `fecha_inicio_estado`, `fecha_fin_estado`, `creado_en`, `modificado_en`) VALUES
(1, 'Sucursal Centro', 'Av. Juarez 123, CDMX', '55-1234-5678', 'centro@tienda.com', 'Activa', '2026-09-03', NULL, '2026-09-03 02:58:21', '2026-09-03 02:58:21'),
(2, 'Sucursal Norte', 'Blvd. Norte 456, GDL', '33-9876-5432', 'norte@tienda.com', 'Activa', '2026-09-03', NULL, '2026-09-03 02:58:21', '2026-09-03 02:58:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Usuarios`
--

CREATE TABLE `Usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `estado` enum('Activo','Vacaciones','Incapacidad','Licencia','Baja') DEFAULT 'Activo',
  `fecha_inicio_estado` date NOT NULL,
  `fecha_fin_estado` date DEFAULT NULL,
  `fecha_contratacion` date NOT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `modificado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Usuarios`
--

INSERT INTO `Usuarios` (`id_usuario`, `nombre`, `email`, `contrasena_hash`, `id_rol`, `id_sucursal`, `estado`, `fecha_inicio_estado`, `fecha_fin_estado`, `fecha_contratacion`, `creado_en`, `modificado_en`) VALUES
(1, 'Angel Moises Guzman Solis', 'angelmoises549@gmail.com', '12345678', 1, NULL, 'Activo', '2026-09-03', NULL, '2026-09-03', '2026-09-03 02:58:21', '2026-09-05 02:18:56'),
(2, 'Erick_NG', 'core.armored@tienda.com', '$2y$10$3MJiGv7mS3z9/wX2doheEumjHN5k.H2KiwfXs2EZzSbcEdPDgADZS', 2, 1, 'Activo', '2026-09-03', NULL, '2026-09-03', '2026-09-03 02:58:21', '2026-09-05 02:15:35'),
(3, 'Gerente Norte', 'gerente.norte@tienda.com', '$2y$10$Wt1xfPO4s39rY3LVBFD3VOJoK67pCJQ.UrJEjDohZd.03vfBUkdOW', 2, 2, 'Activo', '2026-09-03', NULL, '2026-09-03', '2026-09-03 02:58:21', '2026-09-05 02:15:35'),
(4, 'Cajero Centro 1', 'cajero1.centro@tienda.com', '$2y$10$nZs1VWcrJcykqBIYjc4DEOaRctgKatXFTuwa8ruaKv.bpN2u0Z.OC', 3, 1, 'Activo', '2026-09-03', NULL, '2026-09-03', '2026-09-03 02:58:21', '2026-09-05 02:15:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ventas`
--

CREATE TABLE `Ventas` (
  `id_venta` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_cajero` int(11) NOT NULL,
  `id_metodo_pago` int(11) NOT NULL,
  `fecha_hora` timestamp NULL DEFAULT current_timestamp(),
  `subtotal` decimal(10,2) NOT NULL CHECK (`subtotal` >= 0),
  `total` decimal(10,2) NOT NULL CHECK (`total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `Categorias`
--
ALTER TABLE `Categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre_categoria` (`nombre_categoria`);

--
-- Indices de la tabla `Detalle_Venta`
--
ALTER TABLE `Detalle_Venta`
  ADD PRIMARY KEY (`id_venta`,`id_producto`),
  ADD KEY `idx_detalle_venta_producto` (`id_producto`);

--
-- Indices de la tabla `Inventario_Sucursal`
--
ALTER TABLE `Inventario_Sucursal`
  ADD PRIMARY KEY (`id_sucursal`,`id_producto`),
  ADD KEY `idx_inventario_sucursal` (`id_sucursal`),
  ADD KEY `idx_inventario_producto` (`id_producto`);

--
-- Indices de la tabla `Metodos_Pago`
--
ALTER TABLE `Metodos_Pago`
  ADD PRIMARY KEY (`id_metodo_pago`),
  ADD UNIQUE KEY `nombre_metodo` (`nombre_metodo`);

--
-- Indices de la tabla `Movimientos_Inventario`
--
ALTER TABLE `Movimientos_Inventario`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `realizado_por` (`realizado_por`),
  ADD KEY `idx_movimientos_fecha` (`fecha_hora`),
  ADD KEY `idx_movimientos_sucursal_producto` (`id_sucursal`,`id_producto`);

--
-- Indices de la tabla `Productos`
--
ALTER TABLE `Productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD KEY `idx_productos_estado` (`estado`),
  ADD KEY `idx_productos_categoria` (`id_categoria`);

--
-- Indices de la tabla `Roles`
--
ALTER TABLE `Roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `Sucursales`
--
ALTER TABLE `Sucursales`
  ADD PRIMARY KEY (`id_sucursal`);

--
-- Indices de la tabla `Usuarios`
--
ALTER TABLE `Usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `id_rol` (`id_rol`),
  ADD KEY `idx_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_estado` (`estado`),
  ADD KEY `idx_usuarios_sucursal` (`id_sucursal`);

--
-- Indices de la tabla `Ventas`
--
ALTER TABLE `Ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `id_metodo_pago` (`id_metodo_pago`),
  ADD KEY `idx_ventas_fecha` (`fecha_hora`),
  ADD KEY `idx_ventas_sucursal` (`id_sucursal`),
  ADD KEY `idx_ventas_cajero` (`id_cajero`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `Categorias`
--
ALTER TABLE `Categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `Metodos_Pago`
--
ALTER TABLE `Metodos_Pago`
  MODIFY `id_metodo_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `Movimientos_Inventario`
--
ALTER TABLE `Movimientos_Inventario`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Productos`
--
ALTER TABLE `Productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `Roles`
--
ALTER TABLE `Roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `Sucursales`
--
ALTER TABLE `Sucursales`
  MODIFY `id_sucursal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `Usuarios`
--
ALTER TABLE `Usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `Ventas`
--
ALTER TABLE `Ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `Detalle_Venta`
--
ALTER TABLE `Detalle_Venta`
  ADD CONSTRAINT `Detalle_Venta_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `Ventas` (`id_venta`) ON DELETE CASCADE,
  ADD CONSTRAINT `Detalle_Venta_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `Productos` (`id_producto`);

--
-- Filtros para la tabla `Inventario_Sucursal`
--
ALTER TABLE `Inventario_Sucursal`
  ADD CONSTRAINT `Inventario_Sucursal_ibfk_1` FOREIGN KEY (`id_sucursal`) REFERENCES `Sucursales` (`id_sucursal`),
  ADD CONSTRAINT `Inventario_Sucursal_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `Productos` (`id_producto`);

--
-- Filtros para la tabla `Movimientos_Inventario`
--
ALTER TABLE `Movimientos_Inventario`
  ADD CONSTRAINT `Movimientos_Inventario_ibfk_1` FOREIGN KEY (`id_sucursal`,`id_producto`) REFERENCES `Inventario_Sucursal` (`id_sucursal`, `id_producto`),
  ADD CONSTRAINT `Movimientos_Inventario_ibfk_2` FOREIGN KEY (`realizado_por`) REFERENCES `Usuarios` (`id_usuario`);

--
-- Filtros para la tabla `Productos`
--
ALTER TABLE `Productos`
  ADD CONSTRAINT `Productos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `Categorias` (`id_categoria`);

--
-- Filtros para la tabla `Usuarios`
--
ALTER TABLE `Usuarios`
  ADD CONSTRAINT `Usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `Roles` (`id_rol`),
  ADD CONSTRAINT `Usuarios_ibfk_2` FOREIGN KEY (`id_sucursal`) REFERENCES `Sucursales` (`id_sucursal`);

--
-- Filtros para la tabla `Ventas`
--
ALTER TABLE `Ventas`
  ADD CONSTRAINT `Ventas_ibfk_1` FOREIGN KEY (`id_sucursal`) REFERENCES `Sucursales` (`id_sucursal`),
  ADD CONSTRAINT `Ventas_ibfk_2` FOREIGN KEY (`id_cajero`) REFERENCES `Usuarios` (`id_usuario`),
  ADD CONSTRAINT `Ventas_ibfk_3` FOREIGN KEY (`id_metodo_pago`) REFERENCES `Metodos_Pago` (`id_metodo_pago`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
