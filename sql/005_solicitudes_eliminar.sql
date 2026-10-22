-- 005: Solicitudes de eliminación de productos (issue #36)
-- El gerente puede solicitar al admin quitar un producto del catálogo.
-- Se puede ejecutar más de una vez: cada paso se omite si ya está aplicado.
-- La columna id_producto copia el tipo exacto de Productos.id_producto para que
-- la llave foránea sea compatible (INT, INT UNSIGNED, BIGINT...).

-- 1) Columna tipo
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Solicitudes_Productos' AND COLUMN_NAME = 'tipo');
SET @sql := IF(@existe = 0,
    'ALTER TABLE Solicitudes_Productos ADD COLUMN tipo ENUM(''Agregar'',''Eliminar'') NOT NULL DEFAULT ''Agregar'' AFTER id_usuario',
    'SELECT ''tipo ya existe''');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- 2) Columna id_producto con el mismo tipo que Productos.id_producto
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Solicitudes_Productos' AND COLUMN_NAME = 'id_producto');
SET @tipo := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Productos' AND COLUMN_NAME = 'id_producto');
SET @sql := IF(@existe = 0,
    CONCAT('ALTER TABLE Solicitudes_Productos ADD COLUMN id_producto ', @tipo, ' NULL DEFAULT NULL AFTER tipo'),
    'SELECT ''id_producto ya existe''');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- 3) Llave foránea
SET @existe := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Solicitudes_Productos'
                  AND CONSTRAINT_NAME = 'fk_solicitud_producto');
SET @sql := IF(@existe = 0,
    'ALTER TABLE Solicitudes_Productos ADD CONSTRAINT fk_solicitud_producto FOREIGN KEY (id_producto) REFERENCES Productos (id_producto)',
    'SELECT ''fk_solicitud_producto ya existe''');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

-- Para deshacerlo:
--   ALTER TABLE Solicitudes_Productos DROP FOREIGN KEY fk_solicitud_producto, DROP COLUMN id_producto, DROP COLUMN tipo;
