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
   POST: alta de un producto del catálogo en la sucursal.
   El resto de acciones (ajustar, recibir, precio) están en producto.php.
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrfValido()) {
            throw new RuntimeException(t('error.formulario_expirado'));
        }
        if (($_POST['action'] ?? '') === 'agregar_producto') {
            $idProducto = (int) ($_POST['id_producto'] ?? 0);
            agregarProductoAInventario(
                $pdo,
                (int) $idSucursalGerente,
                $idProducto,
                max(0, (int) ($_POST['cantidad_inicial'] ?? 0)),
                (float) ($_POST['precio_venta'] ?? -1),
                (int) $gerente['id_usuario']
            );
            // Directo a la ficha del producto recién agregado
            $_SESSION['flash'] = ['msg' => t('exist.agregado')];
            header('Location: producto.php?id=' . $idProducto);
            exit;
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = t('error.base_datos');
    }
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

// Productos activos del catálogo que la sucursal aún no tiene
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.codigo, p.nombre, p.precio, c.nombre_categoria
    FROM Productos p
    LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
    WHERE p.estado = 'Activo'
      AND NOT EXISTS (
          SELECT 1 FROM Inventario_Sucursal i
          WHERE i.id_producto = p.id_producto AND i.id_sucursal = ?
      )
    ORDER BY p.nombre
");
$stmt->execute([$idSucursalGerente]);
$productosDisponibles = $stmt->fetchAll();

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
        <?php if (!empty($productosDisponibles)): ?>
            <button type="button" class="btn btn-primary" onclick="abrirModalAgregarProducto()">
                + <?= e(t('exist.agregar')) ?>
            </button>
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
            <?php if (!empty($productosDisponibles)): ?>
                <?= e(t('exist.vacio_accion')) ?>
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
                        <?= e($p['codigo']) ?> · <?= e($p['nombre_categoria'] ?? t('comun.sin_categoria')) ?>
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

<!-- Modal: agregar producto del catálogo a la sucursal -->
<div class="modal-overlay" id="modalAgregarProducto">
    <div class="modal-box">
        <h2><?= e(t('exist.agregar')) ?></h2>
        <p class="modal-ayuda"><?= e(t('exist.agregar_ayuda')) ?></p>

        <form id="formAgregarProducto" method="POST" action="existencias.php"
              onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <input type="hidden" name="action" value="agregar_producto">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="form-group">
                <label for="buscarProductoAgregar"><?= e(t('comun.producto')) ?></label>
                <div class="picker" id="pickerProducto">
                    <input type="hidden" name="id_producto" value="">

                    <div class="picker-input-wrap">
                        <svg class="picker-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="buscarProductoAgregar" class="picker-input"
                               placeholder="<?= e(t('exist.buscar')) ?>"
                               autocomplete="off" required
                               role="combobox" aria-autocomplete="list"
                               aria-expanded="false" aria-controls="listaProductosAgregar">
                        <button type="button" class="picker-clear" aria-label="<?= e(t('comun.quitar_seleccion')) ?>" hidden>&times;</button>
                    </div>

                    <ul class="picker-lista" id="listaProductosAgregar" role="listbox">
                        <?php foreach ($productosDisponibles as $pd): ?>
                            <li class="picker-item" role="option"
                                data-id="<?= (int) $pd['id_producto'] ?>"
                                data-nombre="<?= e($pd['nombre']) ?>"
                                data-precio="<?= e((string) $pd['precio']) ?>"
                                data-buscar="<?= e($pd['nombre'] . ' ' . $pd['codigo'] . ' ' . ($pd['nombre_categoria'] ?? '')) ?>">
                                <span class="picker-item-nombre"><?= e($pd['nombre']) ?></span>
                                <span class="picker-item-meta"><?= e($pd['codigo']) ?> · <?= e($pd['nombre_categoria'] ?? t('comun.sin_categoria')) ?></span>
                            </li>
                        <?php endforeach; ?>
                        <li class="picker-vacio" hidden><?= e(t('exist.sin_resultados')) ?></li>
                    </ul>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= e(t('exist.cantidad_inicial')) ?></label>
                    <input type="number" name="cantidad_inicial" min="0" step="1" value="0" required>
                </div>
                <div class="form-group">
                    <label><?= e(t('comun.precio_venta_iva')) ?></label>
                    <input type="number" name="precio_venta" min="0" step="0.01" placeholder="0.00" required>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalAgregarProducto')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('comun.agregar')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
