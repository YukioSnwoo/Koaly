<?php
session_start();
require_once '../admin/database.php';

if (!isset($_SESSION['id_usuario']) || (int) ($_SESSION['id_rol'] ?? 0) !== 3) {
    header('Location: ../login.html');
    exit;
}

$termino = trim((string) ($_GET['buscar'] ?? ''));
$productos = [];
$mensajeBusqueda = '';

if ($termino === '') {
    $mensajeBusqueda = 'Escribe el nombre o la clave del producto para comenzar.';
} else {
    $stmt = $pdo->prepare(
        "SELECT p.id_producto AS clave, p.nombre, ip.cantidad_disponible,
                p.precio, COALESCE(p.descuento, 0) AS descuento
         FROM Inventario_Sucursal ip
         INNER JOIN Productos p ON p.id_producto = ip.id_producto
         WHERE ip.id_sucursal = :id_sucursal
           AND (CAST(p.id_producto AS CHAR) = :clave OR p.nombre LIKE :nombre)
         ORDER BY CASE WHEN CAST(p.id_producto AS CHAR) = :clave_exacta THEN 0 ELSE 1 END,
                  p.nombre
         LIMIT 50"
    );
    $stmt->execute([
        ':id_sucursal' => $_SESSION['id_sucursal'] ?? 0,
        ':clave' => $termino,
        ':nombre' => "%$termino%",
        ':clave_exacta' => $termino
    ]);
    $productos = $stmt->fetchAll();

    if (empty($productos)) {
        $mensajeBusqueda = 'No encontramos productos con ese nombre o clave.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - Cajero</title>
    <link rel="stylesheet" href="cajero.css">
</head>
<body>
    <div class="header">
        <h1>Koaly - Punto de Venta</h1>
        <div class="user-info">
            <span id="userName"></span>
            <button id="logoutButton" type="button">Cerrar sesión</button>
        </div>
    </div>
    <div class="container">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Punto de venta</p>
                <h2>Buscar producto</h2>
                <p class="muted">Consulta por nombre o clave dentro del inventario de tu sucursal.</p>
            </div>
            <span class="branch" id="sucursalName">Sucursal</span>
        </div>
        <form class="search-panel" method="get" role="search">
            <label for="productSearch">Nombre o clave del producto</label>
            <div class="search-row">
                <input id="productSearch" name="buscar" type="search" value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. café o 1042" autocomplete="off">
                <button type="submit">Buscar</button>
            </div>
        </form>
        <section class="results" aria-live="polite">
            <div class="results-heading">
                <h3>Resultados</h3>
                <?php if ($termino !== '' && !empty($productos)): ?><span><?= count($productos) ?> coincidencia<?= count($productos) === 1 ? '' : 's' ?></span><?php endif; ?>
            </div>
            <?php if (empty($productos)): ?>
                <p class="empty-state"><?= htmlspecialchars($mensajeBusqueda, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Clave</th><th>Producto</th><th>Existencias</th><th>Precio</th><th>Descuento</th></tr></thead>
                        <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td class="product-key">#<?= htmlspecialchars($producto['clave']) ?></td>
                                <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                <td><?= htmlspecialchars($producto['cantidad_disponible']) ?></td>
                                <td>$<?= number_format((float) $producto['precio'], 2) ?></td>
                                <td><?= number_format((float) $producto['descuento'], 2) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="cajero.js"></script>
</body>
</html>
