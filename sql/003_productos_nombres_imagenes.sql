-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Renombra 12 productos de prueba, les asigna imagen, precio y descripción reales.
-- Antes, subir al servidor (carpeta /Imagenes) los archivos que se mencionan aquí.
-- Cada UPDATE busca el producto por su nombre actual; si alguno dice
-- "0 filas afectadas", ese nombre no coincide y hay que revisarlo.

UPDATE Productos SET
  nombre = 'Agua Mineral Topo Chico 1.5 L',
  descripcion = 'Agua mineral natural con gas, botella de 1.5 L',
  precio = 32.00,
  imagen = 'Imagenes/topo_chico1.5l.webp',
  id_categoria = (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Bebidas'),
  modificado_en = NOW()
WHERE nombre = 'Agua mineral 1.5 L';

UPDATE Productos SET
  nombre = 'AirPods Pro (2.ª generación)',
  descripcion = 'Audífonos inalámbricos Apple con cancelación activa de ruido y estuche de carga USB-C',
  precio = 4999.00,
  imagen = 'Imagenes/AirpodsPro2gen.jpeg',
  modificado_en = NOW()
WHERE nombre = 'Audífonos Bluetooth Pro';

UPDATE Productos SET
  nombre = 'Red Bull Bebida Energética 473 ml',
  descripcion = 'Bebida energética en lata de 473 ml',
  precio = 49.00,
  imagen = 'Imagenes/redbull.avif',
  id_categoria = (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Bebidas'),
  modificado_en = NOW()
WHERE nombre = 'Bebida energética 473 ml';

UPDATE Productos SET
  nombre = 'Starbucks Frappuccino Coffee 280 ml',
  descripcion = 'Bebida de café con leche lista para tomar, botella de vidrio de 280 ml',
  precio = 45.00,
  imagen = 'Imagenes/Starbucks_frapp280ml.avif',
  id_categoria = (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Bebidas'),
  modificado_en = NOW()
WHERE nombre = 'Cafetera de goteo 12 tazas';

UPDATE Productos SET
  nombre = 'Adaptador de Corriente Doble USB-C 35 W',
  descripcion = 'Cargador de pared Apple de 35 W con dos puertos USB-C',
  precio = 1299.00,
  imagen = 'Imagenes/Adaptador_corriente35w.jpeg',
  modificado_en = NOW()
WHERE nombre = 'Cargador rápido 65 W';

UPDATE Productos SET
  nombre = 'Cerveza Artesanal Colimita 355 ml',
  descripcion = 'Cerveza artesanal estilo lager de Colima, botella de 355 ml',
  precio = 45.00,
  imagen = 'Imagenes/Cerveza_colimita355ml.webp',
  id_categoria = (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Bebidas'),
  modificado_en = NOW()
WHERE nombre = 'Cerveza artesanal IPA 355 ml';

UPDATE Productos SET
  nombre = 'Disco Duro Externo WD 2 TB',
  descripcion = 'Disco duro portátil Western Digital de 2 TB, USB 3.0',
  precio = 1499.00,
  imagen = 'Imagenes/DiscoDuroEXT2TB.jpeg',
  modificado_en = NOW()
WHERE nombre = 'Disco Duro Externo 2 TB';

UPDATE Productos SET
  nombre = 'Doritos Nacho 58 g',
  descripcion = 'Totopos de maíz sabor queso nacho, bolsa de 58 g',
  precio = 22.00,
  imagen = 'Imagenes/DoritosNacho58g.webp',
  modificado_en = NOW()
WHERE nombre = 'Doritos#D';

UPDATE Productos SET
  nombre = 'Cable USB-C a Lightning (1 m)',
  descripcion = 'Cable Apple de USB-C a Lightning de 1 m para carga y datos',
  precio = 449.00,
  imagen = 'Imagenes/usbc-lighting.avif',
  modificado_en = NOW()
WHERE nombre = 'Hub USB-C 7 en 1';

UPDATE Productos SET
  nombre = 'Agua Natural Epura 1 L',
  descripcion = 'Agua purificada natural, botella de 1 L',
  precio = 14.00,
  imagen = 'Imagenes/aguaEPURA1LT.webp',
  id_categoria = (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Bebidas'),
  modificado_en = NOW()
WHERE nombre = 'Jeans corte recto';

UPDATE Productos SET
  nombre = 'Baraja de Póker',
  descripcion = 'Baraja inglesa de 54 naipes para póker',
  precio = 59.00,
  imagen = 'Imagenes/barajadepoker.webp',
  modificado_en = NOW()
WHERE nombre = 'Juego de sábanas Queen';

UPDATE Productos SET
  nombre = 'Jumex Único Fresco Naranja con Pulpa 960 ml',
  descripcion = 'Jugo de naranja con pulpa, botella de 960 ml',
  precio = 38.00,
  imagen = 'Imagenes/flavorNaranja-con-pulpa-format960ml.png.webp',
  id_categoria = (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Bebidas'),
  modificado_en = NOW()
WHERE nombre = 'Jugo de naranja 1 L';

-- Verificación: deben salir los 12 productos con su imagen
SELECT id_producto, codigo, nombre, precio, imagen
FROM Productos
WHERE imagen IN (
  'Imagenes/topo_chico1.5l.webp', 'Imagenes/AirpodsPro2gen.jpeg', 'Imagenes/redbull.avif',
  'Imagenes/Starbucks_frapp280ml.avif', 'Imagenes/Adaptador_corriente35w.jpeg',
  'Imagenes/Cerveza_colimita355ml.webp', 'Imagenes/DiscoDuroEXT2TB.jpeg',
  'Imagenes/DoritosNacho58g.webp', 'Imagenes/usbc-lighting.avif', 'Imagenes/aguaEPURA1LT.webp',
  'Imagenes/barajadepoker.webp', 'Imagenes/flavorNaranja-con-pulpa-format960ml.png.webp'
)
ORDER BY nombre;
