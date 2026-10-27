-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- SOLO PARA PRUEBAS: deja a un cajero como recién dado de alta, para recorrer
-- otra vez todo el flujo de ingreso:
--   correo con contraseña temporal → aviso de privacidad → login → cambio de contraseña → caja
--
-- Qué hace con el cajero del correo de abajo (solo rol 3: no toca gerentes ni admin):
--   - borra sus aceptaciones y enlaces del aviso de privacidad (lo volverá a pedir)
--   - lo marca para cambiar la contraseña al entrar
-- Su contraseña actual sigue sirviendo hasta el paso siguiente.
--
-- Después, en el panel del gerente → Cajeros, en su tarjeta aparece
-- "Reenviar acceso": genera una contraseña temporal nueva y le manda el correo
-- con el enlace del aviso.
--
-- Para no tocar cuentas de nadie, otra opción es dar de alta un cajero nuevo con
-- un alias de Gmail (tucorreo+prueba1@gmail.com, +prueba2…): llega al mismo buzón.

SET @correo = 'tenshi.gs.729@gmail.com';   -- ← cambia aquí el correo del cajero

DELETE FROM avisos_privacidad
WHERE id_usuario = (SELECT id_usuario FROM Usuarios WHERE email = @correo AND id_rol = 3);

UPDATE Usuarios
SET debe_cambiar = 1, temporal_expira_en = NULL
WHERE email = @correo AND id_rol = 3;

-- Verificación: debe_cambiar = 1 y avisos = 0
SELECT u.id_usuario, u.nombre, u.email, u.debe_cambiar, u.temporal_expira_en,
       (SELECT COUNT(*) FROM avisos_privacidad a WHERE a.id_usuario = u.id_usuario) AS avisos
FROM Usuarios u
WHERE u.email = @correo;
