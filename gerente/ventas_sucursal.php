<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/csv.php';

const VENTAS_POR_PAGINA = 50;

/* ============================================================
 * 1. Periodo: rango explícito (?desde=&hasta=) o un atajo (?periodo=)
 *    El periodo es lo único que afecta a los KPIs y a los desgloses.
 * ============================================================ */
$hoy = new DateTimeImmutable('today');

$atajos = [
    'hoy' => ['Hoy',       $hoy],
    '7'   => ['7 días',    $hoy->modify('-6 days')],
    '30'  => ['30 días',   $hoy->modify('-29 days')],
    'mes' => ['Este mes',  $hoy->modify('first day of this month')],
];

$leerFecha = static function (string $valor): ?DateTimeImmutable {
    $f = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return ($f && $f->format('Y-m-d') === $valor) ? $f : null;
};

$desde = $leerFecha((string) ($_GET['desde'] ?? ''));
$hasta = $leerFecha((string) ($_GET['hasta'] ?? ''));

if ($desde && $hasta) {
    if ($desde > $hasta) {
        [$desde, $hasta] = [$hasta, $desde];
    }
} else {
    $clave = array_key_exists($_GET['periodo'] ?? '', $atajos) ? $_GET['periodo'] : 'hoy';
    $desde = $atajos[$clave][1];
    $hasta = $hoy;
}

$atajoActivo = null;
foreach ($atajos as $clave => [, $inicioAtajo]) {
    if ($desde == $inicioAtajo && $hasta == $hoy) {
        $atajoActivo = $clave;
        break;
    }
}

$dias = (int) $desde->diff($hasta)->days + 1;

// Rango semiabierto [inicio, fin)
$inicio = $desde->format('Y-m-d 00:00:00');
$fin    = $hasta->modify('+1 day')->format('Y-m-d 00:00:00');

// Periodo anterior de la misma duración, para comparar
$inicioAnterior = $desde->modify("-{$dias} days")->format('Y-m-d 00:00:00');

$wherePeriodo  = "v.id_sucursal = ? AND v.fecha_hora >= ? AND v.fecha_hora < ?";
$paramsPeriodo = [$idSucursalGerente, $inicio, $fin];

/* ============================================================
 * 2. Opciones de la tabla: filtros, orden y agrupación
 *    Solo afectan a la tabla (y al CSV), no a los KPIs.
 * ============================================================ */
$stmt = $pdo->prepare("SELECT id_usuario, nombre FROM Usuarios WHERE id_sucursal = ? AND id_rol = 3 ORDER BY nombre");
$stmt->execute([$idSucursalGerente]);
$cajeros = array_column($stmt->fetchAll(), 'nombre', 'id_usuario');

$metodos = array_column(
    $pdo->query("SELECT id_metodo_pago, nombre_metodo FROM Metodos_Pago ORDER BY nombre_metodo")->fetchAll(),
    'nombre_metodo', 'id_metodo_pago'
);

$stmt = $pdo->prepare("SELECT id_caja, numero_caja, nombre FROM Cajas WHERE id_sucursal = ? ORDER BY numero_caja");
$stmt->execute([$idSucursalGerente]);
$cajas = [];
foreach ($stmt->fetchAll() as $c) {
    $cajas[(int) $c['id_caja']] = 'Caja ' . $c['numero_caja'] . ($c['nombre'] ? ' · ' . $c['nombre'] : '');
}
const SIN_CAJA = -1;

