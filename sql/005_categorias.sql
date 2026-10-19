-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Deja el catálogo con 6 categorías y asigna la suya a cada producto:
--   Abarrotes, Bebidas, Cuidado Personal, Electronica, Hogar, Ropa
-- Bebidas, Electronica, Hogar y Ropa ya existían (el panel de gerente las traduce
-- por ese nombre exacto, por eso Electronica va sin acento).
-- Se puede correr más de una vez. Si antes corriste la versión con 15 categorías,
-- sus productos se mueven a estas 6 y las categorías sobrantes se borran.

-- 1) Las 6 categorías (solo se crean las que falten)
INSERT INTO Categorias (nombre_categoria)
SELECT n.nombre
FROM (
  SELECT 'Abarrotes' AS nombre
  UNION ALL SELECT 'Bebidas'
  UNION ALL SELECT 'Cuidado Personal'
  UNION ALL SELECT 'Electronica'
  UNION ALL SELECT 'Hogar'
  UNION ALL SELECT 'Ropa'
) n
WHERE NOT EXISTS (SELECT 1 FROM Categorias c WHERE c.nombre_categoria = n.nombre);

-- 2) Categorías sobrantes de la versión anterior -> a cuál de las 6 pasan sus productos
--    (también las solicitudes de productos que las usen)
UPDATE Productos p
JOIN Categorias vieja ON vieja.id_categoria = p.id_categoria
JOIN (
  SELECT 'Botanas y Dulces' AS vieja, 'Abarrotes' AS nueva
  UNION ALL SELECT 'Carnes y Salchichonería', 'Abarrotes'
  UNION ALL SELECT 'Frutas y Verduras', 'Abarrotes'
  UNION ALL SELECT 'Lácteos y Huevo', 'Abarrotes'
  UNION ALL SELECT 'Panadería y Tortillería', 'Abarrotes'
  UNION ALL SELECT 'Farmacia', 'Cuidado Personal'
  UNION ALL SELECT 'Cuidado y Limpieza del Hogar', 'Hogar'
  UNION ALL SELECT 'Electrodomésticos', 'Hogar'
  UNION ALL SELECT 'Juguetes y Entretenimiento', 'Hogar'
  UNION ALL SELECT 'Mascotas', 'Hogar'
  UNION ALL SELECT 'Papelería', 'Hogar'
) m ON m.vieja = vieja.nombre_categoria
JOIN Categorias nueva ON nueva.nombre_categoria = m.nueva
SET p.id_categoria = nueva.id_categoria, p.modificado_en = NOW();

UPDATE Solicitudes_Productos s
JOIN Categorias vieja ON vieja.id_categoria = s.id_categoria
JOIN (
  SELECT 'Botanas y Dulces' AS vieja, 'Abarrotes' AS nueva
  UNION ALL SELECT 'Carnes y Salchichonería', 'Abarrotes'
  UNION ALL SELECT 'Frutas y Verduras', 'Abarrotes'
  UNION ALL SELECT 'Lácteos y Huevo', 'Abarrotes'
  UNION ALL SELECT 'Panadería y Tortillería', 'Abarrotes'
  UNION ALL SELECT 'Farmacia', 'Cuidado Personal'
  UNION ALL SELECT 'Cuidado y Limpieza del Hogar', 'Hogar'
  UNION ALL SELECT 'Electrodomésticos', 'Hogar'
  UNION ALL SELECT 'Juguetes y Entretenimiento', 'Hogar'
  UNION ALL SELECT 'Mascotas', 'Hogar'
  UNION ALL SELECT 'Papelería', 'Hogar'
) m ON m.vieja = vieja.nombre_categoria
JOIN Categorias nueva ON nueva.nombre_categoria = m.nueva
SET s.id_categoria = nueva.id_categoria;

-- 3) Categoría de los productos de prueba (por nombre)
UPDATE Productos p
JOIN Categorias c ON c.nombre_categoria = 'Bebidas'
SET p.id_categoria = c.id_categoria, p.modificado_en = NOW()
WHERE p.nombre IN (
  'Agua Mineral Topo Chico 1.5 L', 'Agua Natural Epura 1 L', 'Cerveza Artesanal Colimita 355 ml',
  'Coca-Cola lata 355 ml', 'Coca-Cola vidrio 355 ml', 'Jumex Único Fresco Naranja con Pulpa 960 ml',
  'Red Bull Bebida Energética 473 ml', 'Starbucks Frappuccino Coffee 280 ml'
);

UPDATE Productos p
JOIN Categorias c ON c.nombre_categoria = 'Electronica'
SET p.id_categoria = c.id_categoria, p.modificado_en = NOW()
WHERE p.nombre IN (
  'AirPods Pro (2.ª generación)', 'Cable USB-C a Lightning (1 m)', 'Celular oppo g9',
  'Disco Duro Externo WD 2 TB', 'Laptop Gamer', 'Adaptador de Corriente Doble USB-C 35 W'
);

UPDATE Productos p
JOIN Categorias c ON c.nombre_categoria = 'Abarrotes'
SET p.id_categoria = c.id_categoria, p.modificado_en = NOW()
WHERE p.nombre IN ('Doritos Nacho 58 g');

UPDATE Productos p
JOIN Categorias c ON c.nombre_categoria = 'Cuidado Personal'
SET p.id_categoria = c.id_categoria, p.modificado_en = NOW()
WHERE p.nombre IN ('desodorante GUESS');

UPDATE Productos p
JOIN Categorias c ON c.nombre_categoria = 'Hogar'
SET p.id_categoria = c.id_categoria, p.modificado_en = NOW()
WHERE p.nombre IN ('Licuadora 1.5 L', 'Baraja de Póker');

-- 4) Borrar las categorías sobrantes (ya sin productos ni solicitudes)
DELETE c FROM Categorias c
WHERE c.nombre_categoria IN (
  'Botanas y Dulces', 'Carnes y Salchichonería', 'Frutas y Verduras', 'Lácteos y Huevo',
  'Panadería y Tortillería', 'Farmacia', 'Cuidado y Limpieza del Hogar', 'Electrodomésticos',
  'Juguetes y Entretenimiento', 'Mascotas', 'Papelería'
)
AND NOT EXISTS (SELECT 1 FROM Productos p WHERE p.id_categoria = c.id_categoria)
AND NOT EXISTS (SELECT 1 FROM Solicitudes_Productos s WHERE s.id_categoria = c.id_categoria);

-- 5) Inventario_Sucursal guarda una copia del nombre de la categoría: se sincroniza
UPDATE Inventario_Sucursal i
JOIN Productos p ON p.id_producto = i.id_producto
JOIN Categorias c ON c.id_categoria = p.id_categoria
SET i.Categoria_Producto = c.nombre_categoria;

-- Verificación 1: deben quedar solo las 6 categorías, cada una con sus productos
SELECT c.id_categoria, c.nombre_categoria, COUNT(p.id_producto) AS productos
FROM Categorias c
LEFT JOIN Productos p ON p.id_categoria = c.id_categoria
GROUP BY c.id_categoria, c.nombre_categoria
ORDER BY c.nombre_categoria;

-- Verificación 2: productos con su categoría, para revisar que ninguno quedó en una que no le toca
SELECT p.id_producto, p.nombre, c.nombre_categoria
FROM Productos p
LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
ORDER BY c.nombre_categoria, p.nombre;
