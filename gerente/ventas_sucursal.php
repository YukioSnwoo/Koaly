<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/csv.php';
require_once __DIR__ . '/includes/filtros.php';

const VENTAS_POR_PAGINA = 50;
const SIN_CAJA = -1;

/* ============================================================
 * 1. Periodo: atajo (?periodo=) o rango (?desde=&hasta=), acotado a
 *    [primera venta de la sucursal, hoy] (includes/filtros.php).
 * ============================================================ */
$hoy = new DateTimeImmutable('today');

$stmt = $pdo->prepare("SELECT MIN(fecha_hora) FROM Ventas WHERE id_sucursal = ?");
$stmt->execute([$idSucursalGerente]);
$primeraVenta = $stmt->fetchColumn();
$inicioHistorico = $primeraVenta ? new DateTimeImmutable(substr($primeraVenta, 0, 10)) : $hoy;

$periodo     = leerPeriodo($_GET, $hoy, $inicioHistorico, 'hoy');
$desde       = $periodo['desde'];
$hasta       = $periodo['hasta'];
$atajoActivo = $periodo['atajo'];
$esTodo      = $atajoActivo === 'todo';

$dias   = (int) $desde->diff($hasta)->days + 1;
$inicio = $desde->format('Y-m-d 00:00:00');
$fin    = $hasta->modify('+1 day')->format('Y-m-d 00:00:00');
$inicioAnterior = $desde->modify("-{$dias} days")->format('Y-m-d 00:00:00');

/* ============================================================
 * 2. Filtros (caja, cajero, método, producto): afectan a toda la
 *    página. Cada opción lleva su rango de actividad [primera, última
 *    venta] para que el calendario solo deje elegir días posibles.
 *    Orden y agrupación solo afectan a la tabla.
 * ============================================================ */
