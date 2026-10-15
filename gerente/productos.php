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
           COALESCE(inv.cantidad_disponible, 0) AS stock_sucursal,
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

<div class="toolbar">
    <input type="search" id="buscarProducto" placeholder="<?= e(t('exist.buscar')) ?>" aria-label="<?= e(t('exist.buscar')) ?>">
    <select id="filtroInventario" aria-label="<?= e(t('cat.filtro_aria')) ?>">
        <option value="todos"><?= e(t('cat.filtro_todos')) ?></option>
        <option value="con"><?= e(t('cat.filtro_con')) ?></option>
        <option value="sin"><?= e(t('cat.filtro_sin')) ?></option>
    </select>
</div>

<div class="panel">
    <div class="tabla-scroll">
        <table class="tabla-productos" id="tablaProductos">
            <thead>
                <tr>
                    <th><?= e(t('cat.col_imagen')) ?></th>
                    <th><?= e(t('mov.codigo')) ?></th>
                    <th><?= e(t('comun.producto')) ?></th>
                    <th><?= e(t('cat.col_categoria')) ?></th>
                    <th class="num"><?= e(t('cat.col_precio')) ?></th>
                    <th><?= e(t('cat.col_inventario')) ?></th>
                    <th><?= e(t('cat.col_estado')) ?></th>
                    <th><?= e(t('cat.col_acciones')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="8" class="empty-state"><?= e(t('cat.vacio')) ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productos as $p): ?>
                        <?php
                            $stock = (int) $p['stock_sucursal'];
                            $enInventario = (bool) $p['en_inventario'];
                            if (!$enInventario) {
                                [$stockBadge, $stockTexto] = ['badge-inactivo', t('cat.no_en_inventario')];
                            } else {
                                [$stockBadge, $stockTexto] = badgeStock($stock);
                            }
                            $estado = (string) $p['estado'];
                        ?>
                        <tr data-nombre="<?= e(mb_strtolower($p['nombre'] . ' ' . $p['codigo'])) ?>"
                            data-en-inventario="<?= $enInventario ? '1' : '0' ?>">
                            <td>
                                <?php if (!empty($p['imagen'])): ?>
                                    <img class="producto-thumb" src="../<?= e($p['imagen']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <div class="producto-thumb producto-thumb--placeholder"><?= e(t('comun.sin_imagen')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['codigo']) ?></td>
                            <td>
                                <div class="producto-nombre"><?= e($p['nombre']) ?></div>
                                <?php if (!empty($p['descripcion'])): ?>
                                    <div class="producto-desc"><?= e($p['descripcion']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['nombre_categoria'] ?? t('comun.sin_categoria')) ?></td>
                            <td class="num"><?= dinero($p['precio']) ?></td>
                            <td><span class="badge <?= $stockBadge ?>"><?= e($stockTexto) ?></span></td>
                            <td>
                                <span class="badge <?= $badgeEstadoProducto[$estado] ?? 'badge-inactivo' ?>">
                                    <?= e(isset($badgeEstadoProducto[$estado]) ? t('estado_producto.' . $estado) : t('cat.sin_estado')) ?>
                                </span>
                            </td>
                            <td>
                                <div class="acciones">
                                    <?php if ($enInventario): ?>
                                        <a class="btn btn-ghost btn-sm" href="producto.php?id=<?= (int) $p['id_producto'] ?>"><?= e(t('cat.ver_ficha')) ?></a>
                                    <?php elseif ($estado === 'Activo'): ?>
                                        <button type="button" class="btn btn-success btn-sm"
                                                onclick="abrirModalAgregarInventario(this)"
                                                data-id="<?= (int) $p['id_producto'] ?>"
                                                data-nombre="<?= e($p['nombre']) ?>"
                                                data-codigo="<?= e($p['codigo']) ?>"
                                                data-categoria="<?= e($p['nombre_categoria'] ?? t('comun.sin_categoria')) ?>"
                                                data-precio="<?= e((string) $p['precio']) ?>">
                                            + <?= e(t('cat.agregar_inventario')) ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="texto-tenue" style="font-size:0.85rem;"><?= e(t('cat.no_disponible')) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- ============================================================
     Solicitudes de producto de la sucursal
     ============================================================ -->
<section style="margin-top: 2rem;">
    <h2 class="section-title">
        <?= e(t('cat.solicitudes')) ?>
        <?php if ($totalSolicitudesPendientes > 0): ?>
            <span class="badge badge-warning" style="margin-left: 0.5rem;">
                <?= e(tn('cat.n_pendientes', $totalSolicitudesPendientes)) ?>
            </span>
        <?php endif; ?>
    </h2>

    <?php if (empty($solicitudes)): ?>
        <div class="panel">
            <div class="empty-state"><?= e(t('cat.solicitudes_vacio')) ?></div>
        </div>
    <?php else: ?>
        <div class="panel">
            <div class="tabla-scroll">
                <table class="tabla-productos">
                    <thead>
                        <tr>
                            <th><?= e(t('comun.fecha')) ?></th>
                            <th><?= e(t('comun.producto')) ?></th>
                            <th><?= e(t('cat.col_categoria')) ?></th>
                            <th class="num"><?= e(t('cat.col_precio_sugerido')) ?></th>
                            <th><?= e(t('cat.col_proveedor')) ?></th>
                            <th><?= e(t('cat.col_estado')) ?></th>
                            <th><?= e(t('mov.detalle')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $s): ?>
                            <tr>
                                <td><?= e(fecha($s['creado_en'])) ?></td>
                                <td>
                                    <div class="producto-nombre"><?= e($s['nombre_producto']) ?></div>
                                    <?php if (!empty($s['descripcion'])): ?>
                                        <div class="producto-desc"><?= e($s['descripcion']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($s['nombre_categoria'] ?? t('comun.sin_categoria')) ?></td>
                                <td class="num"><?= dinero($s['precio_sugerido']) ?></td>
                                <td><?= !empty($s['proveedor_sugerido']) ? e($s['proveedor_sugerido']) : '<span class="texto-tenue">—</span>' ?></td>
                                <td>
                                    <?php if ($s['estado'] === 'Aprobada'): ?>
                                        <span class="badge badge-activo"><?= e(t('solicitud.Aprobada')) ?></span>
                                    <?php elseif ($s['estado'] === 'Rechazada'): ?>
                                        <span class="badge badge-danger"><?= e(t('solicitud.Rechazada')) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-inactivo"><?= e(t('solicitud.Pendiente')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.85rem;">
                                    <?php if ($s['estado'] === 'Rechazada' && !empty($s['motivo_rechazo'])): ?>
                                        <span class="texto-peligro"><?= e($s['motivo_rechazo']) ?></span>
                                    <?php elseif ($s['estado'] === 'Aprobada'): ?>
                                        <span class="texto-ok"><?= e(t('cat.solicitud_creada', ['id' => (int) $s['id_producto_creado']])) ?></span>
                                    <?php else: ?>
                                        <span class="texto-tenue"><?= e(t('cat.en_revision')) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
                            <option value="<?= (int) $c['id_categoria'] ?>"><?= e($c['nombre_categoria']) ?></option>
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
