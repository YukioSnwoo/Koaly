<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';

$errores = [];
$exito = '';

/* ------------------------------------------------------------
 P RG: recuperar mensaje flash tras un P*OST exitoso
 ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $exito = $flash['msg'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        /*
         * El gerente no modifica el catálogo global (nombre, foto, código,
         * categoría, estado): eso es del admin. Aquí solo puede dar de alta
         * productos en su inventario y solicitar productos nuevos.
         * Stock y precio de venta de su sucursal se editan en existencias.php.
         */

        /* -------- Agregar producto del catálogo a mi inventario -------- */
        if ($action === 'agregar_inventario') {
            agregarProductoAInventario(
                $pdo,
                (int) $idSucursalGerente,
                                       (int) ($_POST['id_producto'] ?? 0),
                                       max(0, (int) ($_POST['cantidad_inicial'] ?? 0)),
                                       (float) ($_POST['precio_venta'] ?? -1),
                                       (int) $gerente['id_usuario']
            );

            $_SESSION['flash'] = ['msg' => 'Producto agregado al inventario.'];
            header('Location: productos.php');
            exit;
        }

        /* -------- Solicitar nuevo producto al admin -------- */
        elseif ($action === 'solicitar_producto') {
            $nombreProducto     = trim($_POST['nombre_producto'] ?? '');
            $descripcion        = trim($_POST['descripcion'] ?? '');
            $idCategoria        = (int) ($_POST['id_categoria'] ?? 0);
            $precioSugerido     = (float) ($_POST['precio_sugerido'] ?? -1);
            $proveedorSugerido  = trim($_POST['proveedor_sugerido'] ?? '');
            $justificacion      = trim($_POST['justificacion'] ?? '');

            if ($nombreProducto === '' || mb_strlen($nombreProducto) > 100) {
                throw new RuntimeException('El nombre del producto es obligatorio (máx. 100 caracteres).');
            }
            if ($idCategoria <= 0) {
                throw new RuntimeException('Selecciona una categoría.');
            }
            if ($precioSugerido < 0) {
                throw new RuntimeException('El precio sugerido no puede ser negativo.');
            }
            if ($justificacion === '') {
                throw new RuntimeException('Indica una justificación para tu solicitud.');
            }
            if ($proveedorSugerido !== '' && mb_strlen($proveedorSugerido) > 100) {
                throw new RuntimeException('El proveedor sugerido no debe superar 100 caracteres.');
            }

            $check = $pdo->prepare("SELECT 1 FROM Categorias WHERE id_categoria = ?");
            $check->execute([$idCategoria]);
            if ($check->fetch() === false) {
                throw new RuntimeException('Categoría inválida.');
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
                           $justificacion
            ]);

            $_SESSION['flash'] = ['msg' => 'Solicitud enviada. El administrador la revisará pronto.'];
            header('Location: productos.php');
            exit;
        }

    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = 'Error al guardar en la base de datos.';
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

// Mis solicitudes de esta sucursal
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

$pageTitle    = 'Productos';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
<div>
<h1>Productos</h1>
<p>Consulta el catálogo. Para nuevos productos, envía una solicitud al administrador.</p>
</div>
<button type="button" class="btn btn-primary" onclick="abrirModalSolicitarProducto()">
+ Solicitar producto
</button>
</div>

<?php if ($exito): ?>
<div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<div class="toolbar">
<input type="text" id="buscarProducto" placeholder="Buscar producto por nombre...">
<select id="filtroInventario" aria-label="Filtrar por inventario">
<option value="todos">Todos los productos</option>
<option value="con">En mi inventario</option>
<option value="sin">No en mi inventario</option>
</select>
</div>

<div class="panel">
<div class="tabla-scroll">
<table class="tabla-productos" id="tablaProductos">
<thead>
<tr>
<th>Imagen</th>
<th>Clave</th>
<th>Producto</th>
<th>Categoría</th>
<th>Precio catálogo (IVA incl.)</th>
<th>Inventario</th>
<th>Estado</th>
<th>Acciones</th>
</tr>
</thead>
<tbody>
<?php if (empty($productos)): ?>
<tr>
<td colspan="8" class="empty-state">No hay productos registrados todavía.</td>
</tr>
<?php else: ?>
<?php foreach ($productos as $p): ?>
<?php
$stock = (int) $p['stock_sucursal'];
$enInventario = (bool) $p['en_inventario'];

