<?php require 'header.php'; ?>
<?php require 'sidebar.php'; ?>

<?php
$hoy = date('Y-m-d');

// Stats
$numSucursales = $pdo->query("SELECT COUNT(*) FROM Sucursales WHERE estado = 'Activa'")->fetchColumn();
$numGerentes = $pdo->query("SELECT COUNT(*) FROM Usuarios WHERE id_rol = 2")->fetchColumn();
$bajoStock = $pdo->query("SELECT COUNT(*) FROM Inventario_Sucursal WHERE cantidad_disponible <= 5")->fetchColumn();

$ventasHoy = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM Ventas WHERE DATE(fecha_hora) = ?");
$ventasHoy->execute([$hoy]);
$totalVentas = $ventasHoy->fetchColumn();

// Sucursales con gerente y ventas del día
$sucursales = $pdo->prepare("
    SELECT s.id_sucursal, s.nombre, s.estado,
           g.nombre AS gerente_nombre,
           COALESCE(v.total_ventas, 0) AS ventas_hoy
    FROM Sucursales s
    LEFT JOIN Usuarios g ON g.id_sucursal = s.id_sucursal AND g.id_rol = 2
    LEFT JOIN (
        SELECT id_sucursal, SUM(total) AS total_ventas
        FROM Ventas WHERE DATE(fecha_hora) = ?
        GROUP BY id_sucursal
    ) v ON v.id_sucursal = s.id_sucursal
    ORDER BY s.nombre
");
$sucursales->execute([$hoy]);
$listaSucursales = $sucursales->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><?= e(t('nav.panel')) ?></h1>
        <p><?= e(t('panel.subtitulo')) ?></p>
    </div>
</div>

<?php
// Ticket de corte: las sucursales con más ventas primero; el resto se suma en una sola línea.
$ordenadas = $listaSucursales;
usort($ordenadas, fn($a, $b) => (float) $b['ventas_hoy'] <=> (float) $a['ventas_hoy']);
$lineasTicket = array_slice($ordenadas, 0, 6);
$restantes = array_slice($ordenadas, 6);
$sinGerente = count(array_filter($listaSucursales, fn($s) => !$s['gerente_nombre']));
$inactivas = count(array_filter($listaSucursales, fn($s) => $s['estado'] !== 'Activa'));
$pendientes = $bajoStock + $sinGerente;

// Gráfica de ganancias de una sucursal (la dibuja graficas.js); por defecto, la que más vendió hoy
require_once __DIR__ . '/metricas_ventas.php';
$idsSucursal = array_map('intval', array_column($listaSucursales, 'id_sucursal'));
$idGrafica = (int) ($_GET['sucursal'] ?? 0);
if (!in_array($idGrafica, $idsSucursal, true)) {
    $idGrafica = $idsSucursal ? (int) $ordenadas[0]['id_sucursal'] : 0;
}
$diasGrafica = ($_GET['dias'] ?? '') === '30' ? 30 : 7;
$graficas = null;
if ($idGrafica > 0) {
    $hastaGrafica = new DateTimeImmutable('today');
    $desdeGrafica = $hastaGrafica->modify('-' . ($diasGrafica - 1) . ' days');
    $tendencia = serieTendencia($pdo, $desdeGrafica, $hastaGrafica,
        'v.id_sucursal = ? AND v.fecha_hora >= ? AND v.fecha_hora < ?',
        [$idGrafica, $desdeGrafica->format('Y-m-d 00:00:00'), $hastaGrafica->modify('+1 day')->format('Y-m-d 00:00:00')],
        explode(',', t('panel.dias_cortos')), explode(',', t('panel.meses_cortos')));
    $graficas = [
        'textos'    => ['venta' => t('panel.grafica_venta'), 'ganancia' => t('panel.grafica_ganancia')],
        'tendencia' => $tendencia,
    ];
}
?>

<div class="hero">
    <div class="ticket">
        <div class="ticket__top">
            <b><?= e(t('panel.corte')) ?></b>
            <span><?= fecha(time()) ?></span>
        </div>
        <?php if (empty($listaSucursales)): ?>
            <p class="ticket__vacio"><?= e(t('panel.sin_sucursales')) ?></p>
        <?php else: ?>
            <?php foreach ($lineasTicket as $s): ?>
                <div class="ticket__row"><?= htmlspecialchars($s['nombre']) ?><i></i><em>$<?= number_format((float) $s['ventas_hoy'], 0, '.', ',') ?></em></div>
            <?php endforeach; ?>
            <?php if ($restantes): ?>
                <div class="ticket__row"><?= e(tn('panel.otras', count($restantes))) ?><i></i><em>$<?= number_format(array_sum(array_column($restantes, 'ventas_hoy')), 0, '.', ',') ?></em></div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="ticket__total">
            <span><?= e(t('panel.total_dia')) ?></span>
            <b>$<?= number_format((float) $totalVentas, 0, '.', ',') ?></b>
        </div>
    </div>

    <div class="pend">
        <p class="section-title"><?= e(t('panel.para_atender')) ?></p>
        <?php if ($bajoStock > 0): ?>
            <a class="pend__it" href="inventario.php">
                <span class="pend__ic pend__ic--danger"><i class="ti ti-alert-triangle"></i></span>
                <span class="pend__txt"><b><?= e(tn('panel.bajo_stock', (int) $bajoStock)) ?></b><small><?= e(t('panel.bajo_stock_ayuda')) ?></small></span>
                <span class="btn btn--sm"><?= e(t('panel.ver')) ?></span>
            </a>
        <?php endif; ?>
        <?php if ($sinGerente > 0): ?>
            <a class="pend__it" href="gerentes.php">
                <span class="pend__ic pend__ic--warn"><i class="ti ti-user-off"></i></span>
                <span class="pend__txt"><b><?= e(tn('panel.sin_gerente', $sinGerente)) ?></b><small><?= e(tn('panel.sin_gerente_ayuda', $sinGerente)) ?><?= $inactivas > 0 ? ' · ' . e(tn('panel.n_inactivas', $inactivas)) : '' ?></small></span>
                <span class="btn btn--primary btn--sm"><?= e(t('panel.asignar')) ?></span>
            </a>
        <?php endif; ?>
        <?php if ($pendientes === 0): ?>
            <div class="pend__it pend__it--ok">
                <span class="pend__ic pend__ic--ok"><i class="ti ti-check"></i></span>
                <span class="pend__txt"><b><?= e(t('panel.en_orden')) ?></b><small><?= e(t('panel.en_orden_ayuda')) ?></small></span>
            </div>
        <?php endif; ?>
        <div class="pend__mini">
            <div class="stat-card"><p class="stat-card__label"><?= e(t('panel.sucursales_activas')) ?></p><p class="stat-card__value"><?= $numSucursales ?></p></div>
            <div class="stat-card"><p class="stat-card__label"><?= e(t('panel.gerentes_registrados')) ?></p><p class="stat-card__value"><?= $numGerentes ?></p></div>
        </div>
    </div>
</div>

<?php if ($graficas): ?>
<section class="grafica-panel">
    <div class="grafica-panel__cabecera">
        <h2 class="section-title"><?= e(t('panel.grafica_titulo')) ?></h2>
        <form method="GET" class="grafica-panel__filtros" onchange="this.submit()">
            <select name="sucursal" class="select" aria-label="<?= e(t('panel.grafica_sucursal')) ?>">
                <?php foreach ($listaSucursales as $s): ?>
                    <option value="<?= (int) $s['id_sucursal'] ?>" <?= (int) $s['id_sucursal'] === $idGrafica ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="dias" class="select" aria-label="<?= e(t('panel.grafica_periodo')) ?>">
                <option value="7" <?= $diasGrafica === 7 ? 'selected' : '' ?>><?= e(t('panel.grafica_7')) ?></option>
                <option value="30" <?= $diasGrafica === 30 ? 'selected' : '' ?>><?= e(t('panel.grafica_30')) ?></option>
            </select>
        </form>
    </div>
    <?php if (array_sum($graficas['tendencia']['neta']) <= 0): ?>
        <p class="grafica-panel__aviso"><?= e(t('panel.grafica_sin_ventas')) ?></p>
    <?php else: ?>
        <?php if (!array_filter($graficas['tendencia']['utilidad'], fn($v) => $v !== null)): ?>
            <p class="grafica-panel__aviso"><?= e(t('panel.grafica_sin_costo')) ?></p>
        <?php endif; ?>
        <div class="grafica-panel__lienzo"><canvas id="graficaTendencia" role="img" aria-label="<?= e(t('panel.grafica_titulo')) ?>"></canvas></div>
        <script type="application/json" id="datosGraficas"><?= json_encode($graficas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <script src="../gerente/js/vendor/chart.umd.min.js"></script>
        <script src="graficas.js?v=<?= filemtime(__DIR__ . '/graficas.js') ?>"></script>
    <?php endif; ?>
</section>
<?php endif; ?>

<div style="margin-top:1rem;display:flex;gap:8px">
    <a href="sucursales.php" class="btn btn--primary"><i class="ti ti-plus"></i><?= e(t('suc.nueva')) ?></a>
    <a href="gerentes.php" class="btn btn--primary"><i class="ti ti-user-plus"></i><?= e(t('ger.registrar')) ?></a>
</div>

<?php require 'footer.php'; ?>
