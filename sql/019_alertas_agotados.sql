-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Avisos al gerente de productos agotados en su sucursal (campanita de notificaciones).
--
-- Una alerta nace de dos formas:
--   Venta    el cajero vendió la última pieza (cajero/registrar_venta.php).
--   Intento  el cajero quiso agregar a la venta un producto que ya estaba agotado
--            (cajero/avisar_agotado.php).
--
-- Para no saturar al gerente hay a lo más UNA alerta abierta por producto y día:
-- si vuelven a intentar venderlo (o el cajero le da clic varias veces), solo se
-- suma a "intentos" y no sale otra notificación. Al día siguiente, si sigue agotado
-- y lo vuelven a pedir, sale una nueva, como recordatorio.
-- La alerta se cierra sola cuando el producto vuelve a tener existencias (el gerente
-- ajusta el stock o se vende); si se vuelve a agotar, aunque sea el mismo día, sale otra.
--
-- abierta_el lleva el día mientras la alerta sigue abierta y queda en NULL al
-- cerrarse: así la llave única no choca con las alertas ya cerradas.
-- Las fechas van en hora de México, como Turnos_Caja. Se puede correr más de una vez.

CREATE TABLE IF NOT EXISTS Alertas_Agotados (
  id_alerta       INT       NOT NULL AUTO_INCREMENT,
  id_sucursal     INT       NOT NULL,
  id_producto     INT       NOT NULL,
  origen          ENUM('Venta', 'Intento') NOT NULL,
  id_cajero       INT       NULL,          -- quien vendió la última pieza o el primero que lo intentó
  id_venta        INT       NULL,          -- la venta que lo agotó (origen Venta)
  intentos        INT       NOT NULL DEFAULT 0,   -- veces que lo intentaron vender ya agotado
  ultimo_intento  DATETIME  NULL,
  creado_en       DATETIME  NOT NULL,
  abierta_el      DATE      NULL,          -- día de la alerta mientras sigue abierta
  resuelta_en     DATETIME  NULL,          -- cuando volvió a tener existencias

  PRIMARY KEY (id_alerta),
  UNIQUE KEY uk_alerta_abierta (id_sucursal, id_producto, abierta_el),
  KEY idx_alerta_sucursal_fecha (id_sucursal, creado_en),
  CONSTRAINT fk_alerta_sucursal FOREIGN KEY (id_sucursal) REFERENCES Sucursales (id_sucursal) ON DELETE CASCADE,
  CONSTRAINT fk_alerta_producto FOREIGN KEY (id_producto) REFERENCES Productos (id_producto) ON DELETE CASCADE,
  CONSTRAINT fk_alerta_cajero   FOREIGN KEY (id_cajero)   REFERENCES Usuarios (id_usuario)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación
SHOW CREATE TABLE Alertas_Agotados;
