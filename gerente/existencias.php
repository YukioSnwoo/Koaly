<?php
require_once __DIR__ . '/guardia.php';

$UMBRAL_BAJO = 10;

$errores = [];
$exito   = '';

/* ============================================================
   POST
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        /* -------- Ajustar stock -------- */
        if ($action === 'ajustar_stock') {
            $idProducto   = (int) ($_POST['id_producto'] ?? 0);
            $cantidadNueva = (int) ($_POST['cantidad_nueva'] ?? -1);
            $motivo       = trim($_POST['motivo'] ?? 'Conteo físico');
            $detalle      = trim($_POST['detalle'] ?? '');

            if ($idProducto <= 0 || $cantidadNueva < 0) {
                throw new RuntimeException('Cantidad inválida.');
            }

            // Verificar que el producto existe en el inventario de esta sucursal
            $stmt = $pdo->prepare("
                SELECT cantidad_disponible
                FROM Inventario_Sucursal
                WHERE id_sucursal = ? AND id_producto = ?
            ");
            $stmt->execute([$idSucursalGerente, $idProducto]);
            $cantidadActual = $stmt->fetchColumn();

            if ($cantidadActual === false) {
                throw new RuntimeException('El producto no está en el inventario de tu sucursal.');
            }
            $cantidadActual = (int) $cantidadActual;

            if ($cantidadNueva === $cantidadActual) {
                throw new RuntimeException('La cantidad es la misma que la actual. No hay nada que actualizar.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE Inventario_Sucursal
                SET cantidad_disponible = ?
                WHERE id_sucursal = ? AND id_producto = ?
            ");
            $stmt->execute([$cantidadNueva, $idSucursalGerente, $idProducto]);

            $delta = $cantidadNueva - $cantidadActual;
            $motivoCompleto = $motivo . ($detalle !== '' ? ' — ' . $detalle : '');

            $stmt = $pdo->prepare("
                INSERT INTO Movimientos_Inventario
                    (id_sucursal, id_producto, tipo_movimiento, cantidad,
                     existencia_posterior, motivo_detalle, realizado_por)
                VALUES (?, ?, 'AJUSTE_MANUAL', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idSucursalGerente,
                $idProducto,
                $delta,
                $cantidadNueva,
                $motivoCompleto,
                (int) $gerente['id_usuario']
            ]);

            $pdo->commit();
            $exito = 'Stock actualizado correctamente.';

        /* -------- Agregar producto al inventario -------- */
        } elseif ($action === 'agregar_producto') {
            $idProducto     = (int) ($_POST['id_producto'] ?? 0);
            $cantidadInicial = max(0, (int) ($_POST['cantidad_inicial'] ?? 0));
            $precioVenta    = (float) ($_POST['precio_venta'] ?? -1);

            if ($idProducto <= 0 || $precioVenta < 0) {
                throw new RuntimeException('Datos inválidos.');
            }

            // Verificar que el producto existe y está activo
            $stmt = $pdo->prepare("
                SELECT p.id_categoria, c.nombre_categoria
                FROM Productos p
                INNER JOIN Categorias c ON c.id_categoria = p.id_categoria
                WHERE p.id_producto = ? AND p.estado = 'Activo'
                LIMIT 1
            ");
            $stmt->execute([$idProducto]);
            $prod = $stmt->fetch();
            if (!$prod) {
                throw new RuntimeException('Producto no encontrado o no está activo.');
            }

            // Verificar que no esté ya en esta sucursal
            $stmt = $pdo->prepare("
                SELECT 1 FROM Inventario_Sucursal
                WHERE id_sucursal = ? AND id_producto = ?
            ");
            $stmt->execute([$idSucursalGerente, $idProducto]);
            if ($stmt->fetch() !== false) {
                throw new RuntimeException('Ese producto ya está en el inventario de tu sucursal.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO Inventario_Sucursal
                    (id_sucursal, id_producto, Categoria_Producto,
                     cantidad_disponible, precio_venta)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idSucursalGerente,
                $idProducto,
                $prod['nombre_categoria'],
                $cantidadInicial,
                $precioVenta
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO Movimientos_Inventario
                    (id_sucursal, id_producto, tipo_movimiento, cantidad,
                     existencia_posterior, motivo_detalle, realizado_por)
                VALUES (?, ?, 'AJUSTE_MANUAL', ?, ?, 'Alta en inventario', ?)
            ");
            $stmt->execute([
                $idSucursalGerente,
                $idProducto,
                $cantidadInicial,
                $cantidadInicial,
                (int) $gerente['id_usuario']
            ]);

            $pdo->commit();
            $exito = 'Producto agregado al inventario.';

        /* -------- Poner stock en cero -------- */
        } elseif ($action === 'poner_en_cero') {
            $idProducto = (int) ($_POST['id_producto'] ?? 0);

            if ($idProducto <= 0) {
                throw new RuntimeException('Producto inválido.');
            }

            $stmt = $pdo->prepare("
                SELECT cantidad_disponible
                FROM Inventario_Sucursal
                WHERE id_sucursal = ? AND id_producto = ?
            ");
            $stmt->execute([$idSucursalGerente, $idProducto]);
            $cantidadActual = $stmt->fetchColumn();

            if ($cantidadActual === false) {
                throw new RuntimeException('Producto no encontrado en tu inventario.');
            }
            $cantidadActual = (int) $cantidadActual;

            if ($cantidadActual === 0) {
                throw new RuntimeException('Ese producto ya está en cero.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE Inventario_Sucursal
                SET cantidad_disponible = 0
                WHERE id_sucursal = ? AND id_producto = ?
            ");
            $stmt->execute([$idSucursalGerente, $idProducto]);

            $stmt = $pdo->prepare("
                INSERT INTO Movimientos_Inventario
                    (id_sucursal, id_producto, tipo_movimiento, cantidad,
                     existencia_posterior, motivo_detalle, realizado_por)
                VALUES (?, ?, 'AJUSTE_MANUAL', ?, 0, 'Puesto en cero por gerente', ?)
            ");
            $stmt->execute([
                $idSucursalGerente,
                $idProducto,
                -$cantidadActual,
                (int) $gerente['id_usuario']
            ]);

            $pdo->commit();
            $exito = 'Producto puesto en cero.';
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
   Datos para la vista
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.codigo, p.nombre, p.imagen,
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

$totalProductos  = count($inventario);
$totalBajo       = 0;
$totalAgotado    = 0;
$valorInventario = 0.0;
foreach ($inventario as $p) {
    $s = (int) $p['cantidad_disponible'];
    if ($s === 0) $totalAgotado++;
    elseif ($s < $UMBRAL_BAJO) $totalBajo++;
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
    <?php if (!empty($productosDisponibles)): ?>
        <button type="button" class="btn btn-primary" onclick="abrirModalAgregarProducto()">
            + Agregar producto al inventario
        </button>
    <?php endif; ?>
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
            <span class="kpi-sub">menos de <?= $UMBRAL_BAJO ?> unidades</span>
        </div>
        <div class="kpi kpi--ok">
            <span class="kpi-label">Valor del inventario</span>
            <span class="kpi-value kpi-value--ok">$<?= number_format($valorInventario, 0) ?></span>
            <span class="kpi-sub"><?= $totalProductos ?> productos</span>
        </div>
    </section>

    <div class="toolbar">
        <input type="text" id="buscarExistencia" placeholder="Buscar por nombre o código...">
        <select id="filtroStock" aria-label="Filtrar por estado de stock">
            <option value="todos">Todos los estados</option>
            <option value="normal">Stock normal (10+)</option>
            <option value="bajo">Bajo stock (1-9)</option>
            <option value="agotado">Agotados (0)</option>
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
                        <th>Precio venta</th>
                        <th>Stock</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventario as $p): ?>
                        <?php
                            $stock = (int) $p['cantidad_disponible'];
                            if ($stock === 0) {
                                $stockBadge = 'badge-danger';
                                $stockTexto = 'Agotado';
                            } elseif ($stock < 5) {
                                $stockBadge = 'badge-danger';
                                $stockTexto = $stock . ' ' . ($stock === 1 ? 'unidad' : 'unidades');
                            } elseif ($stock < $UMBRAL_BAJO) {
                                $stockBadge = 'badge-warning';
                                $stockTexto = $stock . ' unidades';
                            } else {
                                $stockBadge = 'badge-activo';
                                $stockTexto = $stock . ' unidades';
                            }
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
                            </td>
                            <td><?= htmlspecialchars($p['nombre_categoria'] ?? 'Sin categoría') ?></td>
                            <td>
                                <?php if ($precio > 0): ?>
                                    $<?= number_format($precio, 2) ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">Sin precio</span>
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
                                    >Ajustar</button>

                                    <?php if ($stock > 0): ?>
                                        <form method="POST" style="display:contents"
                                              data-confirm="Se pondrá el stock de '<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>' en 0. El producto seguirá en el inventario como agotado."
                                              data-confirm-titulo="Poner stock en cero"
                                              data-confirm-aceptar="Poner en cero"
                                              data-confirm-peligroso="1">
                                            <input type="hidden" name="action" value="poner_en_cero">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                            <input type="hidden" name="id_producto" value="<?= (int) $p['id_producto'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Poner en cero</button>
                                        </form>
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

        <form id="formAjustarStock" method="POST">
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
                    <label>Precio de venta</label>
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

<!-- Modal: Agregar producto al inventario -->
<div class="modal-overlay" id="modalAgregarProducto">
    <div class="modal-box">
        <h2>Agregar producto al inventario</h2>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom:1rem;">
            Selecciona un producto del catálogo global que aún no esté en tu sucursal.
        </p>

        <form id="formAgregarProducto" method="POST">
            <input type="hidden" name="action" value="agregar_producto">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

            <div class="form-group">
                <label>Producto</label>
                <select name="id_producto" required>
                    <option value="">Selecciona un producto</option>
                    <?php foreach ($productosDisponibles as $pd): ?>
                        <option value="<?= (int) $pd['id_producto'] ?>" data-precio="<?= htmlspecialchars((string) $pd['precio'], ENT_QUOTES) ?>">
                            <?= htmlspecialchars($pd['nombre']) ?> — <?= htmlspecialchars($pd['nombre_categoria'] ?? 'Sin categoría') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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