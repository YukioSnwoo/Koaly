-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Datos personales de los empleados, en su propia tabla (1 a 1 con Usuarios):
--   - Usuarios se queda con lo del acceso (correo, contraseña, rol, sucursal);
--     así el login y los paneles no cargan RFC ni domicilio sin necesidad.
--   - Solo RRHH y admin leen todo; el empleado ve lo suyo en perfil/.
-- Todo es opcional (NULL): los usuarios de antes siguen funcionando sin datos.
-- La nómina (salario, CLABE) va aparte, en su propia fase.
-- Se puede correr más de una vez (IF NOT EXISTS es de MariaDB, que es la base del hosting).
--
-- OJO: son datos personales nuevos (RFC, CURP, NSS, domicilio). Hay que
-- agregarlos al aviso de privacidad y subir AVISO_PRIVACIDAD_VERSION en
-- privacidad/aviso.php, para que los cajeros lo acepten otra vez.

CREATE TABLE IF NOT EXISTS Empleados_Datos (
  id_usuario                     INT          NOT NULL,
  rfc                            VARCHAR(13)  NULL,            -- 13 en persona física
  curp                           CHAR(18)     NULL,
  nss                            CHAR(11)     NULL,            -- número de seguridad social (IMSS)
  fecha_nacimiento               DATE         NULL,
  telefono                       VARCHAR(20)  NULL,
  -- Domicilio por partes: así se puede filtrar por municipio o validar el código postal
  calle                          VARCHAR(120) NULL,
  numero_exterior                VARCHAR(10)  NULL,
  numero_interior                VARCHAR(10)  NULL,
  colonia                        VARCHAR(80)  NULL,
  codigo_postal                  CHAR(5)      NULL,
  municipio                      VARCHAR(80)  NULL,
  estado                         VARCHAR(40)  NULL,
  contacto_emergencia_nombre     VARCHAR(100) NULL,
  contacto_emergencia_parentesco VARCHAR(30)  NULL,
  contacto_emergencia_telefono   VARCHAR(20)  NULL,
  actualizado_en                 TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  actualizado_por                INT          NULL,            -- quién los capturó o cambió (RRHH, admin o el propio empleado)
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uk_rfc  (rfc),
  UNIQUE KEY uk_curp (curp),
  UNIQUE KEY uk_nss  (nss),
  CONSTRAINT fk_datos_usuario      FOREIGN KEY (id_usuario)      REFERENCES Usuarios (id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_datos_actualizador FOREIGN KEY (actualizado_por) REFERENCES Usuarios (id_usuario) ON DELETE SET NULL,
  -- Formatos oficiales; la base rechaza un RFC o CURP mal escrito aunque el PHP se equivoque
  CONSTRAINT chk_rfc  CHECK (rfc  IS NULL OR rfc  REGEXP '^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$'),
  CONSTRAINT chk_curp CHECK (curp IS NULL OR curp REGEXP '^[A-Z]{4}[0-9]{6}[HMX][A-Z]{5}[A-Z0-9][0-9]$'),
  CONSTRAINT chk_nss  CHECK (nss  IS NULL OR nss  REGEXP '^[0-9]{11}$'),
  CONSTRAINT chk_cp   CHECK (codigo_postal IS NULL OR codigo_postal REGEXP '^[0-9]{5}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación: la tabla existe y está vacía (o con lo que ya se capturó)
SELECT COUNT(*) AS empleados_con_datos FROM Empleados_Datos;