// Productos vendidos en el periodo (para "ventas que incluyen…")
$stmt = $pdo->prepare("
    SELECT DISTINCT p.id_producto, p.nombre
    FROM Detalle_Venta dv
    INNER JOIN Ventas v    ON v.id_venta = dv.id_venta
    INNER JOIN Productos p ON p.id_producto = dv.id_producto
    WHERE $wherePeriodo
    ORDER BY p.nombre
");
$stmt->execute($paramsPeriodo);
$productosVendidos = array_column($stmt->fetchAll(), 'nombre', 'id_producto');

$idCajero   = (int) ($_GET['id_cajero'] ?? 0);
$idMetodo   = (int) ($_GET['id_metodo'] ?? 0);
$idCaja     = (int) ($_GET['id_caja'] ?? 0);
$idProducto = (int) ($_GET['id_producto'] ?? 0);
if (!isset($cajeros[$idCajero]))   $idCajero = 0;
if (!isset($metodos[$idMetodo]))   $idMetodo = 0;
if ($idCaja !== SIN_CAJA && !isset($cajas[$idCaja])) $idCaja = 0;
if (!isset($productosVendidos[$idProducto])) $idProducto = 0;

$ordenes = [
    'recientes'      => ['Más recientes',          'v.fecha_hora DESC'],
    'antiguas'       => ['Más antiguas',           'v.fecha_hora ASC'],
    'monto_desc'     => ['Mayor monto',            'v.total DESC'],
    'monto_asc'      => ['Menor monto',            'v.total ASC'],
    'articulos_desc' => ['Más artículos',          'articulos DESC'],
    'articulos_asc'  => ['Menos artículos',        'articulos ASC'],
    'efectivo'       => ['Efectivo primero',       "(m.nombre_metodo = 'Efectivo') DESC, v.fecha_hora DESC"],
    'tarjeta'        => ['Tarjeta primero',        "(m.nombre_metodo LIKE 'Tarjeta%') DESC, v.fecha_hora DESC"],
];
$orden = (string) ($_GET['orden'] ?? '');
if (!array_key_exists($orden, $ordenes)) $orden = 'recientes';

// Agrupar: [etiqueta, ORDER BY del grupo, columna de la fila que identifica el grupo]
$agrupaciones = [
    ''       => ['Sin agrupar',  null,                                   null],
    'cajero' => ['Por cajero',   'u.nombre, v.id_cajero',                'id_cajero'],
    'metodo' => ['Por método',   'm.nombre_metodo',                      'id_metodo_pago'],
    'caja'   => ['Por caja',     '(v.id_caja IS NULL), c.numero_caja',   'id_caja'],
];
$agrupar = (string) ($_GET['agrupar'] ?? '');
if (!array_key_exists($agrupar, $agrupaciones)) $agrupar = '';

$whereTabla  = $wherePeriodo;
$paramsTabla = $paramsPeriodo;
if ($idCajero > 0) {
    $whereTabla .= " AND v.id_cajero = ?";
    $paramsTabla[] = $idCajero;
}
if ($idMetodo > 0) {
    $whereTabla .= " AND v.id_metodo_pago = ?";
    $paramsTabla[] = $idMetodo;
}
if ($idCaja === SIN_CAJA) {
    $whereTabla .= " AND v.id_caja IS NULL";
} elseif ($idCaja > 0) {
    $whereTabla .= " AND v.id_caja = ?";
    $paramsTabla[] = $idCaja;
}
if ($idProducto > 0) {
    $whereTabla .= " AND EXISTS (SELECT 1 FROM Detalle_Venta d2 WHERE d2.id_venta = v.id_venta AND d2.id_producto = ?)";
    $paramsTabla[] = $idProducto;
}

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
    ? 'Sin caja'
    : 'Caja ' . $v['numero_caja'] . ($v['caja_nombre'] ? ' · ' . $v['caja_nombre'] : '');

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
                date('d/m/Y H:i', strtotime($v['fecha_hora'])),
                $v['cajero_nombre'],
                $nombreCaja($v),
                $v['nombre_metodo'],
                (int) $v['articulos'],
                number_format((float) $v['subtotal'], 2, '.', ''),
                number_format((float) $v['total'] - (float) $v['subtotal'], 2, '.', ''),
                number_format((float) $v['total'], 2, '.', ''),
            ];
        }
    })();

    enviarCsv(
        'ventas_' . $desde->format('Y-m-d') . '_' . $hasta->format('Y-m-d') . '.csv',
        ['Folio', 'Fecha', 'Cajero', 'Caja', 'Método de pago', 'Artículos', 'Subtotal', 'IVA', 'Total'],
        $filas
    );
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

