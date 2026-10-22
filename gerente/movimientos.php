<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/includes/csv.php';
require_once __DIR__ . '/includes/filtros.php';

const MOVIMIENTOS_POR_PAGINA = 50;

/* ============================================================
 * Filtros: categoría del producto, producto, tipo de movimiento y fechas
 * (por defecto 30 días). La categoría es la misma de Productos; el tipo
 * de movimiento (venta, compra, merma...) es otra cosa y se llama así.
 * ============================================================ */
$hoy = new DateTimeImmutable('today');

// Fechas posibles: del primer movimiento de la sucursal a hoy (includes/filtros.php)
$stmt = $pdo->prepare("SELECT MIN(fecha_hora) FROM Movimientos_Inventario WHERE id_sucursal = ?");
$stmt->execute([$idSucursalGerente]);
$primerMovimiento = $stmt->fetchColumn();
$inicioHistorico = $primerMovimiento ? new DateTimeImmutable(substr($primerMovimiento, 0, 10)) : $hoy;

$periodo = leerPeriodo($_GET, $hoy, $inicioHistorico, '30');
$desde   = $periodo['desde'];
$hasta   = $periodo['hasta'];

// Rango de actividad de cada opción: el calendario solo deja elegir días posibles
$rangoMov = static fn(string $clave, string $join = '') => rangosDeActividad($pdo, "
    SELECT $clave AS clave, MIN(m.fecha_hora) AS desde, MAX(m.fecha_hora) AS hasta, COUNT(*) AS num
    FROM Movimientos_Inventario m $join WHERE m.id_sucursal = ? GROUP BY $clave
", [$idSucursalGerente]);
$rangosProducto  = $rangoMov('m.id_producto');
$rangosCategoria = $rangoMov('p.id_categoria', 'INNER JOIN Productos p ON p.id_producto = m.id_producto');
$rangosTipo      = $rangoMov('m.tipo_movimiento');
$diasConMovimiento = diasConActividad($pdo, "SELECT DISTINCT DATE(fecha_hora) AS dia FROM Movimientos_Inventario WHERE id_sucursal = ? ORDER BY dia", [$idSucursalGerente]);

$categorias = array_column(
    $pdo->query("SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria")->fetchAll(),
    'nombre_categoria', 'id_categoria'
);
$idCategoria = (int) ($_GET['categoria'] ?? 0);
if (!isset($categorias[$idCategoria])) $idCategoria = 0;

// Productos de la sucursal con su categoría: la lista se acota a la categoría
// elegida en el navegador (data-padre) sin recargar
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.nombre, p.id_categoria
    FROM Inventario_Sucursal i
    INNER JOIN Productos p ON p.id_producto = i.id_producto
    WHERE i.id_sucursal = ?
    ORDER BY p.nombre
");
$stmt->execute([$idSucursalGerente]);
$filasProductos   = $stmt->fetchAll();
$productos        = array_column($filasProductos, 'nombre', 'id_producto');
$categoriaProducto = array_column($filasProductos, 'id_categoria', 'id_producto');

$idProducto = (int) ($_GET['producto'] ?? 0);
if (!isset($productos[$idProducto])) $idProducto = 0;
// Un producto de otra categoría no cuenta: manda la categoría elegida
if ($idProducto && $idCategoria && (int) $categoriaProducto[$idProducto] !== $idCategoria) $idProducto = 0;

$tipo = (string) ($_GET['tipo'] ?? '');
if (!isset(TIPOS_MOVIMIENTO[$tipo])) $tipo = '';

$where  = "m.id_sucursal = ? AND m.fecha_hora >= ? AND m.fecha_hora < ?";
$params = [$idSucursalGerente, $desde->format('Y-m-d 00:00:00'), $hasta->modify('+1 day')->format('Y-m-d 00:00:00')];
if ($idProducto > 0) {
    $where .= " AND m.id_producto = ?";
    $params[] = $idProducto;
} elseif ($idCategoria > 0) {
    $where .= " AND m.id_producto IN (SELECT id_producto FROM Productos WHERE id_categoria = ?)";
    $params[] = $idCategoria;
}
if ($tipo !== '') {
    $where .= " AND m.tipo_movimiento = ?";
    $params[] = $tipo;
}

$selectMovimientos = "
    SELECT m.fecha_hora, m.tipo_movimiento, m.cantidad, m.existencia_posterior,
           m.motivo_detalle, m.id_producto,
           p.nombre AS producto, p.codigo, c.nombre_categoria,
           u.nombre AS usuario
    FROM Movimientos_Inventario m
    INNER JOIN Productos p  ON p.id_producto = m.id_producto
    LEFT JOIN Categorias c  ON c.id_categoria = p.id_categoria
    LEFT JOIN Usuarios u    ON u.id_usuario = m.realizado_por
    WHERE $where
";

/* ============================================================
 * Exportar CSV con los filtros actuales
 * ============================================================ */
if (($_GET['exportar'] ?? '') === 'csv') {
    $stmt = $pdo->prepare($selectMovimientos . " ORDER BY m.fecha_hora, m.id_movimiento");
    $stmt->execute($params);

    $filas = (function () use ($stmt) {
        while ($m = $stmt->fetch()) {
            yield [
                fecha($m['fecha_hora']),
                $m['codigo'],
                $m['producto'],
                tValor('categoria', $m['nombre_categoria'], t('comun.sin_categoria')),
                esCambioPrecio($m) ? t('tipo.PRECIO') : etiquetaTipo($m['tipo_movimiento']),
                (int) $m['cantidad'],
                (int) $m['existencia_posterior'],
                $m['motivo_detalle'] ?? '',
                $m['usuario'] ?? '',
            ];
        }
    })();

    enviarCsv(
        t('mov.csv_archivo') . '_' . $desde->format('Y-m-d') . '_' . $hasta->format('Y-m-d') . '.csv',
        [t('comun.fecha'), t('mov.codigo'), t('comun.producto'), t('cat.col_categoria'), t('mov.tipo'), t('mov.cantidad'),
         t('mov.quedaron'), t('mov.detalle'), t('mov.realizo')],
        $filas
    );
}

/* ============================================================
 * Filas (carga normal y carga progresiva con ?parcial=1)
 * ============================================================ */
$pintarFilas = static function (array $movimientos): string {
    ob_start();
    foreach ($movimientos as $m) {
        $cant = (int) $m['cantidad'];
        $esPrecio = esCambioPrecio($m);
        ?>
        <tr>
            <td><?= e(fecha($m['fecha_hora'])) ?></td>
            <td>
                <a class="enlace-producto" href="producto.php?id=<?= (int) $m['id_producto'] ?>">
                    <span class="producto-nombre"><?= e($m['producto']) ?></span>
                    <span class="producto-desc"><?= e($m['codigo']) ?> · <?= e(tValor('categoria', $m['nombre_categoria'], t('comun.sin_categoria'))) ?></span>
                </a>
            </td>
            <td>
                <?php if ($esPrecio): ?>
                    <span class="badge badge-warning"><?= e(t('tipo.PRECIO')) ?></span>
                <?php else: ?>
                    <span class="badge <?= TIPOS_MOVIMIENTO[$m['tipo_movimiento']] ?? 'badge-inactivo' ?>"><?= e(etiquetaTipo($m['tipo_movimiento'])) ?></span>
                <?php endif; ?>
            </td>
            <td class="num <?= $esPrecio ? '' : ($cant >= 0 ? 'texto-ok' : 'texto-peligro') ?>">
                <?= $esPrecio ? '—' : ($cant > 0 ? '+' : '') . numero($cant) ?>
            </td>
            <td class="num"><?= numero((int) $m['existencia_posterior']) ?></td>
            <td class="texto-tenue"><?= e($m['motivo_detalle'] ?? '') ?></td>
            <td><?= e($m['usuario'] ?? '—') ?></td>
        </tr>
        <?php
    }
    return ob_get_clean();
};

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

$totalPaginas = max(1, (int) ceil($numMovimientos / MOVIMIENTOS_POR_PAGINA));
$pagina = min($totalPaginas, max(1, (int) ($_GET['pagina'] ?? 1)));

$stmt = $pdo->prepare($selectMovimientos
    . " ORDER BY m.fecha_hora DESC, m.id_movimiento DESC LIMIT " . MOVIMIENTOS_POR_PAGINA
    . " OFFSET " . (($pagina - 1) * MOVIMIENTOS_POR_PAGINA));
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

if (($_GET['parcial'] ?? '') === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Hay-Mas: ' . ($pagina < $totalPaginas ? '1' : '0'));
    header('X-Mostrando: ' . min($numMovimientos, $pagina * MOVIMIENTOS_POR_PAGINA));
    echo $pintarFilas($movimientos);
    exit;
}

$estadoUrl = array_filter([
    'categoria' => $idCategoria ?: null,
    'producto' => $idProducto ?: null,
    'tipo'     => $tipo ?: null,
    'desde'    => $desde->format('Y-m-d'),
    'hasta'    => $hasta->format('Y-m-d'),
]);
$url = static fn(array $extra) => 'movimientos.php?' . http_build_query($estadoUrl + $extra);

// Barra de filtros unificada: periodo + categoría, producto y tipo de movimiento
$hayFiltros = $idCategoria || $idProducto || $tipo !== '';
$filtros = [
    'accion'  => 'movimientos.php',
    'ancla'   => '',
    'ocultos' => [],
    'periodo' => [
        'desde' => $desde, 'hasta' => $hasta, 'inicio' => $inicioHistorico, 'hoy' => $hoy,
        'atajos' => $periodo['atajos'], 'dias' => $diasConMovimiento,
    ],
    'facetas' => [
        ['nombre' => 'categoria', 'etiqueta' => t('cat.col_categoria'), 'todos' => t('cat.filtro_categorias'), 'valor' => (string) $idCategoria,
         'opciones' => array_map(fn($id, $nombre) => ['valor' => (string) $id, 'texto' => tValor('categoria', $nombre), 'rango' => $rangosCategoria[(string) $id] ?? null],
                                 array_keys($categorias), $categorias)],
        ['nombre' => 'producto', 'etiqueta' => t('comun.producto'), 'todos' => t('mov.filtro_productos'), 'valor' => (string) $idProducto, 'depende' => 'categoria',
         'opciones' => array_map(fn($id, $nombre) => ['valor' => (string) $id, 'texto' => $nombre, 'rango' => $rangosProducto[(string) $id] ?? null,
                                                      'padre' => (string) (int) $categoriaProducto[$id]],
                                 array_keys($productos), $productos)],
        ['nombre' => 'tipo', 'etiqueta' => t('mov.tipo'), 'todos' => t('mov.filtro_tipos'), 'vacio' => '', 'valor' => $tipo,
         'opciones' => array_map(fn($valor) => ['valor' => $valor, 'texto' => etiquetaTipo($valor), 'rango' => $rangosTipo[$valor] ?? null],
                                 array_keys(TIPOS_MOVIMIENTO))],
    ],
    'limpiar' => $hayFiltros ? 'movimientos.php?' . http_build_query(['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')]) : null,
];

