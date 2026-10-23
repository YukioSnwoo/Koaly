-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Caja estilo OXXO: retiros parciales a la tómbola, alerta de excedente de efectivo
-- y arqueo con el efectivo esperado en cualquier momento del turno.
-- Requiere sql/008_turnos_caja.sql. Se puede correr más de una vez.
--
-- Desde aquí, lo esperado en caja es:
--   efectivo de apertura + Σ Ventas.monto_efectivo del turno − Σ Retiros_Caja.monto del turno
-- y lo que el cajero entrega al gerente al cerrar es el retiro final más los retiros parciales.

-- 1) Límite de efectivo por caja: si la caja pasa de este monto, el cajero recibe la
--    alerta de "Excedente de efectivo" para hacer un retiro parcial.
--    Recomendado: $5,000 (el fondo base de $1,000 más $4,000). El gerente lo ajusta en Cajas.
ALTER TABLE Cajas
  ADD COLUMN IF NOT EXISTS limite_efectivo DECIMAL(10,2) NOT NULL DEFAULT 5000.00 AFTER fondo_fijo;
-- Cajas con un fondo base de $5,000 o más: el límite queda $4,000 arriba del fondo
UPDATE Cajas SET limite_efectivo = fondo_fijo + 4000.00 WHERE limite_efectivo <= fondo_fijo;

-- 2) Retiros parciales: cada sobre que el cajero mete a la tómbola durante el turno
CREATE TABLE IF NOT EXISTS Retiros_Caja (
  id_retiro  INT NOT NULL AUTO_INCREMENT,
  id_turno   INT NOT NULL,
  id_cajero  INT NOT NULL,
  fecha      DATETIME NOT NULL,
  monto      DECIMAL(10,2) NOT NULL,
  desglose   JSON NULL,                 -- piezas, con los ids de la caja: {"b500":4, "b200":1, ...}

  PRIMARY KEY (id_retiro),
  KEY idx_retiro_turno (id_turno),
  CONSTRAINT fk_retiro_turno  FOREIGN KEY (id_turno)  REFERENCES Turnos_Caja (id_turno),
  CONSTRAINT fk_retiro_cajero FOREIGN KEY (id_cajero) REFERENCES Usuarios (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) El corte guarda cuánto se retiró durante el turno (los cortes anteriores quedan en NULL = $0)
ALTER TABLE Turnos_Caja
  ADD COLUMN IF NOT EXISTS retiros_parciales DECIMAL(10,2) NULL AFTER teorico_tarjeta;

-- Verificación
SELECT id_caja, numero_caja, nombre, fondo_fijo, limite_efectivo FROM Cajas ORDER BY id_sucursal, numero_caja;
SHOW CREATE TABLE Retiros_Caja;
