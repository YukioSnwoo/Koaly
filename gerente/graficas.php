<?php
/**
 * Gráficas de la sucursal: un tablero con todo a la vista (boceto A).
 * Los números salen de admin/metricas_ventas.php (venta sin IVA, ganancia con
 * el costo de cada venta) y las gráficas las dibuja js/graficas.js con Chart.js.
 * Las gráficas solo muestran; las decisiones las toma el gerente.
 */
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/filtros.php';
require_once __DIR__ . '/../admin/metricas_ventas.php';

// Margen por debajo del cual una categoría se pinta en rojo
const MARGEN_BAJO = 20;

/* ---------- Periodo (por defecto, los últimos 30 días) ---------- */
$hoy = new DateTimeImmutable('today');
$stmt = $pdo->prepare("SELECT MIN(fecha_hora) FROM Ventas WHERE id_sucursal = ?");
$stmt->execute([$idSucursalGerente]);
$primeraVenta = $stmt->fetchColumn();
$inicioHistorico = $primeraVenta ? new DateTimeImmutable(substr($primeraVenta, 0, 10)) : $hoy;

$periodo     = leerPeriodo($_GET, $hoy, $inicioHistorico, '30');
$desde       = $periodo['desde'];
$hasta       = $periodo['hasta'];
$esTodo      = $periodo['atajo'] === 'todo';
$dias        = (int) $desde->diff($hasta)->days + 1;
$inicio      = $desde->format('Y-m-d 00:00:00');
$fin         = $hasta->modify('+1 day')->format('Y-m-d 00:00:00');
$inicioAnterior = $desde->modify("-{$dias} days")->format('Y-m-d 00:00:00');

$where  = 'v.id_sucursal = ? AND v.fecha_hora >= ? AND v.fecha_hora < ?';
$params = [$idSucursalGerente, $inicio, $fin];

/* ---------- Indicadores ---------- */
$metricas = metricasVentas($pdo, $where, $params);
$variacion = null;
if (!$esTodo) {
    $anterior  = metricasVentas($pdo, $where, [$idSucursalGerente, $inicioAnterior, $inicio]);
    $variacion = variacionPorcentual($metricas['venta_neta'], $anterior['venta_neta']);
}

/* ---------- Datos de las gráficas ---------- */
$graficas = null;
$abcResumen = [];
$sinCostoMatriz = 0;
if ($metricas['num_ventas'] > 0) {
    $diasCortos  = explode(',', t('grafica.dias_cortos'));
    $mesesCortos = explode(',', t('grafica.meses_cortos'));

    $tendencia = serieTendencia($pdo, $desde, $hasta, $where, $params, $diasCortos, $mesesCortos);

    $metodosPago = ventasPorMetodo($pdo, $where, $params);

    $calor = mapaCalorVentas($pdo, $where, $params);
    $diasLunes = array_merge(array_slice($diasCortos, 1), [$diasCortos[0]]);   // WEEKDAY: lunes = 0

    // Margen por categoría (solo las que tienen costo), de mayor a menor
    $categorias = array_values(array_filter(rentabilidadPor($pdo, 'categoria', $where, $params), fn($f) => $f['margen'] !== null));
    usort($categorias, fn($a, $b) => $b['margen'] <=> $a['margen']);

    // Productos: matriz (unidades contra margen) y ABC (por venta)
    $productos = rentabilidadPor($pdo, 'producto', $where, $params);
    $conMargen = array_values(array_filter($productos, fn($p) => $p['margen'] !== null));
    $sinCostoMatriz = count($productos) - count($conMargen);
    // Cortes de los cuadrantes: unidades promedio y margen de la tienda
    $corteUnidades = $conMargen ? array_sum(array_column($conMargen, 'unidades')) / count($conMargen) : 0;
    $corteMargen   = $metricas['margen'] ?? 0;

    $abc = clasificarABC($productos);
    foreach (['A', 'B', 'C'] as $clase) {
        $deClase = array_filter($abc, fn($p) => $p['clase'] === $clase);
        $abcResumen[$clase] = [
            'n'   => count($deClase),
            'pct' => $metricas['venta_neta'] > 0 ? array_sum(array_column($deClase, 'neta')) / $metricas['venta_neta'] * 100 : 0,
        ];
    }
    $abcVisibles = array_slice($abc, 0, 20);   // los 20 que más venden; el resto se resume en las fichas

    $graficas = [
        'textos' => [
            'venta' => t('grafica.venta'), 'ganancia' => t('grafica.ganancia'), 'ventas' => t('grafica.num_ventas'),
            'acumulado' => t('grafica.acumulado'), 'margen' => t('grafica.margen'), 'unidades' => t('grafica.unidades'),
        ],
        'tendencia' => $tendencia,
        'metodos' => [
            'etiquetas' => array_map(fn($m) => tValor('metodo_pago', $m['etiqueta']), $metodosPago),
            'neta'      => array_map(fn($m) => round($m['neta'], 2), $metodosPago),
        ],
        'calor' => [
            'horas' => $calor['horas'],
            'dias'  => $diasLunes,
            'filas' => $calor['filas'],
        ],
        'categorias' => [
            'etiquetas' => array_map(fn($f) => tValor('categoria', $f['etiqueta'], t('comun.sin_categoria')), $categorias),
            'margen'    => array_map(fn($f) => round($f['margen'], 1), $categorias),
            'bajo'      => MARGEN_BAJO,
        ],
        'matriz' => [
            'puntos' => array_map(fn($p) => [
                'n' => $p['etiqueta'], 'x' => $p['unidades'], 'y' => round($p['margen'], 1), 'venta' => round($p['neta'], 2),
            ], $conMargen),
            'corteX' => round($corteUnidades, 1),
            'corteY' => round($corteMargen, 1),
        ],
        'abc' => [
            'etiquetas' => array_map(fn($p) => $p['etiqueta'], $abcVisibles),
            'neta'      => array_map(fn($p) => round($p['neta'], 2), $abcVisibles),
            'acumulado' => array_map(fn($p) => round($p['acumulado'], 1), $abcVisibles),
            'clase'     => array_map(fn($p) => $p['clase'], $abcVisibles),
        ],
    ];
}

