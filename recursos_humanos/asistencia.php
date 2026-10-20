<?php
session_start();
require_once __DIR__ . '/../admin/database.php';
$verCss = filemtime(__DIR__ . '/humanos.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencia - RRHH</title>
    <link rel="stylesheet" href="humanos.css?v=<?= $verCss ?>">
</head>
<body>
    <div class="header">
        <h1>Koaly - Asistencia</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></span>
            <button onclick="logout()">Cerrar sesión</button>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-link">← Volver al panel</a>

        <div class="page-header">
            <div>
                <h1>Control de Asistencia</h1>
                <p>Registro de entrada y salida del personal</p>
            </div>
        </div>

        <div class="panel">
            <div class="empty-state">Página en construcción</div>
        </div>
    </div>

    <script src="../auth.js"></script>
</body>
</html>