<?php require 'header.php'; ?>
<?php require 'sidebar.php'; ?>

<?php
require_once __DIR__ . '/metricas_ventas.php';

// Periodo elegido y el anterior de la misma duración (para comparar)
$periodo = $_GET['periodo'] ?? '7';
$hasta = new DateTimeImmutable('today');
$desde = match($periodo) {
    '30' => $hasta->modify('-29 days'),
    'mes' => $hasta->modify('first day of this month'),
    default => $hasta->modify('-6 days'),
};
$dias = (int) $desde->diff($hasta)->days + 1;
$desdeAnt = $desde->modify("-$dias days");
$hastaAnt = $desde->modify('-1 day');

$condPeriodo = 'v.fecha_hora >= ? AND v.fecha_hora < ?';
$rango = fn(DateTimeImmutable $a, DateTimeImmutable $b) => [$a->format('Y-m-d 00:00:00'), $b->modify('+1 day')->format('Y-m-d 00:00:00')];
$paramsActual = $rango($desde, $hasta);
$paramsAnterior = $rango($desdeAnt, $hastaAnt);

// Cifras generales y tendencia (todas las sucursales)
$actual = metricasVentas($pdo, $condPeriodo, $paramsActual);
$anterior = metricasVentas($pdo, $condPeriodo, $paramsAnterior);
$tendencia = serieTendencia($pdo, $desde, $hasta, $condPeriodo, $paramsActual,
    explode(',', t('panel.dias_cortos')), explode(',', t('panel.meses_cortos')));
$hayVentas = $actual['num_ventas'] > 0;
$hayGanancia = (bool) array_filter($tendencia['utilidad'], fn($v) => $v !== null);

// Comparativo por sucursal
$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM Sucursales WHERE estado = 'Activa' ORDER BY nombre")->fetchAll();
$comparativo = [];
foreach ($sucursales as $s) {
    $cond = "v.id_sucursal = ? AND $condPeriodo";
    $m = metricasVentas($pdo, $cond, array_merge([$s['id_sucursal']], $paramsActual));
    $ant = metricasVentas($pdo, $cond, array_merge([$s['id_sucursal']], $paramsAnterior));
    $comparativo[] = [
        'nombre'    => $s['nombre'],
        'neta'      => round($m['venta_neta'], 2),
        'utilidad'  => $m['utilidad'] !== null ? round($m['utilidad'], 2) : null,
        'margen'    => $m['margen'],
        'variacion' => variacionPorcentual($m['venta_neta'], $ant['venta_neta']),
    ];
}
usort($comparativo, fn($a, $b) => $b['neta'] <=> $a['neta']);

// Productos más vendidos (por unidades) y métodos de pago
$productosTop = rentabilidadPor($pdo, 'producto', $condPeriodo, $paramsActual);
usort($productosTop, fn($a, $b) => $b['unidades'] <=> $a['unidades']);
$productosTop = array_slice($productosTop, 0, 5);
$metodosPago = ventasPorMetodo($pdo, $condPeriodo, $paramsActual);
$totalVentasMetodo = array_sum(array_column($metodosPago, 'num'));

$datosReportes = [
    'textos' => [
        'venta' => t('panel.grafica_venta'),
        'ganancia' => t('panel.grafica_ganancia'),
    ],
    'tendencia' => $tendencia,
    'comparativo' => [
        'etiquetas' => array_column($comparativo, 'nombre'),
        'neta' => array_column($comparativo, 'neta'),
        'utilidad' => array_column($comparativo, 'utilidad'),
    ],
];

/** Chip con la variación frente al periodo anterior. */
$chipVariacion = function (?float $v): string {
    if ($v === null) return '<span class="rep-var rep-var--nd">—</span>';
    $clase = $v >= 0 ? 'rep-var--sube' : 'rep-var--baja';
    return '<span class="rep-var ' . $clase . '">' . ($v >= 0 ? '+' : '') . number_format($v, 1) . '%</span>';
};
$dinero = fn(?float $v) => $v === null ? '—' : '$' . number_format($v, 0, '.', ',');
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

