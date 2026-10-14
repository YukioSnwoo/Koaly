<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';

const MOVIMIENTOS_POR_PAGINA = 50;

/* ============================================================
 * Filtros: producto, tipo y rango de fechas (por defecto 30 días)
 * ============================================================ */
$hoy = new DateTimeImmutable('today');

$leerFecha = static function (string $valor): ?DateTimeImmutable {
    $f = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return ($f && $f->format('Y-m-d') === $valor) ? $f : null;
};

$desde = $leerFecha((string) ($_GET['desde'] ?? '')) ?? $hoy->modify('-29 days');
$hasta = $leerFecha((string) ($_GET['hasta'] ?? '')) ?? $hoy;
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

// Productos del inventario de la sucursal (también valida ?producto=)
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.codigo, p.nombre
    FROM Inventario_Sucursal i
    INNER JOIN Productos p ON p.id_producto = i.id_producto
    WHERE i.id_sucursal = ?
    ORDER BY p.nombre
");
$stmt->execute([$idSucursalGerente]);
$productos = $stmt->fetchAll();
$productosPorId = array_column($productos, null, 'id_producto');

$idProducto = (int) ($_GET['producto'] ?? 0);
if (!isset($productosPorId[$idProducto])) $idProducto = 0;

$tipo = (string) ($_GET['tipo'] ?? '');
if (!isset(TIPOS_MOVIMIENTO[$tipo])) $tipo = '';

$where  = "m.id_sucursal = ? AND m.fecha_hora >= ? AND m.fecha_hora < ?";
$params = [$idSucursalGerente, $desde->format('Y-m-d 00:00:00'), $hasta->modify('+1 day')->format('Y-m-d 00:00:00')];
if ($idProducto > 0) {
    $where .= " AND m.id_producto = ?";
    $params[] = $idProducto;
}
if ($tipo !== '') {
    $where .= " AND m.tipo_movimiento = ?";
    $params[] = $tipo;
}

/* ============================================================
 * Resumen: entradas y salidas del periodo
 * ============================================================ */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS num,
           COALESCE(SUM(CASE WHEN m.cantidad > 0 THEN m.cantidad END), 0) AS entradas,
           COALESCE(SUM(CASE WHEN m.cantidad < 0 THEN -m.cantidad END), 0) AS salidas
    FROM Movimientos_Inventario m
    WHERE $where
");
$stmt->execute($params);
$resumen = $stmt->fetch();
$numMovimientos = (int) $resumen['num'];

/* ============================================================
 * Listado paginado
 * ============================================================ */
$totalPaginas = max(1, (int) ceil($numMovimientos / MOVIMIENTOS_POR_PAGINA));
$pagina = min($totalPaginas, max(1, (int) ($_GET['pagina'] ?? 1)));
$offset = ($pagina - 1) * MOVIMIENTOS_POR_PAGINA;

$stmt = $pdo->prepare("
    SELECT m.fecha_hora, m.tipo_movimiento, m.cantidad, m.existencia_posterior,
           m.referencia_id, m.motivo_detalle,
           p.nombre AS producto, p.codigo,
           u.nombre AS usuario
    FROM Movimientos_Inventario m
    INNER JOIN Productos p ON p.id_producto = m.id_producto
    LEFT JOIN Usuarios u   ON u.id_usuario = m.realizado_por
    WHERE $where
    ORDER BY m.fecha_hora DESC, m.id_movimiento DESC
    LIMIT " . MOVIMIENTOS_POR_PAGINA . " OFFSET " . $offset
);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

$badgeTipo = [
    'VENTA'              => 'badge-metodo',
    'COMPRA_PROVEEDOR'   => 'badge-activo',
    'DEVOLUCION_CLIENTE' => 'badge-activo',
    'AJUSTE_MERMA'       => 'badge-warning',
    'AJUSTE_ROBO'        => 'badge-danger',
    'TRASLADO'           => 'badge-inactivo',
    'AJUSTE_MANUAL'      => 'badge-inactivo',
];

$filtros = array_filter([
    'producto' => $idProducto ?: null,
    'tipo'     => $tipo ?: null,
    'desde'    => $desde->format('Y-m-d'),
    'hasta'    => $hasta->format('Y-m-d'),
]);
$urlPagina = fn(int $n) => 'movimientos.php?' . http_build_query($filtros + ['pagina' => $n]);

