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

// Umbral de bajo inventario
$UMBRAL = 10;

$productos = [];
if ($gerente['id_sucursal'] !== null) {
    $stmt = $pdo->prepare("
        SELECT p.id_producto, p.codigo, p.nombre, p.imagen,
               c.nombre_categoria,
               i.cantidad_disponible, i.precio_venta
        FROM Inventario_Sucursal i
        INNER JOIN Productos p ON p.id_producto = i.id_producto
        LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
        WHERE i.id_sucursal = ?
          AND i.cantidad_disponible < ?
        ORDER BY i.cantidad_disponible ASC, p.nombre ASC
    ");
    $stmt->execute([$gerente['id_sucursal'], $UMBRAL]);
    $productos = $stmt->fetchAll();
}

$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bajo Inventario - Panel Gerente</title>
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
                <h1>Productos con bajo inventario</h1>
                <p>Sucursal: <?= htmlspecialchars($gerente['sucursal_nombre'] ?? 'Sin asignar') ?> — Umbral: menos de <?= $UMBRAL ?> unidades</p>
            </div>
        </div>

        <?php if ($gerente['id_sucursal'] === null): ?>
            <div class="alert alert-error">No tienes una sucursal asignada. Contacta al administrador.</div>
        <?php elseif (empty($productos)): ?>
            <div class="panel">
                <div class="empty-state">¡Todo bien! No hay productos con bajo inventario.</div>
            </div>
        <?php else: ?>
            <div class="panel">
                <table class="tabla-productos">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Clave</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $p): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($p['imagen'])): ?>
                                        <img class="producto-thumb" src="../<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
                                    <?php else: ?>
                                        <div class="producto-thumb producto-thumb--placeholder">Sin imagen</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['codigo']) ?></td>
                                <td>
                                    <div class="producto-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($p['nombre_categoria'] ?? 'Sin categoría') ?></td>
                                <td>$<?= number_format((float) $p['precio_venta'], 2) ?></td>
                                <td>
                                    <span class="badge badge-inactivo"><?= (int) $p['cantidad_disponible'] ?> unidades</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="../auth.js"></script>
</body>
</html>