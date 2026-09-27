<?php
session_start();
require_once __DIR__ . '/../admin/database.php';
$verCss = filemtime(__DIR__ . '/humanos.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Empleados - RRHH</title>
    <link rel="stylesheet" href="humanos.css?v=<?= $verCss ?>">
</head>
<body>
    <div class="header">
        <h1>Koaly - Empleados</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></span>
            <button onclick="logout()">Cerrar sesión</button>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← Volver al panel</a>

        <div class="page-header">
            <div>
                <h1>Gestión de Empleados</h1>
                <p>Lista de empleados registrados en el sistema</p>
            </div>
        </div>

        <div class="panel">
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="4" class="empty-state">Página en construcción</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../auth.js"></script>
</body>
</html>