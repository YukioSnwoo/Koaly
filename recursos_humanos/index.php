<?php
require_once __DIR__ . '/guardia.php';

$verCss = filemtime(__DIR__ . '/humanos.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel RRHH</title>
    <link rel="stylesheet" href="humanos.css?v=<?= $verCss ?>">
</head>
<body>
    <div class="header">
        <h1>Koaly - Recursos Humanos</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($usuario['nombre']) ?></span>
            <a href="../logout.php" class="boton-salir">Cerrar sesión</a>
        </div>
    </div>
    <div class="container">
        <div class="welcome">
            <h2>Bienvenido, <?= htmlspecialchars($usuario['nombre']) ?></h2>
            <p>Sucursal: <?= htmlspecialchars($usuario['sucursal_nombre'] ?? 'Sin asignar') ?></p>
        </div>
        <div class="cards">
            <a href="empleados.php" class="card">
                <h3>Empleados</h3>
                <p>Consultar y gestionar empleados</p>
            </a>
            <a href="asistencia.php" class="card">
                <h3>Asistencia</h3>
                <p>Control de asistencia del personal</p>
            </a>
            <a href="nomina.php" class="card">
                <h3>Nomina</h3>
                <p>Gestion de nomina</p>
            </a>
        </div>
    </div>
</body>
</html>