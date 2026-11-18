-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Avisos de privacidad editables desde el panel del admin (admin/avisos.php).
--
--   avisos_versiones       cada versión publicada de cada aviso (cajeros, gerentes,
--                          clientes). Nunca se borra ni se edita una versión: la que
--                          aceptó alguien tiene que poder leerse igual años después.
--                          La vigente de cada tipo es la última publicada; mientras
--                          no haya ninguna, se usa el texto del código (privacidad/aviso.php).
--   avisos_notificaciones  a quién ya se le mandó el correo de "el aviso cambió" de
--                          cada versión, para no mandárselo dos veces.
--
-- Las aceptaciones siguen en avisos_privacidad (sql/010): su columna version guarda
-- la versión de esta tabla (o la del código). Se puede correr más de una vez.

CREATE TABLE IF NOT EXISTS avisos_versiones (
  id             INT          NOT NULL AUTO_INCREMENT,
  tipo           ENUM('empleado', 'gerente', 'cliente') NOT NULL,   -- empleado = cajeros
  version        VARCHAR(20)  NOT NULL,      -- "2026-11-18"; la de gerentes con "G-"; ".2" si hubo otra ese día
  texto_es       MEDIUMTEXT   NOT NULL,      -- el aviso en el formato del editor (privacidad/formato.php)
  texto_en       MEDIUMTEXT   NULL,          -- traducción; sin ella, en inglés se muestra el español
  cambios_es     TEXT         NULL,          -- qué cambió, un punto por renglón
  cambios_en     TEXT         NULL,
  publicado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  publicado_por  INT          NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_aviso_version (tipo, version),
  CONSTRAINT fk_aviso_version_admin FOREIGN KEY (publicado_por) REFERENCES Usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avisos_notificaciones (
  id_usuario  INT          NOT NULL,
  version     VARCHAR(20)  NOT NULL,
  enviado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario, version),
  CONSTRAINT fk_aviso_notificacion_usuario FOREIGN KEY (id_usuario) REFERENCES Usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
