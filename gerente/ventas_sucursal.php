<?php
require_once __DIR__ . '/guardia.php';

$stmt = $pdo->prepare("
    SELECT v.id_venta, v.fecha_hora, v.subtotal, v.total,
           u.nombre AS cajero_nombre,
           m.nombre_metodo
    FROM Ventas v
    INNER JOIN Usuarios u       ON u.id_usuario = v.id_cajero
    INNER JOIN Metodos_Pago m   ON m.id_metodo_pago = v.id_metodo_pago
    WHERE v.id_sucursal = ?
    ORDER BY v.fecha_hora DESC
    LIMIT 100
");
$stmt->execute([$idSucursalGerente]);
$ventas = $stmt->fetchAll();

$totalVentas = count($ventas);
$sumaTotal = array_sum(array_map(fn($v) => (float) $v['total'], $ventas));

$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas de mi Sucursal - Panel Gerente</title>
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
        <a href="index.php" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Volver al panel
        </a>

        <div class="page-header">
            <div>
                <h1>Ventas de mi sucursal</h1>
                <p>Sucursal: <?= htmlspecialchars($gerente['sucursal_nombre'] ?? 'Sin asignar') ?></p>
            </div>
        </div>

        <?php if ($gerente['id_sucursal'] === null): ?>
            <div class="alert alert-error">No tienes una sucursal asignada. Contacta al administrador.</div>
        <?php elseif (empty($ventas)): ?>
            <div class="panel">
                <div class="empty-state">No hay ventas registradas en tu sucursal todavía.</div>
            </div>
        <?php else: ?>
            <div class="cards" style="margin-bottom: 2rem;">
                <div class="card">
                    <h3>Total de ventas</h3>
                    <p style="font-size: 1.8rem; color: #7c3aed; font-weight: 700;"><?= $totalVentas ?></p>
                    <p>Últimas 100 registradas</p>
                </div>
                <div class="card">
                    <h3>Ingresos totales</h3>
                    <p style="font-size: 1.8rem; color: #7c3aed; font-weight: 700;">$<?= number_format($sumaTotal, 2) ?></p>
                    <p>Suma de las ventas mostradas</p>
                </div>
            </div>

            <div class="panel">
                <table class="tabla-productos">
                    <thead>
                        <tr>
                            <th>#Venta</th>
                            <th>Fecha</th>
                            <th>Cajero</th>
                            <th>Método de pago</th>
                            <th>Subtotal</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $v): ?>
                            <tr>
                                <td>#<?= (int) $v['id_venta'] ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['fecha_hora']))) ?></td>
                                <td><?= htmlspecialchars($v['cajero_nombre']) ?></td>
                                <td><?= htmlspecialchars($v['nombre_metodo']) ?></td>
                                <td>$<?= number_format((float) $v['subtotal'], 2) ?></td>
                                <td><strong>$<?= number_format((float) $v['total'], 2) ?></strong></td>
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