if (!$enInventario) {
    $stockBadge = 'badge-inactivo';
    $stockTexto = 'No en tu inventario';
} else {
    [$stockBadge, $stockTexto] = badgeStock($stock);
}
?>
<tr data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre'])) ?>"
data-en-inventario="<?= $enInventario ? '1' : '0' ?>">
<td>
<?php if (!empty($p['imagen'])): ?>
<img class="producto-thumb" src="../<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
<?php else: ?>
<div class="producto-thumb producto-thumb--placeholder">Sin imagen</div>
<?php endif; ?>
</td>
<td><?= htmlspecialchars($p['codigo']) ?></td>
<td>
<div class="producto-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
<?php if (!empty($p['descripcion'])): ?>
<div class="producto-desc"><?= htmlspecialchars($p['descripcion']) ?></div>
<?php endif; ?>
</td>
<td><?= htmlspecialchars($p['nombre_categoria'] ?? 'Sin categoría') ?></td>
<td>$<?= number_format((float) $p['precio'], 2) ?></td>
<td>
<span class="badge <?= $stockBadge ?>"><?= $stockTexto ?></span>
</td>
<td>
<?php if ($p['estado'] === 'Activo'): ?>
<span class="badge badge-activo">Activo</span>
<?php elseif ($p['estado'] === 'Descontinuado'): ?>
<span class="badge badge-danger">Descontinuado</span>
<?php elseif ($p['estado'] === 'Proximamente'): ?>
<span class="badge badge-warning">Próximamente</span>
<?php else: ?>
<span class="badge badge-inactivo"><?= htmlspecialchars($p['estado'] ?: 'Sin estado') ?></span>
<?php endif; ?>
</td>
<td>
<div class="acciones">
<?php if ($enInventario): ?>
<a class="btn btn-ghost btn-sm"
href="existencias.php?buscar=<?= urlencode($p['codigo']) ?>">Ver en existencias</a>
<?php elseif ($p['estado'] === 'Activo'): ?>
<button type="button" class="btn btn-success btn-sm"
onclick="abrirModalAgregarInventario(this)"
data-id="<?= (int) $p['id_producto'] ?>"
data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
data-codigo="<?= htmlspecialchars($p['codigo'], ENT_QUOTES) ?>"
data-categoria="<?= htmlspecialchars($p['nombre_categoria'] ?? 'Sin categoría', ENT_QUOTES) ?>"
data-precio="<?= htmlspecialchars((string) $p['precio'], ENT_QUOTES) ?>"
>+ Agregar a inventario</button>
<?php else: ?>
<span style="color:#94a3b8; font-size:0.85rem;">No disponible</span>
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
Sección: Mis solicitudes de producto
============================================================ -->
<section style="margin-top: 2rem;">
<h2 class="section-title">
Mis solicitudes
<?php if ($totalSolicitudesPendientes > 0): ?>
<span class="badge badge-warning" style="margin-left: 0.5rem;">
<?= $totalSolicitudesPendientes ?> pendiente<?= $totalSolicitudesPendientes === 1 ? '' : 's' ?>
</span>
<?php endif; ?>
</h2>

<?php if (empty($solicitudes)): ?>
<div class="panel">
<div class="empty-state">
Aún no has enviado solicitudes de producto. Usa "+ Solicitar producto" para pedir uno nuevo al administrador.
</div>
</div>
<?php else: ?>
<div class="panel">
<div class="tabla-scroll">
<table class="tabla-productos">
<thead>
<tr>
<th>Fecha</th>
<th>Producto</th>
<th>Categoría</th>
<th>Precio sugerido</th>
<th>Proveedor</th>
<th>Estado</th>
<th>Detalle</th>
</tr>
</thead>
<tbody>
<?php foreach ($solicitudes as $s): ?>
<tr>
<td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($s['creado_en']))) ?></td>
<td>
<div class="producto-nombre"><?= htmlspecialchars($s['nombre_producto']) ?></div>
<?php if (!empty($s['descripcion'])): ?>
<div class="producto-desc"><?= htmlspecialchars($s['descripcion']) ?></div>
<?php endif; ?>
</td>
<td><?= htmlspecialchars($s['nombre_categoria'] ?? 'Sin categoría') ?></td>
<td>$<?= number_format((float) $s['precio_sugerido'], 2) ?></td>
<td>
<?php if (!empty($s['proveedor_sugerido'])): ?>
<?= htmlspecialchars($s['proveedor_sugerido']) ?>
<?php else: ?>
<span style="color:#94a3b8;">—</span>
<?php endif; ?>
</td>
<td>
<?php if ($s['estado'] === 'Aprobada'): ?>
<span class="badge badge-activo">Aprobada</span>
<?php elseif ($s['estado'] === 'Rechazada'): ?>
<span class="badge badge-danger">Rechazada</span>
<?php else: ?>
<span class="badge badge-inactivo">Pendiente</span>
<?php endif; ?>
</td>
<td>
<?php if ($s['estado'] === 'Rechazada' && !empty($s['motivo_rechazo'])): ?>
<span style="color:#dc2626; font-size:0.85rem;">
<?= htmlspecialchars($s['motivo_rechazo']) ?>
</span>
<?php elseif ($s['estado'] === 'Aprobada'): ?>
<span style="color:#16a34a; font-size:0.85rem;">
Producto creado (ID <?= (int) $s['id_producto_creado'] ?>)
</span>
<?php else: ?>
<span style="color:#94a3b8; font-size:0.85rem;">En revisión</span>
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