$rangoVentas = static fn(string $clave, string $desde = 'Ventas v', string $extra = '') => rangosDeActividad($pdo, "
    SELECT $clave AS clave, MIN(v.fecha_hora) AS desde, MAX(v.fecha_hora) AS hasta, COUNT(*) AS num
    FROM $desde WHERE v.id_sucursal = ? $extra GROUP BY $clave
", [$idSucursalGerente]);
$rangosCajero   = $rangoVentas('v.id_cajero');
$rangosMetodo   = $rangoVentas('v.id_metodo_pago');
$rangosCaja     = $rangoVentas('COALESCE(v.id_caja, ' . SIN_CAJA . ')');
$rangosProducto = $rangoVentas('dv.id_producto', 'Detalle_Venta dv INNER JOIN Ventas v ON v.id_venta = dv.id_venta');
$diasConVentas  = diasConActividad($pdo, "SELECT DISTINCT DATE(fecha_hora) AS dia FROM Ventas WHERE id_sucursal = ? ORDER BY dia", [$idSucursalGerente]);

$stmt = $pdo->prepare("SELECT id_usuario, nombre FROM Usuarios WHERE id_sucursal = ? AND id_rol = 3 ORDER BY nombre");
$stmt->execute([$idSucursalGerente]);
$cajeros = array_column($stmt->fetchAll(), 'nombre', 'id_usuario');

$metodos = array_map(
    fn($nombre) => tValor('metodo_pago', $nombre),
    array_column(
        $pdo->query("SELECT id_metodo_pago, nombre_metodo FROM Metodos_Pago ORDER BY nombre_metodo")->fetchAll(),
        'nombre_metodo', 'id_metodo_pago'
    )
);

$stmt = $pdo->prepare("SELECT id_caja, numero_caja, nombre FROM Cajas WHERE id_sucursal = ? ORDER BY numero_caja");
$stmt->execute([$idSucursalGerente]);
$cajas = [];
foreach ($stmt->fetchAll() as $c) {
    $cajas[(int) $c['id_caja']] = t('cajas.caja_n', ['n' => $c['numero_caja']]) . ($c['nombre'] ? ' · ' . $c['nombre'] : '');
}

$productosVendidos = [];
if ($rangosProducto) {
    $marcas = implode(',', array_fill(0, count($rangosProducto), '?'));
    $stmt = $pdo->prepare("SELECT id_producto, nombre FROM Productos WHERE id_producto IN ($marcas) ORDER BY nombre");
    $stmt->execute(array_map('intval', array_keys($rangosProducto)));
    $productosVendidos = array_column($stmt->fetchAll(), 'nombre', 'id_producto');
}

$idCajero   = (int) ($_GET['id_cajero'] ?? 0);
$idMetodo   = (int) ($_GET['id_metodo'] ?? 0);
$idCaja     = (int) ($_GET['id_caja'] ?? 0);
$idProducto = (int) ($_GET['id_producto'] ?? 0);
if (!isset($cajeros[$idCajero]))   $idCajero = 0;
if (!isset($metodos[$idMetodo]))   $idMetodo = 0;
if ($idCaja !== SIN_CAJA && !isset($cajas[$idCaja])) $idCaja = 0;
if (!isset($productosVendidos[$idProducto])) $idProducto = 0;

$ordenes = [
    'recientes'      => [t('ventas.orden_recientes'),      'v.fecha_hora DESC'],
    'antiguas'       => [t('ventas.orden_antiguas'),       'v.fecha_hora ASC'],
    'monto_desc'     => [t('ventas.orden_monto_desc'),     'v.total DESC'],
    'monto_asc'      => [t('ventas.orden_monto_asc'),      'v.total ASC'],
    'articulos_desc' => [t('ventas.orden_articulos_desc'), 'articulos DESC'],
    'articulos_asc'  => [t('ventas.orden_articulos_asc'),  'articulos ASC'],
    'efectivo'       => [t('ventas.orden_efectivo'),       "(m.nombre_metodo = 'Efectivo') DESC, v.fecha_hora DESC"],
    'tarjeta'        => [t('ventas.orden_tarjeta'),        "(m.nombre_metodo LIKE 'Tarjeta%') DESC, v.fecha_hora DESC"],
];
$orden = (string) ($_GET['orden'] ?? '');
if (!array_key_exists($orden, $ordenes)) $orden = 'recientes';

// [etiqueta, ORDER BY del grupo, columna de la fila que identifica el grupo]
$agrupaciones = [
    ''       => [t('ventas.agrupar_ninguno'), null,                                 null],
    'cajero' => [t('ventas.agrupar_cajero'),  'u.nombre, v.id_cajero',              'id_cajero'],
    'metodo' => [t('ventas.agrupar_metodo'),  'm.nombre_metodo',                    'id_metodo_pago'],
    'caja'   => [t('ventas.agrupar_caja'),    '(v.id_caja IS NULL), c.numero_caja', 'id_caja'],
];
$agrupar = (string) ($_GET['agrupar'] ?? '');
if (!array_key_exists($agrupar, $agrupaciones)) $agrupar = '';

$whereFiltros  = '';
$paramsFiltros = [];
if ($idCajero > 0) {
    $whereFiltros .= " AND v.id_cajero = ?";
    $paramsFiltros[] = $idCajero;
}
if ($idMetodo > 0) {
    $whereFiltros .= " AND v.id_metodo_pago = ?";
    $paramsFiltros[] = $idMetodo;
}
if ($idCaja === SIN_CAJA) {
    $whereFiltros .= " AND v.id_caja IS NULL";
} elseif ($idCaja > 0) {
    $whereFiltros .= " AND v.id_caja = ?";
    $paramsFiltros[] = $idCaja;
}
if ($idProducto > 0) {
    $whereFiltros .= " AND EXISTS (SELECT 1 FROM Detalle_Venta d2 WHERE d2.id_venta = v.id_venta AND d2.id_producto = ?)";
    $paramsFiltros[] = $idProducto;
}

// Un solo filtro para todo: KPIs, desgloses, tabla y CSV
$wherePeriodo  = "v.id_sucursal = ? AND v.fecha_hora >= ? AND v.fecha_hora < ?" . $whereFiltros;
$paramsPeriodo = array_merge([$idSucursalGerente, $inicio, $fin], $paramsFiltros);
$whereTabla    = $wherePeriodo;
$paramsTabla   = $paramsPeriodo;

$orderBy = ($agrupar !== '' ? $agrupaciones[$agrupar][1] . ', ' : '') . $ordenes[$orden][1] . ', v.id_venta DESC';

$selectVentas = "
    SELECT v.id_venta, v.fecha_hora, v.subtotal, v.total,
           v.id_cajero, v.id_metodo_pago, v.id_caja,
           u.nombre AS cajero_nombre,
           m.nombre_metodo,
           c.numero_caja, c.nombre AS caja_nombre,
           (SELECT COALESCE(SUM(dv.cantidad), 0) FROM Detalle_Venta dv WHERE dv.id_venta = v.id_venta) AS articulos
    FROM Ventas v
    INNER JOIN Usuarios u     ON u.id_usuario = v.id_cajero
    INNER JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
    LEFT JOIN Cajas c         ON c.id_caja = v.id_caja
    WHERE $whereTabla
    ORDER BY $orderBy
";

$nombreCaja = static fn(array $v): string => $v['id_caja'] === null
    ? t('ventas.sin_caja')
    : t('cajas.caja_n', ['n' => $v['numero_caja']]) . ($v['caja_nombre'] ? ' · ' . $v['caja_nombre'] : '');

/* ============================================================
 * Exportar CSV: la tabla tal como está filtrada y ordenada
 * ============================================================ */
if (($_GET['exportar'] ?? '') === 'csv') {
    $stmt = $pdo->prepare($selectVentas);
    $stmt->execute($paramsTabla);

    $filas = (function () use ($stmt, $nombreCaja) {
        while ($v = $stmt->fetch()) {
            yield [
                $v['id_venta'],
                fecha($v['fecha_hora']),
                $v['cajero_nombre'],
                $nombreCaja($v),
                tValor('metodo_pago', $v['nombre_metodo']),
                (int) $v['articulos'],
                number_format((float) $v['subtotal'], 2, '.', ''),
                number_format((float) $v['total'] - (float) $v['subtotal'], 2, '.', ''),
                number_format((float) $v['total'], 2, '.', ''),
            ];
        }
    })();

    enviarCsv(
        t('ventas.csv_archivo') . '_' . $desde->format('Y-m-d') . '_' . $hasta->format('Y-m-d') . '.csv',
        [t('ventas.col_folio'), t('comun.fecha'), t('ventas.col_cajero'), t('ventas.col_caja'), t('ventas.col_metodo'),
         t('ventas.col_articulos'), t('ventas.col_subtotal'), t('ventas.col_iva'), t('ventas.col_total')],
        $filas
    );
}

/* ============================================================
 * Filas de la tabla. Se usa en la carga normal y en la carga
 * progresiva (?parcial=1), que pide la página siguiente al bajar.
 * $grupoPrevio: último grupo ya pintado, para no repetir su encabezado.
 * ============================================================ */
$totalesGrupo = [];
if ($agrupar !== '') {
    $colGrupo = ['cajero' => 'v.id_cajero', 'metodo' => 'v.id_metodo_pago', 'caja' => 'v.id_caja'][$agrupar];
    $stmt = $pdo->prepare("SELECT $colGrupo AS clave, COUNT(*) AS num, SUM(v.total) AS monto FROM Ventas v WHERE $whereTabla GROUP BY $colGrupo");
    $stmt->execute($paramsTabla);
    foreach ($stmt->fetchAll() as $g) {
        $totalesGrupo[(string) $g['clave']] = $g;
    }
}

$cargarPagina = static function (int $pagina) use ($pdo, $selectVentas, $paramsTabla): array {
    $stmt = $pdo->prepare($selectVentas . " LIMIT " . VENTAS_POR_PAGINA . " OFFSET " . (($pagina - 1) * VENTAS_POR_PAGINA));
    $stmt->execute($paramsTabla);
    $ventas = $stmt->fetchAll();

    $articulos = [];
    if ($ventas) {
        $ids = array_map(fn($v) => (int) $v['id_venta'], $ventas);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT dv.id_venta, p.nombre, dv.cantidad
            FROM Detalle_Venta dv
            INNER JOIN Productos p ON p.id_producto = dv.id_producto
            WHERE dv.id_venta IN ($marcas)
            ORDER BY p.nombre
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $a) {
            $articulos[(int) $a['id_venta']][] = $a;
        }
    }
    return [$ventas, $articulos];
};

