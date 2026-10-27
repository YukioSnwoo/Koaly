-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- SOLO PARA PRUEBAS: crea la cuenta de Recursos Humanos (rol 4) que usa el
-- botón "4 · RRHH" del acceso rápido en index.php.
--
--   correo:     rrhh@tienda.com
--   contraseña: 123
--
-- La contraseña va en texto plano a propósito: login/login.php la reconoce
-- (no empieza con '$') y la guarda con password_hash en el primer ingreso.
-- Queda en la Sucursal Centro (1): fuera de admin, el login exige una sucursal activa.
-- Si la cuenta ya existe no se duplica.

SET @correo = 'rrhh@tienda.com';

INSERT INTO Usuarios (nombre, email, contrasena_hash, id_rol, id_sucursal, estado,
                      fecha_inicio_estado, fecha_contratacion, debe_cambiar)
SELECT 'Recursos Humanos (prueba)', @correo, '123', 4, 1, 'Activo',
       CURDATE(), CURDATE(), 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM Usuarios WHERE email = @correo);

-- Verificación: una fila con id_rol = 4, sucursal activa y debe_cambiar = 0
SELECT u.id_usuario, u.nombre, u.email, u.id_rol, r.nombre_rol,
       s.nombre AS sucursal, s.estado AS sucursal_estado, u.debe_cambiar
FROM Usuarios u
JOIN Roles r ON r.id_rol = u.id_rol
LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
WHERE u.email = @correo;
