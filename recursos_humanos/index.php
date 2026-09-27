<?php
session_start();
require_once __DIR__ . '/../admin/database.php';

$usuario = null;
if (isset($_SESSION['id_usuario'])) {
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.id_rol, u.id_sucursal, s.nombre AS sucursal_nombre
        FROM Usuarios u
        LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
        WHERE u.id_usuario = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['id_usuario']]);
    $usuario = $stmt->fetch();
}

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
            <span><?= htmlspecialchars($usuario['nombre'] ?? 'Usuario') ?></span>
            <button onclick="logout()">Cerrar sesión</button>
        </div>
    </div>
    <div class="container">
        <div class="welcome">
            <h2>Bienvenido, <?= htmlspecialchars($usuario['nombre'] ?? 'Usuario') ?></h2>
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
    <script src="../auth.js"></script>
    <script>
        if (!<?= $usuario ? 'true' : 'false' ?>) {
            const userJS = requireRol(4);
            if (userJS) {
                document.querySelector('.user-info span').textContent = userJS.nombre;
                document.querySelector('.welcome h2').textContent = 'Bienvenida, ' + userJS.nombre;
            }
        }
    </script>
</body>
</html>