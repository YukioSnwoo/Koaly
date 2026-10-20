<?php
/**
 * Productos: todo el catálogo de la empresa, con el inventario de la sucursal.
 * Los que están en la sucursal muestran stock y llevan a su ficha
 * (producto.php); los demás dicen que no están y se pueden agregar.
 */
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';

$errores = [];
$exito = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException(t('error.formulario_expirado'));
        }

        /*
         * El gerente no modifica el catálogo global (nombre, foto, código,
         * categoría, estado): eso es del admin. Aquí solo da de alta productos
         * en su inventario y solicita productos nuevos.
         * Stock y precio de venta de la sucursal se editan en producto.php.
         */

        /* -------- Agregar producto del catálogo al inventario -------- */
        if ($action === 'agregar_inventario') {
            $idProducto = (int) ($_POST['id_producto'] ?? 0);
            agregarProductoAInventario(
                $pdo,
                (int) $idSucursalGerente,
                $idProducto,
                max(0, (int) ($_POST['cantidad_inicial'] ?? 0)),
                (float) ($_POST['precio_venta'] ?? -1),
                (int) $gerente['id_usuario']
            );

            $_SESSION['flash'] = ['msg' => t('exist.agregado')];
            header('Location: producto.php?id=' . $idProducto);
            exit;
        }

        /* -------- Solicitar un producto nuevo al administrador -------- */
        elseif ($action === 'solicitar_producto') {
            $nombreProducto    = trim($_POST['nombre_producto'] ?? '');
            $descripcion       = trim($_POST['descripcion'] ?? '');
            $idCategoria       = (int) ($_POST['id_categoria'] ?? 0);
            $precioSugerido    = (float) ($_POST['precio_sugerido'] ?? -1);
            $proveedorSugerido = trim($_POST['proveedor_sugerido'] ?? '');
            $justificacion     = trim($_POST['justificacion'] ?? '');

            if ($nombreProducto === '' || mb_strlen($nombreProducto) > 100) {
                throw new RuntimeException(t('error.nombre_producto'));
            }
            if ($idCategoria <= 0) {
                throw new RuntimeException(t('error.categoria_requerida'));
            }
            if ($precioSugerido < 0) {
                throw new RuntimeException(t('error.precio_negativo'));
            }
            if ($justificacion === '') {
                throw new RuntimeException(t('error.justificacion'));
            }
            if (mb_strlen($proveedorSugerido) > 100) {
                throw new RuntimeException(t('error.proveedor_largo'));
            }

            $check = $pdo->prepare("SELECT 1 FROM Categorias WHERE id_categoria = ?");
            $check->execute([$idCategoria]);
            if ($check->fetch() === false) {
                throw new RuntimeException(t('error.categoria_invalida'));
            }

            $stmt = $pdo->prepare("
                INSERT INTO Solicitudes_Productos
                    (id_sucursal, id_usuario, nombre_producto, descripcion, id_categoria,
                     precio_sugerido, proveedor_sugerido, justificacion, estado, creado_en)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW())
            ");
            $stmt->execute([
                $idSucursalGerente,
                (int) $gerente['id_usuario'],
                $nombreProducto,
                $descripcion !== '' ? $descripcion : null,
                $idCategoria,
                $precioSugerido,
                $proveedorSugerido !== '' ? $proveedorSugerido : null,
                $justificacion,
            ]);

            $_SESSION['flash'] = ['msg' => t('cat.solicitud_enviada')];
            header('Location: productos.php');
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
   Datos para la vista: todo el catálogo, con el inventario de la
   sucursal al lado (NULL si el producto no está en ella)
   ============================================================ */
$categorias = $pdo->query("SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria")->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.imagen, p.precio, p.estado,
           p.id_categoria, c.nombre_categoria,
           i.cantidad_disponible, i.precio_venta,
           (i.id_producto IS NOT NULL) AS en_inventario
    FROM Productos p
    LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
    LEFT JOIN Inventario_Sucursal i ON i.id_producto = p.id_producto AND i.id_sucursal = ?
    ORDER BY p.nombre
");
$stmt->execute([$idSucursalGerente]);
$productos = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT s.*, c.nombre_categoria
    FROM Solicitudes_Productos s
    LEFT JOIN Categorias c ON c.id_categoria = s.id_categoria
    WHERE s.id_sucursal = ?
    ORDER BY s.creado_en DESC
    LIMIT 50
");
$stmt->execute([$idSucursalGerente]);
$solicitudes = $stmt->fetchAll();

$totalSolicitudesPendientes = 0;
foreach ($solicitudes as $s) {
    if ($s['estado'] === 'Pendiente') $totalSolicitudesPendientes++;
}

$badgeEstadoProducto = [
    'Activo'        => 'badge-activo',
    'Descontinuado' => 'badge-danger',
    'Proximamente'  => 'badge-warning',
];

// Grupo de cada producto (pestañas): en la sucursal, por agregar o no disponible.
// Los KPIs solo cuentan lo que hay en la sucursal.
$conteos = ['con' => 0, 'agregar' => 0, 'no' => 0, 'todos' => count($productos)];
$totalBajo = $totalAgotado = 0;
$valorInventario = 0.0;
foreach ($productos as &$p) {
    $p['grupo'] = $p['en_inventario'] ? 'con' : ($p['estado'] === 'Activo' ? 'agregar' : 'no');
    $conteos[$p['grupo']]++;
    if ($p['grupo'] === 'con') {
        $stock = (int) $p['cantidad_disponible'];
        if ($stock === 0) $totalAgotado++;
        elseif ($stock < STOCK_UMBRAL_BAJO) $totalBajo++;
        $valorInventario += $stock * (float) $p['precio_venta'];
    }
}
unset($p);

$pestanas = [
    'con'     => t('prods.tab_en_sucursal'),
    'agregar' => t('prods.tab_por_agregar'),
    'no'      => t('prods.tab_no_disponibles'),
    'todos'   => t('prods.tab_todos'),
];

// Filtros iniciales por URL, p. ej. productos.php?stock=alerta desde el inicio
$filtrosStock    = ['todos', 'normal', 'bajo', 'agotado', 'alerta'];
$filtroInicial   = in_array($_GET['stock'] ?? '', $filtrosStock, true) ? $_GET['stock'] : 'todos';
$busquedaInicial = trim((string) ($_GET['buscar'] ?? ''));
$ordenes         = ['nombre', 'stock_asc', 'stock_desc', 'precio_asc', 'precio_desc'];
$ordenInicial    = in_array($_GET['orden'] ?? '', $ordenes, true) ? $_GET['orden'] : 'nombre';
// Sin productos en la sucursal se abre en "Por agregar" para empezar por ahí
$grupoInicial = isset($pestanas[$_GET['grupo'] ?? '']) ? $_GET['grupo'] : ($conteos['con'] > 0 ? 'con' : 'agregar');

$iniciales = static function (string $nombre): string {
    $ini = '';
    foreach (array_slice(preg_split('/\s+/', trim($nombre)), 0, 2) as $palabra) {
        $ini .= mb_strtoupper(mb_substr($palabra, 0, 1));
    }
    return $ini ?: '?';
};

$pageTitle    = t('prods.titulo');
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('prods.titulo')) ?></h1>
        <p><?= e(t('prods.subtitulo')) ?></p>
    </div>
    <div class="acciones">
        <a href="movimientos.php" class="btn btn-ghost"><?= e(t('mov.titulo')) ?></a>
        <button type="button" class="btn btn-primary" onclick="abrirModalSolicitarProducto()">
            + <?= e(t('cat.solicitar')) ?>
        </button>
    </div>
