<?php
require_once __DIR__ . '/guardia.php';

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
            <div class="card card--disabled">
                <h3>Inventario</h3>
                <p>Controlar existencias y actualizar stock (próximamente)</p>
            </div>
            <div class="card card--disabled">
                <h3>Cajas</h3>
                <p>Registrar y eliminar cajas de la sucursal (próximamente)</p>
            </div>
            <div class="card card--disabled">
                <h3>Ventas</h3>
                <p>Supervisar historial de ventas de la sucursal (próximamente)</p>
            </div>
        </div>
    </div>
    <script src="../auth.js"></script>
</body>
</html>
