-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK,
-- ANTES o justo después de subir el cambio "la caja cobra el precio de venta
-- de la sucursal" (PR #87).
--
-- Desde ese cambio la caja cobra Inventario_Sucursal.precio_venta (el que pone
-- el gerente) y solo si vale 0 usa Productos.precio (el del catálogo del admin).
-- Antes cobraba siempre el del catálogo. Si los dos no coinciden, al subir el
-- cambio el cliente empieza a pagar otro precio sin que nadie lo note.
--
-- Pasos: 1) correr la consulta de revisión; 2) decidir; 3) corregir;
-- 4) volver a correr la revisión (no debe salir nada que no se haya querido).

-- ------------------------------------------------------------------
-- 1) Revisión: productos activos cuyo precio en caja va a cambiar
--    precio_catalogo = lo que cobraba la caja hasta ahora
--    precio_sucursal = lo que cobrará desde el cambio
-- ------------------------------------------------------------------
SELECT s.nombre                          AS sucursal,
       p.codigo,
       p.nombre                          AS producto,
       p.precio                          AS precio_catalogo,
       i.precio_venta                    AS precio_sucursal,
       i.precio_venta - p.precio         AS diferencia,
       i.modificado_en                   AS sucursal_modificado,
       p.modificado_en                   AS catalogo_modificado
FROM Inventario_Sucursal i
INNER JOIN Productos p  ON p.id_producto = i.id_producto
INNER JOIN Sucursales s ON s.id_sucursal = i.id_sucursal
WHERE p.estado = 'Activo'
  AND i.precio_venta <> 0
  AND i.precio_venta <> p.precio
ORDER BY s.nombre, ABS(i.precio_venta - p.precio) DESC;

-- Productos con precio de sucursal en 0 (la caja usa el del catálogo; se
-- recomienda llenarlos para que la ficha del gerente muestre el precio real)
SELECT s.nombre AS sucursal, p.codigo, p.nombre AS producto, p.precio AS precio_catalogo
FROM Inventario_Sucursal i
INNER JOIN Productos p  ON p.id_producto = i.id_producto
INNER JOIN Sucursales s ON s.id_sucursal = i.id_sucursal
WHERE p.estado = 'Activo' AND i.precio_venta = 0
ORDER BY s.nombre, p.nombre;

-- ------------------------------------------------------------------
-- 2) y 3) Corrección
-- ------------------------------------------------------------------

-- a) Precio de sucursal en 0 → se copia el del catálogo (no cambia lo que
--    cobra la caja; solo deja el dato limpio). Seguro de correr.
UPDATE Inventario_Sucursal i
INNER JOIN Productos p ON p.id_producto = i.id_producto
SET i.precio_venta = p.precio
WHERE i.precio_venta = 0 AND p.precio > 0;

-- b) Precios que no coinciden. Hay que decidir cuál es el bueno:
--    - Si el gerente lo cambió a propósito (sucursal_modificado más reciente
--      y la diferencia tiene sentido), se deja: el nuevo comportamiento es el
--      correcto y no se corre nada.
--    - Si es un dato viejo (p. ej. el admin subió el precio del catálogo y la
--      sucursal se quedó con el anterior), se iguala al catálogo.
--
--    Uno por uno (recomendado): cambiar el código y la sucursal.
-- UPDATE Inventario_Sucursal i
-- INNER JOIN Productos p ON p.id_producto = i.id_producto
-- SET i.precio_venta = p.precio
-- WHERE p.codigo = 'CODIGO_AQUI' AND i.id_sucursal = 1;
--
--    Todos de golpe (la caja sigue cobrando exactamente lo de antes).
--    OJO: borra los precios que el gerente haya puesto a propósito.
-- UPDATE Inventario_Sucursal i
-- INNER JOIN Productos p ON p.id_producto = i.id_producto
-- SET i.precio_venta = p.precio
-- WHERE p.estado = 'Activo' AND i.precio_venta <> p.precio AND p.precio > 0;