<!-- ============================================================
Modal: Agregar producto del catálogo a mi inventario
============================================================ -->
<div class="modal-overlay" id="modalAgregarInventario">
<div class="modal-box">
<h2>Agregar al inventario</h2>

<div class="producto-resumen">
<div class="producto-resumen-nombre" id="agregarInvNombre"></div>
<div class="producto-resumen-meta" id="agregarInvMeta"></div>
</div>

<form id="formAgregarInventario" method="POST" action="productos.php"
onsubmit="this.querySelector('[type=submit]').disabled = true;">
<input type="hidden" name="action" value="agregar_inventario">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
<input type="hidden" name="id_producto" value="">

<div class="form-row">
<div class="form-group">
<label>Cantidad inicial</label>
<input type="number" name="cantidad_inicial" min="0" step="1" value="0" required>
</div>
<div class="form-group">
<label>Precio de venta (IVA incluido)</label>
<input type="number" name="precio_venta" min="0" step="0.01" placeholder="0.00" required>
</div>
</div>

<div class="modal-actions">
<button type="button" class="btn btn-ghost" onclick="cerrarModal('modalAgregarInventario')">Cancelar</button>
<button type="submit" class="btn btn-primary">Agregar</button>
</div>
</form>
</div>
</div>


<!-- ============================================================
Modal: Solicitar producto nuevo al administrador
============================================================ -->
<div class="modal-overlay" id="modalSolicitarProducto">
<div class="modal-box">
<h2>Solicitar producto nuevo</h2>
<p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">
El administrador revisará tu solicitud y, si la aprueba, el producto
se creará en el catálogo global.
</p>

<form id="formSolicitarProducto" method="POST" action="productos.php">
<input type="hidden" name="action" value="solicitar_producto">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

<div class="form-group">
<label>Nombre del producto *</label>
<input type="text" name="nombre_producto" maxlength="100" required
placeholder="Ej. Refresco de uva 355 ml">
</div>

<div class="form-group">
<label>Descripción (opcional)</label>
<textarea name="descripcion" placeholder="Marca, presentación, sabor, etc."></textarea>
</div>

<div class="form-row">
<div class="form-group">
<label>Categoría *</label>
<select name="id_categoria" required>
<option value="">Selecciona una categoría</option>
<?php foreach ($categorias as $c): ?>
<option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>Precio sugerido *</label>
<input type="number" name="precio_sugerido" min="0" step="0.01"
placeholder="0.00" required>
</div>
</div>

<div class="form-group">
<label>Proveedor sugerido (opcional)</label>
<input type="text" name="proveedor_sugerido" maxlength="100"
placeholder="Si sabes con quién conseguirlo, escríbelo aquí">
</div>

<div class="form-group">
<label>Justificación *</label>
<textarea name="justificacion" required
placeholder="¿Por qué quieres vender este producto? (demanda de clientes, temporada, etc.)"></textarea>
</div>

<div class="modal-actions">
<button type="button" class="btn btn-ghost" onclick="cerrarModal('modalSolicitarProducto')">Cancelar</button>
<button type="submit" class="btn btn-primary">Enviar solicitud</button>
</div>
</form>
</div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
