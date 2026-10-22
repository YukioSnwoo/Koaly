-- Aceptación del aviso de privacidad por los empleados (privacidad/)
-- Ejecuta esto en phpMyAdmin sobre u772860605_DATAK. Se puede ejecutar más de una vez.
--
-- Cada fila es un enlace enviado o una aceptación:
--   - token_hash/expira_en: enlace personal para leer y aceptar el aviso (se guarda
--     solo el hash SHA-256 del token; el enlace deja de servir al aceptarlo)
--   - aceptado_en/ip/agente: constancia de la aceptación
--   - version: versión del aviso (AVISO_PRIVACIDAD_VERSION en privacidad/aviso.php).
--     Si el aviso cambia, se sube la versión y todos deben aceptarlo otra vez.
--   Las fechas son TIMESTAMP (UTC): el login, la página del aviso y el gerente
--   las comparan y muestran bien aunque cada uno use otra zona horaria.

CREATE TABLE IF NOT EXISTS `avisos_privacidad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `expira_en` timestamp NULL DEFAULT NULL,
  `enviado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `aceptado_en` timestamp NULL DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `agente` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token_hash`),
  KEY `idx_usuario_version` (`id_usuario`, `version`, `aceptado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
