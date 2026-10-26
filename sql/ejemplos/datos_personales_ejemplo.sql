-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK,
-- después de sql/014_datos_personales.sql.
-- SOLO PARA PRUEBAS: llena Empleados_Datos de los usuarios que ya existen con
-- datos FALSOS. RFC, CURP y NSS tienen el formato correcto pero no son de nadie;
-- los teléfonos usan el 555 y los domicilios son inventados (Colima).
-- En la CURP el sexo va como X para no suponerlo a partir del nombre.
--
-- Va por id_usuario (no por correo, para no subir correos reales a git).
-- Un id que no exista se salta. Si ya tiene datos, los reemplaza.
-- Para quitarlos: DELETE FROM Empleados_Datos;   (no toca Usuarios)

-- LIKE copia columnas, CHECK y UNIQUE (no las llaves foráneas): un RFC mal formado falla aquí
CREATE TEMPORARY TABLE tmp_datos LIKE Empleados_Datos;

INSERT INTO tmp_datos (id_usuario, rfc, curp, nss, fecha_nacimiento, telefono,
                       calle, numero_exterior, numero_interior, colonia, codigo_postal, municipio, estado,
                       contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono)
VALUES
(1, 'ANMG781023SU4', 'ANMG781023XCMMDT04', '76614355458', '1978-10-23', '312 555 1173', 'Calle Aldama', '1139', NULL, 'Lomas de Circunvalación', '28010', 'Colima', 'Colima', 'Jorge Díaz', 'Padre', '312 555 2145'),
(2, 'ERNX781011BF4', 'ERNX781011XCMJRR09', '79119952178', '1978-10-11', '312 555 5609', 'Calle Revolución', '936', 'C2', 'Villa de Álvarez Centro', '28970', 'Villa de Álvarez', 'Colima', 'Laura Sánchez', 'Hermana', '312 555 5171'),
(3, 'CEHG780227FU4', 'CEHG780227XCMFBH06', '18916728674', '1978-02-27', '312 555 8774', 'Av. Tecnológico', '905', NULL, 'Real de Minas', '28976', 'Villa de Álvarez', 'Colima', 'Pedro Cruz', 'Hermano', '312 555 4870'),
(4, 'GIMX891016EK7', 'GIMX891016XCMLTB06', '60595745483', '1989-10-16', '312 555 7355', 'Calle Hidalgo', '1156', NULL, 'Centro', '28000', 'Colima', 'Colima', 'Sofía Vega', 'Tía', '312 555 1569'),
(7, 'FERX810501A94', 'FERX810501XCMZPL01', '02303484233', '1981-05-01', '312 555 9325', 'Calle Aldama', '1487', NULL, 'El Diezmo', '28010', 'Colima', 'Colima', 'Laura Sánchez', 'Hermana', '312 555 4233'),
(8, 'URLX860813S18', 'URLX860813XCMNVC03', '67514123023', '1986-08-13', '312 555 2742', 'Av. Rey Colimán', '926', NULL, 'Las Palmas', '28078', 'Colima', 'Colima', 'Pedro Cruz', 'Hermano', '312 555 7039'),
(9, 'JEDM920714J53', 'JEDM920714XCMDGH02', '03598096747', '1992-07-14', '312 555 2123', 'Av. Tecnológico', '1779', NULL, 'El Diezmo', '28010', 'Colima', 'Colima', 'Rosa Gómez', 'Madre', '312 555 2887'),
(10, 'ELXX020127LE0', 'ELXX020127XCMMCGA6', '92077780414', '2002-01-27', '312 555 1126', 'Calle Venustiano Carranza', '1404', NULL, 'Villa de Álvarez Centro', '28970', 'Villa de Álvarez', 'Colima', 'Laura Sánchez', 'Hermana', '312 555 2828'),
(11, 'MALH020213154', 'MALH020213XCMPNBA4', '24943897017', '2002-02-13', '312 555 7862', 'Calle Morelos', '797', NULL, 'El Diezmo', '28010', 'Colima', 'Colima', 'Carmen Ruiz', 'Pareja', '312 555 1496'),
(12, 'JUPR871216S18', 'JUPR871216XCMFKK03', '41317800394', '1987-12-16', '312 555 4008', 'Calle Guerrero', '470', NULL, 'Placetas Estadio', '28050', 'Colima', 'Colima', 'Pedro Cruz', 'Hermano', '312 555 7106'),
(13, 'ANMS8912071G9', 'ANMS891207XCMPVJ05', '57840297247', '1989-12-07', '312 555 6025', 'Calle Juárez', '107', NULL, 'Las Palmas', '28078', 'Colima', 'Colima', 'Carmen Ruiz', 'Pareja', '312 555 6980'),
(14, 'LURX950512MJ0', 'LURX950512XCMJZD02', '93469795253', '1995-05-12', '312 555 1249', 'Av. Constitución', '1685', NULL, 'El Diezmo', '28010', 'Colima', 'Colima', 'Laura Sánchez', 'Hermana', '312 555 6002'),
(15, 'URLX020506WN3', 'URLX020506XCMFZKA6', '49716179183', '2002-05-06', '312 555 9732', 'Calle Hidalgo', '1122', NULL, 'Centro', '28000', 'Colima', 'Colima', 'Carmen Ruiz', 'Pareja', '312 555 3153'),
(16, 'GUXX861116HF1', 'GUXX861116XCMKKF02', '78756815185', '1986-11-16', '312 555 1649', 'Calle Revolución', '941', NULL, 'Las Palmas', '28078', 'Colima', 'Colima', 'Jorge Díaz', 'Padre', '312 555 8395'),
(22, 'ZIXX850125D13', 'ZIXX850125XCMKFP08', '24602165738', '1985-01-25', '312 555 4999', 'Av. Rey Colimán', '1576', NULL, 'Placetas Estadio', '28050', 'Colima', 'Colima', 'Jorge Díaz', 'Padre', '312 555 4547'),
(23, 'ELXX801221HT3', 'ELXX801221XCMZCJ02', '36413303404', '1980-12-21', '312 555 5515', 'Av. San Fernando', '692', NULL, 'Villa de Álvarez Centro', '28970', 'Villa de Álvarez', 'Colima', 'Jorge Díaz', 'Padre', '312 555 1047'),
(24, 'ERXX9207219U7', 'ERXX920721XCMTKC07', '27343894176', '1992-07-21', '312 555 5764', 'Calle Revolución', '1099', NULL, 'Centro', '28000', 'Colima', 'Colima', 'Pedro Cruz', 'Hermano', '312 555 6290'),
(25, 'URLX010903AY1', 'URLX010903XCMKHJA7', '47766069314', '2001-09-03', '312 555 7901', 'Calle Guerrero', '1243', 'C2', 'Jardines de Vista Hermosa', '28017', 'Colima', 'Colima', 'Carmen Ruiz', 'Pareja', '312 555 8443'),
(26, 'TEXX8506137T6', 'TEXX850613XCMLPH00', '24177037002', '1985-06-13', '312 555 7306', 'Av. Constitución', '1904', NULL, 'Placetas Estadio', '28050', 'Colima', 'Colima', 'Sofía Vega', 'Tía', '312 555 4445'),
(27, 'FEXX840327CX5', 'FEXX840327XCMDZJ00', '11803862055', '1984-03-27', '312 555 1342', 'Av. Constitución', '859', 'C1', 'Placetas Estadio', '28050', 'Colima', 'Colima', 'Carmen Ruiz', 'Pareja', '312 555 1415'),
(28, 'SINX781214NA2', 'SINX781214XCMFMN00', '08375234476', '1978-12-14', '312 555 9170', 'Calle Morelos', '1397', NULL, 'Villa de Álvarez Centro', '28970', 'Villa de Álvarez', 'Colima', 'Pedro Cruz', 'Hermano', '312 555 5470'),
(29, 'LIMX900302F15', 'LIMX900302XCMVHH09', '86546850050', '1990-03-02', '312 555 2233', 'Calle Revolución', '1131', NULL, 'Villa de Álvarez Centro', '28970', 'Villa de Álvarez', 'Colima', 'Rosa Gómez', 'Madre', '312 555 3666');

