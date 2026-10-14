<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/csv.php';

const VENTAS_POR_PAGINA = 50;

/* ============================================================
 * Periodo: rango explícito (?desde=&hasta=) o un atajo (?periodo=)
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

// Atajo activo (si el rango coincide con alguno), para resaltarlo
$atajoActivo = null;
foreach ($atajos as $clave => [, $inicioAtajo]) {
    if ($desde == $inicioAtajo && $hasta == $hoy) {
        $atajoActivo = $clave;
        break;
    }
}

$dias = (int) $desde->diff($hasta)->days + 1;

// Rango semiabierto [inicio, fin) para no depender de la hora 23:59:59
$inicio = $desde->format('Y-m-d 00:00:00');
$fin    = $hasta->modify('+1 day')->format('Y-m-d 00:00:00');

// Periodo anterior de la misma duración, para comparar
$inicioAnterior = $desde->modify("-{$dias} days")->format('Y-m-d 00:00:00');
$finAnterior    = $inicio;

/* ============================================================
 * Filtros: cajero y método de pago (validados contra la sucursal)
 * ============================================================ */
$stmt = $pdo->prepare("
    SELECT id_usuario, nombre
    FROM Usuarios
    WHERE id_sucursal = ? AND id_rol = 3
    ORDER BY nombre
");
$stmt->execute([$idSucursalGerente]);
$cajeros = $stmt->fetchAll();
$cajerosPorId = array_column($cajeros, 'nombre', 'id_usuario');

$metodos = $pdo->query("SELECT id_metodo_pago, nombre_metodo FROM Metodos_Pago ORDER BY nombre_metodo")->fetchAll();
$metodosPorId = array_column($metodos, 'nombre_metodo', 'id_metodo_pago');

$idCajero = (int) ($_GET['id_cajero'] ?? 0);
if (!isset($cajerosPorId[$idCajero])) $idCajero = 0;

$idMetodo = (int) ($_GET['id_metodo'] ?? 0);
if (!isset($metodosPorId[$idMetodo])) $idMetodo = 0;

/**
 * WHERE común a todas las consultas. Devuelve [sql, params].
 */
$construirWhere = static function (string $ini, string $fin) use ($idSucursalGerente, $idCajero, $idMetodo): array {
    $sql    = "v.id_sucursal = ? AND v.fecha_hora >= ? AND v.fecha_hora < ?";
    $params = [$idSucursalGerente, $ini, $fin];
    if ($idCajero > 0) {
        $sql .= " AND v.id_cajero = ?";
        $params[] = $idCajero;
    }
    if ($idMetodo > 0) {
        $sql .= " AND v.id_metodo_pago = ?";
        $params[] = $idMetodo;
    }
    return [$sql, $params];
};

[$where, $params] = $construirWhere($inicio, $fin);

/* ============================================================
 * Exportar CSV: todas las ventas del periodo con los filtros actuales
 * ============================================================ */
if (($_GET['exportar'] ?? '') === 'csv') {
    $stmt = $pdo->prepare("
        SELECT v.id_venta, v.fecha_hora, u.nombre AS cajero, m.nombre_metodo,
               (SELECT COALESCE(SUM(dv.cantidad), 0) FROM Detalle_Venta dv WHERE dv.id_venta = v.id_venta) AS articulos,
               v.subtotal, v.total
        FROM Ventas v
        INNER JOIN Usuarios u     ON u.id_usuario = v.id_cajero
        INNER JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
        WHERE $where
        ORDER BY v.fecha_hora, v.id_venta
    ");
    $stmt->execute($params);

    $filas = (function () use ($stmt) {
        while ($v = $stmt->fetch()) {
            yield [
                $v['id_venta'],
                date('d/m/Y H:i', strtotime($v['fecha_hora'])),
                $v['cajero'],
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
        ['Folio', 'Fecha', 'Cajero', 'Método de pago', 'Artículos', 'Subtotal', 'IVA', 'Total'],
        $filas
    );
}

/* ============================================================
 * KPIs (sobre todo el periodo, no sobre la página mostrada)
 * ============================================================ */
$stmt = $pdo->prepare("SELECT COUNT(*) AS num, COALESCE(SUM(v.total), 0) AS monto FROM Ventas v WHERE $where");
$stmt->execute($params);
$resumen = $stmt->fetch();
$numVentas = (int) $resumen['num'];
$monto     = (float) $resumen['monto'];
$ticket    = $numVentas > 0 ? $monto / $numVentas : 0.0;

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(dv.cantidad), 0)
    FROM Detalle_Venta dv
    INNER JOIN Ventas v ON v.id_venta = dv.id_venta
    WHERE $where
");
$stmt->execute($params);
$unidades = (int) $stmt->fetchColumn();

[$whereAnt, $paramsAnt] = $construirWhere($inicioAnterior, $finAnterior);
$stmt = $pdo->prepare("SELECT COALESCE(SUM(v.total), 0) FROM Ventas v WHERE $whereAnt");
$stmt->execute($paramsAnt);
$montoAnterior = (float) $stmt->fetchColumn();
$variacion = $montoAnterior > 0 ? ($monto - $montoAnterior) / $montoAnterior * 100 : null;

/* ============================================================
 * Desgloses
 * ============================================================ */
$stmt = $pdo->prepare("
    SELECT m.nombre_metodo, COUNT(*) AS num, SUM(v.total) AS monto
    FROM Ventas v
    INNER JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
    WHERE $where
    GROUP BY m.id_metodo_pago, m.nombre_metodo
    ORDER BY monto DESC
");
$stmt->execute($params);
$porMetodo = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT u.nombre, COUNT(*) AS num, SUM(v.total) AS monto
    FROM Ventas v
    INNER JOIN Usuarios u ON u.id_usuario = v.id_cajero
    WHERE $where
    GROUP BY u.id_usuario, u.nombre
    ORDER BY monto DESC
");
$stmt->execute($params);
$porCajero = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.nombre, p.codigo, SUM(dv.cantidad) AS unidades
    FROM Detalle_Venta dv
    INNER JOIN Ventas v    ON v.id_venta = dv.id_venta
    INNER JOIN Productos p ON p.id_producto = dv.id_producto
    WHERE $where
    GROUP BY p.id_producto, p.nombre, p.codigo
    ORDER BY unidades DESC
    LIMIT 5
");
$stmt->execute($params);
$topProductos = $stmt->fetchAll();

/* ============================================================
 * Listado paginado + artículos de cada venta de la página
 * ============================================================ */
$totalPaginas = max(1, (int) ceil($numVentas / VENTAS_POR_PAGINA));
$pagina = min($totalPaginas, max(1, (int) ($_GET['pagina'] ?? 1)));
$offset = ($pagina - 1) * VENTAS_POR_PAGINA;

$stmt = $pdo->prepare("
    SELECT v.id_venta, v.fecha_hora, v.subtotal, v.total,
           u.nombre AS cajero_nombre,
           m.nombre_metodo
    FROM Ventas v
    INNER JOIN Usuarios u     ON u.id_usuario = v.id_cajero
    INNER JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
    WHERE $where
    ORDER BY v.fecha_hora DESC, v.id_venta DESC
    LIMIT " . VENTAS_POR_PAGINA . " OFFSET " . $offset
);
$stmt->execute($params);
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
 * URLs que conservan los filtros
 * ============================================================ */
$filtrosBase = array_filter([
    'id_cajero' => $idCajero ?: null,
    'id_metodo' => $idMetodo ?: null,
]);
$filtrosRango = $filtrosBase + ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')];
$urlVentas = fn(array $extra) => 'ventas_sucursal.php?' . http_build_query($extra);

$pct = fn(float $parte, float $todo) => $todo > 0 ? round($parte / $todo * 100) : 0;

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
    <a class="btn btn-ghost"
       href="<?= htmlspecialchars($urlVentas($filtrosRango + ['exportar' => 'csv'])) ?>">Exportar CSV</a>
</div>

<div class="acciones" style="margin-bottom: 0.75rem;">
    <?php foreach ($atajos as $clave => [$texto]): ?>
        <a href="<?= htmlspecialchars($urlVentas($filtrosBase + ['periodo' => $clave])) ?>"
           class="btn btn-sm <?= $clave === $atajoActivo ? 'btn-primary' : 'btn-ghost' ?>"><?= $texto ?></a>
    <?php endforeach; ?>
</div>

<form method="GET" action="ventas_sucursal.php" class="toolbar">
    <input type="date" name="desde" value="<?= $desde->format('Y-m-d') ?>"
           max="<?= $hoy->format('Y-m-d') ?>" aria-label="Desde">
    <input type="date" name="hasta" value="<?= $hasta->format('Y-m-d') ?>"
           max="<?= $hoy->format('Y-m-d') ?>" aria-label="Hasta">
    <select name="id_cajero" aria-label="Cajero">
        <option value="0">Todos los cajeros</option>
        <?php foreach ($cajeros as $c): ?>
            <option value="<?= (int) $c['id_usuario'] ?>"<?= (int) $c['id_usuario'] === $idCajero ? ' selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="id_metodo" aria-label="Método de pago">
        <option value="0">Todos los métodos</option>
        <?php foreach ($metodos as $m): ?>
            <option value="<?= (int) $m['id_metodo_pago'] ?>"<?= (int) $m['id_metodo_pago'] === $idMetodo ? ' selected' : '' ?>>
                <?= htmlspecialchars($m['nombre_metodo']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Aplicar</button>
    <?php if ($idCajero || $idMetodo): ?>
        <a href="<?= htmlspecialchars($urlVentas(['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')])) ?>"
           class="btn btn-ghost">Quitar filtros</a>
    <?php endif; ?>
</form>

<section class="kpi-grid">
    <div class="kpi kpi--ok">
        <span class="kpi-label">Monto vendido</span>
        <span class="kpi-value kpi-value--ok">$<?= number_format($monto, 2) ?></span>
        <span class="kpi-sub">
            <?php if ($variacion === null): ?>
                sin ventas en el periodo anterior
            <?php else: ?>
                <span style="color: <?= $variacion >= 0 ? '#16a34a' : '#dc2626' ?>; font-weight: 600;">
                    <?= $variacion >= 0 ? '▲' : '▼' ?> <?= number_format(abs($variacion), 1) ?>%
                </span>
                vs. <?= $dias === 1 ? 'día' : $dias . ' días' ?> anterior<?= $dias === 1 ? '' : 'es' ?>
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
        <span class="kpi-value">$<?= number_format($ticket, 2) ?></span>
        <span class="kpi-sub">monto / ventas</span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Unidades vendidas</span>
        <span class="kpi-value"><?= number_format($unidades) ?></span>
        <span class="kpi-sub">artículos en el periodo</span>
    </div>
</section>

<?php if ($numVentas === 0): ?>
    <div class="panel">
        <div class="empty-state">
            No hay ventas en este periodo<?= ($idCajero || $idMetodo) ? ' con los filtros seleccionados' : '' ?>.
        </div>
    </div>
<?php else: ?>

    <div class="ventas-desgloses">
        <div class="panel">
            <h2 class="section-title">Por método de pago</h2>
            <table class="tabla-productos">
                <tbody>
                    <?php foreach ($porMetodo as $fila): ?>
                        <tr>
                            <td><span class="badge badge-metodo"><?= htmlspecialchars($fila['nombre_metodo']) ?></span></td>
                            <td><?= (int) $fila['num'] ?> ventas</td>
                            <td><strong>$<?= number_format((float) $fila['monto'], 2) ?></strong></td>
                            <td style="color:#64748b;"><?= $pct((float) $fila['monto'], $monto) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2 class="section-title">Por cajero</h2>
            <table class="tabla-productos">
                <tbody>
                    <?php foreach ($porCajero as $fila): ?>
                        <tr>
                            <td><?= htmlspecialchars($fila['nombre']) ?></td>
                            <td><?= (int) $fila['num'] ?> ventas</td>
                            <td><strong>$<?= number_format((float) $fila['monto'], 2) ?></strong></td>
                            <td style="color:#64748b;"><?= $pct((float) $fila['monto'], $monto) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2 class="section-title">Más vendidos</h2>
            <?php if (empty($topProductos)): ?>
                <div class="empty-state">Sin detalle de productos.</div>
            <?php else: ?>
                <table class="tabla-productos">
                    <tbody>
                        <?php foreach ($topProductos as $i => $fila): ?>
                            <tr>
                                <td style="color:#94a3b8;"><?= $i + 1 ?></td>
                                <td>
                                    <div class="producto-nombre"><?= htmlspecialchars($fila['nombre']) ?></div>
                                    <div class="producto-desc"><?= htmlspecialchars($fila['codigo']) ?></div>
                                </td>
                                <td><strong><?= (int) $fila['unidades'] ?></strong> u.</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="tabla-scroll">
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th>#Venta</th>
                        <th>Fecha</th>
                        <th>Cajero</th>
                        <th>Método de pago</th>
                        <th>Artículos</th>
                        <th>Subtotal</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v): ?>
                        <?php
                            $articulos = $articulosPorVenta[(int) $v['id_venta']] ?? [];
                            $numArticulos = array_sum(array_map(fn($a) => (int) $a['cantidad'], $articulos));
                        ?>
                        <tr>
                            <td><strong>#<?= (int) $v['id_venta'] ?></strong></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['fecha_hora']))) ?></td>
                            <td><?= htmlspecialchars($v['cajero_nombre']) ?></td>
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
                                    <span style="color:#94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>$<?= number_format((float) $v['subtotal'], 2) ?></td>
                            <td><strong>$<?= number_format((float) $v['total'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="acciones" style="justify-content: space-between; align-items: center; padding: 1rem 0 0;">
                <span style="color:#64748b; font-size:0.9rem;">
                    Página <?= $pagina ?> de <?= $totalPaginas ?> &middot;
                    <?= $offset + 1 ?>–<?= $offset + count($ventas) ?> de <?= number_format($numVentas) ?> ventas
                </span>
                <span class="acciones">
                    <?php if ($pagina > 1): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($urlVentas($filtrosRango + ['pagina' => $pagina - 1])) ?>">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($pagina < $totalPaginas): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($urlVentas($filtrosRango + ['pagina' => $pagina + 1])) ?>">Siguiente →</a>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
