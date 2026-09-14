<?php
// header.php — Inicio de cada página del admin
session_start();

// Protección de acceso
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] !== 1) {
    header('Location: ../index.php');
    exit;
}

// Conexión a BD
require_once __DIR__ . '/database.php';

// Datos del usuario en sesión
$stmt = $pdo->prepare("SELECT id_usuario, nombre, email, id_rol, id_sucursal FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$_SESSION['id_usuario']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Idioma
if (!isset($_SESSION['idioma'])) $_SESSION['idioma'] = 'es';
$idioma = $_SESSION['idioma'];

$textos = [
    'es' => [
        'panel_general' => 'Panel general', 'sucursales' => 'Sucursales',
        'gerentes' => 'Gerentes', 'inventario' => 'Inventario global',
        'ventas' => 'Ventas', 'reportes' => 'Reportes',
        'configuracion' => 'Configuración', 'cerrar_sesion' => 'Cerrar sesión',
    ],
    'en' => [
        'panel_general' => 'Dashboard', 'sucursales' => 'Branches',
        'gerentes' => 'Managers', 'inventario' => 'Global inventory',
        'ventas' => 'Sales', 'reportes' => 'Reports',
        'configuracion' => 'Settings', 'cerrar_sesion' => 'Log out',
    ],
];
$t = $textos[$idioma] ?? $textos['es'];

$pagina_actual = basename($_SERVER['PHP_SELF']);

// Iniciales del avatar
$iniciales = '';
$palabras = explode(' ', $admin['nombre']);
foreach ($palabras as $p) $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
$iniciales = mb_substr($iniciales, 0, 2);
?>
<!DOCTYPE html>
<html lang="<?= $idioma ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koalicius · Admin</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
