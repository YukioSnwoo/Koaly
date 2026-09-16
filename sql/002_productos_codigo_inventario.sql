-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Agrega un código de producto tipo código de barras (más largo que el id interno).

-- 1) Nueva columna
ALTER TABLE Productos ADD COLUMN codigo VARCHAR(50) NULL AFTER id_producto;

-- 2) Rellenar los productos existentes con un código generado a partir de su id
UPDATE Productos SET codigo = CONCAT('750', LPAD(id_producto, 10, '0')) WHERE codigo IS NULL;

-- 3) Ya no debe quedar ninguno vacío, y cada código debe ser único
ALTER TABLE Productos MODIFY codigo VARCHAR(50) NOT NULL;
ALTER TABLE Productos ADD UNIQUE KEY uq_productos_codigo (codigo);