$stmt = $pdo->prepare("SELECT COALESCE(SUM(v.total), 0) FROM Ventas v WHERE $wherePeriodo");
$stmt->execute([$idSucursalGerente, $inicioAnterior, $inicio]);
$montoAnterior = (float) $stmt->fetchColumn();
$variacion = $montoAnterior > 0 ? ($monto - $montoAnterior) / $montoAnterior * 100 : null;

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
$porMetodo = $stmt->fetchAll();

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
 * 5. Tabla: total filtrado, totales por grupo y página actual
 * ============================================================ */
$stmt = $pdo->prepare("SELECT COUNT(*) AS num, COALESCE(SUM(v.total), 0) AS monto FROM Ventas v WHERE $whereTabla");
$stmt->execute($paramsTabla);
$resumenTabla = $stmt->fetch();
$numTabla = (int) $resumenTabla['num'];

// Totales de cada grupo con los filtros de la tabla (para los encabezados de grupo)
$totalesGrupo = [];
if ($agrupar !== '') {
    $colGrupo = ['cajero' => 'v.id_cajero', 'metodo' => 'v.id_metodo_pago', 'caja' => 'v.id_caja'][$agrupar];
    $stmt = $pdo->prepare("SELECT $colGrupo AS clave, COUNT(*) AS num, SUM(v.total) AS monto FROM Ventas v WHERE $whereTabla GROUP BY $colGrupo");
    $stmt->execute($paramsTabla);
    foreach ($stmt->fetchAll() as $g) {
        $totalesGrupo[(string) $g['clave']] = $g;
    }
}

$totalPaginas = max(1, (int) ceil($numTabla / VENTAS_POR_PAGINA));
$pagina = min($totalPaginas, max(1, (int) ($_GET['pagina'] ?? 1)));
$offset = ($pagina - 1) * VENTAS_POR_PAGINA;

$stmt = $pdo->prepare($selectVentas . " LIMIT " . VENTAS_POR_PAGINA . " OFFSET " . $offset);
$stmt->execute($paramsTabla);
$ventas = $stmt->fetchAll();

$articulosPorVenta = [];
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
        $articulosPorVenta[(int) $a['id_venta']][] = $a;
    }
}

/* ============================================================
 * URLs: cada enlace conserva el resto del estado
 * ============================================================ */
$estadoPeriodo = ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')];
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
$dinero = static fn($n) => '$' . number_format((float) $n, 2);

$pageTitle    = 'Ventas de mi Sucursal';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Ventas de mi sucursal</h1>
        <p>
            Sucursal: <strong><?= htmlspecialchars($gerente['sucursal_nombre']) ?></strong>
            &middot;
            <?php if ($dias === 1): ?>
                <?= $desde->format('d/m/Y') ?>
            <?php else: ?>
                <?= $desde->format('d/m/Y') ?> – <?= $hasta->format('d/m/Y') ?> (<?= $dias ?> días)
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="<?= htmlspecialchars($url($estadoPeriodo + $estadoTabla + ['exportar' => 'csv'])) ?>">Exportar CSV</a>
</div>

<!-- Periodo: afecta a todo -->
<div class="ventas-periodo">
    <div class="acciones">
        <?php foreach ($atajos as $clave => [$texto]): ?>
            <a href="<?= htmlspecialchars($url(['periodo' => $clave] + $estadoTabla)) ?>"
               class="btn btn-sm <?= $clave === $atajoActivo ? 'btn-primary' : 'btn-ghost' ?>"><?= $texto ?></a>
        <?php endforeach; ?>
    </div>
    <form method="GET" action="ventas_sucursal.php" class="ventas-rango">
        <?php foreach ($estadoTabla as $k => $v): ?>
            <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) $v) ?>">
        <?php endforeach; ?>
        <label>Del <input type="date" name="desde" value="<?= $desde->format('Y-m-d') ?>" max="<?= $hoy->format('Y-m-d') ?>"></label>
        <label>al <input type="date" name="hasta" value="<?= $hasta->format('Y-m-d') ?>" max="<?= $hoy->format('Y-m-d') ?>"></label>
        <button type="submit" class="btn btn-ghost btn-sm">Ver periodo</button>
    </form>
