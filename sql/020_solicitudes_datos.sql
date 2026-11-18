-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Cambios de nombre y correo con aprobación (perfil/guardar.php y admin/solicitudes_datos.php).
--
-- Desde "Mi perfil", el cajero y el gerente ya no cambian su nombre ni su correo
-- directamente: mandan una solicitud.
--   Cajero   la revisa el gerente de su sucursal (gerente/cajeros.php). Si la
--            sucursal no tiene gerente activo, la revisa la administración.
--   Gerente  la revisa la administración (admin/gerentes.php).
--   Admin    sigue cambiando sus datos directamente.
-- Quien revisa solo aprueba o rechaza; no puede editar lo que se pidió. Al rechazar,
-- el título es obligatorio y el motivo opcional. El resultado le llega a quien la
-- pidió en su campanita de notificaciones.
--
-- Hay a lo más UNA solicitud pendiente por persona: pendiente_de lleva el id del
-- usuario mientras está Pendiente y queda en NULL al resolverse o cancelarse, así
-- la llave única no choca con las ya resueltas.
-- La contraseña se sigue cambiando directo. Se puede correr más de una vez.

CREATE TABLE IF NOT EXISTS Solicitudes_Datos (
  id_solicitud      INT           NOT NULL AUTO_INCREMENT,
  id_usuario        INT           NOT NULL,              -- quien pide el cambio
  revisa            ENUM('Gerente', 'Admin') NOT NULL,
  id_sucursal       INT           NULL,                  -- la del cajero, para el gerente que revisa
  nombre_antes      VARCHAR(100)  NOT NULL,
  email_antes       VARCHAR(100)  NOT NULL,
  -- Lo que se pide; NULL en lo que no cambia
  nombre            VARCHAR(100)  NULL,                  -- completo, como Usuarios.nombre
  nombres           VARCHAR(60)   NULL,
  apellido_paterno  VARCHAR(40)   NULL,
  apellido_materno  VARCHAR(40)   NULL,
  email             VARCHAR(100)  NULL,
  estado            ENUM('Pendiente', 'Aprobada', 'Rechazada', 'Cancelada') NOT NULL DEFAULT 'Pendiente',
  rechazo_titulo    VARCHAR(80)   NULL,
  rechazo_motivo    TEXT          NULL,
  creado_en         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resuelto_en       TIMESTAMP     NULL DEFAULT NULL,
  resuelto_por      INT           NULL,
  pendiente_de      INT           NULL,

  PRIMARY KEY (id_solicitud),
  UNIQUE KEY uk_solicitud_datos_pendiente (pendiente_de),
  KEY idx_solicitud_datos_revisa (revisa, estado, id_sucursal),
  KEY idx_solicitud_datos_usuario (id_usuario, id_solicitud),
  CONSTRAINT fk_solicitud_datos_usuario  FOREIGN KEY (id_usuario)   REFERENCES Usuarios (id_usuario)    ON DELETE CASCADE,
  CONSTRAINT fk_solicitud_datos_sucursal FOREIGN KEY (id_sucursal)  REFERENCES Sucursales (id_sucursal) ON DELETE SET NULL,
  CONSTRAINT fk_solicitud_datos_revisor  FOREIGN KEY (resuelto_por) REFERENCES Usuarios (id_usuario)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación
SHOW CREATE TABLE Solicitudes_Datos;
