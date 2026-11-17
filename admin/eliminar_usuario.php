<?php
/**
 * Eliminar permanentemente una cuenta ya dada de baja (gerente desde el admin,
 * cajero desde el panel del gerente). Dar de baja solo cambia el estado y el
 * correo sigue ocupado; esto borra la fila de Usuarios para liberarlo.
 *
 * Solo se borra a quien no dejó rastro. Ventas, turnos, retiros, solicitudes y
 * movimientos de inventario apuntan al usuario con llave foránea: si hay alguno,
 * MySQL rechaza el DELETE (1451) y la cuenta debe quedarse en Baja para no
 * perder ese historial.
 */

require_once __DIR__ . '/../perfil/perfil.php';   // PERFIL_CARPETA_FOTOS

/** La cuenta tiene historial y no se puede borrar. */
final class CuentaConHistorial extends RuntimeException
{
}

/**
 * Borra la cuenta $idUsuario con rol $idRol. Quien llama ya revisó que exista,
 * que le toque (sucursal) y que esté en Baja. Abre y cierra su propia transacción.
 *
 * @throws CuentaConHistorial si otra tabla la usa
 */
function eliminarCuentaDadaDeBaja(PDO $pdo, int $idUsuario, int $idRol): void
{
    $stmt = $pdo->prepare("SELECT email FROM Usuarios WHERE id_usuario = ? AND id_rol = ? AND estado = 'Baja'");
    $stmt->execute([$idUsuario, $idRol]);
    $email = $stmt->fetchColumn();
    if ($email === false) {
        throw new RuntimeException('eliminarCuentaDadaDeBaja: la cuenta no existe o no está en Baja');
    }

    // Foto de perfil (sql/011); sin la columna no hay foto que borrar
    $foto = consultaOpcional($pdo, "SELECT foto FROM Usuarios WHERE id_usuario = ?", [$idUsuario]);

    // El historial de solicitudes no tiene llave foránea: se revisa a mano
    if (consultaOpcional($pdo, "SELECT 1 FROM Solicitudes_Productos_Historial WHERE id_usuario = ? LIMIT 1", [$idUsuario])) {
        throw new CuentaConHistorial();
    }

    $pdo->beginTransaction();
    try {
        // Tablas que lo mencionan sin llave foránea (o que no deben frenar el borrado)
        foreach ([
            ["UPDATE Cajas SET estado = 'Inactiva', id_cajero_asignado = NULL WHERE id_cajero_asignado = ?", $idUsuario],
            ['DELETE FROM Historial_Estado_Gerente WHERE id_gerente = ?', $idUsuario],
            ['DELETE FROM avisos_privacidad WHERE id_usuario = ?', $idUsuario],
            ['DELETE FROM password_resets WHERE email = ?', $email],
            ['DELETE FROM login_intentos WHERE email = ?', $email],
        ] as [$sql, $valor]) {
            ejecutarOpcional($pdo, $sql, [$valor]);
        }

        try {
            $pdo->prepare("DELETE FROM Usuarios WHERE id_usuario = ? AND id_rol = ?")->execute([$idUsuario, $idRol]);
        } catch (PDOException $e) {
            // 1451: ventas, turnos, solicitudes... apuntan a este usuario
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                throw new CuentaConHistorial();
            }
            throw $e;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($foto && str_starts_with((string) $foto, PERFIL_CARPETA_FOTOS . '/')) {
        @unlink(__DIR__ . '/../' . $foto);
    }
}

/** Ejecuta la sentencia; si la tabla o la columna aún no existen (migración pendiente), no hace nada. */
function ejecutarOpcional(PDO $pdo, string $sql, array $params): void
{
    try {
        $pdo->prepare($sql)->execute($params);
    } catch (PDOException $e) {
        // 42S02: no existe la tabla · 42S22: no existe la columna. En MySQL no abortan la transacción.
        if (!in_array($e->errorInfo[0] ?? '', ['42S02', '42S22'], true)) {
            throw $e;
        }
    }
}

/** Primer valor de la consulta, o null si no hay filas o falta la tabla o la columna. */
function consultaOpcional(PDO $pdo, string $sql, array $params): mixed
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $valor = $stmt->fetchColumn();
        return $valor === false ? null : $valor;
    } catch (PDOException $e) {
        if (!in_array($e->errorInfo[0] ?? '', ['42S02', '42S22'], true)) {
            throw $e;
        }
        return null;
    }
}