-- La cuenta de prueba de RRHH (sql/ejemplos/cuenta_rrhh_prueba.sql) no tiene id fijo
SET @id_rrhh = (SELECT id_usuario FROM Usuarios WHERE email = 'rrhh@tienda.com');
INSERT INTO tmp_datos (id_usuario, rfc, curp, nss, fecha_nacimiento, telefono,
                       calle, numero_exterior, numero_interior, colonia, codigo_postal, municipio, estado,
                       contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono)
SELECT @id_rrhh, 'REHP990620816', 'REHP990620XCMGNR03', '12866341519', '1999-06-20', '312 555 5013', 'Av. Constitución', '1040', 'B3', 'Las Palmas', '28078', 'Colima', 'Colima', 'Sofía Vega', 'Tía', '312 555 8300'
FROM DUAL WHERE @id_rrhh IS NOT NULL;

-- Solo los que existen en Usuarios
INSERT INTO Empleados_Datos (id_usuario, rfc, curp, nss, fecha_nacimiento, telefono,
                             calle, numero_exterior, numero_interior, colonia, codigo_postal, municipio, estado,
                             contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono)
SELECT t.id_usuario, t.rfc, t.curp, t.nss, t.fecha_nacimiento, t.telefono,
       t.calle, t.numero_exterior, t.numero_interior, t.colonia, t.codigo_postal, t.municipio, t.estado,
       t.contacto_emergencia_nombre, t.contacto_emergencia_parentesco, t.contacto_emergencia_telefono
FROM tmp_datos t
JOIN Usuarios u ON u.id_usuario = t.id_usuario
ON DUPLICATE KEY UPDATE
  rfc = VALUES(rfc), curp = VALUES(curp), nss = VALUES(nss), fecha_nacimiento = VALUES(fecha_nacimiento),
  telefono = VALUES(telefono), calle = VALUES(calle), numero_exterior = VALUES(numero_exterior),
  numero_interior = VALUES(numero_interior), colonia = VALUES(colonia), codigo_postal = VALUES(codigo_postal),
  municipio = VALUES(municipio), estado = VALUES(estado),
  contacto_emergencia_nombre = VALUES(contacto_emergencia_nombre),
  contacto_emergencia_parentesco = VALUES(contacto_emergencia_parentesco),
  contacto_emergencia_telefono = VALUES(contacto_emergencia_telefono);

DROP TEMPORARY TABLE tmp_datos;

-- Verificación: cada usuario con su RFC; los que salgan en NULL no tienen datos
SELECT u.id_usuario, u.nombre, r.nombre_rol, d.rfc, d.municipio, d.telefono
FROM Usuarios u
JOIN Roles r ON r.id_rol = u.id_rol
LEFT JOIN Empleados_Datos d ON d.id_usuario = u.id_usuario
ORDER BY u.id_usuario;
