<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 2) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/database.php';

$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.id_rol, u.id_sucursal
    FROM Usuarios u
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

$sucursal = null;
if ($gerente['id_sucursal'] !== null) {
    $stmt = $pdo->prepare("
        SELECT id_sucursal, nombre, direccion, telefono, email_contacto, estado
        FROM Sucursales
        WHERE id_sucursal = ?
        LIMIT 1
    ");
    $stmt->execute([$gerente['id_sucursal']]);
    $sucursal = $stmt->fetch();
}

$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Sucursal - Panel Gerente</title>
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
        <a href="index.php" class="back-link">&larr; Volver al panel</a>

        <div class="page-header">
            <div>
                <h1>Información de mi sucursal</h1>
                <p>Datos de la sucursal a la que estás asignado</p>
            </div>
        </div>

        <?php if (!$sucursal): ?>
            <div class="alert alert-error">No tienes una sucursal asignada. Contacta al administrador.</div>
        <?php else: ?>
            <div class="panel" style="padding: 2rem;">
                <div class="form-group">
                    <label>Nombre</label>
                    <p><?= htmlspecialchars($sucursal['nombre']) ?></p>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <p><?= htmlspecialchars($sucursal['direccion'] ?? 'No registrada') ?></p>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <p><?= htmlspecialchars($sucursal['telefono'] ?? 'No registrado') ?></p>
                </div>
                <div class="form-group">
                    <label>Email de contacto</label>
                    <p><?= htmlspecialchars($sucursal['email_contacto'] ?? 'No registrado') ?></p>
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <p><span class="badge badge-activo"><?= htmlspecialchars($sucursal['estado']) ?></span></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="../auth.js"></script>
</body>
</html>