$pageTitle    = 'Movimientos de inventario';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Movimientos de inventario</h1>
        <p>
            Entradas y salidas de tu sucursal
            <?php if ($idProducto): ?>
                &middot; <strong><?= htmlspecialchars($productosPorId[$idProducto]['nombre']) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <a href="existencias.php" class="btn btn-ghost">← Existencias</a>
</div>

<form method="GET" action="movimientos.php" class="toolbar">
    <select name="producto" aria-label="Producto">
        <option value="0">Todos los productos</option>
        <?php foreach ($productos as $p): ?>
            <option value="<?= (int) $p['id_producto'] ?>"<?= (int) $p['id_producto'] === $idProducto ? ' selected' : '' ?>>
                <?= htmlspecialchars($p['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="tipo" aria-label="Tipo de movimiento">
        <option value="">Todos los tipos</option>
        <?php foreach (TIPOS_MOVIMIENTO as $valor => $texto): ?>
            <option value="<?= $valor ?>"<?= $valor === $tipo ? ' selected' : '' ?>><?= $texto ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="desde" value="<?= $desde->format('Y-m-d') ?>" max="<?= $hoy->format('Y-m-d') ?>" aria-label="Desde">
    <input type="date" name="hasta" value="<?= $hasta->format('Y-m-d') ?>" max="<?= $hoy->format('Y-m-d') ?>" aria-label="Hasta">
    <button type="submit" class="btn btn-primary">Aplicar</button>
</form>

<section class="kpi-grid">
    <div class="kpi">
        <span class="kpi-label">Movimientos</span>
        <span class="kpi-value"><?= number_format($numMovimientos) ?></span>
        <span class="kpi-sub"><?= $desde->format('d/m/Y') ?> – <?= $hasta->format('d/m/Y') ?></span>
    </div>
    <div class="kpi kpi--ok">
        <span class="kpi-label">Entradas</span>
        <span class="kpi-value kpi-value--ok">+<?= number_format((int) $resumen['entradas']) ?></span>
        <span class="kpi-sub">unidades</span>
    </div>
    <div class="kpi kpi--danger">
        <span class="kpi-label">Salidas</span>
        <span class="kpi-value kpi-value--danger">−<?= number_format((int) $resumen['salidas']) ?></span>
        <span class="kpi-sub">unidades</span>
    </div>
</section>

<?php if ($numMovimientos === 0): ?>
    <div class="panel">
        <div class="empty-state">No hay movimientos con estos filtros.</div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="tabla-scroll">
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Quedaron</th>
                        <th>Detalle</th>
                        <th>Realizó</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $m): ?>
                        <?php $cant = (int) $m['cantidad']; ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($m['fecha_hora']))) ?></td>
                            <td>
                                <div class="producto-nombre"><?= htmlspecialchars($m['producto']) ?></div>
                                <div class="producto-desc"><?= htmlspecialchars($m['codigo']) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $badgeTipo[$m['tipo_movimiento']] ?? 'badge-inactivo' ?>">
                                    <?= htmlspecialchars(TIPOS_MOVIMIENTO[$m['tipo_movimiento']] ?? $m['tipo_movimiento']) ?>
                                </span>
                            </td>
                            <td style="font-weight:700; color: <?= $cant >= 0 ? '#16a34a' : '#dc2626' ?>;">
                                <?= $cant > 0 ? '+' : '' ?><?= $cant ?>
                            </td>
                            <td><?= (int) $m['existencia_posterior'] ?></td>
                            <td style="font-size:0.85rem; color:#475569;">
                                <?= htmlspecialchars($m['motivo_detalle'] ?? '') ?>
                            </td>
                            <td style="font-size:0.85rem;"><?= htmlspecialchars($m['usuario'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="acciones" style="justify-content: space-between; align-items: center; padding: 1rem 0 0;">
                <span style="color:#64748b; font-size:0.9rem;">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                <span class="acciones">
                    <?php if ($pagina > 1): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($urlPagina($pagina - 1)) ?>">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($pagina < $totalPaginas): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($urlPagina($pagina + 1)) ?>">Siguiente →</a>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
