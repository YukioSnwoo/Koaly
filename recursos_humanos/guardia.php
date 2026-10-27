<?php
// Acceso al módulo de RRHH (rol 4): sesión activa con el rol, usuario activo y
// sucursal activa si tiene una. Si no, de vuelta al inicio de sesión.
// Deja en $usuario los datos para la cabecera.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario']) || (int) ($_SESSION['id_rol'] ?? 0) !== 4) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/database.php';

$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.estado, u.id_sucursal,
           s.nombre AS sucursal_nombre, s.estado AS sucursal_estado
    FROM Usuarios u
    LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.id_usuario = ? AND u.id_rol = 4
    LIMIT 1
");
$stmt->execute([$_SESSION['id_usuario']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || $usuario['estado'] !== 'Activo'
    || ($usuario['id_sucursal'] !== null && $usuario['sucursal_estado'] !== 'Activa')) {
    header('Location: ../logout.php');
    exit;
}