$atajosVisibles = ['hoy', '7', '30', 'mes', 'anio', 'todo'];

$pageTitle = t('graficas.titulo');
require __DIR__ . '/header.php';

$pintarVariacion = function (?float $v) use ($esTodo, $desde, $dias): string {
    if ($esTodo) return e(t('ventas.kpi_desde', ['fecha' => fecha($desde, false)]));
    if ($v === null) return e(t('ventas.kpi_sin_anterior'));
    return '<span class="' . ($v >= 0 ? 'texto-ok' : 'texto-peligro') . '" style="font-weight: 600;">'
        . ($v >= 0 ? '▲' : '▼') . ' ' . numero(abs($v), 1) . '%</span> ' . e(tn('ventas.kpi_vs_anterior', $dias));
};
?>

<div class="page-header">
    <div>
        <h1><?= e(t('graficas.titulo')) ?></h1>
        <p>
            <?= e($gerente['sucursal_nombre']) ?> ·
            <?= $dias === 1 ? e(fecha($desde, false)) : e(fecha($desde, false)) . ' – ' . e(fecha($hasta, false)) . ' (' . e(tn('comun.n_dias', $dias)) . ')' ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="ventas_sucursal.php?<?= e(http_build_query(['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')])) ?>"><?= e(t('graficas.ver_ventas')) ?></a>
</div>

