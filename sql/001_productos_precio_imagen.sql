-- Ejecutar este script en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Agrega precio e imagen a Productos, y siembra 2 productos de prueba con imagen.

-- 1) Nuevas columnas en Productos
ALTER TABLE Productos
  ADD COLUMN precio DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER descripcion,
  ADD COLUMN imagen VARCHAR(255) NULL AFTER precio;

-- 2) Categoría "Bebidas" (solo se crea si no existe)
INSERT INTO Categorias (nombre_categoria)
SELECT 'Bebidas'
WHERE NOT EXISTS (SELECT 1 FROM Categorias WHERE nombre_categoria = 'Bebidas');

-- 3) Productos de prueba con imagen (los .png ya están en la carpeta /Imagenes del proyecto)
INSERT INTO Productos (nombre, descripcion, precio, imagen, id_categoria, estado, creado_en, modificado_en)
VALUES
  ('Coca-Cola vidrio 355 ml', 'Refresco de cola en botella de vidrio retornable', 18.00,
   'Imagenes/cocadevidrio.png',
   (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Bebidas'),
   'Activo', NOW(), NOW()),
  ('Coca-Cola lata 355 ml', 'Refresco de cola en lata', 16.00,
   'Imagenes/cocalata.png',
   (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Bebidas'),
   'Activo', NOW(), NOW());