<div class="rep-kpis">
    <div class="stat-card"><p class="stat-card__label"><?= e(t('rep.kpi_venta')) ?></p><p class="stat-card__value"><?= $dinero($actual['venta_neta']) ?></p><?= $chipVariacion(variacionPorcentual($actual['venta_neta'], $anterior['venta_neta'])) ?></div>
    <div class="stat-card"><p class="stat-card__label"><?= e(t('rep.kpi_ganancia')) ?></p><p class="stat-card__value"><?= $dinero($actual['utilidad']) ?></p><?= $chipVariacion(variacionPorcentual($actual['utilidad'], $anterior['utilidad'])) ?></div>
    <div class="stat-card"><p class="stat-card__label"><?= e(t('rep.kpi_margen')) ?></p><p class="stat-card__value"><?= $actual['margen'] === null ? '—' : number_format($actual['margen'], 1) . '%' ?></p></div>
    <div class="stat-card"><p class="stat-card__label"><?= e(t('rep.kpi_ticket')) ?></p><p class="stat-card__value"><?= $dinero($actual['ticket']) ?></p><?= $chipVariacion(variacionPorcentual($actual['ticket'], $anterior['ticket'])) ?></div>
</div>

<?php if (!$hayVentas): ?>
    <p class="grafica-panel__aviso"><?= e(t('panel.grafica_sin_ventas')) ?></p>
<?php else: ?>
    <section class="grafica-panel">
        <div class="grafica-panel__cabecera"><h2 class="section-title"><?= e(t('rep.tendencia')) ?></h2></div>
        <?php if (!$hayGanancia): ?>
            <p class="grafica-panel__aviso"><?= e(t('panel.grafica_sin_costo')) ?></p>
        <?php endif; ?>
        <div class="grafica-panel__lienzo"><canvas id="graficaTendencia" role="img" aria-label="<?= e(t('rep.tendencia')) ?>"></canvas></div>
    </section>

    <section class="grafica-panel">
        <div class="grafica-panel__cabecera"><h2 class="section-title"><?= e(t('rep.ventas_sucursal')) ?></h2></div>
        <div class="grafica-panel__lienzo rep-lienzo-suc" style="height:<?= max(180, count($comparativo) * 56 + 60) ?>px"><canvas id="graficaSucursales" role="img" aria-label="<?= e(t('rep.ventas_sucursal')) ?>"></canvas></div>
        <div class="table-wrap" style="margin-top:.8rem">
            <table class="table table--compact">
                <thead><tr>
                    <th><?= e(t('rep.col_sucursal')) ?></th>
                    <th style="text-align:right"><?= e(t('rep.kpi_venta')) ?></th>
                    <th style="text-align:right"><?= e(t('rep.kpi_margen')) ?></th>
                    <th style="text-align:right"><?= e(t('rep.vs_anterior')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($comparativo as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nombre']) ?></td>
                            <td style="text-align:right"><?= $dinero($c['neta']) ?></td>
                            <td style="text-align:right;color:var(--text-secondary)"><?= $c['margen'] === null ? '—' : number_format($c['margen'], 1) . '%' ?></td>
                            <td style="text-align:right"><?= $chipVariacion($c['variacion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

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
                                <td><?= htmlspecialchars($p['etiqueta']) ?></td>
                                <td style="text-align:right;color:var(--text-secondary)"><?= e(tn('rep.n_unidades', (int) $p['unidades'])) ?></td>
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
                            $pct = $totalVentasMetodo > 0 ? round(($mp['num'] / $totalVentasMetodo) * 100) : 0;
                        ?>
                            <tr>
                                <td><?= e(tValor('metodo_pago', $mp['etiqueta'])) ?></td>
                                <td style="text-align:right;color:var(--text-secondary)"><?= $pct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($hayVentas): ?>
    <script type="application/json" id="datosReportes"><?= json_encode($datosReportes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="../gerente/js/vendor/chart.umd.min.js"></script>
    <script src="reportes.js?v=<?= filemtime(__DIR__ . '/reportes.js') ?>"></script>
<?php endif; ?>

<?php require 'footer.php'; ?>