$pageTitle    = t('mov.titulo');
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('mov.titulo')) ?></h1>
        <p>
            <?= e(t('mov.subtitulo')) ?>
            <?php if ($idProducto): ?>
                · <a href="producto.php?id=<?= $idProducto ?>"><strong><?= e($productos[$idProducto]) ?></strong></a>
            <?php endif; ?>
        </p>
    </div>
    <div class="acciones">
        <a href="<?= e($url(['exportar' => 'csv'])) ?>" class="btn btn-ghost"><?= e(t('comun.exportar_csv')) ?></a>
        <a href="productos.php" class="btn btn-ghost"><?= e(t('prods.titulo')) ?></a>
    </div>
</div>

<?php require __DIR__ . '/includes/barra_filtros.php'; ?>

<section class="kpi-grid">
    <div class="kpi">
        <span class="kpi-label"><?= e(t('mov.kpi_movimientos')) ?></span>
        <span class="kpi-value"><?= numero($numMovimientos) ?></span>
        <span class="kpi-sub"><?= e(fecha($desde, false)) ?> – <?= e(fecha($hasta, false)) ?></span>
    </div>
    <div class="kpi kpi--ok">
        <span class="kpi-label"><?= e(t('mov.kpi_entradas')) ?></span>
        <span class="kpi-value kpi-value--ok">+<?= numero((int) $resumen['entradas']) ?></span>
        <span class="kpi-sub"><?= e(t('mov.kpi_unidades')) ?></span>
    </div>
    <div class="kpi kpi--danger">
        <span class="kpi-label"><?= e(t('mov.kpi_salidas')) ?></span>
        <span class="kpi-value kpi-value--danger">−<?= numero((int) $resumen['salidas']) ?></span>
        <span class="kpi-sub"><?= e(t('mov.kpi_unidades')) ?></span>
    </div>
