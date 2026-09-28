<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 2) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/../admin/csrf.php';

$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.id_rol, u.estado, u.id_sucursal,
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
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$idSucursalGerente = (int) $gerente['id_sucursal'];