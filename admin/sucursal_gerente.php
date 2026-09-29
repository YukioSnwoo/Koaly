<?php
// Reglas compartidas entre sucursales.php y gerentes.php.
// Requiere que la sesión ya esté iniciada (header.php lo hace).

require_once __DIR__ . '/csrf.php';

const SUCURSAL_ACTIVA = 'Activa';
// Valor que ya usaba sucursales.php para cualquier sucursal no activa.
const SUCURSAL_INACTIVA = 'Cierre_Definitivo';

// El usuario debe ser gerente y estar libre (o ya asignado a $idSucursal).
function gerenteAsignable(PDO $pdo, int $idGerente, int $idSucursal = 0): bool
{
    $stmt = $pdo->prepare("SELECT id_sucursal FROM Usuarios WHERE id_usuario = ? AND id_rol = 2");
    $stmt->execute([$idGerente]);
    $fila = $stmt->fetch();

    if (!$fila) {
        return false;
    }

    return $fila['id_sucursal'] === null
        || ($idSucursal > 0 && (int) $fila['id_sucursal'] === $idSucursal);
}

function sucursalSinGerente(PDO $pdo, int $idSucursal): bool
{
    $stmt = $pdo->prepare("
        SELECT 1 FROM Sucursales s
        WHERE s.id_sucursal = ?
          AND NOT EXISTS (SELECT 1 FROM Usuarios u WHERE u.id_sucursal = s.id_sucursal AND u.id_rol = 2)
    ");
    $stmt->execute([$idSucursal]);
    return $stmt->fetch() !== false;
}

// Cambia la sucursal de un gerente (0 = dejarlo sin sucursal) manteniendo la regla
// "sucursal activa solo con gerente": activa la nueva y desactiva la anterior si se queda sin gerente.
// Lanza RuntimeException si no es válido; el llamador debe hacer rollback ante cualquier excepción.
function asignarSucursalAGerente(PDO $pdo, int $idGerente, int $idSucursalNueva): void
{
    $stmt = $pdo->prepare("SELECT id_sucursal FROM Usuarios WHERE id_usuario = ? AND id_rol = 2");
    $stmt->execute([$idGerente]);
    $fila = $stmt->fetch();

    if (!$fila) {
        throw new RuntimeException('El gerente no existe.');
    }

    $idSucursalAnterior = (int) ($fila['id_sucursal'] ?? 0);
    if ($idSucursalNueva === $idSucursalAnterior) {
        return;
    }
    if ($idSucursalNueva > 0 && !sucursalSinGerente($pdo, $idSucursalNueva)) {
        throw new RuntimeException('La sucursal seleccionada no existe o ya tiene gerente.');
    }

    $hoy = date('Y-m-d');
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE Usuarios SET id_sucursal = ? WHERE id_usuario = ? AND id_rol = 2")
        ->execute([$idSucursalNueva > 0 ? $idSucursalNueva : null, $idGerente]);

    if ($idSucursalNueva > 0) {
        $pdo->prepare("UPDATE Sucursales SET estado = ?, fecha_inicio_estado = ? WHERE id_sucursal = ? AND estado <> ?")
            ->execute([SUCURSAL_ACTIVA, $hoy, $idSucursalNueva, SUCURSAL_ACTIVA]);
    }

    if ($idSucursalAnterior > 0) {
        $pdo->prepare("
            UPDATE Sucursales s
            SET s.estado = ?, s.fecha_inicio_estado = ?
            WHERE s.id_sucursal = ? AND s.estado <> ?
              AND NOT EXISTS (SELECT 1 FROM Usuarios u WHERE u.id_sucursal = s.id_sucursal AND u.id_rol = 2)
        ")->execute([SUCURSAL_INACTIVA, $hoy, $idSucursalAnterior, SUCURSAL_INACTIVA]);
    }

    $pdo->commit();
}
