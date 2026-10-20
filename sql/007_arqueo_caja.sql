-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Arqueo de caja: fondo fijo por caja y tabla de arqueos.
-- Requiere haber corrido antes sql/006_pago_combinado.sql (Ventas.monto_efectivo / monto_tarjeta).
-- Correr UNA sola vez (el ALTER TABLE falla si la columna ya existe).

-- 1) Fondo fijo de cada caja: el efectivo con el que arranca el turno.
--    Lo define el gerente en gerente/cajas.php; se resta del efectivo contado.
ALTER TABLE Cajas
  ADD COLUMN fondo_fijo DECIMAL(10,2) NOT NULL DEFAULT 0;

-- 2) Arqueos. Montos en pesos con 2 decimales (el código calcula en centavos).
CREATE TABLE IF NOT EXISTS Arqueos_Caja (
  id_arqueo           INT NOT NULL AUTO_INCREMENT,
  id_caja             INT NOT NULL,
  id_cajero           INT NOT NULL,
  id_sucursal         INT NOT NULL,
  estado              ENUM('Abierto', 'Cerrado', 'Cancelado') NOT NULL DEFAULT 'Abierto',

  -- Periodo: desde el cierre del último arqueo Cerrado de la caja hasta que se inicia este
  periodo_desde       DATETIME NULL,
  fecha_inicio        DATETIME NOT NULL,
  fecha_cierre        DATETIME NULL,

  -- Snapshot al iniciar (el cajero nunca lo ve antes de guardar: arqueo a ciegas)
  fondo_fijo          DECIMAL(10,2) NOT NULL DEFAULT 0,
  teorico_efectivo    DECIMAL(10,2) NOT NULL DEFAULT 0,  -- Σ Ventas.monto_efectivo del periodo
  teorico_tarjeta     DECIMAL(10,2) NOT NULL DEFAULT 0,  -- Σ Ventas.monto_tarjeta del periodo
  num_ventas          INT NOT NULL DEFAULT 0,

  -- Conteo físico. desglose_efectivo = piezas por denominación, con los ids de la caja:
  -- {"b20":3, "b50":0, ..., "b1000":1, "m050":4, "m1":10, ..., "m20":2}
  desglose_efectivo   JSON NULL,
  efectivo_contado    DECIMAL(10,2) NULL,
  vouchers            DECIMAL(10,2) NULL,

  -- Resultado (se llena al cerrar)
  saldo_fisico        DECIMAL(10,2) NULL,  -- efectivo_contado + vouchers - fondo_fijo
  saldo_teorico       DECIMAL(10,2) NULL,  -- teorico_efectivo + teorico_tarjeta
  diferencia          DECIMAL(10,2) NULL,  -- saldo_fisico - saldo_teorico
  diferencia_efectivo DECIMAL(10,2) NULL,  -- (efectivo_contado - fondo_fijo) - teorico_efectivo
  diferencia_tarjeta  DECIMAL(10,2) NULL,  -- vouchers - teorico_tarjeta
  resultado           ENUM('Cuadrada', 'Faltante', 'Sobrante') NULL,
  requiere_revision   TINYINT(1) NOT NULL DEFAULT 0,      -- |diferencia| > umbral ($50)

  -- Candado: solo un arqueo Abierto por caja (los NULL no chocan en un UNIQUE),
  -- así un doble clic nunca crea dos arqueos abiertos
  caja_abierta        INT AS (IF(estado = 'Abierto', id_caja, NULL)) STORED,

  PRIMARY KEY (id_arqueo),
  UNIQUE KEY uq_arqueo_abierto_por_caja (caja_abierta),
  KEY idx_arqueo_caja_estado (id_caja, estado),
  KEY idx_arqueo_sucursal_fecha (id_sucursal, fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Llaves foráneas. Si alguna marca error #150 / #3780, el tipo de la columna no coincide
--    con el de la tabla original (p. ej. INT vs INT UNSIGNED): corre
--    SHOW CREATE TABLE Cajas;  (o Usuarios / Sucursales)  y ajusta el tipo aquí arriba.
ALTER TABLE Arqueos_Caja
  ADD CONSTRAINT fk_arqueo_caja     FOREIGN KEY (id_caja)     REFERENCES Cajas (id_caja),
  ADD CONSTRAINT fk_arqueo_cajero   FOREIGN KEY (id_cajero)   REFERENCES Usuarios (id_usuario),
  ADD CONSTRAINT fk_arqueo_sucursal FOREIGN KEY (id_sucursal) REFERENCES Sucursales (id_sucursal);

-- Verificación
SELECT id_caja, numero_caja, nombre, fondo_fijo FROM Cajas ORDER BY id_sucursal, numero_caja;
SHOW CREATE TABLE Arqueos_Caja;
