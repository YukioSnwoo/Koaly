<?php
/**
 * Ficha de un producto del inventario de la sucursal: datos, ajustes de stock,
 * recepción de mercancía, precio de venta y últimos movimientos.
 */
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/productos.php';

$idProducto = (int) ($_GET['id'] ?? $_POST['id_producto'] ?? 0);

$errores = [];
$exito   = '';
if (!empty($_SESSION['flash'])) {
    $exito = $_SESSION['flash']['msg'] ?? '';
    unset($_SESSION['flash']);
}

/* ============================================================
   POST: ajustar stock, recibir mercancía, cambiar precio
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

        /* -------- Recibir mercancía (entrada de proveedor) -------- */
        } elseif ($action === 'recibir_mercancia') {
            $recibida  = (int) ($_POST['cantidad_recibida'] ?? 0);
            $proveedor = trim($_POST['proveedor'] ?? '');
            $folio     = trim($_POST['folio'] ?? '');

            if ($recibida <= 0) {
                throw new RuntimeException(t('error.recibida_invalida'));
            }
            if (mb_strlen($proveedor) > 100 || mb_strlen($folio) > 50) {
                throw new RuntimeException(t('error.proveedor_largo'));
            }

            $pdo->beginTransaction();
            $cantidadActual = leerStockParaActualizar($pdo, $idSucursalGerente, $idProducto);
            if ($cantidadActual === null) {
                throw new RuntimeException(t('error.no_en_inventario'));
            }
            $cantidadNueva = $cantidadActual + $recibida;

            $stmt = $pdo->prepare("UPDATE Inventario_Sucursal SET cantidad_disponible = ? WHERE id_sucursal = ? AND id_producto = ?");
            $stmt->execute([$cantidadNueva, $idSucursalGerente, $idProducto]);

            $partes = array_filter([
                $proveedor !== '' ? tEs('kardex.proveedor', ['p' => $proveedor]) : null,
                $folio !== '' ? tEs('kardex.folio', ['f' => $folio]) : null,
            ]);
            registrarMovimiento(
                $pdo, $idSucursalGerente, $idProducto, 'COMPRA_PROVEEDOR', $recibida, $cantidadNueva,
                tEs('kardex.recepcion') . ($partes ? ' — ' . implode(' · ', $partes) : ''),
                $idUsuario
            );

            $pdo->commit();
            $mensajeExito = t('prod.recepcion_ok', ['n' => numero($recibida), 'total' => numero($cantidadNueva)]);

        /* -------- Precio de venta de la sucursal -------- */
        } elseif ($action === 'cambiar_precio') {
            $precioVenta = round((float) ($_POST['precio_venta'] ?? -1), 2);
            if ($precioVenta < 0) {
                throw new RuntimeException(t('error.precio_invalido'));
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT precio_venta, cantidad_disponible FROM Inventario_Sucursal
                WHERE id_sucursal = ? AND id_producto = ?
                FOR UPDATE
            ");
            $stmt->execute([$idSucursalGerente, $idProducto]);
            $fila = $stmt->fetch();
            if ($fila === false) {
                throw new RuntimeException(t('error.no_en_inventario'));
            }
            if (round((float) $fila['precio_venta'], 2) === $precioVenta) {
                throw new RuntimeException(t('error.mismo_precio'));
            }

            $stmt = $pdo->prepare("UPDATE Inventario_Sucursal SET precio_venta = ? WHERE id_sucursal = ? AND id_producto = ?");
            $stmt->execute([$precioVenta, $idSucursalGerente, $idProducto]);

            // Rastro en el kardex con cantidad 0 (no mueve stock)
            registrarMovimiento(
                $pdo, $idSucursalGerente, $idProducto, 'AJUSTE_MANUAL', 0, (int) $fila['cantidad_disponible'],
                sprintf('%s $%s → $%s', PREFIJO_CAMBIO_PRECIO,
                    number_format((float) $fila['precio_venta'], 2), number_format($precioVenta, 2)),
                $idUsuario
            );

            $pdo->commit();
            $mensajeExito = t('prod.precio_ok', ['precio' => dinero($precioVenta)]);
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
           i.cantidad_disponible, i.precio_venta
    FROM Inventario_Sucursal i
    INNER JOIN Productos p ON p.id_producto = i.id_producto
    LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
    WHERE i.id_sucursal = ? AND i.id_producto = ?
");
$stmt->execute([$idSucursalGerente, $idProducto]);
$producto = $stmt->fetch();

if (!$producto) {
    $_SESSION['flash'] = ['msg' => t('error.no_en_inventario'), 'tipo' => 'error'];
    header('Location: existencias.php');
    exit;
}

$stock  = (int) $producto['cantidad_disponible'];
$precio = (float) $producto['precio_venta'];
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
$showBackLink = false;
require __DIR__ . '/header.php';
?>

<nav class="migas" aria-label="<?= e(t('comun.ubicacion')) ?>">
    <a href="index.php"><?= e(t('inicio.titulo')) ?></a>
    <span aria-hidden="true">›</span>
    <a href="existencias.php"><?= e(t('exist.titulo')) ?></a>
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
                <dt><?= e(t('prod.precio_catalogo')) ?></dt>
                <dd><?= dinero($producto['precio_catalogo']) ?></dd>
            </div>
            <div>
                <dt><?= e(t('prod.valor_stock')) ?></dt>
                <dd><?= dinero($stock * $precio) ?></dd>
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

<div class="ficha-acciones">
    <!-- Ajustar stock -->
    <form class="ficha-accion" method="POST" action="producto.php?id=<?= (int) $idProducto ?>" id="formAjuste">
        <h2><?= e(t('prod.ajustar')) ?></h2>
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
        <button type="submit" class="btn btn-primary"><?= e(t('prod.guardar_ajuste')) ?></button>
    </form>

    <!-- Recibir mercancía -->
    <form class="ficha-accion" method="POST" action="producto.php?id=<?= (int) $idProducto ?>"
          onsubmit="this.querySelector('[type=submit]').disabled = true;">
        <h2><?= e(t('prod.recibir')) ?></h2>
        <p class="ficha-accion-ayuda"><?= e(t('prod.recibir_ayuda')) ?></p>
        <input type="hidden" name="action" value="recibir_mercancia">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="id_producto" value="<?= (int) $idProducto ?>">

        <div class="form-group">
            <label for="cantidadRecibida"><?= e(t('prod.unidades_recibidas')) ?></label>
            <input type="number" id="cantidadRecibida" name="cantidad_recibida" min="1" step="1" required>
        </div>
        <div class="form-group">
            <label for="proveedor"><?= e(t('prod.proveedor')) ?></label>
            <input type="text" id="proveedor" name="proveedor" maxlength="100">
        </div>
        <div class="form-group">
            <label for="folio"><?= e(t('prod.folio')) ?></label>
            <input type="text" id="folio" name="folio" maxlength="50">
        </div>
        <button type="submit" class="btn btn-primary"><?= e(t('prod.registrar_recepcion')) ?></button>
    </form>

    <!-- Precio de venta -->
    <form class="ficha-accion" method="POST" action="producto.php?id=<?= (int) $idProducto ?>">
        <h2><?= e(t('prod.precio_venta')) ?></h2>
        <p class="ficha-accion-ayuda"><?= e(t('prod.precio_ayuda')) ?></p>
        <input type="hidden" name="action" value="cambiar_precio">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="id_producto" value="<?= (int) $idProducto ?>">

        <div class="form-group">
            <label for="precioVenta"><?= e(t('comun.precio_venta_iva')) ?></label>
            <input type="number" id="precioVenta" name="precio_venta" min="0" step="0.01"
                   value="<?= $precio > 0 ? number_format($precio, 2, '.', '') : '' ?>" required>
        </div>
        <p class="ficha-accion-ayuda"><?= e(t('prod.precio_catalogo_ref', ['precio' => dinero($producto['precio_catalogo'])])) ?></p>
        <button type="submit" class="btn btn-primary"><?= e(t('prod.guardar_precio')) ?></button>
    </form>
</div>

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
