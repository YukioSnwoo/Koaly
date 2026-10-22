-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Solicitudes de producto de ida y vuelta entre gerente y admin:
--   - el gerente adjunta una imagen y, si quiere, los datos de contacto del proveedor;
--   - el admin acepta, rechaza o edita (la edición regresa al gerente);
--   - el gerente aprueba los cambios del admin, los rechaza (se cancela) o edita (regresa al admin).
-- Se puede correr más de una vez (IF NOT EXISTS es de MariaDB, que es la base del hosting).
-- Correr DESPUÉS de sql/005_solicitudes_eliminar.sql, que crea las columnas tipo e id_producto.

-- 0) Revisión previa: los estados guardados deben estar entre
--    Pendiente, Aprobada y Rechazada. Si sale otro, NO sigas y avísame.
SELECT estado, COUNT(*) AS solicitudes FROM Solicitudes_Productos GROUP BY estado;

-- 1) Estados nuevos:
--    Pendiente = turno del admin (nueva o reenviada por el gerente con cambios)
--    Devuelta  = turno del gerente (el admin la editó)
--    Cancelada = el gerente rechazó los cambios del admin
ALTER TABLE Solicitudes_Productos
  MODIFY estado ENUM('Pendiente', 'Devuelta', 'Aprobada', 'Rechazada', 'Cancelada') NOT NULL DEFAULT 'Pendiente';

-- 2) Columnas que ya usa la revisión del admin (por si faltan) y las nuevas
ALTER TABLE Solicitudes_Productos
  ADD COLUMN IF NOT EXISTS motivo_rechazo     TEXT NULL,
  ADD COLUMN IF NOT EXISTS id_producto_creado INT NULL,
  ADD COLUMN IF NOT EXISTS resuelto_en        TIMESTAMP NULL,
  ADD COLUMN IF NOT EXISTS resuelto_por       INT NULL,
  ADD COLUMN IF NOT EXISTS imagen             VARCHAR(255) NULL,   -- ruta desde la raíz, p. ej. Imagenes/solicitudes/prod_x.webp
  -- proveedor_sugerido (ya existe) es el nombre del proveedor
  ADD COLUMN IF NOT EXISTS proveedor_empresa  VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS proveedor_telefono VARCHAR(20)  NULL,
  ADD COLUMN IF NOT EXISTS proveedor_correo   VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS actualizado_en     TIMESTAMP NULL;

-- 3) Historial: cada paso de la solicitud, con quién lo dio, qué cambió y por qué
CREATE TABLE IF NOT EXISTS Solicitudes_Productos_Historial (
  id_historial  INT NOT NULL AUTO_INCREMENT,
  id_solicitud  INT NOT NULL,
  id_usuario    INT NOT NULL,
  rol           ENUM('Gerente', 'Admin') NOT NULL,
  accion        ENUM('Enviada', 'Editada', 'Aprobada', 'Rechazada', 'Cancelada') NOT NULL,
  cambios       TEXT NULL,      -- JSON {campo: {antes, despues}} cuando accion = Editada
  nota          TEXT NULL,      -- justificación, nota para la otra parte o motivo
  creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historial),
  KEY idx_historial_solicitud (id_solicitud, id_historial)
);
