<?php
/**
 * Productos: todo el catálogo de la empresa, con el inventario de la sucursal.
 * Los que están en la sucursal muestran stock y llevan a su ficha
 * (producto.php); los demás dicen que no están y se pueden agregar.
 */
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/../admin/solicitudes_comun.php';

$errores = [];
$exito = '';
$imagenNueva = null; // imagen subida en este POST; se borra si el guardado falla

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
            $ficha         = leerFichaSolicitud($pdo, $_POST);
            $proveedor     = leerProveedorSolicitud($_POST);
            $justificacion = trim($_POST['justificacion'] ?? '');
            if ($justificacion === '') {
                throw new RuntimeException(t('error.justificacion'));
            }
            $imagenNueva = guardarImagenSolicitud($_FILES['imagen'] ?? []);
            if ($imagenNueva === null) {
                throw new RuntimeException('imagen_requerida');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO Solicitudes_Productos
                    (id_sucursal, id_usuario, nombre_producto, descripcion, id_categoria, precio_sugerido, imagen,
                     proveedor_sugerido, proveedor_empresa, proveedor_telefono, proveedor_correo,
                     justificacion, estado, creado_en)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW())
            ");
            $stmt->execute([
                $idSucursalGerente,
                (int) $gerente['id_usuario'],
                $ficha['nombre_producto'],
                $ficha['descripcion'],
                $ficha['id_categoria'],
                $ficha['precio_sugerido'],
                $imagenNueva,
                $proveedor['proveedor_sugerido'],
                $proveedor['proveedor_empresa'],
                $proveedor['proveedor_telefono'],
                $proveedor['proveedor_correo'],
                $justificacion,
            ]);
            $idSolicitud = (int) $pdo->lastInsertId();
            registrarHistorialSolicitud($pdo, $idSolicitud, (int) $gerente['id_usuario'], 'Gerente', 'Enviada', [], $justificacion);
            $pdo->commit();

            $_SESSION['flash'] = ['msg' => t('cat.solicitud_enviada')];
            header('Location: productos.php#solicitudes');
            exit;
        }

        /*
         * -------- Responder a los cambios del administrador --------
         * Solo sobre solicitudes "Devuelta" de la sucursal. Aprobar es la única
         * forma en que el gerente agrega un producto al catálogo: el admin ya
         * revisó la solicitud y propuso esos valores.
         */
        elseif (in_array($action, ['aprobar_cambios', 'cancelar_solicitud', 'editar_solicitud'], true)) {
            $idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
            $idUsuario   = (int) $gerente['id_usuario'];

            if ($action === 'editar_solicitud') {
                $nuevos = leerFichaSolicitud($pdo, $_POST) + leerProveedorSolicitud($_POST);
                $nota   = trim($_POST['nota'] ?? '');
                if ($nota === '') {
                    throw new RuntimeException('nota');
                }
            } elseif ($action === 'cancelar_solicitud') {
                $motivo = trim($_POST['motivo'] ?? '');
                if ($motivo === '') {
                    throw new RuntimeException('motivo');
                }
            }

            $pdo->beginTransaction();
            $sol = leerSolicitudBloqueada($pdo, $idSolicitud);
            if ((int) $sol['id_sucursal'] !== $idSucursalGerente) {
                throw new RuntimeException('no_existe');
            }
            if ($sol['estado'] !== 'Devuelta') {
                throw new RuntimeException('turno');
            }

            if ($action === 'aprobar_cambios') {
                [$idProducto, $reactivado] = crearProductoDesdeSolicitud($pdo, $sol);
                marcarSolicitudAprobada($pdo, $idSolicitud, $idProducto, $idUsuario);
                registrarHistorialSolicitud($pdo, $idSolicitud, $idUsuario, 'Gerente', 'Aprobada');
                $mensaje = t($reactivado ? 'sol.reactivado_msg' : 'sol.aprobada_msg', ['id' => $idProducto]);
            } elseif ($action === 'cancelar_solicitud') {
                $pdo->prepare("UPDATE Solicitudes_Productos SET estado = 'Cancelada', motivo_rechazo = ?, resuelto_en = NOW(), resuelto_por = ?, actualizado_en = NOW() WHERE id_solicitud = ?")
                    ->execute([$motivo, $idUsuario, $idSolicitud]);
                registrarHistorialSolicitud($pdo, $idSolicitud, $idUsuario, 'Gerente', 'Cancelada', [], $motivo);
                $mensaje = t('sol.cancelada_msg');
            } else {
                $imagenNueva = guardarImagenSolicitud($_FILES['imagen'] ?? []);
                if ($imagenNueva !== null) {
                    $nuevos['imagen'] = $imagenNueva;
                }
                $cambios = diferenciasSolicitud($sol, $nuevos, array_merge(CAMPOS_FICHA_SOLICITUD, CAMPOS_PROVEEDOR_SOLICITUD));
                if (!$cambios) {
                    throw new RuntimeException('sin_cambios');
                }
                actualizarSolicitud($pdo, $idSolicitud, $cambios, 'Pendiente');
                registrarHistorialSolicitud($pdo, $idSolicitud, $idUsuario, 'Gerente', 'Editada', $cambios, $nota);
                $mensaje = t('sol.reenviada_msg');
            }

            $pdo->commit();
            $_SESSION['flash'] = ['msg' => $mensaje];
            header('Location: productos.php#solicitudes');
            exit;
        }

    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        borrarImagenSolicitud($imagenNueva);
        // Las claves de admin/solicitudes_comun.php (p. ej. 'proveedor_contacto') se traducen aquí
        $clave = 'sol_error.' . $e->getMessage();
        $errores[] = t($clave) !== $clave ? t($clave) : $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        borrarImagenSolicitud($imagenNueva);
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

