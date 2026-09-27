<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 2) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/database.php';

$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.id_rol, u.id_sucursal, s.nombre AS sucursal_nombre
    FROM Usuarios u
    LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.id_usuario = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['id_usuario']]);
$gerente = $stmt->fetch();

if (!$gerente) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Gerente</title>
    <link rel="stylesheet" href="gerente.css?v=<?= $verGerenteCss ?>">
</head>
<body>
    <div class="header">
        <h1>Koaly - Panel Gerente</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($gerente['nombre']) ?></span>
            <button onclick="logout()">Cerrar sesion</button>
        </div>
    </div>
    <div class="container">
        <div class="welcome">
            <h2>Bienvenido, <?= htmlspecialchars($gerente['nombre']) ?></h2>
            <p>Administracion de sucursal: <?= htmlspecialchars($gerente['sucursal_nombre'] ?? 'Sin asignar') ?></p>
        </div>
        <div class="cards">
            <a href="productos.php" class="card">
                <h3>Productos</h3>
                <p>Registrar, consultar, modificar y desactivar productos</p>
            </a>
            <a href="bajo_inventario.php" class="card">
                <h3>Bajo inventario</h3>
                <p>Consultar productos con existencias bajas en tu sucursal</p>
            </a>
            <a href="historial_ventas.php" class="card">
                <h3>Historial de ventas</h3>
                <p>Consultar todas las ventas realizadas en el sistema</p>
            </a>
            <a href="ventas_sucursal.php" class="card">
                <h3>Ventas de mi sucursal</h3>
                <p>Supervisar las ventas realizadas en tu sucursal</p>
            </a>
            <a href="sucursal.php" class="card">
                <h3>Mi sucursal</h3>
                <p>Consultar la información de tu sucursal asignada</p>
            </a>
        </div>
    </div>
    <script src="../auth.js"></script>
</body>
</html>