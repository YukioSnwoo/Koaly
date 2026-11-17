-- Ejecutar en phpMyAdmin (pestaña SQL) sobre la base u772860605_DATAK.
-- Nombre(s) y apellidos por separado en Usuarios.
--
--   - Los formularios de cajeros (gerente), gerentes (admin) y Mi perfil piden
--     Nombre(s), Apellido paterno y Apellido materno. Basta con uno de los dos
--     apellidos, pero no se pueden dejar vacíos los dos (por eso ambos son NULL).
--   - `nombre` se queda con el nombre completo ("María José López Hernández"):
--     reportes, tickets, notificaciones y correos lo siguen usando igual. El
--     PHP lo arma con las tres partes cada vez que se guarda.
--   - Sin esta migración el sistema funciona igual, solo con el nombre completo
--     (admin/reglas_nombre.php: hayColumnasApellidos).
--
-- Se puede correr más de una vez (IF NOT EXISTS es de MariaDB, la base del hosting).

ALTER TABLE Usuarios
  ADD COLUMN IF NOT EXISTS nombres          VARCHAR(60) NULL AFTER nombre,
  ADD COLUMN IF NOT EXISTS apellido_paterno VARCHAR(40) NULL AFTER nombres,
  ADD COLUMN IF NOT EXISTS apellido_materno VARCHAR(40) NULL AFTER apellido_paterno;

-- Separa los nombres que ya existen. Regla (la misma que partesNombre() en PHP):
--   3 palabras o más: las dos últimas son paterno y materno, el resto nombre(s)
--     "María José López Hernández" → María José | López | Hernández
--   2 palabras: nombre y paterno ("Juan Pérez" → Juan | Pérez)
--   1 palabra: solo nombre(s); los apellidos quedan vacíos hasta que lo editen
-- Solo toca filas sin separar, así que no pisa lo que ya se capturó a mano.
-- CHAR_LENGTH y no LENGTH: con acentos LENGTH cuenta bytes y cortaría mal.
UPDATE Usuarios
SET
  apellido_materno = IF(
    CHAR_LENGTH(TRIM(nombre)) - CHAR_LENGTH(REPLACE(TRIM(nombre), ' ', '')) >= 2,
    SUBSTRING_INDEX(TRIM(nombre), ' ', -1),
    NULL),
  apellido_paterno = CASE
    WHEN CHAR_LENGTH(TRIM(nombre)) - CHAR_LENGTH(REPLACE(TRIM(nombre), ' ', '')) >= 2
      THEN SUBSTRING_INDEX(SUBSTRING_INDEX(TRIM(nombre), ' ', -2), ' ', 1)
    WHEN CHAR_LENGTH(TRIM(nombre)) - CHAR_LENGTH(REPLACE(TRIM(nombre), ' ', '')) = 1
      THEN SUBSTRING_INDEX(TRIM(nombre), ' ', -1)
    ELSE NULL END,
  nombres = CASE
    WHEN CHAR_LENGTH(TRIM(nombre)) - CHAR_LENGTH(REPLACE(TRIM(nombre), ' ', '')) >= 2
      THEN LEFT(TRIM(nombre), CHAR_LENGTH(TRIM(nombre)) - CHAR_LENGTH(SUBSTRING_INDEX(TRIM(nombre), ' ', -2)) - 1)
    WHEN CHAR_LENGTH(TRIM(nombre)) - CHAR_LENGTH(REPLACE(TRIM(nombre), ' ', '')) = 1
      THEN SUBSTRING_INDEX(TRIM(nombre), ' ', 1)
    ELSE TRIM(nombre) END
WHERE nombres IS NULL;

-- Revisa cómo quedó. Los nombres con partículas ("Juan de la Cruz Pérez")
-- pueden quedar mal separados: se corrigen editando a esa persona en el panel.
SELECT id_usuario, nombre, nombres, apellido_paterno, apellido_materno
FROM Usuarios
ORDER BY id_rol, nombre;