/** Devuelve [html de las filas, clave del último grupo pintado]. */
$pintarFilas = static function (array $ventas, array $articulosPorVenta, ?string $grupoPrevio)
    use ($agrupar, $agrupaciones, $totalesGrupo, $nombreCaja): array {
    $grupoActual = $grupoPrevio;
    ob_start();
    foreach ($ventas as $v) {
        if ($agrupar !== '') {
            $claveGrupo = (string) ($v[$agrupaciones[$agrupar][2]] ?? '');
            if ($claveGrupo !== $grupoActual) {
                $grupoActual = $claveGrupo;
                $titulo = match ($agrupar) {
                    'cajero' => $v['cajero_nombre'],
                    'metodo' => tValor('metodo_pago', $v['nombre_metodo']),
                    'caja'   => $nombreCaja($v),
                };
                $tg = $totalesGrupo[$claveGrupo] ?? ['num' => 0, 'monto' => 0];
                echo '<tr class="fila-grupo"><td colspan="8"><strong>' . e($titulo) . '</strong><span>'
                    . e(tn('ventas.n_ventas', (int) $tg['num'])) . ' · ' . dinero($tg['monto'])
                    . '</span></td></tr>';
            }
        }
        $articulos = $articulosPorVenta[(int) $v['id_venta']] ?? [];
        $numArticulos = (int) $v['articulos'];
        ?>
        <tr>
            <td><strong>#<?= (int) $v['id_venta'] ?></strong></td>
            <td><?= e(fecha($v['fecha_hora'])) ?></td>
            <td><?= e($v['cajero_nombre']) ?></td>
            <td>
                <?php if ($v['id_caja'] === null): ?>
                    <span class="badge badge-warning" title="<?= e(t('ventas.sin_caja_ayuda')) ?>"><?= e(t('ventas.sin_caja')) ?></span>
                <?php else: ?>
                    <?= e(t('cajas.caja_n', ['n' => $v['numero_caja']])) ?>
                <?php endif; ?>
            </td>
            <td><span class="badge badge-metodo"><?= e(tValor('metodo_pago', $v['nombre_metodo'])) ?></span></td>
            <td>
                <?php if ($articulos): ?>
                    <details class="venta-articulos">
                        <summary><?= e(tn('ventas.n_articulos', $numArticulos)) ?></summary>
                        <ul>
                            <?php foreach ($articulos as $a): ?>
                                <li><?= numero((int) $a['cantidad']) ?> × <?= e($a['nombre']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php else: ?>
                    <span class="texto-tenue">—</span>
                <?php endif; ?>
            </td>
            <td class="num"><?= dinero($v['subtotal']) ?></td>
            <td class="num"><strong><?= dinero($v['total']) ?></strong></td>
        </tr>
        <?php
    }
    return [ob_get_clean(), $grupoActual];
};

$stmt = $pdo->prepare("SELECT COUNT(*) AS num, COALESCE(SUM(v.total), 0) AS monto FROM Ventas v WHERE $whereTabla");
$stmt->execute($paramsTabla);
$resumenTabla = $stmt->fetch();
$numTabla     = (int) $resumenTabla['num'];
$totalPaginas = max(1, (int) ceil($numTabla / VENTAS_POR_PAGINA));
$pagina       = min($totalPaginas, max(1, (int) ($_GET['pagina'] ?? 1)));

// Carga progresiva: solo las filas de la página pedida
if (($_GET['parcial'] ?? '') === '1') {
    [$ventas, $articulos] = $cargarPagina($pagina);
    [$html, $ultimoGrupo] = $pintarFilas($ventas, $articulos, isset($_GET['grupo_previo']) ? (string) $_GET['grupo_previo'] : null);
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Hay-Mas: ' . ($pagina < $totalPaginas ? '1' : '0'));
    header('X-Ultimo-Grupo: ' . rawurlencode((string) $ultimoGrupo));
    header('X-Mostrando: ' . min($numTabla, $pagina * VENTAS_POR_PAGINA));
    echo $html;
    exit;
}

/* ============================================================
 * 3. KPIs del periodo
 * ============================================================ */
$stmt = $pdo->prepare("SELECT COUNT(*) AS num, COALESCE(SUM(v.total), 0) AS monto FROM Ventas v WHERE $wherePeriodo");
$stmt->execute($paramsPeriodo);
$resumen   = $stmt->fetch();
$numVentas = (int) $resumen['num'];
$monto     = (float) $resumen['monto'];
$ticket    = $numVentas > 0 ? $monto / $numVentas : 0.0;

$stmt = $pdo->prepare("SELECT COALESCE(SUM(dv.cantidad), 0) FROM Detalle_Venta dv INNER JOIN Ventas v ON v.id_venta = dv.id_venta WHERE $wherePeriodo");
$stmt->execute($paramsPeriodo);
$unidades = (int) $stmt->fetchColumn();

$variacion = null;
if (!$esTodo) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(v.total), 0) FROM Ventas v WHERE $wherePeriodo");
    $stmt->execute(array_merge([$idSucursalGerente, $inicioAnterior, $inicio], $paramsFiltros));
    $montoAnterior = (float) $stmt->fetchColumn();
    $variacion = $montoAnterior > 0 ? ($monto - $montoAnterior) / $montoAnterior * 100 : null;
}

