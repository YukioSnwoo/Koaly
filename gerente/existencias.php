<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';

$errores = [];
$exito   = '';

/* ------------------------------------------------------------
 P RG: recuperar mensaje flash tras un P*OST exitoso
 ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    $exito = $_SESSION['flash']['msg'] ?? '';
    unset($_SESSION['flash']);
}

/* ============================================================
 P OST                                  *
 ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mensajeExito = '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        /* -------- Ajustar stock -------- */
        if ($action === 'ajustar_stock') {
            $idProducto   = (int) ($_POST['id_producto'] ?? 0);
            $cantidadNueva = (int) ($_POST['cantidad_nueva'] ?? -1);
            $cantidadVista = (int) ($_POST['cantidad_actual'] ?? -1);
            $motivo       = trim($_POST['motivo'] ?? 'Conteo físico');
            $detalle      = trim($_POST['detalle'] ?? '');

            if ($idProducto <= 0 || $cantidadNueva < 0) {
                throw new RuntimeException('Cantidad inválida.');
            }
            if (!isset(MOTIVOS_AJUSTE[$motivo])) {
                throw new RuntimeException('Motivo de ajuste inválido.');
            }

            // Leer y bloquear la fila dentro de la transacción: una venta
            // concurrente no puede cambiar el stock hasta el commit.
            $pdo->beginTransaction();
            $cantidadActual = leerStockParaActualizar($pdo, (int) $idSucursalGerente, $idProducto);

            if ($cantidadActual === null) {
                throw new RuntimeException('El producto no está en el inventario de tu sucursal.');
            }
            if ($cantidadVista >= 0 && $cantidadVista !== $cantidadActual) {
                throw new RuntimeException(
                    "El stock cambió mientras ajustabas (tenías {$cantidadVista}, ahora hay {$cantidadActual}). Revisa la cantidad e inténtalo de nuevo."
                );
            }
            if ($cantidadNueva === $cantidadActual) {
                throw new RuntimeException('La cantidad es la misma que la actual. No hay nada que actualizar.');
            }

            $stmt = $pdo->prepare("
            UPDATE Inventario_Sucursal
            SET cantidad_disponible = ?
            WHERE id_sucursal = ? AND id_producto = ?
            ");
            $stmt->execute([$cantidadNueva, $idSucursalGerente, $idProducto]);

            $delta = $cantidadNueva - $cantidadActual;
            $motivoCompleto = $motivo . ($detalle !== '' ? ' — ' . $detalle : '');

            registrarMovimiento(
                $pdo, (int) $idSucursalGerente, $idProducto, MOTIVOS_AJUSTE[$motivo],
                $delta, $cantidadNueva, $motivoCompleto, (int) $gerente['id_usuario']
            );

            $pdo->commit();
            $mensajeExito = 'Stock actualizado correctamente.';

            /* -------- Agregar producto al inventario -------- */
        } elseif ($action === 'agregar_producto') {
            agregarProductoAInventario(
                $pdo,
                (int) $idSucursalGerente,
                                       (int) ($_POST['id_producto'] ?? 0),
                                       max(0, (int) ($_POST['cantidad_inicial'] ?? 0)),
                                       (float) ($_POST['precio_venta'] ?? -1),
                                       (int) $gerente['id_usuario']
            );

            $mensajeExito = 'Producto agregado al inventario.';

            /* -------- Recibir mercancía (entrada de proveedor) -------- */
        } elseif ($action === 'recibir_mercancia') {
            $idProducto = (int) ($_POST['id_producto'] ?? 0);
            $recibida   = (int) ($_POST['cantidad_recibida'] ?? 0);
            $proveedor  = trim($_POST['proveedor'] ?? '');
            $folio      = trim($_POST['folio'] ?? '');

            if ($idProducto <= 0 || $recibida <= 0) {
                throw new RuntimeException('Indica cuántas unidades recibiste.');
            }
            if (mb_strlen($proveedor) > 100 || mb_strlen($folio) > 50) {
                throw new RuntimeException('Proveedor (máx. 100) o folio (máx. 50) demasiado largos.');
            }

            $pdo->beginTransaction();
            $cantidadActual = leerStockParaActualizar($pdo, (int) $idSucursalGerente, $idProducto);
            if ($cantidadActual === null) {
                throw new RuntimeException('El producto no está en el inventario de tu sucursal.');
            }

            $cantidadNueva = $cantidadActual + $recibida;
            $stmt = $pdo->prepare("
            UPDATE Inventario_Sucursal
            SET cantidad_disponible = ?
            WHERE id_sucursal = ? AND id_producto = ?
            ");
            $stmt->execute([$cantidadNueva, $idSucursalGerente, $idProducto]);

            $partes = array_filter([
                $proveedor !== '' ? 'Proveedor: ' . $proveedor : null,
                $folio !== '' ? 'Folio: ' . $folio : null,
            ]);
            $motivoRecepcion = 'Recepción de mercancía' . ($partes ? ' — ' . implode(' · ', $partes) : '');

            registrarMovimiento(
                $pdo, (int) $idSucursalGerente, $idProducto, 'COMPRA_PROVEEDOR',
                $recibida, $cantidadNueva, $motivoRecepcion, (int) $gerente['id_usuario']
            );

            $pdo->commit();
            $mensajeExito = "Recepción registrada: +{$recibida} unidades (ahora hay {$cantidadNueva}).";

            /* -------- Cambiar precio de venta (solo en esta sucursal) -------- */
        } elseif ($action === 'cambiar_precio') {
            $idProducto  = (int) ($_POST['id_producto'] ?? 0);
            $precioVenta = round((float) ($_POST['precio_venta'] ?? -1), 2);

            if ($idProducto <= 0 || $precioVenta < 0) {
                throw new RuntimeException('Precio inválido.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
            SELECT precio_venta
            FROM Inventario_Sucursal
            WHERE id_sucursal = ? AND id_producto = ?
            FOR UPDATE
            ");
            $stmt->execute([$idSucursalGerente, $idProducto]);
            $precioActual = $stmt->fetchColumn();

            if ($precioActual === false) {
                throw new RuntimeException('El producto no está en el inventario de tu sucursal.');
            }
            if (round((float) $precioActual, 2) === $precioVenta) {
                throw new RuntimeException('El precio es el mismo que el actual. No hay nada que actualizar.');
            }

            $stmt = $pdo->prepare("
            UPDATE Inventario_Sucursal
            SET precio_venta = ?
            WHERE id_sucursal = ? AND id_producto = ?
            ");
            $stmt->execute([$precioVenta, $idSucursalGerente, $idProducto]);

            // Rastro de quién cambió el precio y cuándo: se registra en el kardex
            // con cantidad 0 (no mueve stock). Ver PREFIJO_CAMBIO_PRECIO.
            $stockActual = leerStockParaActualizar($pdo, (int) $idSucursalGerente, $idProducto) ?? 0;
            registrarMovimiento(
                $pdo, (int) $idSucursalGerente, $idProducto, 'AJUSTE_MANUAL', 0, $stockActual,
                sprintf('%s $%s → $%s', PREFIJO_CAMBIO_PRECIO, number_format((float) $precioActual, 2), number_format($precioVenta, 2)),
                (int) $gerente['id_usuario']
            );

            $pdo->commit();
            $mensajeExito = 'Precio de venta actualizado.';
        }

        // PRG: tras guardar, redirigir a una URL limpia.
        if ($mensajeExito !== '') {
            $_SESSION['flash'] = ['msg' => $mensajeExito];
            header('Location: existencias.php');
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

/* ============================================================
 D atos para la vista                   *
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

// Filtro inicial del toolbar, p. ej. existencias.php?stock=alerta
$filtrosStock = ['todos', 'normal', 'bajo', 'agotado', 'alerta'];
$filtroInicial = in_array($_GET['stock'] ?? '', $filtrosStock, true) ? $_GET['stock'] : 'todos';
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

// Productos del catálogo que NO están en esta sucursal
$stmt = $pdo->prepare("
SELECT p.id_producto, p.codigo, p.nombre, p.precio,
c.nombre_categoria
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

$pageTitle    = 'Existencias';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
<div>
<h1>Existencias</h1>
<p>Controla y ajusta el stock de los productos en tu sucursal</p>
</div>
<div class="acciones">
<a href="movimientos.php" class="btn btn-ghost">Movimientos</a>
<?php if (!empty($productosDisponibles)): ?>
<button type="button" class="btn btn-primary" onclick="abrirModalAgregarProducto()">
+ Agregar producto al inventario
</button>
<?php endif; ?>
</div>
</div>

<?php if ($exito): ?>
<div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<?php if (empty($inventario)): ?>
<div class="panel">
<div class="empty-state">
Tu sucursal no tiene productos en inventario todavía.
<?php if (!empty($productosDisponibles)): ?>
Usa "+ Agregar producto al inventario" para agregar el primero.
<?php endif; ?>
</div>
</div>
<?php else: ?>
<section class="kpi-grid">
<div class="kpi">
<span class="kpi-label">Productos</span>
<span class="kpi-value"><?= $totalProductos ?></span>
<span class="kpi-sub">en inventario</span>
</div>
<div class="kpi kpi--danger">
<span class="kpi-label">Agotados</span>
<span class="kpi-value kpi-value--danger"><?= $totalAgotado ?></span>
<span class="kpi-sub">stock = 0</span>
</div>
<div class="kpi kpi--warn">
<span class="kpi-label">Bajo stock</span>
<span class="kpi-value kpi-value--warn"><?= $totalBajo ?></span>
<span class="kpi-sub">menos de <?= STOCK_UMBRAL_BAJO ?> unidades</span>
</div>
<div class="kpi kpi--ok">
<span class="kpi-label">Valor del inventario</span>
<span class="kpi-value kpi-value--ok">$<?= number_format($valorInventario, 0) ?></span>
<span class="kpi-sub">a precio de venta</span>
</div>
</section>

<div class="toolbar">
<input type="text" id="buscarExistencia" placeholder="Buscar por nombre o código..."
value="<?= htmlspecialchars($busquedaInicial) ?>">
<select id="filtroStock" aria-label="Filtrar por estado de stock" data-umbral="<?= STOCK_UMBRAL_BAJO ?>">
<?php
$opcionesStock = [
    'todos'   => 'Todos los estados',
    'normal'  => 'Stock normal (' . STOCK_UMBRAL_BAJO . '+)',
    'bajo'    => 'Bajo stock (1-' . (STOCK_UMBRAL_BAJO - 1) . ')',
    'agotado' => 'Agotados (0)',
    'alerta'  => 'Bajo o agotado (0-' . (STOCK_UMBRAL_BAJO - 1) . ')',
];
foreach ($opcionesStock as $valor => $texto): ?>
<option value="<?= $valor ?>"<?= $valor === $filtroInicial ? ' selected' : '' ?>><?= $texto ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="panel">
<div class="tabla-scroll">
<table class="tabla-productos" id="tablaExistencias">
<thead>
<tr>
<th>Imagen</th>
<th>Clave</th>
<th>Producto</th>
<th>Categoría</th>
<th>Precio venta (IVA incl.)</th>
<th>Stock</th>
<th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($inventario as $p): ?>
<?php
$stock = (int) $p['cantidad_disponible'];
[$stockBadge, $stockTexto] = badgeStock($stock);
$precio = (float) $p['precio_venta'];
?>
<tr data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre'] . ' ' . $p['codigo'])) ?>"
data-stock="<?= $stock ?>">
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
<?php if ($p['estado_producto'] !== 'Activo'): ?>
<span class="badge badge-danger" title="La caja no vende productos que no están activos en el catálogo">
No se vende: <?= htmlspecialchars($p['estado_producto'] === 'Proximamente' ? 'Próximamente' : ($p['estado_producto'] ?: 'Sin estado')) ?>
</span>
<?php endif; ?>
</td>
<td><?= htmlspecialchars($p['nombre_categoria'] ?? 'Sin categoría') ?></td>
<td>
<?php if ($precio > 0): ?>
$<?= number_format($precio, 2) ?>
<?php else: ?>
<span style="color: #64748b;">Sin precio</span>
<?php endif; ?>
</td>
<td><span class="badge <?= $stockBadge ?>"><?= $stockTexto ?></span></td>
<td>
<div class="acciones">
<button type="button" class="btn btn-ghost btn-sm"
onclick="abrirModalAjustarStock(this)"
data-id="<?= (int) $p['id_producto'] ?>"
data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
data-stock="<?= $stock ?>"
data-precio="<?= htmlspecialchars((string) $precio, ENT_QUOTES) ?>"
>Ajustar stock</button>

<button type="button" class="btn btn-ghost btn-sm"
onclick="abrirModalCambiarPrecio(this)"
data-id="<?= (int) $p['id_producto'] ?>"
data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
data-precio="<?= htmlspecialchars((string) $precio, ENT_QUOTES) ?>"
>Precio</button>

<button type="button" class="btn btn-success btn-sm"
onclick="abrirModalRecibir(this)"
data-id="<?= (int) $p['id_producto'] ?>"
data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
data-stock="<?= $stock ?>"
>Recibir</button>

<a class="btn btn-ghost btn-sm" href="movimientos.php?producto=<?= (int) $p['id_producto'] ?>">Historial</a>

<?php if ($stock > 0): ?>
<button type="button" class="btn btn-danger btn-sm"
onclick="abrirModalAjustarStock(this, 0)"
data-id="<?= (int) $p['id_producto'] ?>"
data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
data-stock="<?= $stock ?>"
data-precio="<?= htmlspecialchars((string) $precio, ENT_QUOTES) ?>"
>Poner en cero</button>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<!-- Modal: Ajustar stock -->
<div class="modal-overlay" id="modalAjustarStock">
<div class="modal-box">
<h2>Ajustar stock</h2>
<p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">
Producto: <strong id="ajustarProductoNombre" style="color:#1e293b;"></strong>
</p>

<form id="formAjustarStock" method="POST" action="existencias.php">
<input type="hidden" name="action" value="ajustar_stock">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
<input type="hidden" name="id_producto" value="">
<input type="hidden" name="cantidad_actual" value="">

<div class="form-row">
<div class="form-group">
<label>Stock actual</label>
<p id="ajustarStockActual" style="padding:0.6rem 0.8rem; background:#f8fafc; border-radius:8px; font-weight:600; color:#475569;"></p>
</div>
<div class="form-group">
<label>Precio de venta (IVA incluido)</label>
<p id="ajustarStockPrecio" style="padding:0.6rem 0.8rem; background:#f8fafc; border-radius:8px; font-weight:600; color:#475569;"></p>
</div>
</div>

<div class="form-group">
<label>Nueva cantidad</label>
<input type="number" name="cantidad_nueva" min="0" step="1" required>
</div>

<div class="form-group">
<label>Motivo del ajuste</label>
<select name="motivo" required>
<option value="Conteo físico">Conteo físico</option>
<option value="Merma">Merma</option>
<option value="Robo">Robo</option>
<option value="Devolución de cliente">Devolución de cliente</option>
<option value="Error de captura">Error de captura</option>
<option value="Otro">Otro</option>
</select>
</div>

<div class="form-group">
<label>Detalle (opcional)</label>
<textarea name="detalle" placeholder="Notas adicionales del ajuste"></textarea>
</div>

<div class="modal-actions">
<button type="button" class="btn btn-ghost" onclick="cerrarModal('modalAjustarStock')">Cancelar</button>
<button type="submit" class="btn btn-primary">Guardar ajuste</button>
</div>
</form>
</div>
</div>

<!-- Modal: Recibir mercancía -->
<div class="modal-overlay" id="modalRecibir">
<div class="modal-box">
<h2>Recibir mercancía</h2>
<p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">
Producto: <strong id="recibirProductoNombre" style="color:#1e293b;"></strong><br>
Stock actual: <strong id="recibirStockActual"></strong>. Lo recibido se <strong>suma</strong> al stock.
</p>

<form id="formRecibir" method="POST" action="existencias.php"
onsubmit="this.querySelector('[type=submit]').disabled = true;">
<input type="hidden" name="action" value="recibir_mercancia">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
<input type="hidden" name="id_producto" value="">

<div class="form-group">
<label>Unidades recibidas</label>
<input type="number" name="cantidad_recibida" min="1" step="1" required>
</div>

<div class="form-row">
<div class="form-group">
<label>Proveedor (opcional)</label>
<input type="text" name="proveedor" maxlength="100" placeholder="Ej. Coca-Cola FEMSA">
</div>
<div class="form-group">
<label>Folio / factura (opcional)</label>
<input type="text" name="folio" maxlength="50" placeholder="Ej. F-10293">
</div>
</div>

<div class="modal-actions">
<button type="button" class="btn btn-ghost" onclick="cerrarModal('modalRecibir')">Cancelar</button>
<button type="submit" class="btn btn-primary">Registrar recepción</button>
</div>
</form>
</div>
</div>

<!-- Modal: Cambiar precio de venta -->
<div class="modal-overlay" id="modalCambiarPrecio">
<div class="modal-box">
<h2>Precio de venta</h2>
<p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">
Producto: <strong id="precioProductoNombre" style="color:#1e293b;"></strong><br>
Solo cambia el precio en tu sucursal.
</p>

<form id="formCambiarPrecio" method="POST" action="existencias.php">
<input type="hidden" name="action" value="cambiar_precio">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
<input type="hidden" name="id_producto" value="">

<div class="form-group">
<label>Nuevo precio de venta (IVA incluido)</label>
<input type="number" name="precio_venta" min="0" step="0.01" placeholder="0.00" required>
</div>

<div class="modal-actions">
<button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCambiarPrecio')">Cancelar</button>
<button type="submit" class="btn btn-primary">Guardar precio</button>
</div>
</form>
</div>
</div>

<!-- Modal: Agregar producto al inventario -->
<div class="modal-overlay" id="modalAgregarProducto">
<div class="modal-box">
<h2>Agregar producto al inventario</h2>
<p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">
Busca un producto del catálogo global que aún no esté en tu sucursal.
</p>

<form id="formAgregarProducto" method="POST" action="existencias.php"
onsubmit="this.querySelector('[type=submit]').disabled = true;">
<input type="hidden" name="action" value="agregar_producto">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

<div class="form-group">
<label for="buscarProductoAgregar">Producto</label>
<div class="picker" id="pickerProducto">
<input type="hidden" name="id_producto" value="">

<div class="picker-input-wrap">
<svg class="picker-icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
</svg>
<input type="text" id="buscarProductoAgregar" class="picker-input"
placeholder="Buscar por nombre o código..."
autocomplete="off" required
role="combobox" aria-autocomplete="list"
aria-expanded="false" aria-controls="listaProductosAgregar">
<button type="button" class="picker-clear" aria-label="Quitar selección" hidden>&times;</button>
</div>

<ul class="picker-lista" id="listaProductosAgregar" role="listbox">
<?php foreach ($productosDisponibles as $pd): ?>
<li class="picker-item" role="option"
data-id="<?= (int) $pd['id_producto'] ?>"
data-nombre="<?= htmlspecialchars($pd['nombre'], ENT_QUOTES) ?>"
data-precio="<?= htmlspecialchars((string) $pd['precio'], ENT_QUOTES) ?>"
data-buscar="<?= htmlspecialchars($pd['nombre'] . ' ' . $pd['codigo'] . ' ' . ($pd['nombre_categoria'] ?? ''), ENT_QUOTES) ?>">
<span class="picker-item-nombre"><?= htmlspecialchars($pd['nombre']) ?></span>
<span class="picker-item-meta"><?= htmlspecialchars($pd['codigo']) ?> · <?= htmlspecialchars($pd['nombre_categoria'] ?? 'Sin categoría') ?></span>
</li>
<?php endforeach; ?>
<li class="picker-vacio" hidden>No se encontraron productos</li>
</ul>
</div>
</div>

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
<button type="button" class="btn btn-ghost" onclick="cerrarModal('modalAgregarProducto')">Cancelar</button>
<button type="submit" class="btn btn-primary">Agregar</button>
</div>
</form>
</div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