</div>

<section class="kpi-grid">
    <div class="kpi kpi--ok">
        <span class="kpi-label">Monto vendido</span>
        <span class="kpi-value kpi-value--ok"><?= $dinero($monto) ?></span>
        <span class="kpi-sub">
            <?php if ($variacion === null): ?>
                sin ventas en el periodo anterior
            <?php else: ?>
                <span style="color: <?= $variacion >= 0 ? '#15803d' : '#dc2626' ?>; font-weight: 600;">
                    <?= $variacion >= 0 ? '▲' : '▼' ?> <?= number_format(abs($variacion), 1) ?>%
                </span>
                vs. <?= $dias === 1 ? 'día anterior' : "{$dias} días anteriores" ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Ventas</span>
        <span class="kpi-value"><?= number_format($numVentas) ?></span>
        <span class="kpi-sub">tickets en el periodo</span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Ticket promedio</span>
        <span class="kpi-value"><?= $dinero($ticket) ?></span>
        <span class="kpi-sub">monto / ventas</span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Unidades vendidas</span>
        <span class="kpi-value"><?= number_format($unidades) ?></span>
        <span class="kpi-sub">artículos en el periodo</span>
    </div>
</section>

<?php if ($ventasSinCaja > 0): ?>
    <div class="alert alert-warning">
        <?= $ventasSinCaja === 1 ? '1 venta de este periodo se registró' : $ventasSinCaja . ' ventas de este periodo se registraron' ?>
        <strong>sin caja asignada</strong>.
        <a href="<?= htmlspecialchars($url($estadoPeriodo + ['id_caja' => SIN_CAJA])) ?>">Ver cuáles</a>
    </div>
<?php endif; ?>

<?php if ($numVentas === 0): ?>
    <div class="panel">
        <div class="empty-state">No hay ventas en este periodo.</div>
    </div>