// Las que esperan respuesta del gerente (con cambios del admin) van primero
$stmt = $pdo->prepare("
    SELECT s.*, c.nombre_categoria
    FROM Solicitudes_Productos s
    LEFT JOIN Categorias c ON c.id_categoria = s.id_categoria
    WHERE s.id_sucursal = ?
    ORDER BY (s.estado = 'Devuelta') DESC, s.creado_en DESC
    LIMIT 50
");
$stmt->execute([$idSucursalGerente]);
$solicitudes = $stmt->fetchAll();
$historiales = historialSolicitudes($pdo, array_column($solicitudes, 'id_solicitud'));

$totalSolicitudesPendientes = $totalPorRevisar = 0;
foreach ($solicitudes as $s) {
    if ($s['estado'] === 'Pendiente') $totalSolicitudesPendientes++;
    if ($s['estado'] === 'Devuelta') $totalPorRevisar++;
}

$estadosSolicitud = [
    'Pendiente' => 'badge-warning',
    'Devuelta'  => 'badge-warning',
    'Aprobada'  => 'badge-activo',
    'Rechazada' => 'badge-danger',
    'Cancelada' => 'badge-inactivo',
];
$nombresCategoria = array_column($categorias, 'nombre_categoria', 'id_categoria');

// Valor legible de un campo de la ficha (también para "antes" de un cambio)
$valorFicha = static function (string $campo, $valor) use ($nombresCategoria): string {
    if ($valor === null || $valor === '') {
        return '—';
    }
    return match ($campo) {
        'id_categoria'    => tValor('categoria', $nombresCategoria[(int) $valor] ?? null, t('comun.sin_categoria')),
        'precio_sugerido' => dinero($valor),
        default           => (string) $valor,
    };
};

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
$categoriaInicial = (int) ($_GET['categoria'] ?? 0);
if (!isset(array_column($categorias, 'id_categoria', 'id_categoria')[$categoriaInicial])) $categoriaInicial = 0;
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
    </div>

    <?php
    // Misma barra que Ventas y Movimientos, pero sin periodo: el catálogo no depende de fechas.
    // El filtrado es inmediato en el navegador (js/paginas.js) y queda en la URL.
    // Sin JavaScript, el formulario envía los mismos parámetros que lee la página.
    ?>
    <form method="GET" action="productos.php" class="filtros filtros--productos" id="filtrosProductos" role="search">
        <input type="hidden" name="grupo" value="<?= e($grupoInicial) ?>" data-filtro-grupo>
        <div class="filtros-campo filtros-campo--busqueda">
            <label class="filtros-etiqueta" for="buscarProducto"><?= e(t('prods.filtro_buscar')) ?></label>
            <div class="filtros-busqueda">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="buscarProducto" name="buscar" placeholder="<?= e(t('exist.buscar')) ?>"
                       value="<?= e($busquedaInicial) ?>" autocomplete="off">
            </div>
        </div>
        <div class="filtros-campo">
            <label class="filtros-etiqueta" for="filtroCategoria"><?= e(t('prods.filtro_categoria')) ?></label>
            <select id="filtroCategoria" name="categoria">
                <option value=""><?= e(t('cat.filtro_categorias')) ?></option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id_categoria'] ?>"<?= (int) $c['id_categoria'] === $categoriaInicial ? ' selected' : '' ?>><?= e(tValor('categoria', $c['nombre_categoria'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php // El stock solo existe en la sucursal: el campo se oculta en las otras pestañas ?>
        <div class="filtros-campo" id="campoStock">
            <label class="filtros-etiqueta" for="filtroStock"><?= e(t('prods.filtro_existencias')) ?></label>
            <select id="filtroStock" name="stock" data-umbral="<?= STOCK_UMBRAL_BAJO ?>">
                <?php
                $opcionesStock = [
                    'todos'   => t('prods.filtro_stock_todos'),
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
        <?php // Sin stock (otras pestañas) quedan al final al ordenar por stock ?>
        <div class="filtros-campo">
            <label class="filtros-etiqueta" for="ordenProductos"><?= e(t('prods.filtro_orden')) ?></label>
            <select id="ordenProductos" name="orden">
                <?php foreach ($ordenes as $valor): ?>
                    <option value="<?= $valor ?>"<?= $valor === $ordenInicial ? ' selected' : '' ?>><?= e(t('prods.orden_' . $valor)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtros-pie">
            <p class="filtros-conteo" id="conteoProductos" aria-live="polite"></p>
            <a class="filtros-limpiar" href="productos.php?grupo=<?= e($grupoInicial) ?>" id="limpiarFiltrosProductos" hidden><?= e(t('comun.quitar_filtros')) ?></a>
            <noscript><button type="submit" class="btn btn-ghost btn-sm"><?= e(t('prods.filtro_aplicar')) ?></button></noscript>
        </div>
    </form>

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
            <?php if ($totalPorRevisar > 0): ?>
                <span class="badge badge-warning badge-latido"><?= e(tn('cat.n_por_revisar', $totalPorRevisar)) ?></span>
            <?php endif; ?>
            <?php if ($totalSolicitudesPendientes > 0): ?>
                <span class="badge badge-inactivo"><?= e(tn('cat.n_pendientes', $totalSolicitudesPendientes)) ?></span>
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
                    $idSol     = (int) $s['id_solicitud'];
                    $estadoSol = isset($estadosSolicitud[$s['estado']]) ? $s['estado'] : 'Pendiente';
                    $edicion   = ultimaEdicionSolicitud($historiales[$idSol] ?? []);
                    $devuelta  = $estadoSol === 'Devuelta';
                ?>
                <li class="solicitud <?= $devuelta ? 'solicitud--devuelta' : '' ?>">
                    <div class="solicitud-principal">
                        <?php if (!empty($s['imagen'])): ?>
                            <img class="solicitud-miniatura" src="../<?= e($s['imagen']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="solicitud-miniatura" aria-hidden="true"><?= e($iniciales($s['nombre_producto'])) ?></span>
                        <?php endif; ?>
                        <div>
                            <span class="badge <?= $estadosSolicitud[$estadoSol] ?>"><?= e(t('solicitud.' . $estadoSol)) ?></span>
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
                            <?php if ($devuelta && $edicion !== null): ?>
                                <div class="chips-cambio" aria-label="<?= e(t('sol.cambios_admin')) ?>">
                                    <?php foreach (array_keys($edicion['cambios']) as $campo): ?>
                                        <span class="chip-cambio"><?= e(t('sol.campo.' . $campo)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="solicitud-estado">
                        <span class="texto-tenue"><?= e(fecha($s['creado_en'])) ?></span>
                        <?php if ($estadoSol === 'Rechazada' && !empty($s['motivo_rechazo'])): ?>
                            <span class="texto-peligro"><?= e($s['motivo_rechazo']) ?></span>
                        <?php elseif ($estadoSol === 'Cancelada'): ?>
                            <span class="texto-tenue"><?= e(t('sol.cancelada_por_ti')) ?><?= !empty($s['motivo_rechazo']) ? ': ' . e($s['motivo_rechazo']) : '' ?></span>
                        <?php elseif ($estadoSol === 'Aprobada' && $esEliminar): ?>
                            <span class="texto-ok"><?= e(t('solicitud.Aprobada')) ?></span>
                        <?php elseif ($estadoSol === 'Aprobada'): ?>
                            <span class="texto-ok"><?= e(t('cat.solicitud_creada', ['id' => (int) $s['id_producto_creado']])) ?></span>
                        <?php elseif (!$devuelta): ?>
                            <span class="texto-tenue"><?= e(t('cat.en_revision')) ?></span>
                        <?php endif; ?>
                        <?php if ($devuelta): ?>
                            <button type="button" class="btn btn-warning btn-sm" onclick="abrirModal('fichaSolicitud<?= $idSol ?>')"><?= e(t('cat.revisar_cambios')) ?></button>
                        <?php else: ?>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="abrirModal('fichaSolicitud<?= $idSol ?>')"><?= e(t('cat.ver_ficha')) ?></button>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>


<!-- ============================================================
     Ficha de cada solicitud: lo que cambió la última edición va en amarillo
     ============================================================ -->
<?php foreach ($solicitudes as $s): ?>
    <?php
        $idSol     = (int) $s['id_solicitud'];
        $estadoSol = isset($estadosSolicitud[$s['estado']]) ? $s['estado'] : 'Pendiente';
        $pasos     = $historiales[$idSol] ?? [];
        $edicion   = ultimaEdicionSolicitud($pasos);
        $cambiados = $edicion['cambios'] ?? [];
        $hayProveedor = !empty($s['proveedor_sugerido']);
    ?>
    <div class="modal-overlay" id="fichaSolicitud<?= $idSol ?>">
        <div class="modal-box ficha-solicitud">
            <div class="ficha-encabezado">
                <h2><?= e($s['nombre_producto']) ?></h2>
                <span class="badge <?= $estadosSolicitud[$estadoSol] ?>"><?= e(t('solicitud.' . $estadoSol)) ?></span>
            </div>

            <?php if ($edicion !== null): ?>
                <div class="ficha-aviso">
                    <strong><?= e(t($edicion['rol'] === 'Admin' ? 'sol.cambios_admin' : 'sol.tus_cambios')) ?></strong>
                    <?php if (!empty($edicion['nota'])): ?>
                        <p>“<?= e($edicion['nota']) ?>”</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ficha-cuerpo">
                <figure class="ficha-imagen <?= isset($cambiados['imagen']) ? 'campo-cambiado' : '' ?>">
                    <?php if (!empty($s['imagen'])): ?>
                        <img src="../<?= e($s['imagen']) ?>" alt="<?= e($s['nombre_producto']) ?>" loading="lazy">
                    <?php else: ?>
                        <span aria-hidden="true"><?= e($iniciales($s['nombre_producto'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($cambiados['imagen']['antes'])): ?>
                        <figcaption class="ficha-imagen-antes">
                            <img src="../<?= e($cambiados['imagen']['antes']) ?>" alt="" loading="lazy">
                            <?= e(t('sol.antes')) ?>
                        </figcaption>
                    <?php endif; ?>
                </figure>
                <dl class="ficha-datos">
                    <?php foreach (['nombre_producto', 'id_categoria', 'precio_sugerido', 'descripcion'] as $campo): ?>
                        <div class="ficha-campo <?= isset($cambiados[$campo]) ? 'campo-cambiado' : '' ?>">
                            <dt><?= e(t('sol.campo.' . $campo)) ?></dt>
                            <dd>
                                <?= e($valorFicha($campo, $s[$campo])) ?>
                                <?php if (isset($cambiados[$campo])): ?>
                                    <del><?= e(t('sol.antes')) ?>: <?= e($valorFicha($campo, $cambiados[$campo]['antes'])) ?></del>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>

            <div class="ficha-seccion">
                <h3><?= e(t('sol.proveedor')) ?></h3>
                <?php if (!$hayProveedor): ?>
                    <p class="texto-tenue"><?= e(t('sol.sin_proveedor')) ?></p>
                <?php else: ?>
                    <dl class="ficha-proveedor">
                        <?php foreach (CAMPOS_PROVEEDOR_SOLICITUD as $campo): ?>
                            <div class="ficha-campo <?= isset($cambiados[$campo]) ? 'campo-cambiado' : '' ?>">
                                <dt><?= e(t('sol.campo.' . $campo)) ?></dt>
                                <dd><?= e($valorFicha($campo, $s[$campo] ?? null)) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>

            <div class="ficha-seccion">
                <h3><?= e(t('sol.justificacion')) ?></h3>
                <p><?= e($s['justificacion'] ?? '') ?></p>
            </div>

            <?php if ($pasos): ?>
                <div class="ficha-seccion">
                    <h3><?= e(t('sol.historial')) ?></h3>
                    <ol class="ficha-historial">
                        <?php foreach ($pasos as $paso): ?>
                            <li>
                                <strong><?= e($paso['autor'] ?? t('sol.rol.' . $paso['rol'])) ?></strong>
                                <span class="texto-tenue">(<?= e(t('sol.rol.' . $paso['rol'])) ?>)</span>
                                <?= e(t('sol.accion.' . $paso['accion'])) ?>
                                · <span class="texto-tenue"><?= e(fecha($paso['creado_en'])) ?></span>
                                <?php if ($paso['cambios']): ?>
                                    <span class="texto-tenue">
                                        — <?= e(implode(', ', array_map(static fn ($c) => t('sol.campo.' . $c), array_keys($paso['cambios'])))) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($paso['nota'])): ?>
                                    <q><?= e($paso['nota']) ?></q>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('fichaSolicitud<?= $idSol ?>')"><?= e(t('sol.cerrar')) ?></button>
                <?php if ($estadoSol === 'Devuelta'): ?>
                    <button type="button" class="btn btn-danger" onclick="abrirModalCancelarSolicitud(this)"
                            data-id="<?= $idSol ?>" data-nombre="<?= e($s['nombre_producto']) ?>">
                        <?= e(t('sol.rechazar')) ?>
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="abrirModalEditarSolicitud(this)"
                            data-id="<?= $idSol ?>"
                            data-nombre="<?= e($s['nombre_producto']) ?>"
                            data-descripcion="<?= e($s['descripcion'] ?? '') ?>"
                            data-categoria="<?= (int) $s['id_categoria'] ?>"
                            data-precio="<?= e((string) $s['precio_sugerido']) ?>"
                            data-imagen="<?= e($s['imagen'] ?? '') ?>"
                            data-proveedor="<?= e($s['proveedor_sugerido'] ?? '') ?>"
                            data-empresa="<?= e($s['proveedor_empresa'] ?? '') ?>"
                            data-telefono="<?= e($s['proveedor_telefono'] ?? '') ?>"
                            data-correo="<?= e($s['proveedor_correo'] ?? '') ?>">
                        <?= e(t('sol.editar')) ?>
                    </button>
                    <form method="POST" action="productos.php" class="form-en-linea"
                          data-confirm="<?= e(t('sol.aprobar_confirmar', ['nombre' => $s['nombre_producto']])) ?>"
                          data-confirm-aceptar="<?= e(t('sol.aprobar')) ?>">
                        <input type="hidden" name="action" value="aprobar_cambios">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="id_solicitud" value="<?= $idSol ?>">
                        <button type="submit" class="btn btn-primary"><?= e(t('sol.aprobar')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>


<!-- Modal: rechazar los cambios del administrador (la solicitud se cancela) -->
<div class="modal-overlay" id="modalCancelarSolicitud">
    <div class="modal-box">
        <h2><?= e(t('sol.cancelar_titulo')) ?></h2>
        <p class="modal-ayuda">
            <strong id="cancelarSolicitudNombre"></strong> — <?= e(t('sol.cancelar_ayuda')) ?>
        </p>
        <form method="POST" action="productos.php" onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <input type="hidden" name="action" value="cancelar_solicitud">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_solicitud" value="">
            <div class="form-group">
                <label><?= e(t('sol.cancelar_motivo')) ?> *</label>
                <textarea name="motivo" required maxlength="500"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCancelarSolicitud')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-danger"><?= e(t('sol.cancelar_confirmar')) ?></button>
            </div>
        </form>
    </div>
</div>


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


<!-- Modal: solicitar un producto nuevo al administrador.
     También edita una solicitud que el admin devolvió con cambios (abrirModalEditarSolicitud). -->
<div class="modal-overlay" id="modalSolicitarProducto">
    <div class="modal-box">
        <h2 data-nuevo="<?= e(t('cat.solicitar_titulo')) ?>" data-editar="<?= e(t('cat.editar_titulo')) ?>"><?= e(t('cat.solicitar_titulo')) ?></h2>
        <p class="modal-ayuda" data-nuevo="<?= e(t('cat.solicitar_ayuda')) ?>" data-editar="<?= e(t('cat.editar_ayuda')) ?>"><?= e(t('cat.solicitar_ayuda')) ?></p>

        <form id="formSolicitarProducto" method="POST" action="productos.php" enctype="multipart/form-data"
              onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <input type="hidden" name="action" value="solicitar_producto">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_solicitud" value="">

            <div class="form-group">
                <label><?= e(t('cat.campo_nombre')) ?> *</label>
                <input type="text" name="nombre_producto" maxlength="100" required placeholder="<?= e(t('cat.campo_nombre_ej')) ?>">
            </div>

            <div class="form-group">
                <label data-nuevo="<?= e(t('cat.campo_imagen')) ?> *" data-editar="<?= e(t('cat.campo_imagen_cambiar')) ?>"><?= e(t('cat.campo_imagen')) ?> *</label>
                <input type="file" name="imagen" accept="image/png,image/jpeg,image/webp" required>
                <div class="imagen-preview" hidden>
                    <img src="" alt="">
                    <span><?= e(t('cat.campo_imagen_ayuda')) ?></span>
                </div>
            </div>

            <div class="form-group">
                <label><?= e(t('cat.campo_descripcion')) ?></label>
                <textarea name="descripcion" maxlength="255" placeholder="<?= e(t('cat.campo_descripcion_ej')) ?>"></textarea>
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
                    <input type="number" name="precio_sugerido" min="0.01" step="0.01" placeholder="0.00" required>
                </div>
            </div>

            <?php // El proveedor es opcional; al marcarlo se piden sus datos (al menos un contacto) ?>
            <div class="form-group">
                <label class="casilla">
                    <input type="checkbox" name="con_proveedor" value="1" id="conProveedor">
                    <?= e(t('cat.con_proveedor')) ?>
                </label>
            </div>
            <fieldset class="campos-proveedor" id="camposProveedor" hidden disabled>
                <div class="form-row">
                    <div class="form-group">
                        <label><?= e(t('cat.proveedor_nombre')) ?> *</label>
                        <input type="text" name="proveedor_sugerido" maxlength="100" required placeholder="<?= e(t('cat.proveedor_nombre_ej')) ?>">
                    </div>
                    <div class="form-group">
                        <label><?= e(t('cat.proveedor_empresa')) ?></label>
                        <input type="text" name="proveedor_empresa" maxlength="100" placeholder="<?= e(t('cat.proveedor_empresa_ej')) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><?= e(t('cat.proveedor_telefono')) ?></label>
                        <input type="tel" name="proveedor_telefono" maxlength="20" pattern="\+?[0-9 \(\)\-]{7,20}" placeholder="55 1234 5678">
                    </div>
                    <div class="form-group">
                        <label><?= e(t('cat.proveedor_correo')) ?></label>
                        <input type="email" name="proveedor_correo" maxlength="150" placeholder="ventas@proveedor.com">
                    </div>
                </div>
                <p class="campos-ayuda"><?= e(t('cat.proveedor_contacto_ayuda')) ?></p>
            </fieldset>

            <div class="form-group" data-modo="nuevo">
                <label><?= e(t('cat.campo_justificacion')) ?> *</label>
                <textarea name="justificacion" required placeholder="<?= e(t('cat.campo_justificacion_ej')) ?>"></textarea>
            </div>
            <div class="form-group" data-modo="editar" hidden>
                <label><?= e(t('cat.campo_nota')) ?> *</label>
                <textarea name="nota" maxlength="500" placeholder="<?= e(t('cat.campo_nota_ej')) ?>" disabled></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalSolicitarProducto')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary" data-nuevo="<?= e(t('cat.enviar_solicitud')) ?>" data-editar="<?= e(t('cat.enviar_cambios')) ?>"><?= e(t('cat.enviar_solicitud')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