<nav class="segmentado graficas-periodo" aria-label="<?= e(t('graficas.periodo_aria')) ?>">
    <?php foreach ($atajosVisibles as $clave): ?>
        <?php $activo = (string) $periodo['atajo'] === $clave; // PHP vuelve número la clave '30' ?>
        <a href="graficas.php?periodo=<?= e($clave) ?>" class="<?= $activo ? 'is-activo' : '' ?>"<?= $activo ? ' aria-current="true"' : '' ?>>
            <?= e($periodo['atajos'][$clave]['texto']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<section class="kpi-grid">
    <div class="kpi kpi--ok">
        <span class="kpi-label"><?= e(t('ventas.kpi_neta')) ?></span>
        <span class="kpi-value kpi-value--ok"><?= dinero($metricas['venta_neta']) ?></span>
        <span class="kpi-sub"><?= $pintarVariacion($variacion) ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label"><?= e(t('ventas.kpi_utilidad')) ?></span>
        <?php if ($metricas['utilidad'] === null): ?>
            <span class="kpi-value">—</span>
            <span class="kpi-sub"><?= e(t(hayCostoEnVentas($pdo) ? 'ventas.kpi_utilidad_sin_costo' : 'ventas.kpi_utilidad_sin_sql')) ?></span>
        <?php else: ?>
            <span class="kpi-value <?= $metricas['utilidad'] < 0 ? 'kpi-value--danger' : '' ?>"><?= dinero($metricas['utilidad']) ?></span>
            <span class="kpi-sub"><?= e(t('ventas.kpi_margen', ['p' => numero($metricas['margen'], 1)])) ?></span>
            <?php if ($metricas['cobertura'] < 99.5): ?>
                <span class="kpi-sub texto-pendiente"><?= e(t('ventas.kpi_cobertura', ['p' => numero($metricas['cobertura'], 0)])) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="kpi">
        <span class="kpi-label"><?= e(t('ventas.kpi_ventas')) ?></span>
        <span class="kpi-value"><?= numero($metricas['num_ventas']) ?></span>
        <span class="kpi-sub"><?= e(t('ventas.kpi_por_ticket', ['n' => numero($metricas['articulos_ticket'], 1)])) ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label"><?= e(t('ventas.kpi_ticket')) ?></span>
        <span class="kpi-value"><?= dinero($metricas['ticket']) ?></span>
        <span class="kpi-sub"><?= e(t('ventas.kpi_ticket_sub')) ?></span>
    </div>
</section>

<?php if ($graficas === null): ?>
    <div class="panel"><div class="empty-state"><?= e(t('graficas.vacio')) ?></div></div>
<?php else: ?>
    <div class="tablero">
        <figure class="grafica tablero-2">
            <figcaption><h2><?= e(t('grafica.tendencia_' . $graficas['tendencia']['agrupar'])) ?></h2></figcaption>
            <div class="grafica-lienzo"><canvas id="graficaTendencia" role="img" aria-label="<?= e(t('grafica.tendencia_' . $graficas['tendencia']['agrupar'])) ?>"></canvas></div>
        </figure>

        <figure class="grafica">
            <figcaption><h2><?= e(t('graficas.metodos')) ?></h2></figcaption>
            <div class="grafica-lienzo"><canvas id="graficaMetodos" role="img" aria-label="<?= e(t('graficas.metodos')) ?>"></canvas></div>
        </figure>

        <figure class="grafica tablero-mitad">
            <figcaption>
                <h2><?= e(t('graficas.calor')) ?></h2>
                <p class="grafica-ayuda"><?= e(t('graficas.calor_ayuda')) ?></p>
            </figcaption>
            <div class="mapa-calor" id="mapaCalor" role="img" aria-label="<?= e(t('graficas.calor')) ?>"></div>
        </figure>

        <figure class="grafica tablero-mitad">
            <figcaption>
                <h2><?= e(t('graficas.margen_categoria')) ?></h2>
                <p class="grafica-ayuda"><?= e(t('graficas.margen_categoria_ayuda', ['p' => MARGEN_BAJO])) ?></p>
            </figcaption>
            <?php if ($graficas['categorias']['etiquetas']): ?>
                <div class="grafica-lienzo"><canvas id="graficaMargen" role="img" aria-label="<?= e(t('graficas.margen_categoria')) ?>"></canvas></div>
            <?php else: ?>
                <div class="empty-state"><?= e(t('graficas.sin_costos')) ?></div>
            <?php endif; ?>
        </figure>

        <figure class="grafica tablero-mitad">
            <figcaption>
                <h2><?= e(t('graficas.matriz')) ?></h2>
                <p class="grafica-ayuda"><?= e(t('graficas.matriz_ayuda')) ?></p>
            </figcaption>
            <?php if ($graficas['matriz']['puntos']): ?>
                <div class="grafica-lienzo grafica-lienzo--alta"><canvas id="graficaMatriz" role="img" aria-label="<?= e(t('graficas.matriz')) ?>"></canvas></div>
                <ul class="cuadrantes">
                    <li><span class="cuadrante-punto" style="background: var(--ok)"></span><?= e(t('graficas.estrellas')) ?></li>
                    <li><span class="cuadrante-punto" style="background: var(--aviso)"></span><?= e(t('graficas.joyas')) ?></li>
                    <li><span class="cuadrante-punto" style="background: var(--acento)"></span><?= e(t('graficas.gancho')) ?></li>
                    <li><span class="cuadrante-punto" style="background: var(--peligro)"></span><?= e(t('graficas.revisar')) ?></li>
                </ul>
                <?php if ($sinCostoMatriz > 0): ?>
                    <p class="grafica-ayuda grafica-ayuda--abajo texto-pendiente"><?= e(tn('graficas.matriz_sin_costo', $sinCostoMatriz)) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state"><?= e(t('graficas.sin_costos')) ?></div>
            <?php endif; ?>
        </figure>

        <figure class="grafica tablero-mitad">
            <figcaption>
                <h2><?= e(t('graficas.abc')) ?></h2>
                <p class="grafica-ayuda"><?= e(t('graficas.abc_ayuda')) ?></p>
            </figcaption>
            <div class="grafica-lienzo grafica-lienzo--alta"><canvas id="graficaABC" role="img" aria-label="<?= e(t('graficas.abc')) ?>"></canvas></div>
            <ul class="abc-fichas">
                <?php foreach (array_filter($abcResumen, fn($r) => $r['n'] > 0) as $clase => $r): ?>
                    <li class="abc-ficha abc-ficha--<?= strtolower($clase) ?>">
                        <?= e(t('graficas.abc_ficha', ['clase' => $clase, 'n' => tn('graficas.n_productos', $r['n']), 'p' => numero($r['pct'], 0)])) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </figure>
    </div>
    <script type="application/json" id="datosGraficas"><?= json_encode($graficas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="js/vendor/chart.umd.min.js"></script>
    <script src="js/graficas.js?v=<?= filemtime(__DIR__ . '/js/graficas.js') ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
