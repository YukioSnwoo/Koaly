-- Estructura de la base de Koaly (u772860605_DATAK), SIN datos.
-- Sacada del volcado de phpMyAdmin del 23/10/2026 (ya incluye las migraciones
-- 001 a 013). Sirve para consultar tablas, columnas, índices y llaves.
--
-- No trae filas a propósito: el volcado completo tiene correos, hashes de
-- contraseñas e IPs de las personas y este repositorio está en GitHub.
-- El volcado completo se guarda fuera de git (sql/respaldos/ está ignorado).
--
-- Regenerarlo después de cada migración nueva: exportar la base en phpMyAdmin
-- (solo estructura, o completa y quitar los INSERT).

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

-- Base de datos: `u772860605_DATAK`

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `avisos_privacidad`

CREATE TABLE `avisos_privacidad` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `expira_en` timestamp NULL DEFAULT NULL,
  `enviado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `aceptado_en` timestamp NULL DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `agente` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Cajas`

CREATE TABLE `Cajas` (
  `id_caja` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `numero_caja` varchar(20) NOT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `estado` enum('Activa','Inactiva','Mantenimiento') DEFAULT 'Activa',
  `id_cajero_asignado` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `modificado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fondo_fijo` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `limite_efectivo` decimal(10,2) NOT NULL DEFAULT 5000.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Categorias`

CREATE TABLE `Categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Detalle_Venta`

CREATE TABLE `Detalle_Venta` (
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL CHECK (`cantidad` > 0),
  `precio_unitario` decimal(10,2) NOT NULL CHECK (`precio_unitario` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Historial_Estado_Gerente`

CREATE TABLE `Historial_Estado_Gerente` (
  `id_historial` int(11) NOT NULL,
  `id_gerente` int(11) NOT NULL,
  `estado_anterior` varchar(30) DEFAULT NULL,
  `estado_nuevo` varchar(30) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `fecha_cambio` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Inventario_Sucursal`

CREATE TABLE `Inventario_Sucursal` (
  `id_sucursal` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `Categoria_Producto` varchar(50) NOT NULL,
  `cantidad_disponible` int(11) NOT NULL DEFAULT 0 CHECK (`cantidad_disponible` >= 0),
  `precio_venta` decimal(10,2) NOT NULL CHECK (`precio_venta` >= 0),
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `modificado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_intentos`

CREATE TABLE `login_intentos` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Metodos_Pago`

CREATE TABLE `Metodos_Pago` (
  `id_metodo_pago` int(11) NOT NULL,
  `nombre_metodo` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Movimientos_Inventario`

CREATE TABLE `Movimientos_Inventario` (
  `id_movimiento` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `tipo_movimiento` enum('VENTA','COMPRA_PROVEEDOR','AJUSTE_MERMA','AJUSTE_ROBO','DEVOLUCION_CLIENTE','TRASLADO','AJUSTE_MANUAL') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `existencia_posterior` int(11) NOT NULL CHECK (`existencia_posterior` >= 0),
  `referencia_id` int(11) DEFAULT NULL,
  `motivo_detalle` text DEFAULT NULL,
  `realizado_por` int(11) NOT NULL,
  `fecha_hora` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `intentos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Productos`

CREATE TABLE `Productos` (
  `id_producto` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `imagen` varchar(255) DEFAULT NULL,
  `id_categoria` int(11) NOT NULL,
  `estado` enum('Activo','Descontinuado','Proximamente') DEFAULT 'Activo',
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `modificado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Retiros_Caja`

CREATE TABLE `Retiros_Caja` (
  `id_retiro` int(11) NOT NULL,
  `id_turno` int(11) NOT NULL,
  `id_cajero` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `desglose` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`desglose`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Roles`

CREATE TABLE `Roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Solicitudes_Productos`

CREATE TABLE `Solicitudes_Productos` (
  `id_solicitud` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre_producto` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `precio_sugerido` decimal(10,2) DEFAULT NULL,
  `proveedor_sugerido` varchar(100) DEFAULT NULL,
  `justificacion` text DEFAULT NULL,
  `estado` enum('Pendiente','Devuelta','Aprobada','Rechazada','Cancelada') NOT NULL DEFAULT 'Pendiente',
  `motivo_rechazo` text DEFAULT NULL,
  `id_producto_creado` int(11) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `resuelto_en` timestamp NULL DEFAULT NULL,
  `resuelto_por` int(11) DEFAULT NULL,
  `tipo` enum('Agregar','Eliminar') NOT NULL DEFAULT 'Agregar',
  `id_producto` int(11) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `proveedor_empresa` varchar(100) DEFAULT NULL,
  `proveedor_telefono` varchar(20) DEFAULT NULL,
  `proveedor_correo` varchar(150) DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Solicitudes_Productos_Historial`

CREATE TABLE `Solicitudes_Productos_Historial` (
  `id_historial` int(11) NOT NULL,
  `id_solicitud` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `rol` enum('Gerente','Admin') NOT NULL,
  `accion` enum('Enviada','Editada','Aprobada','Rechazada','Cancelada') NOT NULL,
  `cambios` text DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Sucursales`

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Turnos_Caja`

CREATE TABLE `Turnos_Caja` (
  `id_turno` int(11) NOT NULL,
  `id_caja` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `estado` enum('Abierto','Cerrado') NOT NULL DEFAULT 'Abierto',
  `id_cajero_apertura` int(11) NOT NULL,
  `fecha_apertura` datetime NOT NULL,
  `efectivo_anterior` decimal(10,2) DEFAULT NULL,
  `efectivo_apertura` decimal(10,2) NOT NULL,
  `desglose_apertura` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`desglose_apertura`)),
  `diferencia_apertura` decimal(10,2) DEFAULT NULL,
  `id_cajero_cierre` int(11) DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `num_ventas` int(11) DEFAULT NULL,
  `teorico_efectivo` decimal(10,2) DEFAULT NULL,
  `teorico_tarjeta` decimal(10,2) DEFAULT NULL,
  `retiros_parciales` decimal(10,2) DEFAULT NULL,
  `desglose_cierre` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`desglose_cierre`)),
  `efectivo_contado` decimal(10,2) DEFAULT NULL,
  `vouchers` decimal(10,2) DEFAULT NULL,
  `efectivo_esperado` decimal(10,2) DEFAULT NULL,
  `diferencia_efectivo` decimal(10,2) DEFAULT NULL,
  `diferencia_tarjeta` decimal(10,2) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT NULL,
  `resultado` enum('Cuadrada','Faltante','Sobrante') DEFAULT NULL,
  `requiere_revision` tinyint(1) NOT NULL DEFAULT 0,
  `efectivo_dejado` decimal(10,2) DEFAULT NULL,
  `efectivo_retirado` decimal(10,2) DEFAULT NULL,
  `estado_revision` enum('Pendiente','Recibido') DEFAULT NULL,
  `id_gerente_revision` int(11) DEFAULT NULL,
  `fecha_revision` datetime DEFAULT NULL,
  `monto_recibido` decimal(10,2) DEFAULT NULL,
  `nota_revision` varchar(255) DEFAULT NULL,
  `caja_abierta` int(11) GENERATED ALWAYS AS (if(`estado` = 'Abierto',`id_caja`,NULL)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Usuarios`

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
  `modificado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `debe_cambiar` tinyint(1) NOT NULL DEFAULT 1,
  `foto` varchar(255) DEFAULT NULL,
  `notificaciones_vistas_en` datetime DEFAULT NULL,
  `temporal_expira_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ventas`

CREATE TABLE `Ventas` (
  `id_venta` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_cajero` int(11) NOT NULL,
  `id_caja` int(11) DEFAULT NULL,
  `id_metodo_pago` int(11) NOT NULL,
  `fecha_hora` timestamp NULL DEFAULT current_timestamp(),
  `subtotal` decimal(10,2) NOT NULL CHECK (`subtotal` >= 0),
  `total` decimal(10,2) NOT NULL CHECK (`total` >= 0),
  `monto_efectivo` decimal(10,2) DEFAULT NULL,
  `monto_tarjeta` decimal(10,2) DEFAULT NULL,
  `id_turno` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas

--
-- Indices de la tabla `avisos_privacidad`
--
ALTER TABLE `avisos_privacidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_token` (`token_hash`),
  ADD KEY `idx_usuario_version` (`id_usuario`,`version`,`aceptado_en`);

--
-- Indices de la tabla `Cajas`
--
ALTER TABLE `Cajas`
  ADD PRIMARY KEY (`id_caja`),
  ADD UNIQUE KEY `uq_caja_sucursal` (`id_sucursal`,`numero_caja`),
  ADD KEY `id_cajero_asignado` (`id_cajero_asignado`);

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
-- Indices de la tabla `Historial_Estado_Gerente`
--
ALTER TABLE `Historial_Estado_Gerente`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_hist_gerente_fecha` (`id_gerente`,`fecha_cambio`);

--
-- Indices de la tabla `Inventario_Sucursal`
--
ALTER TABLE `Inventario_Sucursal`
  ADD PRIMARY KEY (`id_sucursal`,`id_producto`),
  ADD KEY `idx_inventario_sucursal` (`id_sucursal`),
  ADD KEY `idx_inventario_producto` (`id_producto`);

--
-- Indices de la tabla `login_intentos`
--
ALTER TABLE `login_intentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_fecha` (`email`,`creado_en`),
  ADD KEY `idx_ip_fecha` (`ip`,`creado_en`);

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
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`);

--
-- Indices de la tabla `Productos`
--
ALTER TABLE `Productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `uq_productos_codigo` (`codigo`),
  ADD KEY `idx_productos_estado` (`estado`),
  ADD KEY `idx_productos_categoria` (`id_categoria`);

--
-- Indices de la tabla `Retiros_Caja`
--
ALTER TABLE `Retiros_Caja`
  ADD PRIMARY KEY (`id_retiro`),
  ADD KEY `idx_retiro_turno` (`id_turno`),
  ADD KEY `fk_retiro_cajero` (`id_cajero`);

--
-- Indices de la tabla `Roles`
--
ALTER TABLE `Roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `Solicitudes_Productos`
--
ALTER TABLE `Solicitudes_Productos`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `id_sucursal` (`id_sucursal`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_categoria` (`id_categoria`),
  ADD KEY `id_producto_creado` (`id_producto_creado`),
  ADD KEY `resuelto_por` (`resuelto_por`),
  ADD KEY `fk_solicitud_producto` (`id_producto`);

--
-- Indices de la tabla `Solicitudes_Productos_Historial`
--
ALTER TABLE `Solicitudes_Productos_Historial`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_historial_solicitud` (`id_solicitud`,`id_historial`);

--
-- Indices de la tabla `Sucursales`
--
ALTER TABLE `Sucursales`
  ADD PRIMARY KEY (`id_sucursal`);

--
-- Indices de la tabla `Turnos_Caja`
--
ALTER TABLE `Turnos_Caja`
  ADD PRIMARY KEY (`id_turno`),
  ADD UNIQUE KEY `uq_turno_abierto_por_caja` (`caja_abierta`),
  ADD KEY `idx_turno_caja_estado` (`id_caja`,`estado`),
  ADD KEY `idx_turno_sucursal_cierre` (`id_sucursal`,`fecha_cierre`),
  ADD KEY `idx_turno_revision` (`id_sucursal`,`estado_revision`),
  ADD KEY `fk_turno_cajero_ab` (`id_cajero_apertura`),
  ADD KEY `fk_turno_cajero_ci` (`id_cajero_cierre`),
  ADD KEY `fk_turno_gerente` (`id_gerente_revision`);

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
  ADD KEY `idx_ventas_cajero` (`id_cajero`),
  ADD KEY `Ventas_ibfk_caja` (`id_caja`),
  ADD KEY `idx_ventas_turno` (`id_turno`);

--
-- AUTO_INCREMENT de las tablas volcadas

--
-- AUTO_INCREMENT de la tabla `avisos_privacidad`
--
ALTER TABLE `avisos_privacidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Cajas`
--
ALTER TABLE `Cajas`
  MODIFY `id_caja` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Categorias`
--
ALTER TABLE `Categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Historial_Estado_Gerente`
--
ALTER TABLE `Historial_Estado_Gerente`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `login_intentos`
--
ALTER TABLE `login_intentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Metodos_Pago`
--
ALTER TABLE `Metodos_Pago`
  MODIFY `id_metodo_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Movimientos_Inventario`
--
ALTER TABLE `Movimientos_Inventario`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Productos`
--
ALTER TABLE `Productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Retiros_Caja`
--
ALTER TABLE `Retiros_Caja`
  MODIFY `id_retiro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Roles`
--
ALTER TABLE `Roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Solicitudes_Productos`
--
ALTER TABLE `Solicitudes_Productos`
  MODIFY `id_solicitud` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Solicitudes_Productos_Historial`
--
ALTER TABLE `Solicitudes_Productos_Historial`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Sucursales`
--
ALTER TABLE `Sucursales`
  MODIFY `id_sucursal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Turnos_Caja`
--
ALTER TABLE `Turnos_Caja`
  MODIFY `id_turno` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Usuarios`
--
ALTER TABLE `Usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Ventas`
--
ALTER TABLE `Ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas

--
-- Filtros para la tabla `Cajas`
--
ALTER TABLE `Cajas`
  ADD CONSTRAINT `Cajas_ibfk_1` FOREIGN KEY (`id_sucursal`) REFERENCES `Sucursales` (`id_sucursal`),
  ADD CONSTRAINT `Cajas_ibfk_2` FOREIGN KEY (`id_cajero_asignado`) REFERENCES `Usuarios` (`id_usuario`);

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
-- Filtros para la tabla `Retiros_Caja`
--
ALTER TABLE `Retiros_Caja`
  ADD CONSTRAINT `fk_retiro_cajero` FOREIGN KEY (`id_cajero`) REFERENCES `Usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_retiro_turno` FOREIGN KEY (`id_turno`) REFERENCES `Turnos_Caja` (`id_turno`);

--
-- Filtros para la tabla `Solicitudes_Productos`
--
ALTER TABLE `Solicitudes_Productos`
  ADD CONSTRAINT `Solicitudes_Productos_ibfk_1` FOREIGN KEY (`id_sucursal`) REFERENCES `Sucursales` (`id_sucursal`),
  ADD CONSTRAINT `Solicitudes_Productos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `Usuarios` (`id_usuario`),
  ADD CONSTRAINT `Solicitudes_Productos_ibfk_3` FOREIGN KEY (`id_categoria`) REFERENCES `Categorias` (`id_categoria`),
  ADD CONSTRAINT `Solicitudes_Productos_ibfk_4` FOREIGN KEY (`id_producto_creado`) REFERENCES `Productos` (`id_producto`),
  ADD CONSTRAINT `Solicitudes_Productos_ibfk_5` FOREIGN KEY (`resuelto_por`) REFERENCES `Usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_solicitud_producto` FOREIGN KEY (`id_producto`) REFERENCES `Productos` (`id_producto`);

--
-- Filtros para la tabla `Turnos_Caja`
--
ALTER TABLE `Turnos_Caja`
  ADD CONSTRAINT `fk_turno_caja` FOREIGN KEY (`id_caja`) REFERENCES `Cajas` (`id_caja`),
  ADD CONSTRAINT `fk_turno_cajero_ab` FOREIGN KEY (`id_cajero_apertura`) REFERENCES `Usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_turno_cajero_ci` FOREIGN KEY (`id_cajero_cierre`) REFERENCES `Usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_turno_gerente` FOREIGN KEY (`id_gerente_revision`) REFERENCES `Usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_turno_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `Sucursales` (`id_sucursal`);

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
  ADD CONSTRAINT `Ventas_ibfk_3` FOREIGN KEY (`id_metodo_pago`) REFERENCES `Metodos_Pago` (`id_metodo_pago`),
  ADD CONSTRAINT `Ventas_ibfk_caja` FOREIGN KEY (`id_caja`) REFERENCES `Cajas` (`id_caja`),
  ADD CONSTRAINT `fk_ventas_turno` FOREIGN KEY (`id_turno`) REFERENCES `Turnos_Caja` (`id_turno`);

