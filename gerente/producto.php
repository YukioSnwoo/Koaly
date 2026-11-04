<?php
/**
 * Ficha de un producto del inventario de la sucursal: datos, ajustes de stock,
 * precio de venta y costo (ganancia por pieza) y últimos movimientos.
 */
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/../admin/solicitudes_comun.php';

$idProducto = (int) ($_GET['id'] ?? $_POST['id_producto'] ?? 0);

// La columna del costo llega con sql/015_costo_productos.sql; sin ella la
// ficha funciona igual y avisa qué falta
$hayCosto = $pdo->query("SHOW COLUMNS FROM Inventario_Sucursal LIKE 'costo'")->fetch() !== false;

$errores = [];
$exito   = '';
if (!empty($_SESSION['flash'])) {
    $exito = $_SESSION['flash']['msg'] ?? '';
    unset($_SESSION['flash']);
}

/* ============================================================
   POST: ajustar stock, cambiar precio y costo, solicitar eliminar
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mensajeExito = '';
    $idUsuario = (int) $gerente['id_usuario'];

    try {
        if (!csrfValido()) {
            throw new RuntimeException(t('error.formulario_expirado'));
        }
        if ($idProducto <= 0) {
            throw new RuntimeException(t('error.producto_invalido'));
        }

        /* -------- Ajustar stock (conteo, merma, robo…) -------- */
        if ($action === 'ajustar_stock') {
            $cantidadNueva = (int) ($_POST['cantidad_nueva'] ?? -1);
            $cantidadVista = (int) ($_POST['cantidad_actual'] ?? -1);
            $motivo        = (string) ($_POST['motivo'] ?? '');
            $detalle       = trim($_POST['detalle'] ?? '');

            if ($cantidadNueva < 0) {
                throw new RuntimeException(t('error.cantidad_invalida'));
            }
            if (!isset(MOTIVOS_AJUSTE[$motivo])) {
                throw new RuntimeException(t('error.motivo_invalido'));
            }

            // Lectura con bloqueo: una venta simultánea espera al commit
            $pdo->beginTransaction();
            $cantidadActual = leerStockParaActualizar($pdo, $idSucursalGerente, $idProducto);
            if ($cantidadActual === null) {
                throw new RuntimeException(t('error.no_en_inventario'));
            }
            if ($cantidadVista >= 0 && $cantidadVista !== $cantidadActual) {
                throw new RuntimeException(t('error.stock_cambio', ['antes' => $cantidadVista, 'ahora' => $cantidadActual]));
            }
            if ($cantidadNueva === $cantidadActual) {
                throw new RuntimeException(t('error.misma_cantidad'));
            }

            $stmt = $pdo->prepare("UPDATE Inventario_Sucursal SET cantidad_disponible = ? WHERE id_sucursal = ? AND id_producto = ?");
            $stmt->execute([$cantidadNueva, $idSucursalGerente, $idProducto]);

            registrarMovimiento(
                $pdo, $idSucursalGerente, $idProducto, MOTIVOS_AJUSTE[$motivo],
                $cantidadNueva - $cantidadActual, $cantidadNueva,
                tEs('motivo.' . $motivo) . ($detalle !== '' ? ' — ' . $detalle : ''),
                $idUsuario
            );

            $pdo->commit();
            $mensajeExito = t('prod.ajuste_ok', ['n' => numero($cantidadNueva)]);

        /* -------- Precio de venta y costo de la sucursal -------- */
        } elseif ($action === 'cambiar_precio') {
            $precioVenta = round((float) ($_POST['precio_venta'] ?? -1), 2);
            if ($precioVenta < 0) {
                throw new RuntimeException(t('error.precio_invalido'));
            }
            // Costo vacío = desconocido (NULL), no cero
            $costoTexto = trim((string) ($_POST['costo'] ?? ''));
            $costo = $costoTexto === '' ? null : round((float) $costoTexto, 2);
            if ($costo !== null && $costo < 0) {
                throw new RuntimeException(t('error.costo_invalido'));
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT precio_venta, cantidad_disponible" . ($hayCosto ? ', costo' : '') . " FROM Inventario_Sucursal
                WHERE id_sucursal = ? AND id_producto = ?
                FOR UPDATE
            ");
            $stmt->execute([$idSucursalGerente, $idProducto]);
            $fila = $stmt->fetch();
            if ($fila === false) {
                throw new RuntimeException(t('error.no_en_inventario'));
            }
            $precioAntes = round((float) $fila['precio_venta'], 2);
            $costoAntes  = $hayCosto && $fila['costo'] !== null ? round((float) $fila['costo'], 2) : null;
            $cambiaPrecio = $precioAntes !== $precioVenta;
            $cambiaCosto  = $hayCosto && $costoAntes !== $costo;
            if (!$cambiaPrecio && !$cambiaCosto) {
                throw new RuntimeException(t('error.mismo_precio'));
            }

            if ($hayCosto) {
                $stmt = $pdo->prepare("UPDATE Inventario_Sucursal SET precio_venta = ?, costo = ? WHERE id_sucursal = ? AND id_producto = ?");
                $stmt->execute([$precioVenta, $costo, $idSucursalGerente, $idProducto]);
            } else {
                $stmt = $pdo->prepare("UPDATE Inventario_Sucursal SET precio_venta = ? WHERE id_sucursal = ? AND id_producto = ?");
                $stmt->execute([$precioVenta, $idSucursalGerente, $idProducto]);
            }

            // Rastro en el kardex con cantidad 0 (no mueve stock); solo lo que cambió
            $monto = fn (?float $m): string => $m === null ? tEs('kardex.sin_costo') : '$' . number_format($m, 2);
            $cambios = array_filter([
                $cambiaPrecio ? $monto($precioAntes) . ' → ' . $monto($precioVenta) : null,
                $cambiaCosto ? tEs('kardex.costo', ['antes' => $monto($costoAntes), 'ahora' => $monto($costo)]) : null,
            ]);
            registrarMovimiento(
                $pdo, $idSucursalGerente, $idProducto, 'AJUSTE_MANUAL', 0, (int) $fila['cantidad_disponible'],
                PREFIJO_CAMBIO_PRECIO . ' ' . implode(' · ', $cambios),
                $idUsuario
            );

            $pdo->commit();
            $mensajeExito = t('prod.precio_ok', ['precio' => dinero($precioVenta)]);

        /* -------- Solicitar al admin eliminar el producto del catálogo -------- */
        } elseif ($action === 'solicitar_eliminar') {
            $justificacion = trim($_POST['justificacion'] ?? '');
            if ($justificacion === '') {
                throw new RuntimeException(t('error.justificacion'));
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT p.nombre, p.id_categoria, p.estado
                FROM Inventario_Sucursal i
                INNER JOIN Productos p ON p.id_producto = i.id_producto
                WHERE i.id_sucursal = ? AND i.id_producto = ?
                FOR UPDATE
            ");
            // El bloqueo de esta fila (que sí existe) serializa envíos simultáneos
            // de la misma sucursal y producto; la comprobación de abajo no bastaría sola.
            $stmt->execute([$idSucursalGerente, $idProducto]);
            $fila = $stmt->fetch();
            if ($fila === false) {
                throw new RuntimeException(t('error.no_en_inventario'));
            }
            if ($fila['estado'] === 'Descontinuado') {
                throw new RuntimeException(t('cat.eliminar_ya_descontinuado'));
            }

            $stmt = $pdo->prepare("
                SELECT 1 FROM Solicitudes_Productos
                WHERE id_sucursal = ? AND id_producto = ? AND tipo = 'Eliminar' AND estado = 'Pendiente'
                FOR UPDATE
            ");
            $stmt->execute([$idSucursalGerente, $idProducto]);
            if ($stmt->fetch() !== false) {
                throw new RuntimeException(t('cat.eliminar_pendiente'));
            }

            $stmt = $pdo->prepare("
                INSERT INTO Solicitudes_Productos
                    (id_sucursal, id_usuario, tipo, id_producto, nombre_producto, id_categoria,
                     justificacion, estado, creado_en)
                VALUES (?, ?, 'Eliminar', ?, ?, ?, ?, 'Pendiente', NOW())
            ");
            $stmt->execute([
                $idSucursalGerente, $idUsuario, $idProducto,
                $fila['nombre'], $fila['id_categoria'], $justificacion,
            ]);
            // Primer paso del historial, igual que las solicitudes de productos.php
            registrarHistorialSolicitud($pdo, (int) $pdo->lastInsertId(), $idUsuario, 'Gerente', 'Enviada', [], $justificacion);

            $pdo->commit();
            $mensajeExito = t('cat.eliminar_enviada');
        }

        if ($mensajeExito !== '') {
            $_SESSION['flash'] = ['msg' => $mensajeExito];
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
   Datos del producto (solo si está en el inventario de la sucursal)
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.imagen, p.precio AS precio_catalogo,
           p.estado AS estado_producto, c.nombre_categoria,
           i.cantidad_disponible, i.precio_venta, " . ($hayCosto ? 'i.costo' : 'NULL') . " AS costo
    FROM Inventario_Sucursal i
    INNER JOIN Productos p ON p.id_producto = i.id_producto
    LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
    WHERE i.id_sucursal = ? AND i.id_producto = ?
");
$stmt->execute([$idSucursalGerente, $idProducto]);
$producto = $stmt->fetch();

if (!$producto) {
    $_SESSION['flash'] = ['msg' => t('error.no_en_inventario'), 'tipo' => 'error'];
    header('Location: productos.php');
    exit;
}

$stock  = (int) $producto['cantidad_disponible'];
$precio = (float) $producto['precio_venta'];
$costo  = $producto['costo'] !== null ? (float) $producto['costo'] : null;
// Ganancia por pieza y margen sobre el precio (como lo calculan los puntos de venta)
$ganancia = $costo !== null && $precio > 0 ? $precio - $costo : null;
$margen   = $ganancia !== null ? $ganancia / $precio * 100 : null;
[$stockBadge, $stockTexto] = badgeStock($stock);

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(dv.cantidad), 0) AS unidades,
           COALESCE(SUM(dv.cantidad * dv.precio_unitario), 0) AS monto
    FROM Detalle_Venta dv
    INNER JOIN Ventas v ON v.id_venta = dv.id_venta
    WHERE v.id_sucursal = ? AND dv.id_producto = ? AND v.fecha_hora >= CURDATE() - INTERVAL 29 DAY
");
$stmt->execute([$idSucursalGerente, $idProducto]);
$ventas30 = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT 1 FROM Solicitudes_Productos
    WHERE id_sucursal = ? AND id_producto = ? AND tipo = 'Eliminar' AND estado = 'Pendiente'
");
$stmt->execute([$idSucursalGerente, $idProducto]);
$eliminacionPendiente = $stmt->fetch() !== false;

$stmt = $pdo->prepare("
    SELECT MAX(v.fecha_hora) FROM Detalle_Venta dv
    INNER JOIN Ventas v ON v.id_venta = dv.id_venta
    WHERE v.id_sucursal = ? AND dv.id_producto = ?
");
$stmt->execute([$idSucursalGerente, $idProducto]);
$ultimaVenta = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT m.fecha_hora, m.tipo_movimiento, m.cantidad, m.existencia_posterior, m.motivo_detalle,
           u.nombre AS usuario
    FROM Movimientos_Inventario m
    LEFT JOIN Usuarios u ON u.id_usuario = m.realizado_por
    WHERE m.id_sucursal = ? AND m.id_producto = ?
    ORDER BY m.fecha_hora DESC, m.id_movimiento DESC
    LIMIT 10
");
$stmt->execute([$idSucursalGerente, $idProducto]);
$movimientos = $stmt->fetchAll();

$pageTitle    = $producto['nombre'];
require __DIR__ . '/header.php';
?>

<nav class="migas" aria-label="<?= e(t('comun.ubicacion')) ?>">
    <a href="index.php"><?= e(t('inicio.titulo')) ?></a>
    <span aria-hidden="true">›</span>
    <a href="productos.php"><?= e(t('prods.titulo')) ?></a>
    <span aria-hidden="true">›</span>
    <span aria-current="page"><?= e($producto['nombre']) ?></span>
</nav>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= e($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="ficha">
    <div class="ficha-imagen">
        <?php if (!empty($producto['imagen'])): ?>
            <img src="../<?= e($producto['imagen']) ?>" alt="<?= e($producto['nombre']) ?>">
        <?php else: ?>
            <span><?= e(t('comun.sin_imagen')) ?></span>
        <?php endif; ?>
    </div>
    <div class="ficha-info">
        <h1><?= e($producto['nombre']) ?></h1>
        <p class="ficha-meta">
            <?= e($producto['codigo']) ?> · <?= e(tValor('categoria', $producto['nombre_categoria'], t('comun.sin_categoria'))) ?>
            <?php if ($producto['estado_producto'] !== 'Activo'): ?>
                <span class="badge badge-danger" title="<?= e(t('exist.no_se_vende_ayuda')) ?>"><?= e(t('exist.no_se_vende')) ?></span>
            <?php endif; ?>
        </p>
        <?php if (!empty($producto['descripcion'])): ?>
            <p class="ficha-descripcion"><?= e($producto['descripcion']) ?></p>
        <?php endif; ?>

        <dl class="ficha-datos">
            <div>
                <dt><?= e(t('prod.stock')) ?></dt>
                <dd><span class="badge <?= $stockBadge ?>"><?= e($stockTexto) ?></span></dd>
            </div>
            <div>
                <dt><?= e(t('prod.precio_venta')) ?></dt>
                <dd><?= $precio > 0 ? dinero($precio) : e(t('exist.sin_precio')) ?></dd>
            </div>
            <div>
                <dt><?= e(t('prod.costo')) ?></dt>
                <dd><?= $costo !== null ? dinero($costo) : '<span class="texto-tenue">' . e(t('prod.sin_costo')) . '</span>' ?></dd>
            </div>
            <div>
                <dt><?= e(t('prod.ganancia_pieza')) ?></dt>
                <dd class="<?= $ganancia !== null && $ganancia < 0 ? 'texto-peligro' : '' ?>">
                    <?= $ganancia !== null ? dinero($ganancia) . ' <span class="ficha-dato-sub">' . e(t('prod.margen', ['p' => numero((int) round($margen))])) . '</span>' : '—' ?>
                </dd>
            </div>
            <div>
                <dt><?= e(t('prod.vendidas_30')) ?></dt>
                <dd><?= e(tn('stock.unidades', (int) $ventas30['unidades'])) ?> · <?= dinero($ventas30['monto']) ?></dd>
            </div>
            <div>
                <dt><?= e(t('prod.ultima_venta')) ?></dt>
                <dd><?= $ultimaVenta ? e(fecha($ultimaVenta)) : e(t('prod.sin_ventas')) ?></dd>
            </div>
        </dl>
    </div>
</section>

<section class="panel ajustes">
    <div class="panel-encabezado">
        <h2 class="section-title"><?= e(t('prod.ajustes')) ?></h2>
    </div>

    <div class="ajustes-cuerpo">
        <!-- Stock: lo que hay de verdad en tienda -->
        <form class="ajustes-bloque" method="POST" action="producto.php?id=<?= (int) $idProducto ?>" id="formAjuste">
            <h3><?= e(t('prod.ajustar')) ?></h3>
            <p class="ficha-accion-ayuda"><?= e(t('prod.ajustar_ayuda')) ?></p>
            <input type="hidden" name="action" value="ajustar_stock">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_producto" value="<?= (int) $idProducto ?>">
            <input type="hidden" name="cantidad_actual" value="<?= $stock ?>">

            <div class="form-group">
                <label for="cantidadNueva"><?= e(t('prod.cantidad_nueva')) ?></label>
                <div class="campo-con-boton">
                    <input type="number" id="cantidadNueva" name="cantidad_nueva" min="0" step="1" value="<?= $stock ?>" required>
                    <?php if ($stock > 0): ?>
                        <button type="button" class="btn btn-ghost btn-sm"
                                onclick="document.getElementById('cantidadNueva').value = 0; document.getElementById('motivoAjuste').focus();">
                            <?= e(t('prod.poner_cero')) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ajustes-fila">
                <div class="form-group">
                    <label for="motivoAjuste"><?= e(t('prod.motivo')) ?></label>
                    <select id="motivoAjuste" name="motivo" required>
                        <?php foreach (array_keys(MOTIVOS_AJUSTE) as $codigo): ?>
                            <option value="<?= $codigo ?>"><?= e(t('motivo.' . $codigo)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="detalleAjuste"><?= e(t('prod.detalle')) ?></label>
                    <input type="text" id="detalleAjuste" name="detalle" maxlength="200" placeholder="<?= e(t('prod.detalle_ayuda')) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= e(t('prod.guardar_ajuste')) ?></button>
        </form>

        <!-- Precio y costo: cuánto cuesta y a cuánto se vende -->
        <form class="ajustes-bloque" method="POST" action="producto.php?id=<?= (int) $idProducto ?>" id="formPrecio">
            <h3><?= e(t('prod.precio_costo')) ?></h3>
            <p class="ficha-accion-ayuda"><?= e(t('prod.precio_ayuda')) ?></p>
            <input type="hidden" name="action" value="cambiar_precio">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_producto" value="<?= (int) $idProducto ?>">

            <div class="ajustes-fila">
                <div class="form-group">
                    <label for="costoProducto"><?= e(t('prod.costo_campo')) ?></label>
                    <input type="number" id="costoProducto" name="costo" min="0" step="0.01" inputmode="decimal"
                           value="<?= $costo !== null ? number_format($costo, 2, '.', '') : '' ?>"
                           <?= $hayCosto ? '' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label for="precioVenta"><?= e(t('prod.precio_campo')) ?></label>
                    <input type="number" id="precioVenta" name="precio_venta" min="0" step="0.01" inputmode="decimal"
                           value="<?= $precio > 0 ? number_format($precio, 2, '.', '') : '' ?>" required>
                </div>
            </div>
            <?php if ($hayCosto): ?>
                <p class="ajustes-ganancia" id="gananciaVista" aria-live="polite"
                   data-texto="<?= e(t('prod.ganancia_vista')) ?>" data-perdida="<?= e(t('prod.perdida_vista')) ?>"
                   data-vacio="<?= e(t('prod.ganancia_vacia')) ?>"></p>
            <?php else: ?>
                <p class="ficha-accion-ayuda"><span class="badge badge-warning"><?= e(t('prod.costo_pendiente')) ?></span></p>
            <?php endif; ?>
            <p class="ficha-accion-ayuda"><?= e(t('prod.precio_catalogo_ref', ['precio' => dinero($producto['precio_catalogo'])])) ?></p>
            <button type="submit" class="btn btn-primary"><?= e(t('prod.guardar_precio')) ?></button>
        </form>
    </div>

    <!-- Solicitar eliminar del catálogo (lo resuelve el admin); se usa poco -->
    <details class="ajustes-mas"<?= $eliminacionPendiente ? ' open' : '' ?>>
        <summary><?= e(t('cat.eliminar_titulo')) ?></summary>
        <form method="POST" action="producto.php?id=<?= (int) $idProducto ?>"
              onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <p class="ficha-accion-ayuda"><?= e(t('cat.eliminar_ayuda')) ?></p>
            <input type="hidden" name="action" value="solicitar_eliminar">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_producto" value="<?= (int) $idProducto ?>">

            <?php if ($producto['estado_producto'] === 'Descontinuado'): ?>
                <p class="ficha-accion-ayuda"><?= e(t('cat.eliminar_ya_descontinuado')) ?></p>
            <?php elseif ($eliminacionPendiente): ?>
                <p class="ficha-accion-ayuda"><span class="badge badge-warning"><?= e(t('cat.en_revision')) ?></span> <?= e(t('cat.eliminar_pendiente')) ?></p>
            <?php else: ?>
                <div class="form-group">
                    <label for="justificacionEliminar"><?= e(t('cat.campo_justificacion')) ?> *</label>
                    <textarea id="justificacionEliminar" name="justificacion" required
                              placeholder="<?= e(t('cat.eliminar_justificacion_ej')) ?>"></textarea>
                </div>
                <button type="submit" class="btn btn-danger"><?= e(t('cat.enviar_eliminar')) ?></button>
            <?php endif; ?>
        </form>
    </details>
</section>

<?php if ($hayCosto): ?>
<script>
// Ganancia por pieza mientras se escribe el costo o el precio
(() => {
    const costo = document.getElementById('costoProducto');
    const precio = document.getElementById('precioVenta');
    const vista = document.getElementById('gananciaVista');
    const dinero = new Intl.NumberFormat(document.documentElement.lang === 'en' ? 'en-US' : 'es-MX', { style: 'currency', currency: 'MXN' });
    const pintar = () => {
        const c = parseFloat(costo.value), p = parseFloat(precio.value);
        if (!(c >= 0) || !(p > 0)) { vista.textContent = vista.dataset.vacio; vista.className = 'ajustes-ganancia'; return; }
        const g = p - c;
        const texto = g < 0 ? vista.dataset.perdida : vista.dataset.texto;
        vista.textContent = texto.replace('{g}', dinero.format(Math.abs(g))).replace('{p}', Math.round(g / p * 100));
        vista.className = 'ajustes-ganancia' + (g < 0 ? ' es-perdida' : '');
    };
    costo.addEventListener('input', pintar);
    precio.addEventListener('input', pintar);
    pintar();
})();
</script>
<?php endif; ?>

<section class="panel">
    <div class="panel-encabezado">
        <h2 class="section-title"><?= e(t('prod.ultimos_movimientos')) ?></h2>
        <a class="btn btn-ghost btn-sm" href="movimientos.php?producto=<?= (int) $idProducto ?>"><?= e(t('prod.ver_historial')) ?></a>
    </div>
    <?php if (empty($movimientos)): ?>
        <div class="empty-state"><?= e(t('mov.vacio')) ?></div>
    <?php else: ?>
        <div class="tabla-scroll">
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th><?= e(t('comun.fecha')) ?></th>
                        <th><?= e(t('mov.tipo')) ?></th>
                        <th class="num"><?= e(t('mov.cantidad')) ?></th>
                        <th class="num"><?= e(t('mov.quedaron')) ?></th>
                        <th><?= e(t('mov.detalle')) ?></th>
                        <th><?= e(t('mov.realizo')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $m): ?>
                        <?php $cant = (int) $m['cantidad']; $esPrecio = esCambioPrecio($m); ?>
                        <tr>
                            <td><?= e(fecha($m['fecha_hora'])) ?></td>
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
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/footer.php'; ?>
