-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Costo de los productos, para calcular ganancia y margen (gerente: Ventas,
-- Gráficas, panel y ficha del producto; ver admin/metricas_ventas.php).
--
--   - Inventario_Sucursal.costo: lo que le cuesta el producto a la sucursal,
--     CON IVA (igual que precio_venta). Lo captura el gerente en la ficha del
--     producto. NULL = aún no se captura (nunca se supone costo 0).
--   - Detalle_Venta.costo_unitario: copia del costo al momento de vender
--     (cajero/registrar_venta.php). Así la ganancia de una venta no cambia
--     si después cambia el costo.
--
-- Las ventas de antes quedan con costo_unitario NULL: no se sabe qué costo
-- tenían y no se inventa. Mientras falten costos, las pantallas muestran
-- qué parte de la venta sí tiene costo ("cobertura").
--
-- El código revisa si estas columnas existen: sin este script todo funciona,
-- pero sin ganancia. Se puede correr más de una vez (IF NOT EXISTS es de MariaDB).

ALTER TABLE Inventario_Sucursal
  ADD COLUMN IF NOT EXISTS costo DECIMAL(10,2) NULL DEFAULT NULL
    CHECK (costo >= 0)
    AFTER precio_venta;

ALTER TABLE Detalle_Venta
  ADD COLUMN IF NOT EXISTS costo_unitario DECIMAL(10,2) NULL DEFAULT NULL
    CHECK (costo_unitario >= 0)
    AFTER precio_unitario;

-- Verificación: deben salir las dos columnas, tipo decimal(10,2), Null = YES
SHOW COLUMNS FROM Inventario_Sucursal LIKE 'costo';
SHOW COLUMNS FROM Detalle_Venta LIKE 'costo_unitario';
