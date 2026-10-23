<?php require 'header.php'; ?>
<?php require 'sidebar.php'; ?>

<?php
$periodo = $_GET['periodo'] ?? '7';
$fechaInicio = match($periodo) {
    '30' => date('Y-m-d', strtotime('-30 days')),
    'mes' => date('Y-m-01'),
    default => date('Y-m-d', strtotime('-7 days')),
};

// Ventas por sucursal
$stmt = $pdo->prepare("
    SELECT s.nombre, COALESCE(SUM(v.total), 0) AS total_ventas
    FROM Sucursales s
    LEFT JOIN Ventas v ON v.id_sucursal = s.id_sucursal AND v.fecha_hora >= ?
    WHERE s.estado = 'Activa'
    GROUP BY s.id_sucursal, s.nombre
    ORDER BY total_ventas DESC
");
$stmt->execute([$fechaInicio]);
$ventasSucursal = $stmt->fetchAll();

$maxVentas = max(array_column($ventasSucursal, 'total_ventas') ?: [1]);

// Productos más vendidos
$productos = $pdo->prepare("
    SELECT p.nombre, SUM(dv.cantidad) AS total_unidades
    FROM Detalle_Venta dv
    INNER JOIN Productos p ON p.id_producto = dv.id_producto
    INNER JOIN Ventas v ON v.id_venta = dv.id_venta
    WHERE v.fecha_hora >= ?
    GROUP BY p.id_producto, p.nombre
    ORDER BY total_unidades DESC
    LIMIT 5
");
$productos->execute([$fechaInicio]);
$productosTop = $productos->fetchAll();

// Métodos de pago
$pagos = $pdo->prepare("
    SELECT mp.nombre_metodo, COUNT(*) AS num_ventas
    FROM Ventas v
    INNER JOIN Metodos_Pago mp ON mp.id_metodo_pago = v.id_metodo_pago
    WHERE v.fecha_hora >= ?
    GROUP BY mp.id_metodo_pago, mp.nombre_metodo
    ORDER BY num_ventas DESC
");
$pagos->execute([$fechaInicio]);
$metodosPago = $pagos->fetchAll();
$totalVentasMetodo = array_sum(array_column($metodosPago, 'num_ventas'));
?>

<div class="page-header">
    <div>
        <h1><?= e(t('nav.reportes')) ?></h1>
        <p><?= e(t('rep.subtitulo')) ?></p>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:center">
        <select name="periodo" class="select" onchange="this.form.submit()">
            <option value="7" <?= $periodo === '7' ? 'selected' : '' ?>><?= e(t('rep.ultimos_7')) ?></option>
            <option value="30" <?= $periodo === '30' ? 'selected' : '' ?>><?= e(t('rep.ultimos_30')) ?></option>
            <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>><?= e(t('rep.este_mes')) ?></option>
        </select>
    </form>
</div>

<p class="section-title" style="margin-top:1.25rem"><?= e(t('rep.ventas_sucursal')) ?></p>
<div class="chart-bars">
    <?php foreach ($ventasSucursal as $vs):
        $altura = $maxVentas > 0 ? round(($vs['total_ventas'] / $maxVentas) * 110) : 0;
    ?>
        <div class="chart-bar">
            <span class="chart-bar__value">$<?= number_format($vs['total_ventas'] / 1000, 1) ?>k</span>
            <div class="chart-bar__rect" style="height:<?= max($altura, 4) ?>px"></div>
            <span class="chart-bar__label"><?= htmlspecialchars(mb_strstr($vs['nombre'], ' ', false) ?: $vs['nombre']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid-2">
    <div>
        <p class="section-title"><?= e(t('rep.mas_vendidos')) ?></p>
        <div class="table-wrap">
            <table class="table table--compact">
                <tbody>
                    <?php if (empty($productosTop)): ?>
                        <tr><td style="color:var(--text-muted);text-align:center"><?= e(t('comun.sin_datos')) ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($productosTop as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nombre']) ?></td>
                                <td style="text-align:right;color:var(--text-secondary)"><?= e(tn('rep.n_unidades', (int) $p['total_unidades'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <p class="section-title"><?= e(t('rep.metodos_pago')) ?></p>
        <div class="table-wrap">
            <table class="table table--compact">
                <tbody>
                    <?php if (empty($metodosPago)): ?>
                        <tr><td style="color:var(--text-muted);text-align:center"><?= e(t('comun.sin_datos')) ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($metodosPago as $mp):
                            $pct = $totalVentasMetodo > 0 ? round(($mp['num_ventas'] / $totalVentasMetodo) * 100) : 0;
                        ?>
                            <tr>
                                <td><?= e(tValor('metodo_pago', $mp['nombre_metodo'])) ?></td>
                                <td style="text-align:right;color:var(--text-secondary)"><?= $pct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
