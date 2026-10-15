<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';

$errores = [];
$exito   = '';

/* ------------------------------------------------------------
   PRG: mensaje flash tras un POST exitoso
   ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    // Un flash puede ser un error (p. ej. ficha de un producto que no es de la sucursal)
    if (($_SESSION['flash']['tipo'] ?? '') === 'error') {
        $errores[] = $_SESSION['flash']['msg'] ?? '';
    } else {
        $exito = $_SESSION['flash']['msg'] ?? '';
    }
    unset($_SESSION['flash']);
}

/* ============================================================
   Datos para la vista
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.codigo, p.nombre, p.imagen, p.estado AS estado_producto,
           c.nombre_categoria,
           i.cantidad_disponible, i.precio_venta
    FROM Inventario_Sucursal i
    INNER JOIN Productos p ON p.id_producto = i.id_producto
    LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
    WHERE i.id_sucursal = ?
    ORDER BY p.nombre
");
$stmt->execute([$idSucursalGerente]);
$inventario = $stmt->fetchAll();

// Filtro inicial, p. ej. existencias.php?stock=alerta desde el inicio
$filtrosStock    = ['todos', 'normal', 'bajo', 'agotado', 'alerta'];
$filtroInicial   = in_array($_GET['stock'] ?? '', $filtrosStock, true) ? $_GET['stock'] : 'todos';
$busquedaInicial = trim((string) ($_GET['buscar'] ?? ''));

$totalProductos  = count($inventario);
$totalBajo       = 0;
$totalAgotado    = 0;
$valorInventario = 0.0;
foreach ($inventario as $p) {
    $s = (int) $p['cantidad_disponible'];
    if ($s === 0) $totalAgotado++;
    elseif ($s < STOCK_UMBRAL_BAJO) $totalBajo++;
    $valorInventario += $s * (float) $p['precio_venta'];
}

// Productos activos del catálogo que la sucursal aún no tiene (se agregan desde el catálogo)
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM Productos p
    WHERE p.estado = 'Activo'
      AND NOT EXISTS (
          SELECT 1 FROM Inventario_Sucursal i
          WHERE i.id_producto = p.id_producto AND i.id_sucursal = ?
      )
");
$stmt->execute([$idSucursalGerente]);
$totalPorAgregar = (int) $stmt->fetchColumn();

$iniciales = static function (string $nombre): string {
    $ini = '';
    foreach (array_slice(preg_split('/\s+/', trim($nombre)), 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini ?: '?';
};

$pageTitle    = t('exist.titulo');
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('exist.titulo')) ?></h1>
        <p><?= e(t('exist.subtitulo')) ?></p>
    </div>
    <div class="acciones">
        <a href="movimientos.php" class="btn btn-ghost"><?= e(t('mov.titulo')) ?></a>
        <?php if ($totalPorAgregar > 0): ?>
            <a href="productos.php?grupo=agregar" class="btn btn-primary">+ <?= e(t('exist.agregar')) ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= e($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<?php if (empty($inventario)): ?>
    <div class="panel">
        <div class="empty-state">
            <?= e(t('exist.vacio')) ?>
            <?php if ($totalPorAgregar > 0): ?>
                <a href="productos.php?grupo=agregar"><?= e(t('exist.vacio_accion')) ?></a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <section class="kpi-grid">
        <div class="kpi">
            <span class="kpi-label"><?= e(t('exist.kpi_productos')) ?></span>
            <span class="kpi-value"><?= numero($totalProductos) ?></span>
            <span class="kpi-sub"><?= e(t('exist.kpi_productos_sub')) ?></span>
        </div>
        <div class="kpi kpi--danger">
            <span class="kpi-label"><?= e(t('exist.kpi_agotados')) ?></span>
            <span class="kpi-value kpi-value--danger"><?= numero($totalAgotado) ?></span>
            <span class="kpi-sub"><?= e(t('exist.kpi_agotados_sub')) ?></span>
        </div>
        <div class="kpi kpi--warn">
            <span class="kpi-label"><?= e(t('exist.kpi_bajo')) ?></span>
            <span class="kpi-value kpi-value--warn"><?= numero($totalBajo) ?></span>
            <span class="kpi-sub"><?= e(t('exist.kpi_bajo_sub', ['n' => STOCK_UMBRAL_BAJO])) ?></span>
        </div>
        <div class="kpi kpi--ok">
            <span class="kpi-label"><?= e(t('exist.kpi_valor')) ?></span>
            <span class="kpi-value kpi-value--ok"><?= dinero($valorInventario) ?></span>
            <span class="kpi-sub"><?= e(t('exist.kpi_valor_sub')) ?></span>
        </div>
    </section>

    <div class="toolbar">
        <input type="search" id="buscarExistencia" placeholder="<?= e(t('exist.buscar')) ?>"
               value="<?= e($busquedaInicial) ?>" aria-label="<?= e(t('exist.buscar')) ?>">
        <select id="filtroStock" aria-label="<?= e(t('exist.filtro_stock')) ?>" data-umbral="<?= STOCK_UMBRAL_BAJO ?>">
            <?php
            $opcionesStock = [
                'todos'   => t('exist.filtro_todos'),
                'normal'  => t('exist.filtro_normal', ['n' => STOCK_UMBRAL_BAJO]),
                'bajo'    => t('exist.filtro_bajo', ['n' => STOCK_UMBRAL_BAJO - 1]),
                'agotado' => t('exist.filtro_agotado'),
                'alerta'  => t('exist.filtro_alerta', ['n' => STOCK_UMBRAL_BAJO - 1]),
            ];
            foreach ($opcionesStock as $valor => $texto): ?>
                <option value="<?= $valor ?>"<?= $valor === $filtroInicial ? ' selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="producto-grid" id="gridExistencias">
        <?php foreach ($inventario as $p): ?>
            <?php
                $stock = (int) $p['cantidad_disponible'];
                [$stockBadge, $stockTexto] = badgeStock($stock);
                $precio = (float) $p['precio_venta'];
                $noSeVende = $p['estado_producto'] !== 'Activo';
            ?>
            <a class="producto-card <?= $noSeVende ? 'is-inactivo' : '' ?>"
               href="producto.php?id=<?= (int) $p['id_producto'] ?>"
               data-nombre="<?= e(mb_strtolower($p['nombre'] . ' ' . $p['codigo'])) ?>"
               data-stock="<?= $stock ?>">
                <div class="producto-card-imagen">
                    <?php if (!empty($p['imagen'])): ?>
                        <img src="../<?= e($p['imagen']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span aria-hidden="true"><?= e($iniciales($p['nombre'])) ?></span>
                    <?php endif; ?>
                    <span class="badge <?= $stockBadge ?> producto-card-stock"><?= e($stockTexto) ?></span>
                </div>
                <div class="producto-card-cuerpo">
                    <span class="producto-card-nombre"><?= e($p['nombre']) ?></span>
                    <span class="producto-card-meta">
                        <?= e($p['codigo']) ?> · <?= e(tValor('categoria', $p['nombre_categoria'], t('comun.sin_categoria'))) ?>
                    </span>
                    <div class="producto-card-pie">
                        <strong><?= $precio > 0 ? dinero($precio) : e(t('exist.sin_precio')) ?></strong>
                        <?php if ($noSeVende): ?>
                            <span class="badge badge-danger" title="<?= e(t('exist.no_se_vende_ayuda')) ?>"><?= e(t('exist.no_se_vende')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="empty-state" id="sinResultados" hidden><?= e(t('exist.sin_resultados')) ?></div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