/* ============================================================
 * 4. Desgloses del periodo
 * ============================================================ */
$stmt = $pdo->prepare("
    SELECT m.nombre_metodo AS etiqueta, COUNT(*) AS num, SUM(v.total) AS monto
    FROM Ventas v INNER JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
    WHERE $wherePeriodo
    GROUP BY m.id_metodo_pago, m.nombre_metodo
    ORDER BY monto DESC
");
$stmt->execute($paramsPeriodo);
$porMetodo = array_map(fn($f) => ['etiqueta' => tValor('metodo_pago', $f['etiqueta'])] + $f, $stmt->fetchAll());

$stmt = $pdo->prepare("
    SELECT u.nombre AS etiqueta, COUNT(*) AS num, SUM(v.total) AS monto
    FROM Ventas v INNER JOIN Usuarios u ON u.id_usuario = v.id_cajero
    WHERE $wherePeriodo
    GROUP BY u.id_usuario, u.nombre
    ORDER BY monto DESC
");
$stmt->execute($paramsPeriodo);
$porCajero = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT v.id_caja, c.numero_caja, c.nombre AS caja_nombre, COUNT(*) AS num, SUM(v.total) AS monto
    FROM Ventas v LEFT JOIN Cajas c ON c.id_caja = v.id_caja
    WHERE $wherePeriodo
    GROUP BY v.id_caja, c.numero_caja, c.nombre
    ORDER BY monto DESC
");
$stmt->execute($paramsPeriodo);
$porCaja = array_map(fn($f) => $f + ['etiqueta' => $nombreCaja($f)], $stmt->fetchAll());
$ventasSinCaja = 0;
foreach ($porCaja as $f) {
    if ($f['id_caja'] === null) $ventasSinCaja = (int) $f['num'];
}

$stmt = $pdo->prepare("
    SELECT p.nombre, p.codigo, SUM(dv.cantidad) AS unidades
    FROM Detalle_Venta dv
    INNER JOIN Ventas v    ON v.id_venta = dv.id_venta
    INNER JOIN Productos p ON p.id_producto = dv.id_producto
    WHERE $wherePeriodo
    GROUP BY p.id_producto, p.nombre, p.codigo
    ORDER BY unidades DESC
    LIMIT 5
");
$stmt->execute($paramsPeriodo);
$topProductos = $stmt->fetchAll();

/* ============================================================
 * URLs: cada enlace conserva el resto del estado
 * ============================================================ */
$estadoPeriodo = $atajoActivo
    ? ['periodo' => $atajoActivo]
    : ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')];
$estadoTabla = array_filter([
    'id_cajero'   => $idCajero ?: null,
    'id_metodo'   => $idMetodo ?: null,
    'id_caja'     => $idCaja ?: null,
    'id_producto' => $idProducto ?: null,
    'orden'       => $orden !== 'recientes' ? $orden : null,
    'agrupar'     => $agrupar ?: null,
]);
$url = static fn(array $params) => 'ventas_sucursal.php?' . http_build_query(array_filter($params, fn($v) => $v !== null));

$hayFiltros = $idCajero || $idMetodo || $idCaja || $idProducto;
$pct = static fn(float $parte, float $todo) => $todo > 0 ? round($parte / $todo * 100) : 0;

// Barra de filtros unificada: periodo + caja, cajero, método y producto
$opciones = static fn(array $lista, array $rangos) => array_map(
    fn($id, $texto) => ['valor' => (string) $id, 'texto' => $texto, 'rango' => $rangos[(string) $id] ?? null],
    array_keys($lista), $lista
);
$opcionesCaja = $opciones($cajas, $rangosCaja);
if (isset($rangosCaja[(string) SIN_CAJA])) {
    $opcionesCaja[] = ['valor' => (string) SIN_CAJA, 'texto' => t('ventas.sin_caja'), 'rango' => $rangosCaja[(string) SIN_CAJA]];
}
$filtros = [
    'accion'  => 'ventas_sucursal.php',
    'ancla'   => '',
    'ocultos' => array_filter(['orden' => $orden !== 'recientes' ? $orden : null, 'agrupar' => $agrupar ?: null]),
    'periodo' => [
        'desde' => $desde, 'hasta' => $hasta, 'inicio' => $inicioHistorico, 'hoy' => $hoy,
        'atajos' => $periodo['atajos'], 'dias' => $diasConVentas,
    ],
    'facetas' => [
        ['nombre' => 'id_caja',     'etiqueta' => t('ventas.col_caja'),    'todos' => t('ventas.filtro_cajas'),     'valor' => (string) $idCaja,     'opciones' => $opcionesCaja],
        ['nombre' => 'id_cajero',   'etiqueta' => t('ventas.col_cajero'),  'todos' => t('ventas.filtro_cajeros'),   'valor' => (string) $idCajero,   'opciones' => $opciones($cajeros, $rangosCajero)],
        ['nombre' => 'id_metodo',   'etiqueta' => t('ventas.col_metodo'),  'todos' => t('ventas.filtro_metodos'),   'valor' => (string) $idMetodo,   'opciones' => $opciones($metodos, $rangosMetodo)],
        ['nombre' => 'id_producto', 'etiqueta' => t('filtros.producto'),   'todos' => t('ventas.filtro_productos'), 'valor' => (string) $idProducto, 'opciones' => $opciones($productosVendidos, $rangosProducto)],
    ],
    'limpiar' => $hayFiltros ? $url($estadoPeriodo + array_intersect_key($estadoTabla, ['orden' => 1, 'agrupar' => 1])) : null,
];

$pageTitle    = t('ventas.titulo');
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('ventas.titulo')) ?></h1>
        <p>
            <?= e($gerente['sucursal_nombre']) ?> ·
            <?php if ($dias === 1): ?>
                <?= e(fecha($desde, false)) ?>
            <?php else: ?>
                <?= e(fecha($desde, false)) ?> – <?= e(fecha($hasta, false)) ?> (<?= e(tn('comun.n_dias', $dias)) ?>)
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="<?= e($url($estadoPeriodo + $estadoTabla + ['exportar' => 'csv'])) ?>"><?= e(t('comun.exportar_csv')) ?></a>
</div>

<?php require __DIR__ . '/includes/barra_filtros.php'; ?>

<section class="kpi-grid">
    <div class="kpi kpi--ok">
        <span class="kpi-label"><?= e(t('ventas.kpi_monto')) ?></span>
        <span class="kpi-value kpi-value--ok"><?= dinero($monto) ?></span>
        <span class="kpi-sub">
            <?php if ($esTodo): ?>
                <?= e(t('ventas.kpi_desde', ['fecha' => fecha($desde, false)])) ?>
            <?php elseif ($variacion === null): ?>
                <?= e(t('ventas.kpi_sin_anterior')) ?>
            <?php else: ?>
                <span class="<?= $variacion >= 0 ? 'texto-ok' : 'texto-peligro' ?>" style="font-weight: 600;">
                    <?= $variacion >= 0 ? '▲' : '▼' ?> <?= numero(abs($variacion), 1) ?>%
                </span>
                <?= e(tn('ventas.kpi_vs_anterior', $dias)) ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="kpi">
        <span class="kpi-label"><?= e(t('ventas.kpi_ventas')) ?></span>
        <span class="kpi-value"><?= numero($numVentas) ?></span>
        <span class="kpi-sub"><?= e(t('ventas.kpi_ventas_sub')) ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label"><?= e(t('ventas.kpi_ticket')) ?></span>
        <span class="kpi-value"><?= dinero($ticket) ?></span>
        <span class="kpi-sub"><?= e(t('ventas.kpi_ticket_sub')) ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label"><?= e(t('ventas.kpi_unidades')) ?></span>
        <span class="kpi-value"><?= numero($unidades) ?></span>
        <span class="kpi-sub"><?= e(t('ventas.kpi_unidades_sub')) ?></span>
    </div>
</section>

<?php if ($ventasSinCaja > 0): ?>
    <div class="alert alert-warning">
        <?= e(tn('ventas.aviso_sin_caja', $ventasSinCaja)) ?>
        <a href="<?= e($url($estadoPeriodo + ['id_caja' => SIN_CAJA]) . '#tabla-ventas') ?>"><?= e(t('ventas.aviso_sin_caja_ver')) ?></a>
    </div>
<?php endif; ?>

<?php if ($numVentas === 0): ?>
    <div class="panel">
        <div class="empty-state"><?= e(t('ventas.vacio')) ?></div>
    </div>
<?php else: ?>

    <?php
    $desgloses = [
        'metodo' => [t('ventas.desglose_metodo'), $porMetodo],
        'cajero' => [t('ventas.desglose_cajero'), $porCajero],
        'caja'   => [t('ventas.desglose_caja'),   $porCaja],
    ];
    ?>
    <div class="ventas-desgloses">
        <?php foreach ($desgloses as $clave => [$titulo, $filas]): ?>
            <?php $activo = $agrupar === $clave; ?>
            <section class="desglose <?= $activo ? 'is-activo' : '' ?>">
                <header class="desglose-head">
                    <h2><?= e($titulo) ?></h2>
                    <a class="btn btn-sm <?= $activo ? 'btn-primary' : 'btn-ghost' ?>"
                       href="<?= e($url($estadoPeriodo + ['agrupar' => $activo ? null : $clave] + $estadoTabla) . '#tabla-ventas') ?>">
                        <?= e($activo ? t('ventas.agrupado') : t('ventas.agrupar_tabla')) ?>
                    </a>
                </header>
                <ul class="desglose-lista">
                    <?php foreach ($filas as $f): ?>
                        <?php $porcentaje = $pct((float) $f['monto'], $monto); ?>
                        <li>
                            <div class="desglose-fila">
                                <span class="desglose-nombre"><?= e($f['etiqueta']) ?></span>
                                <span class="desglose-num"><?= e(tn('ventas.n_ventas', (int) $f['num'])) ?></span>
                                <strong class="desglose-monto"><?= dinero($f['monto']) ?></strong>
                                <span class="desglose-pct"><?= $porcentaje ?>%</span>
                            </div>
                            <div class="desglose-barra"><span style="width: <?= $porcentaje ?>%"></span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>

        <section class="desglose">
            <header class="desglose-head"><h2><?= e(t('ventas.desglose_top')) ?></h2></header>
            <ol class="desglose-lista desglose-lista--top">
                <?php foreach ($topProductos as $f): ?>
                    <li>
                        <div class="desglose-fila">
                            <span class="desglose-nombre"><?= e($f['nombre']) ?> <small><?= e($f['codigo']) ?></small></span>
                            <strong class="desglose-monto"><?= e(tn('stock.unidades', (int) $f['unidades'])) ?></strong>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <!-- Tabla de ventas con sus herramientas justo encima -->
    <section class="panel ventas-tabla" id="tabla-ventas">
        <div class="ventas-tabla-head">
            <div>
                <h2 class="section-title"><?= e(t('ventas.tabla_titulo')) ?></h2>
                <p>
                    <?= e(tn('ventas.n_ventas', $numTabla)) ?> · <?= dinero($resumenTabla['monto']) ?>
                </p>
            </div>
            <div class="segmentado" role="group" aria-label="<?= e(t('ventas.agrupar_aria')) ?>">
                <?php foreach ($agrupaciones as $clave => [$texto]): ?>
                    <a href="<?= e($url($estadoPeriodo + ['agrupar' => $clave ?: null] + $estadoTabla) . '#tabla-ventas') ?>"
                       class="<?= $clave === $agrupar ? 'is-activo' : '' ?>"
                       <?= $clave === $agrupar ? 'aria-current="true"' : '' ?>><?= e($texto) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="GET" action="ventas_sucursal.php#tabla-ventas" class="toolbar ventas-filtros" onchange="this.submit()">
            <?php foreach ($estadoPeriodo + $estadoTabla as $k => $v): ?>
                <?php if ($k !== 'orden'): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <select name="orden" aria-label="<?= e(t('ventas.ordenar')) ?>">
                <?php foreach ($ordenes as $clave => [$texto]): ?>
                    <option value="<?= $clave ?>"<?= $clave === $orden ? ' selected' : '' ?>><?= e(t('ventas.ordenar')) ?>: <?= e($texto) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-primary"><?= e(t('comun.aplicar')) ?></button></noscript>
        </form>

        <?php if ($numTabla === 0): ?>
            <div class="empty-state"><?= e(t('ventas.sin_coincidencias')) ?></div>
        <?php else: ?>
            <?php
                [$ventas, $articulos] = $cargarPagina($pagina);
                [$filasHtml, $ultimoGrupo] = $pintarFilas($ventas, $articulos, null);
            ?>
            <div class="tabla-scroll">
                <table class="tabla-productos">
                    <thead>
                        <tr>
                            <th><?= e(t('ventas.col_folio')) ?></th>
                            <th><?= e(t('comun.fecha')) ?></th>
                            <th><?= e(t('ventas.col_cajero')) ?></th>
                            <th><?= e(t('ventas.col_caja')) ?></th>
                            <th><?= e(t('ventas.col_metodo')) ?></th>
                            <th><?= e(t('ventas.col_articulos')) ?></th>
                            <th class="num"><?= e(t('ventas.col_subtotal')) ?></th>
                            <th class="num"><?= e(t('ventas.col_total')) ?></th>
                        </tr>
                    </thead>
                    <tbody data-scroll-infinito
                           data-url="<?= e($url($estadoPeriodo + $estadoTabla + ['parcial' => '1'])) ?>"
                           data-pagina="<?= $pagina ?>"
                           data-total-paginas="<?= $totalPaginas ?>"
                           data-ultimo-grupo="<?= e((string) $ultimoGrupo) ?>">
                        <?= $filasHtml ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagina < $totalPaginas): ?>
                <div class="carga-progresiva">
                    <a class="btn btn-ghost btn-sm" data-cargar-mas
                       href="<?= e($url($estadoPeriodo + $estadoTabla + ['pagina' => $pagina + 1]) . '#tabla-ventas') ?>">
                        <?= e(t('comun.cargar_mas')) ?>
                    </a>
                    <span class="texto-tenue" data-contador data-total="<?= $numTabla ?>">
                        <?= e(t('comun.mostrando', ['n' => numero(min($numTabla, $pagina * VENTAS_POR_PAGINA)), 'total' => numero($numTabla)])) ?>
                    </span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
