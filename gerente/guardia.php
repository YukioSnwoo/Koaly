<?php
// Guardia de las páginas del gerente. En cada petición valida contra la base de datos
// que el gerente siga activo y que su sucursal exista y esté activa; si no, cierra su sesión.
// Deja disponibles: $pdo, $gerente e $idSucursalGerente.

session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 2) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/../admin/csrf.php';

$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.estado, u.id_sucursal,
           s.nombre AS sucursal_nombre, s.estado AS sucursal_estado
    FROM Usuarios u
    LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.id_usuario = ? AND u.id_rol = 2
    LIMIT 1
");
$stmt->execute([$_SESSION['id_usuario']]);
$gerente = $stmt->fetch();

if (
    !$gerente
    || $gerente['estado'] !== 'Activo'
    || $gerente['id_sucursal'] === null
    || $gerente['sucursal_estado'] !== 'Activa'
) {
    $_SESSION = [];
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$idSucursalGerente = (int) $gerente['id_sucursal'];
