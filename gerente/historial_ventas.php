<?php
require_once __DIR__ . '/guardia.php';

$stmt = $pdo->query("
    SELECT v.id_venta, v.fecha_hora, v.subtotal, v.total,
           s.nombre AS sucursal_nombre,
           u.nombre AS cajero_nombre,
           m.nombre_metodo
    FROM Ventas v
    INNER JOIN Sucursales s ON s.id_sucursal = v.id_sucursal
    INNER JOIN Usuarios u   ON u.id_usuario = v.id_cajero
    INNER JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
    ORDER BY v.fecha_hora DESC
    LIMIT 100
");
$ventas = $stmt->fetchAll();

$totalVentas    = count($ventas);
$sumaTotal      = array_sum(array_map(fn($v) => (float) $v['total'], $ventas));
$promedioTicket = $totalVentas > 0 ? $sumaTotal / $totalVentas : 0;

$pageTitle    = 'Historial de Ventas';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Historial general de ventas</h1>
        <p>Últimas <strong>100</strong> ventas registradas en todas las sucursales del sistema</p>
    </div>
</div>

<?php if (empty($ventas)): ?>
    <div class="panel">
        <div class="empty-state">
            No hay ventas registradas todavía.
        </div>
    </div>
<?php else: ?>
    <section class="cards" style="margin-bottom: 1.5rem;">
        <div class="card card--static">
            <div class="card-head">
                <span class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"/>
                        <line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                </span>
                <h3>Ventas mostradas</h3>
            </div>
            <p style="font-size: 1.8rem; color: #7c3aed; font-weight: 700; line-height: 1;"><?= $totalVentas ?></p>
            <p>Últimas 100 registradas</p>
        </div>

        <div class="card card--static">
            <div class="card-head">
                <span class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </span>
                <h3>Monto total</h3>
            </div>
            <p style="font-size: 1.8rem; color: #7c3aed; font-weight: 700; line-height: 1;">$<?= number_format($sumaTotal, 2) ?></p>
            <p>Suma de las ventas mostradas</p>
        </div>

        <div class="card card--static">
            <div class="card-head">
                <span class="card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                </span>
                <h3>Ticket promedio</h3>
            </div>
            <p style="font-size: 1.8rem; color: #7c3aed; font-weight: 700; line-height: 1;">$<?= number_format($promedioTicket, 2) ?></p>
            <p>Promedio por venta</p>
        </div>
    </section>

    <div class="panel">
        <div class="tabla-scroll">
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th>#Venta</th>
                        <th>Fecha</th>
                        <th>Sucursal</th>
                        <th>Cajero</th>
                        <th>Método de pago</th>
                        <th>Subtotal</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v): ?>
                        <tr>
                            <td><strong>#<?= (int) $v['id_venta'] ?></strong></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['fecha_hora']))) ?></td>
                            <td><?= htmlspecialchars($v['sucursal_nombre']) ?></td>
                            <td><?= htmlspecialchars($v['cajero_nombre']) ?></td>
                            <td><span class="badge badge-metodo"><?= htmlspecialchars($v['nombre_metodo']) ?></span></td>
                            <td>$<?= number_format((float) $v['subtotal'], 2) ?></td>
                            <td><strong>$<?= number_format((float) $v['total'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>