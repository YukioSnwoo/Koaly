-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Menú de perfil de los tres paneles (perfil/):
--   - foto de perfil que sube cada usuario;
--   - hasta cuándo vio sus notificaciones (las posteriores salen como nuevas).
-- Se puede correr más de una vez (IF NOT EXISTS es de MariaDB, que es la base del hosting).

ALTER TABLE Usuarios
  ADD COLUMN IF NOT EXISTS foto                     VARCHAR(255) NULL,  -- ruta desde la raíz, p. ej. Imagenes/perfiles/u_x.webp
  ADD COLUMN IF NOT EXISTS notificaciones_vistas_en DATETIME     NULL;  -- hora de México, como Turnos_Caja

-- Verificación
SELECT id_usuario, nombre, foto, notificaciones_vistas_en FROM Usuarios LIMIT 5;
