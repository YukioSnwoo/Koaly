-- Tablas del inicio de sesión (login/login.php)
-- Ejecuta esto en phpMyAdmin sobre u772860605_DATAK. Se puede ejecutar más
-- de una vez: si las tablas ya existen, solo las ajusta.

-- Códigos de recuperación de contraseña.
-- El código se guarda con hash (password_hash) y cuenta los intentos fallidos:
-- al quinto se invalida.
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `codigo_hash` varchar(255) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `intentos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la tabla ya existía con la versión anterior (código en texto plano),
-- la adapta. Los códigos pendientes se descartan: duran 15 minutos.
DELETE FROM `password_resets`;
ALTER TABLE `password_resets`
  ADD COLUMN IF NOT EXISTS `codigo_hash` varchar(255) NOT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `intentos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `usado`,
  DROP INDEX IF EXISTS `idx_codigo`,
  DROP COLUMN IF EXISTS `codigo`;

-- Intentos fallidos de inicio de sesión. Tras 5 fallos en 15 minutos el
-- correo queda bloqueado ese tiempo (20 por IP), para frenar a quien
-- prueba contraseñas. Las filas viejas se borran solas desde login.php.
CREATE TABLE IF NOT EXISTS `login_intentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_fecha` (`email`, `creado_en`),
  KEY `idx_ip_fecha` (`ip`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
