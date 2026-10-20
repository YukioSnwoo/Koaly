-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Turnos de caja: abrir caja (cuánto hay), cerrar caja (corte a ciegas, cuánto se deja y
-- cuánto se entrega) y revisión del corte por el gerente.
-- Reemplaza la tabla Arqueos_Caja de sql/007 (nunca se usó) y conserva Cajas.fondo_fijo.
-- Correr UNA sola vez, después del 006 y el 007.

-- 0) Revisión previa: Arqueos_Caja debe estar vacía (ningún código la ha usado).
--    Si esto da más de 0, NO sigas y avísame.
SELECT COUNT(*) AS arqueos_guardados FROM Arqueos_Caja;

-- 1) Se quita la tabla del 007; los turnos guardan la apertura y el cierre juntos
DROP TABLE IF EXISTS Arqueos_Caja;

-- 2) Fondo base recomendado: $1,000 por caja (también para cajas y sucursales nuevas).
--    El gerente lo puede ajustar por caja.
ALTER TABLE Cajas
  MODIFY fondo_fijo DECIMAL(10,2) NOT NULL DEFAULT 1000.00;
UPDATE Cajas SET fondo_fijo = 1000.00 WHERE fondo_fijo = 0;

-- 3) Turnos. Montos en pesos con 2 decimales (el código calcula en centavos).
CREATE TABLE IF NOT EXISTS Turnos_Caja (
  id_turno             INT NOT NULL AUTO_INCREMENT,
  id_caja              INT NOT NULL,
  id_sucursal          INT NOT NULL,
  estado               ENUM('Abierto', 'Cerrado') NOT NULL DEFAULT 'Abierto',

  -- Apertura: el cajero confirma o corrige lo que dejó el turno anterior
  id_cajero_apertura   INT NOT NULL,
  fecha_apertura       DATETIME NOT NULL,
  efectivo_anterior    DECIMAL(10,2) NULL,           -- lo que dejó el turno anterior (NULL si es el primero)
  efectivo_apertura    DECIMAL(10,2) NOT NULL,       -- lo que hay al abrir
  desglose_apertura    JSON NULL,                    -- piezas, si se contó con billetes y monedas
  diferencia_apertura  DECIMAL(10,2) NULL,           -- efectivo_apertura - efectivo_anterior

  -- Cierre: conteo a ciegas (el cajero no ve lo esperado antes de guardar)
  id_cajero_cierre     INT NULL,
  fecha_cierre         DATETIME NULL,
  num_ventas           INT NULL,
  teorico_efectivo     DECIMAL(10,2) NULL,           -- Σ Ventas.monto_efectivo del turno
  teorico_tarjeta      DECIMAL(10,2) NULL,           -- Σ Ventas.monto_tarjeta del turno
  desglose_cierre      JSON NULL,                    -- {"b20":3, ..., "m050":4, ...}
  efectivo_contado     DECIMAL(10,2) NULL,
  vouchers             DECIMAL(10,2) NULL,
  efectivo_esperado    DECIMAL(10,2) NULL,           -- efectivo_apertura + teorico_efectivo
  diferencia_efectivo  DECIMAL(10,2) NULL,           -- efectivo_contado - efectivo_esperado
  diferencia_tarjeta   DECIMAL(10,2) NULL,           -- vouchers - teorico_tarjeta
  diferencia           DECIMAL(10,2) NULL,           -- diferencia_efectivo + diferencia_tarjeta
  resultado            ENUM('Cuadrada', 'Faltante', 'Sobrante') NULL,
  requiere_revision    TINYINT(1) NOT NULL DEFAULT 0, -- |diferencia| o |diferencia_apertura| > umbral ($50)

  -- Lo que pasa al siguiente turno y lo que se entrega al gerente
  efectivo_dejado      DECIMAL(10,2) NULL,           -- sugerido: Cajas.fondo_fijo
  efectivo_retirado    DECIMAL(10,2) NULL,           -- efectivo_contado - efectivo_dejado

  -- Revisión del gerente: confirma que recibió el retiro
  estado_revision      ENUM('Pendiente', 'Recibido') NULL,   -- 'Pendiente' al cerrar
  id_gerente_revision  INT NULL,
  fecha_revision       DATETIME NULL,
  monto_recibido       DECIMAL(10,2) NULL,
  nota_revision        VARCHAR(255) NULL,

  -- Candado: solo un turno Abierto por caja (los NULL no chocan en un UNIQUE)
  caja_abierta         INT AS (IF(estado = 'Abierto', id_caja, NULL)) STORED,

  PRIMARY KEY (id_turno),
  UNIQUE KEY uq_turno_abierto_por_caja (caja_abierta),
  KEY idx_turno_caja_estado (id_caja, estado),
  KEY idx_turno_sucursal_cierre (id_sucursal, fecha_cierre),
  KEY idx_turno_revision (id_sucursal, estado_revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE Turnos_Caja
  ADD CONSTRAINT fk_turno_caja        FOREIGN KEY (id_caja)             REFERENCES Cajas (id_caja),
  ADD CONSTRAINT fk_turno_sucursal    FOREIGN KEY (id_sucursal)         REFERENCES Sucursales (id_sucursal),
  ADD CONSTRAINT fk_turno_cajero_ab   FOREIGN KEY (id_cajero_apertura)  REFERENCES Usuarios (id_usuario),
  ADD CONSTRAINT fk_turno_cajero_ci   FOREIGN KEY (id_cajero_cierre)    REFERENCES Usuarios (id_usuario),
  ADD CONSTRAINT fk_turno_gerente     FOREIGN KEY (id_gerente_revision) REFERENCES Usuarios (id_usuario);

-- 4) Cada venta queda ligada al turno en que se cobró (las anteriores se quedan en NULL)
ALTER TABLE Ventas
  ADD COLUMN id_turno INT NULL,
  ADD KEY idx_ventas_turno (id_turno),
  ADD CONSTRAINT fk_ventas_turno FOREIGN KEY (id_turno) REFERENCES Turnos_Caja (id_turno);

-- Verificación
SELECT id_caja, numero_caja, nombre, fondo_fijo FROM Cajas ORDER BY id_sucursal, numero_caja;
SHOW CREATE TABLE Turnos_Caja;
