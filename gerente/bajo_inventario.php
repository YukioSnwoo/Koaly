<?php
require_once __DIR__ . '/guardia.php';

$UMBRAL = 10;

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
$stmt->execute([$idSucursalGerente, $UMBRAL]);
$productos = $stmt->fetchAll();

$totalBajo = count($productos);

$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
$verGerenteJs  = filemtime(__DIR__ . '/gerente.js');
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
    <header class="header">
        <h1>Koaly - Panel Gerente</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($gerente['nombre']) ?></span>
            <button type="button" onclick="if (confirm('¿Seguro que deseas cerrar sesión?')) logout();">Cerrar sesión</button>
        </div>
    </header>

    <main class="container">
        <a href="index.php" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Volver al panel
        </a>

        <div class="page-header">
            <div>
                <h1>Productos con bajo inventario</h1>
                <p>
                    Sucursal: <strong><?= htmlspecialchars($gerente['sucursal_nombre']) ?></strong>
                    &middot; Umbral: menos de <strong><?= $UMBRAL ?></strong> unidades
                </p>
            </div>
        </div>

        <?php if (empty($productos)): ?>
            <div class="panel">
                <div class="empty-state">
                    No hay productos con bajo inventario en este momento.
                </div>
            </div>
        <?php else: ?>
            <section class="cards" style="margin-bottom: 1.5rem;">
                <div class="card card--static" style="border-left-color: #dc2626;">
                    <div class="card-head">
                        <span class="card-icon" aria-hidden="true" style="background:#fef2f2; color:#dc2626;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </span>
                        <h3>Productos en alerta</h3>
                    </div>
                    <p style="font-size: 1.8rem; color: #dc2626; font-weight: 700; line-height: 1;">
                        <?= $totalBajo ?>
                    </p>
                    <p>Requieren reposición</p>
                </div>
            </section>

            <div class="panel">
                <div class="tabla-scroll">
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
                                <?php
                                    $stock = (int) $p['cantidad_disponible'];
                                    if ($stock === 0) {
                                        $badgeClass = 'badge-danger';
                                        $badgeTexto = 'Agotado';
                                    } elseif ($stock < 5) {
                                        $badgeClass = 'badge-danger';
                                        $badgeTexto = $stock . ' ' . ($stock === 1 ? 'unidad' : 'unidades');
                                    } else {
                                        $badgeClass = 'badge-warning';
                                        $badgeTexto = $stock . ' unidades';
                                    }
                                    $precio = (float) $p['precio_venta'];
                                ?>
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
                                    <td>
                                        <?php if ($precio > 0): ?>
                                            $<?= number_format($precio, 2) ?>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">Sin precio</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>"><?= $badgeTexto ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="../auth.js"></script>
    <script src="gerente.js?v=<?= $verGerenteJs ?>"></script>
</body>
</html>