</div>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= e($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<?php if (empty($productos)): ?>
    <div class="panel"><div class="empty-state"><?= e(t('cat.vacio')) ?></div></div>
<?php else: ?>
    <section class="kpi-grid" aria-label="<?= e(t('prods.resumen_aria')) ?>">
        <div class="kpi">
            <span class="kpi-label"><?= e(t('exist.kpi_productos')) ?></span>
            <span class="kpi-value"><?= numero($conteos['con']) ?></span>
            <span class="kpi-sub"><?= e(t('prods.kpi_productos_sub', ['n' => numero($conteos['todos'])])) ?></span>
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

    <div class="catalogo-barra">
        <div class="segmentado" role="group" aria-label="<?= e(t('cat.filtro_aria')) ?>" id="pestanasProductos">
            <?php foreach ($pestanas as $clave => $texto): ?>
                <button type="button" data-grupo="<?= $clave ?>" class="<?= $clave === $grupoInicial ? 'is-activo' : '' ?>"
                        aria-pressed="<?= $clave === $grupoInicial ? 'true' : 'false' ?>">
                    <?= e($texto) ?> <span class="segmentado-conteo"><?= numero($conteos[$clave]) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="toolbar catalogo-filtros">
            <input type="search" id="buscarProducto" placeholder="<?= e(t('exist.buscar')) ?>"
                   value="<?= e($busquedaInicial) ?>" aria-label="<?= e(t('exist.buscar')) ?>">
            <select id="filtroCategoria" aria-label="<?= e(t('cat.col_categoria')) ?>">
                <option value=""><?= e(t('cat.filtro_categorias')) ?></option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id_categoria'] ?>"><?= e(tValor('categoria', $c['nombre_categoria'])) ?></option>
                <?php endforeach; ?>
            </select>
            <?php // El stock solo existe en la sucursal: el filtro se oculta en las otras pestañas ?>
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
            <?php // Sin stock (otras pestañas) quedan al final al ordenar por stock ?>
            <select id="ordenProductos" aria-label="<?= e(t('prods.ordenar')) ?>">
                <?php foreach ($ordenes as $valor): ?>
                    <option value="<?= $valor ?>"<?= $valor === $ordenInicial ? ' selected' : '' ?>><?= e(t('prods.orden_' . $valor)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="producto-grid" id="gridProductos">
        <?php foreach ($productos as $p): ?>
            <?php
                $id        = (int) $p['id_producto'];
                $estado    = (string) $p['estado'];
                $categoria = tValor('categoria', $p['nombre_categoria'], t('comun.sin_categoria'));
                $atributos = 'data-grupo="' . $p['grupo'] . '" data-categoria="' . (int) $p['id_categoria'] . '"'
                    . ' data-nombre="' . e(mb_strtolower($p['nombre'] . ' ' . $p['codigo'] . ' ' . ($p['descripcion'] ?? ''))) . '"'
                    . ' data-precio="' . (float) ($p['grupo'] === 'con' ? $p['precio_venta'] : $p['precio']) . '"';
            ?>
            <?php if ($p['grupo'] === 'con'): ?>
                <?php
                    $stock = (int) $p['cantidad_disponible'];
                    [$stockBadge, $stockTexto] = badgeStock($stock);
                    $precio    = (float) $p['precio_venta'];
                    $noSeVende = $estado !== 'Activo';
                ?>
                <a class="producto-card <?= $noSeVende ? 'is-inactivo' : '' ?>" href="producto.php?id=<?= $id ?>"
                   <?= $atributos ?> data-stock="<?= $stock ?>">
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
                        <span class="producto-card-meta"><?= e($p['codigo']) ?> · <?= e($categoria) ?></span>
                        <div class="producto-card-pie">
                            <strong><?= $precio > 0 ? dinero($precio) : e(t('exist.sin_precio')) ?></strong>
                            <?php if ($noSeVende): ?>
                                <span class="badge badge-danger" title="<?= e(t('exist.no_se_vende_ayuda')) ?>"><?= e(t('exist.no_se_vende')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php else: ?>
                <?php
                    // Fuera de la sucursal: se puede agregar si está activo; si no, se dice por qué no
                    [$etiquetaClase, $etiquetaTexto] = $p['grupo'] === 'agregar'
                        ? ['badge-inactivo', t('prods.no_en_sucursal')]
                        : [$badgeEstadoProducto[$estado] ?? 'badge-inactivo',
                           isset($badgeEstadoProducto[$estado]) ? t('estado_producto.' . $estado) : t('cat.sin_estado')];
                ?>
                <article class="producto-card catalogo-card is-fuera <?= $p['grupo'] === 'no' ? 'is-inactivo' : '' ?>" <?= $atributos ?>>
                    <div class="producto-card-imagen">
                        <?php if (!empty($p['imagen'])): ?>
                            <img src="../<?= e($p['imagen']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span aria-hidden="true"><?= e($iniciales($p['nombre'])) ?></span>
                        <?php endif; ?>
                        <span class="badge <?= $etiquetaClase ?> producto-card-stock"><?= e($etiquetaTexto) ?></span>
                    </div>
                    <div class="producto-card-cuerpo">
                        <h3 class="producto-card-nombre"><?= e($p['nombre']) ?></h3>
                        <span class="producto-card-meta"><?= e($p['codigo']) ?> · <?= e($categoria) ?></span>
                        <?php if (!empty($p['descripcion'])): ?>
                            <p class="catalogo-card-desc"><?= e($p['descripcion']) ?></p>
                        <?php endif; ?>
                        <div class="producto-card-pie">
                            <span class="catalogo-precio">
                                <small><?= e(t('prod.precio_catalogo')) ?></small>
                                <strong><?= dinero($p['precio']) ?></strong>
                            </span>
                            <?php if ($p['grupo'] === 'agregar'): ?>
                                <button type="button" class="btn btn-primary btn-sm"
                                        onclick="abrirModalAgregarInventario(this)"
                                        data-id="<?= $id ?>"
                                        data-nombre="<?= e($p['nombre']) ?>"
                                        data-codigo="<?= e($p['codigo']) ?>"
                                        data-categoria="<?= e($categoria) ?>"
                                        data-precio="<?= e((string) $p['precio']) ?>">
                                    + <?= e(t('comun.agregar')) ?>
                                </button>
                            <?php else: ?>
                                <span class="texto-tenue catalogo-no-disponible"><?= e(t('cat.no_disponible')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="panel" id="productosSinResultados" hidden>
        <div class="empty-state"><?= e(t('cat.sin_resultados')) ?></div>
    </div>
<?php endif; ?>


<!-- ============================================================
     Solicitudes de producto de la sucursal
     ============================================================ -->
<section class="panel catalogo-solicitudes" id="solicitudes">
    <div class="panel-encabezado">
        <h2 class="section-title">
            <?= e(t('cat.solicitudes')) ?>
            <?php if ($totalSolicitudesPendientes > 0): ?>
                <span class="badge badge-warning"><?= e(tn('cat.n_pendientes', $totalSolicitudesPendientes)) ?></span>
            <?php endif; ?>
        </h2>
        <button type="button" class="btn btn-ghost btn-sm" onclick="abrirModalSolicitarProducto()">+ <?= e(t('cat.solicitar')) ?></button>
    </div>

    <?php if (empty($solicitudes)): ?>
        <div class="empty-state"><?= e(t('cat.solicitudes_vacio')) ?></div>
    <?php else: ?>
        <ul class="solicitudes-lista">
            <?php foreach ($solicitudes as $s): ?>
                <?php
                    $estadoSol = in_array($s['estado'], ['Aprobada', 'Rechazada'], true) ? $s['estado'] : 'Pendiente';
                    $claseSol = ['Aprobada' => 'badge-activo', 'Rechazada' => 'badge-danger', 'Pendiente' => 'badge-warning'][$estadoSol];
                ?>
                <li class="solicitud">
                    <div class="solicitud-principal">
                        <span class="badge <?= $claseSol ?>"><?= e(t('solicitud.' . $estadoSol)) ?></span>
                        <div>
                            <?php $esEliminar = ($s['tipo'] ?? 'Agregar') === 'Eliminar'; ?>
                            <span class="badge badge-inactivo"><?= e(t($esEliminar ? 'cat.tipo_Eliminar' : 'cat.tipo_Agregar')) ?></span>
                            <strong><?= e($s['nombre_producto']) ?></strong>
                            <?php if ($esEliminar): ?>
                                <p class="solicitud-desc"><?= e($s['justificacion']) ?></p>
                            <?php else: ?>
                            <span class="texto-tenue">
                                · <?= e(tValor('categoria', $s['nombre_categoria'], t('comun.sin_categoria'))) ?>
                                · <?= e(t('cat.col_precio_sugerido')) ?>: <?= dinero($s['precio_sugerido']) ?>
                                <?php if (!empty($s['proveedor_sugerido'])): ?>
                                    · <?= e($s['proveedor_sugerido']) ?>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!$esEliminar && !empty($s['descripcion'])): ?>
                                <p class="solicitud-desc"><?= e($s['descripcion']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="solicitud-estado">
                        <span class="texto-tenue"><?= e(fecha($s['creado_en'])) ?></span>
                        <?php if ($estadoSol === 'Rechazada' && !empty($s['motivo_rechazo'])): ?>
                            <span class="texto-peligro"><?= e($s['motivo_rechazo']) ?></span>
                        <?php elseif ($estadoSol === 'Aprobada' && $esEliminar): ?>
                            <span class="texto-ok"><?= e(t('solicitud.Aprobada')) ?></span>
                        <?php elseif ($estadoSol === 'Aprobada'): ?>
                            <span class="texto-ok"><?= e(t('cat.solicitud_creada', ['id' => (int) $s['id_producto_creado']])) ?></span>
                        <?php else: ?>
                            <span class="texto-tenue"><?= e(t('cat.en_revision')) ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>


<!-- Modal: agregar producto del catálogo al inventario -->
<div class="modal-overlay" id="modalAgregarInventario">
    <div class="modal-box">
        <h2><?= e(t('cat.agregar_inventario')) ?></h2>

        <div class="producto-resumen">
            <div class="producto-resumen-nombre" id="agregarInvNombre"></div>
            <div class="producto-resumen-meta" id="agregarInvMeta"></div>
        </div>

        <form id="formAgregarInventario" method="POST" action="productos.php"
              onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <input type="hidden" name="action" value="agregar_inventario">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_producto" value="">

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
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalAgregarInventario')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('comun.agregar')) ?></button>
            </div>
        </form>
    </div>
</div>


<!-- Modal: solicitar un producto nuevo al administrador -->
<div class="modal-overlay" id="modalSolicitarProducto">
    <div class="modal-box">
        <h2><?= e(t('cat.solicitar_titulo')) ?></h2>
        <p class="modal-ayuda"><?= e(t('cat.solicitar_ayuda')) ?></p>

        <form id="formSolicitarProducto" method="POST" action="productos.php">
            <input type="hidden" name="action" value="solicitar_producto">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="form-group">
                <label><?= e(t('cat.campo_nombre')) ?> *</label>
                <input type="text" name="nombre_producto" maxlength="100" required placeholder="<?= e(t('cat.campo_nombre_ej')) ?>">
            </div>

            <div class="form-group">
                <label><?= e(t('cat.campo_descripcion')) ?></label>
                <textarea name="descripcion" placeholder="<?= e(t('cat.campo_descripcion_ej')) ?>"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= e(t('cat.col_categoria')) ?> *</label>
                    <select name="id_categoria" required>
                        <option value=""><?= e(t('cat.elegir_categoria')) ?></option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int) $c['id_categoria'] ?>"><?= e(tValor('categoria', $c['nombre_categoria'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= e(t('cat.col_precio_sugerido')) ?> *</label>
                    <input type="number" name="precio_sugerido" min="0" step="0.01" placeholder="0.00" required>
                </div>
            </div>

            <div class="form-group">
                <label><?= e(t('cat.campo_proveedor')) ?></label>
                <input type="text" name="proveedor_sugerido" maxlength="100" placeholder="<?= e(t('cat.campo_proveedor_ej')) ?>">
            </div>

            <div class="form-group">
                <label><?= e(t('cat.campo_justificacion')) ?> *</label>
                <textarea name="justificacion" required placeholder="<?= e(t('cat.campo_justificacion_ej')) ?>"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalSolicitarProducto')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('cat.enviar_solicitud')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
