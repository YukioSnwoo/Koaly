<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';

$errores = [];
$exito = '';

/* ------------------------------------------------------------
   PRG: mensaje flash tras un POST exitoso
   ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    $exito = $_SESSION['flash']['msg'] ?? '';
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

$categorias = $pdo->query("SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria")->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre_categoria,
           (inv.id_producto IS NOT NULL) AS en_inventario
    FROM Productos p
    LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
    LEFT JOIN Inventario_Sucursal inv ON inv.id_producto = p.id_producto AND inv.id_sucursal = ?
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

// Cada producto cae en un grupo para las pestañas: en la sucursal, por agregar o no disponible
$conteos = ['todos' => count($productos), 'con' => 0, 'agregar' => 0, 'no' => 0];
foreach ($productos as &$p) {
    $p['grupo'] = $p['en_inventario'] ? 'con' : ($p['estado'] === 'Activo' ? 'agregar' : 'no');
    $conteos[$p['grupo']]++;
}
unset($p);

$pestanas = [
    'todos'   => t('cat.tab_todos'),
    'con'     => t('cat.tab_en_sucursal'),
    'agregar' => t('cat.tab_por_agregar'),
    'no'      => t('cat.tab_no_disponibles'),
];
// Pestaña inicial, p. ej. productos.php?grupo=agregar desde Inventario
$grupoInicial = isset($pestanas[$_GET['grupo'] ?? '']) ? $_GET['grupo'] : 'todos';

$iniciales = static function (string $nombre): string {
    $ini = '';
    foreach (array_slice(preg_split('/\s+/', trim($nombre)), 0, 2) as $palabra) {
        $ini .= mb_strtoupper(mb_substr($palabra, 0, 1));
    }
    return $ini ?: '?';
};

$pageTitle    = t('cat.titulo');
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('cat.titulo')) ?></h1>
        <p><?= e(t('cat.subtitulo')) ?></p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalSolicitarProducto()">
        + <?= e(t('cat.solicitar')) ?>
    </button>
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
    <div class="catalogo-barra">
        <div class="segmentado" role="group" aria-label="<?= e(t('cat.filtro_aria')) ?>" id="pestanasCatalogo">
            <?php foreach ($pestanas as $clave => $texto): ?>
                <button type="button" data-grupo="<?= $clave ?>" class="<?= $clave === $grupoInicial ? 'is-activo' : '' ?>"
                        aria-pressed="<?= $clave === $grupoInicial ? 'true' : 'false' ?>">
                    <?= e($texto) ?> <span class="segmentado-conteo"><?= numero($conteos[$clave]) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="toolbar catalogo-filtros">
            <input type="search" id="buscarCatalogo" placeholder="<?= e(t('exist.buscar')) ?>" aria-label="<?= e(t('exist.buscar')) ?>">
            <select id="filtroCategoria" aria-label="<?= e(t('cat.col_categoria')) ?>">
                <option value=""><?= e(t('cat.filtro_categorias')) ?></option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id_categoria'] ?>"><?= e(tValor('categoria', $c['nombre_categoria'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="producto-grid" id="gridCatalogo">
        <?php foreach ($productos as $p): ?>
            <?php
                $estado = (string) $p['estado'];
                $id     = (int) $p['id_producto'];
                if ($p['grupo'] === 'con') {
                    [$etiquetaClase, $etiquetaTexto] = ['badge-activo', t('cat.en_tu_sucursal')];
                } elseif ($p['grupo'] === 'agregar') {
                    [$etiquetaClase, $etiquetaTexto] = ['badge-metodo', t('cat.disponible')];
                } else {
                    [$etiquetaClase, $etiquetaTexto] = [
                        $badgeEstadoProducto[$estado] ?? 'badge-inactivo',
                        isset($badgeEstadoProducto[$estado]) ? t('estado_producto.' . $estado) : t('cat.sin_estado'),
                    ];
                }
            ?>
            <article class="producto-card catalogo-card <?= $p['grupo'] === 'no' ? 'is-inactivo' : '' ?>"
                     data-grupo="<?= $p['grupo'] ?>"
                     data-categoria="<?= (int) $p['id_categoria'] ?>"
                     data-nombre="<?= e(mb_strtolower($p['nombre'] . ' ' . $p['codigo'] . ' ' . ($p['descripcion'] ?? ''))) ?>">
                <div class="producto-card-imagen">
                    <?php if (!empty($p['imagen'])): ?>
                        <img src="../<?= e($p['imagen']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span aria-hidden="true"><?= e($iniciales($p['nombre'])) ?></span>
                    <?php endif; ?>
                    <span class="badge <?= $etiquetaClase ?> producto-card-stock"><?= e($etiquetaTexto) ?></span>
                </div>
                <div class="producto-card-cuerpo">
                    <h3 class="producto-card-nombre">
                        <?php if ($p['grupo'] === 'con'): ?>
                            <a href="producto.php?id=<?= $id ?>"><?= e($p['nombre']) ?></a>
                        <?php else: ?>
                            <?= e($p['nombre']) ?>
                        <?php endif; ?>
                    </h3>
                    <span class="producto-card-meta"><?= e($p['codigo']) ?> · <?= e(tValor('categoria', $p['nombre_categoria'], t('comun.sin_categoria'))) ?></span>
                    <?php if (!empty($p['descripcion'])): ?>
                        <p class="catalogo-card-desc"><?= e($p['descripcion']) ?></p>
                    <?php endif; ?>
                    <div class="producto-card-pie">
                        <span class="catalogo-precio">
                            <small><?= e(t('prod.precio_catalogo')) ?></small>
                            <strong><?= dinero($p['precio']) ?></strong>
                        </span>
                        <?php if ($p['grupo'] === 'con'): ?>
                            <a class="btn btn-ghost btn-sm" href="producto.php?id=<?= $id ?>"><?= e(t('cat.ver_inventario')) ?></a>
                        <?php elseif ($p['grupo'] === 'agregar'): ?>
                            <button type="button" class="btn btn-primary btn-sm"
                                    onclick="abrirModalAgregarInventario(this)"
                                    data-id="<?= $id ?>"
                                    data-nombre="<?= e($p['nombre']) ?>"
                                    data-codigo="<?= e($p['codigo']) ?>"
                                    data-categoria="<?= e(tValor('categoria', $p['nombre_categoria'], t('comun.sin_categoria'))) ?>"
                                    data-precio="<?= e((string) $p['precio']) ?>">
                                + <?= e(t('comun.agregar')) ?>
                            </button>
                        <?php else: ?>
                            <span class="texto-tenue catalogo-no-disponible"><?= e(t('cat.no_disponible')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="panel" id="catalogoSinResultados" hidden>
        <div class="empty-state"><?= e(t('cat.sin_resultados')) ?></div>
    </div>
<?php endif; ?>


<!-- ============================================================
     Solicitudes de producto de la sucursal
     ============================================================ -->
<section class="panel catalogo-solicitudes">
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
                            <strong><?= e($s['nombre_producto']) ?></strong>
                            <span class="texto-tenue">
                                · <?= e(tValor('categoria', $s['nombre_categoria'], t('comun.sin_categoria'))) ?>
                                · <?= e(t('cat.col_precio_sugerido')) ?>: <?= dinero($s['precio_sugerido']) ?>
                                <?php if (!empty($s['proveedor_sugerido'])): ?>
                                    · <?= e($s['proveedor_sugerido']) ?>
                                <?php endif; ?>
                            </span>
                            <?php if (!empty($s['descripcion'])): ?>
                                <p class="solicitud-desc"><?= e($s['descripcion']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="solicitud-estado">
                        <span class="texto-tenue"><?= e(fecha($s['creado_en'])) ?></span>
                        <?php if ($estadoSol === 'Rechazada' && !empty($s['motivo_rechazo'])): ?>
                            <span class="texto-peligro"><?= e($s['motivo_rechazo']) ?></span>
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