</section>

<?php if ($numMovimientos === 0): ?>
    <div class="panel">
        <div class="empty-state"><?= e(t('mov.vacio')) ?></div>
    </div>
<?php else: ?>
    <section class="panel">
        <div class="tabla-scroll">
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th><?= e(t('comun.fecha')) ?></th>
                        <th><?= e(t('comun.producto')) ?></th>
                        <th><?= e(t('mov.tipo')) ?></th>
                        <th class="num"><?= e(t('mov.cantidad')) ?></th>
                        <th class="num"><?= e(t('mov.quedaron')) ?></th>
                        <th><?= e(t('mov.detalle')) ?></th>
                        <th><?= e(t('mov.realizo')) ?></th>
                    </tr>
                </thead>
                <tbody data-scroll-infinito
                       data-url="<?= e($url(['parcial' => '1'])) ?>"
                       data-pagina="<?= $pagina ?>"
                       data-total-paginas="<?= $totalPaginas ?>">
                    <?= $pintarFilas($movimientos) ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagina < $totalPaginas): ?>
            <div class="carga-progresiva">
                <a class="btn btn-ghost btn-sm" data-cargar-mas href="<?= e($url(['pagina' => $pagina + 1])) ?>"><?= e(t('comun.cargar_mas')) ?></a>
                <span class="texto-tenue" data-contador data-total="<?= $numMovimientos ?>">
                    <?= e(t('comun.mostrando', ['n' => numero(min($numMovimientos, $pagina * MOVIMIENTOS_POR_PAGINA)), 'total' => numero($numMovimientos)])) ?>
                </span>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
