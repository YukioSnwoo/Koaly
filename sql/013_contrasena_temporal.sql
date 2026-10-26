-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Vencimiento de la contraseña temporal que el sistema genera y envía por
-- correo al dar de alta o restablecer a un cajero (login/reglas_contrasena.php).
--   NULL = no vence (gerentes y usuarios creados antes de este cambio).
-- Al elegir su contraseña (cambio obligatorio u "Olvidé mi contraseña") vuelve a NULL.
-- Sin esta columna todo funciona igual, solo que la temporal no vence.
-- Se puede correr más de una vez (IF NOT EXISTS es de MariaDB, que es la base del hosting).

ALTER TABLE Usuarios
  ADD COLUMN IF NOT EXISTS temporal_expira_en DATETIME NULL;  -- hora de México, como Turnos_Caja

-- Verificación
SELECT id_usuario, email, debe_cambiar, temporal_expira_en FROM Usuarios WHERE id_rol = 3 LIMIT 10;