<?php else: ?>

    <!-- Desgloses del periodo. Los tres primeros agrupan la tabla de abajo. -->
    <?php
    $desgloses = [
        'metodo' => ['Por método de pago', $porMetodo],
        'cajero' => ['Por cajero',         $porCajero],
        'caja'   => ['Por caja',           $porCaja],
    ];
    ?>
    <div class="ventas-desgloses">
        <?php foreach ($desgloses as $clave => [$titulo, $filas]): ?>
            <?php $activo = $agrupar === $clave; ?>
            <section class="desglose <?= $activo ? 'is-activo' : '' ?>">
                <header class="desglose-head">
                    <h2><?= $titulo ?></h2>
                    <a class="btn btn-sm <?= $activo ? 'btn-primary' : 'btn-ghost' ?>"
                       href="<?= htmlspecialchars($url($estadoPeriodo + ['agrupar' => $activo ? null : $clave] + $estadoTabla) . '#tabla-ventas') ?>">
                        <?= $activo ? 'Agrupado ✓' : 'Agrupar tabla' ?>
                    </a>
                </header>
                <ul class="desglose-lista">
                    <?php foreach ($filas as $f): ?>
                        <?php $porcentaje = $pct((float) $f['monto'], $monto); ?>
                        <li>
                            <div class="desglose-fila">
                                <span class="desglose-nombre"><?= htmlspecialchars($f['etiqueta']) ?></span>
                                <span class="desglose-num"><?= (int) $f['num'] ?> venta<?= (int) $f['num'] === 1 ? '' : 's' ?></span>
                                <strong class="desglose-monto"><?= $dinero($f['monto']) ?></strong>
                                <span class="desglose-pct"><?= $porcentaje ?>%</span>
                            </div>
                            <div class="desglose-barra"><span style="width: <?= $porcentaje ?>%"></span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>

        <section class="desglose">
            <header class="desglose-head"><h2>Más vendidos</h2></header>
            <?php if (empty($topProductos)): ?>
                <p class="desglose-vacio">Sin detalle de productos.</p>
            <?php else: ?>
                <ol class="desglose-lista desglose-lista--top">
                    <?php foreach ($topProductos as $f): ?>
                        <li>
                            <div class="desglose-fila">
                                <span class="desglose-nombre">
                                    <?= htmlspecialchars($f['nombre']) ?>
                                    <small><?= htmlspecialchars($f['codigo']) ?></small>
                                </span>
                                <strong class="desglose-monto"><?= (int) $f['unidades'] ?> u.</strong>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <!-- Tabla de ventas con sus propias herramientas justo encima -->
    <section class="panel ventas-tabla" id="tabla-ventas">
        <div class="ventas-tabla-head">
            <div>
                <h2 class="section-title">Ventas</h2>
                <p>
                    <?= number_format($numTabla) ?> de <?= number_format($numVentas) ?> ventas
                    &middot; <?= $dinero($resumenTabla['monto']) ?>
                    <?php if ($hayFiltros): ?>
                        &middot; <a href="<?= htmlspecialchars($url($estadoPeriodo + array_intersect_key($estadoTabla, ['orden' => 1, 'agrupar' => 1])) . '#tabla-ventas') ?>">Quitar filtros</a>
                    <?php endif; ?>
                </p>
            </div>
            <div class="segmentado" role="group" aria-label="Agrupar ventas">
                <?php foreach ($agrupaciones as $clave => [$texto]): ?>
                    <a href="<?= htmlspecialchars($url($estadoPeriodo + ['agrupar' => $clave ?: null] + $estadoTabla) . '#tabla-ventas') ?>"
                       class="<?= $clave === $agrupar ? 'is-activo' : '' ?>"
                       <?= $clave === $agrupar ? 'aria-current="true"' : '' ?>><?= $texto ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="GET" action="ventas_sucursal.php#tabla-ventas" class="toolbar ventas-filtros" onchange="this.submit()">
            <input type="hidden" name="desde" value="<?= $desde->format('Y-m-d') ?>">
            <input type="hidden" name="hasta" value="<?= $hasta->format('Y-m-d') ?>">
            <?php if ($agrupar): ?><input type="hidden" name="agrupar" value="<?= $agrupar ?>"><?php endif; ?>

            <select name="orden" aria-label="Ordenar">
                <?php foreach ($ordenes as $clave => [$texto]): ?>
                    <option value="<?= $clave ?>"<?= $clave === $orden ? ' selected' : '' ?>>Ordenar: <?= $texto ?></option>
                <?php endforeach; ?>
            </select>
            <select name="id_cajero" aria-label="Cajero">
                <option value="0">Todos los cajeros</option>
                <?php foreach ($cajeros as $id => $nombre): ?>
                    <option value="<?= (int) $id ?>"<?= (int) $id === $idCajero ? ' selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="id_metodo" aria-label="Método de pago">
                <option value="0">Todos los métodos</option>
                <?php foreach ($metodos as $id => $nombre): ?>
                    <option value="<?= (int) $id ?>"<?= (int) $id === $idMetodo ? ' selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="id_caja" aria-label="Caja">
                <option value="0">Todas las cajas</option>
                <?php foreach ($cajas as $id => $nombre): ?>
                    <option value="<?= (int) $id ?>"<?= (int) $id === $idCaja ? ' selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
                <option value="<?= SIN_CAJA ?>"<?= $idCaja === SIN_CAJA ? ' selected' : '' ?>>Sin caja</option>
            </select>
            <select name="id_producto" aria-label="Ventas que incluyen el producto">
                <option value="0">Con cualquier producto</option>
                <?php foreach ($productosVendidos as $id => $nombre): ?>
                    <option value="<?= (int) $id ?>"<?= (int) $id === $idProducto ? ' selected' : '' ?>>Incluye: <?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-primary">Aplicar</button></noscript>
        </form>

        <?php if ($numTabla === 0): ?>
            <div class="empty-state">Ninguna venta coincide con estos filtros.</div>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla-productos">
                    <thead>
                        <tr>
                            <th>#Venta</th>
                            <th>Fecha</th>
                            <th>Cajero</th>
                            <th>Caja</th>
                            <th>Método</th>
                            <th>Artículos</th>
                            <th class="num">Subtotal</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $grupoActual = null; ?>
                        <?php foreach ($ventas as $v): ?>
                            <?php
                                if ($agrupar !== '') {
                                    $claveGrupo = (string) ($v[$agrupaciones[$agrupar][2]] ?? '');
                                    if ($claveGrupo !== $grupoActual) {
                                        $grupoActual = $claveGrupo;
                                        $tituloGrupo = match ($agrupar) {
                                            'cajero' => $v['cajero_nombre'],
                                            'metodo' => $v['nombre_metodo'],
                                            'caja'   => $nombreCaja($v),
                                        };
                                        $tg = $totalesGrupo[$claveGrupo] ?? ['num' => 0, 'monto' => 0];
                                        echo '<tr class="fila-grupo"><td colspan="8">'
                                            . '<strong>' . htmlspecialchars($tituloGrupo) . '</strong>'
                                            . '<span>' . (int) $tg['num'] . ' venta' . ((int) $tg['num'] === 1 ? '' : 's')
                                            . ' &middot; ' . $dinero($tg['monto']) . '</span>'
                                            . '</td></tr>';
                                    }
                                }
                                $articulos = $articulosPorVenta[(int) $v['id_venta']] ?? [];
                                $numArticulos = (int) $v['articulos'];
                            ?>
                            <tr>
                                <td><strong>#<?= (int) $v['id_venta'] ?></strong></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['fecha_hora']))) ?></td>
                                <td><?= htmlspecialchars($v['cajero_nombre']) ?></td>
                                <td>
                                    <?php if ($v['id_caja'] === null): ?>
                                        <span class="badge badge-warning" title="Venta registrada sin caja asignada">Sin caja</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars('Caja ' . $v['numero_caja']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-metodo"><?= htmlspecialchars($v['nombre_metodo']) ?></span></td>
                                <td>
                                    <?php if ($articulos): ?>
                                        <details class="venta-articulos">
                                            <summary><?= $numArticulos ?> <?= $numArticulos === 1 ? 'artículo' : 'artículos' ?></summary>
                                            <ul>
                                                <?php foreach ($articulos as $a): ?>
                                                    <li><?= (int) $a['cantidad'] ?> × <?= htmlspecialchars($a['nombre']) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php else: ?>
                                        <span style="color: #64748b;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num"><?= $dinero($v['subtotal']) ?></td>
                                <td class="num"><strong><?= $dinero($v['total']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <div class="ventas-paginacion">
                    <span>
                        Página <?= $pagina ?> de <?= $totalPaginas ?> &middot;
                        <?= $offset + 1 ?>–<?= $offset + count($ventas) ?> de <?= number_format($numTabla) ?>
                    </span>
                    <span class="acciones">
                        <?php if ($pagina > 1): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($url($estadoPeriodo + $estadoTabla + ['pagina' => $pagina - 1]) . '#tabla-ventas') ?>">← Anterior</a>
                        <?php endif; ?>
                        <?php if ($pagina < $totalPaginas): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($url($estadoPeriodo + $estadoTabla + ['pagina' => $pagina + 1]) . '#tabla-ventas') ?>">Siguiente →</a>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
