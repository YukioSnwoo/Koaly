-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Pago combinado en la caja: una parte con tarjeta y el resto en efectivo.
-- Correr UNA sola vez (el ALTER TABLE falla si las columnas ya existen).

-- 1) Método de pago "Varios" (solo se crea si no existe).
--    Los reportes del gerente y del admin lo muestran como un método más.
INSERT INTO Metodos_Pago (nombre_metodo)
SELECT 'Varios'
WHERE NOT EXISTS (SELECT 1 FROM Metodos_Pago WHERE nombre_metodo = 'Varios');

-- 2) Cuánto se cobró con cada método en cada venta.
--    monto_efectivo es el efectivo neto (lo recibido menos el cambio);
--    monto_efectivo + monto_tarjeta = total.
ALTER TABLE Ventas
  ADD COLUMN monto_efectivo DECIMAL(10,2) NULL AFTER total,
  ADD COLUMN monto_tarjeta  DECIMAL(10,2) NULL AFTER monto_efectivo;

-- 3) Ventas anteriores: todo en efectivo o todo con tarjeta, según su método
--    (las de otros métodos, como transferencia, se quedan en NULL)
UPDATE Ventas v
JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
SET v.monto_efectivo = v.total, v.monto_tarjeta = 0
WHERE m.nombre_metodo = 'Efectivo';

UPDATE Ventas v
JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
SET v.monto_efectivo = 0, v.monto_tarjeta = v.total
WHERE m.nombre_metodo LIKE 'Tarjeta%';

-- Verificación: métodos de pago y totales por método
SELECT m.id_metodo_pago, m.nombre_metodo, COUNT(v.id_venta) AS ventas,
       SUM(v.monto_efectivo) AS efectivo, SUM(v.monto_tarjeta) AS tarjeta
FROM Metodos_Pago m
LEFT JOIN Ventas v ON v.id_metodo_pago = m.id_metodo_pago
GROUP BY m.id_metodo_pago, m.nombre_metodo
ORDER BY m.nombre_metodo;
