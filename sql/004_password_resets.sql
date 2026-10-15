-- Tabla para códigos de recuperación de contraseña (login/login.php)
-- Ejecuta esto una vez en tu base de datos u772860605_DATAK
--
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
-- la adapta. Los códigos viejos se descartan: duraban 15 minutos.
DELETE FROM `password_resets`;
ALTER TABLE `password_resets`
  ADD COLUMN IF NOT EXISTS `codigo_hash` varchar(255) NOT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `intentos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `usado`,
  DROP INDEX IF EXISTS `idx_codigo`,
  DROP COLUMN IF EXISTS `codigo`;
