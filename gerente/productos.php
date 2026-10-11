<?php
require_once __DIR__ . '/guardia.php';

$carpetaImagenes = __DIR__ . '/../Imagenes';
$rutaImagenesRelativa = 'Imagenes';

/**
 * Valida y mueve una imagen subida a la carpeta de imágenes del proyecto.
 */
function guardarImagenProducto(array $archivo, string $carpetaDestino, string $rutaRelativaBase): ?string
{
    if (!isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Ocurrió un error al subir la imagen.');
    }
    if ($archivo['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen no debe superar 5MB.');
    }

    $tiposPermitidos = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $mime = mime_content_type($archivo['tmp_name']);

    if (!isset($tiposPermitidos[$mime])) {
        throw new RuntimeException('Formato de imagen no permitido. Usa PNG, JPG o WEBP.');
    }

    if (!is_dir($carpetaDestino)) {
        mkdir($carpetaDestino, 0755, true);
    }

    $nombreArchivo = uniqid('prod_', true) . '.' . $tiposPermitidos[$mime];
    $rutaDestino = $carpetaDestino . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }

    return $rutaRelativaBase . '/' . $nombreArchivo;
}

function actualizarInventarioSucursal(PDO $pdo, int $idProducto, int $idSucursal, int $cantidad, float $precio, string $categoriaNombre): void
{
    $stmt = $pdo->prepare("
    INSERT INTO Inventario_Sucursal (id_sucursal, id_producto, Categoria_Producto, cantidad_disponible, precio_venta)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    Categoria_Producto = VALUES(Categoria_Producto),
                          cantidad_disponible = VALUES(cantidad_disponible),
                          precio_venta = VALUES(precio_venta)
    ");
    $stmt->execute([$idSucursal, $idProducto, $categoriaNombre, $cantidad, $precio]);
}

/**
 * Da de alta un producto del catálogo en el inventario de la sucursal.
 */
function agregarProductoAInventario(PDO $pdo, int $idSucursal, int $idProducto, int $cantidad, float $precio, int $idUsuario): void
{
    if ($idProducto <= 0 || $precio < 0) {
        throw new RuntimeException('Datos inválidos.');
    }

    $stmt = $pdo->prepare("
    SELECT c.nombre_categoria
    FROM Productos p
    INNER JOIN Categorias c ON c.id_categoria = p.id_categoria
    WHERE p.id_producto = ? AND p.estado = 'Activo'
    LIMIT 1
    ");
    $stmt->execute([$idProducto]);
    $categoriaNombre = $stmt->fetchColumn();
    if ($categoriaNombre === false) {
        throw new RuntimeException('Producto no encontrado o no está activo.');
    }

    $stmt = $pdo->prepare("
    SELECT 1 FROM Inventario_Sucursal
    WHERE id_sucursal = ? AND id_producto = ?
    ");
    $stmt->execute([$idSucursal, $idProducto]);
    if ($stmt->fetch() !== false) {
        throw new RuntimeException('Ese producto ya está en el inventario de tu sucursal.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
    INSERT INTO Inventario_Sucursal
    (id_sucursal, id_producto, Categoria_Producto, cantidad_disponible, precio_venta)
    VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$idSucursal, $idProducto, (string) $categoriaNombre, $cantidad, $precio]);

    $stmt = $pdo->prepare("
    INSERT INTO Movimientos_Inventario
    (id_sucursal, id_producto, tipo_movimiento, cantidad,
                          existencia_posterior, motivo_detalle, realizado_por)
    VALUES (?, ?, 'AJUSTE_MANUAL', ?, ?, 'Alta en inventario', ?)
    ");
    $stmt->execute([$idSucursal, $idProducto, $cantidad, $cantidad, $idUsuario]);

    $pdo->commit();
}

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
    $rutaImagenSubida = null;
    $rutaImagenAnterior = null;

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        /* -------- Editar producto existente -------- */
        if ($action === 'editar') {
            $id = (int) ($_POST['id_producto'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio = (float) ($_POST['precio'] ?? -1);
            $idCategoria = (int) ($_POST['id_categoria'] ?? 0);
            $codigoInput = trim($_POST['codigo'] ?? '');
            $cantidadInventario = max(0, (int) ($_POST['cantidad_inventario'] ?? 0));

            if ($id <= 0) {
                throw new RuntimeException('Producto inválido.');
            }
            if ($nombre === '' || $idCategoria <= 0 || $precio < 0) {
                throw new RuntimeException('Completa nombre, categoría y un precio válido.');
            }

            $catCheck = $pdo->prepare("SELECT nombre_categoria FROM Categorias WHERE id_categoria = ?");
            $catCheck->execute([$idCategoria]);
            $categoriaNombre = $catCheck->fetchColumn();
            if ($categoriaNombre === false) {
                throw new RuntimeException('Categoría inválida.');
            }
            $categoriaNombre = (string) $categoriaNombre;

            $rutaImagenSubida = guardarImagenProducto($_FILES['imagen'] ?? [], $carpetaImagenes, $rutaImagenesRelativa);

            // IDOR: el producto debe estar en el inventario de esta sucursal
            $check = $pdo->prepare("
            SELECT 1 FROM Inventario_Sucursal
            WHERE id_sucursal = ? AND id_producto = ?
            ");
            $check->execute([$idSucursalGerente, $id]);
            if ($check->fetch() === false) {
                throw new RuntimeException('Este producto no está en el inventario de tu sucursal.');
            }

            // Validar código duplicado si lo cambia
            if ($codigoInput !== '') {
                $check = $pdo->prepare("SELECT 1 FROM Productos WHERE codigo = ? AND id_producto <> ?");
                $check->execute([$codigoInput, $id]);
                if ($check->fetch() !== false) {
                    throw new RuntimeException('Ese código ya está en uso por otro producto.');
                }
            }

            if ($rutaImagenSubida !== null) {
                $stmtImg = $pdo->prepare("SELECT imagen FROM Productos WHERE id_producto = ?");
                $stmtImg->execute([$id]);
                $rutaImagenAnterior = $stmtImg->fetchColumn() ?: null;
            }

            $pdo->beginTransaction();

            if ($codigoInput !== '' && $rutaImagenSubida !== null) {
                $stmt = $pdo->prepare("UPDATE Productos SET codigo=?, nombre=?, descripcion=?, precio=?, imagen=?, id_categoria=?, modificado_en=NOW() WHERE id_producto=?");
                $stmt->execute([$codigoInput, $nombre, $descripcion, $precio, $rutaImagenSubida, $idCategoria, $id]);
            } elseif ($codigoInput !== '') {
                $stmt = $pdo->prepare("UPDATE Productos SET codigo=?, nombre=?, descripcion=?, precio=?, id_categoria=?, modificado_en=NOW() WHERE id_producto=?");
                $stmt->execute([$codigoInput, $nombre, $descripcion, $precio, $idCategoria, $id]);
            } elseif ($rutaImagenSubida !== null) {
                $stmt = $pdo->prepare("UPDATE Productos SET nombre=?, descripcion=?, precio=?, imagen=?, id_categoria=?, modificado_en=NOW() WHERE id_producto=?");
                $stmt->execute([$nombre, $descripcion, $precio, $rutaImagenSubida, $idCategoria, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE Productos SET nombre=?, descripcion=?, precio=?, id_categoria=?, modificado_en=NOW() WHERE id_producto=?");
                $stmt->execute([$nombre, $descripcion, $precio, $idCategoria, $id]);
            }

            actualizarInventarioSucursal($pdo, $id, $idSucursalGerente, $cantidadInventario, $precio, $categoriaNombre);

            $pdo->commit();

            if ($rutaImagenSubida !== null && $rutaImagenAnterior !== null) {
                $f = __DIR__ . '/../' . $rutaImagenAnterior;
                if (is_file($f)) @unlink($f);
            }

            $_SESSION['flash'] = ['msg' => 'Producto actualizado correctamente.'];
            header('Location: productos.php');
            exit;
        }

        /* -------- Cambiar estado (activar/desactivar) -------- */
        elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_producto'] ?? 0);
            $nuevoEstado = ($_POST['nuevo_estado'] ?? '') === 'Activo' ? 'Activo' : 'Inactivo';

            if ($id <= 0) {
                throw new RuntimeException('Producto inválido.');
            }

            $check = $pdo->prepare("
            SELECT 1 FROM Inventario_Sucursal
            WHERE id_sucursal = ? AND id_producto = ?
            ");
            $check->execute([$idSucursalGerente, $id]);
            if ($check->fetch() === false) {
                throw new RuntimeException('Este producto no está en el inventario de tu sucursal.');
            }

            $stmt = $pdo->prepare("UPDATE Productos SET estado=?, modificado_en=NOW() WHERE id_producto=?");
            $stmt->execute([$nuevoEstado, $id]);

            $_SESSION['flash'] = ['msg' => 'Estado del producto actualizado.'];
            header('Location: productos.php');
            exit;
        }

        /* -------- Agregar producto del catálogo a mi inventario -------- */
        elseif ($action === 'agregar_inventario') {
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
        if ($rutaImagenSubida !== null) {
            $f = __DIR__ . '/../' . $rutaImagenSubida;
            if (is_file($f)) @unlink($f);
        }
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($rutaImagenSubida !== null) {
            $f = __DIR__ . '/../' . $rutaImagenSubida;
            if (is_file($f)) @unlink($f);
        }
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
<th>Precio</th>
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
} elseif ($stock === 0) {
    $stockBadge = 'badge-danger';
    $stockTexto = 'Agotado';
} elseif ($stock < 5) {
    $stockBadge = 'badge-danger';
    $stockTexto = $stock . ' ' . ($stock === 1 ? 'unidad' : 'unidades');
} elseif ($stock < 10) {
    $stockBadge = 'badge-warning';
    $stockTexto = $stock . ' unidades';
} else {
    $stockBadge = 'badge-activo';
    $stockTexto = $stock . ' unidades';
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
<button type="button" class="btn btn-ghost btn-sm"
onclick="abrirModalEditar(this)"
data-id="<?= $p['id_producto'] ?>"
data-codigo="<?= htmlspecialchars($p['codigo'], ENT_QUOTES) ?>"
data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
data-descripcion="<?= htmlspecialchars($p['descripcion'] ?? '', ENT_QUOTES) ?>"
data-precio="<?= htmlspecialchars((string) $p['precio'], ENT_QUOTES) ?>"
data-categoria="<?= (int) $p['id_categoria'] ?>"
data-cantidad="<?= (int) $p['stock_sucursal'] ?>"
data-imagen="<?= htmlspecialchars($p['imagen'] ?? '', ENT_QUOTES) ?>"
>Editar</button>

<form method="POST" style="display:inline"
data-confirm="<?= $p['estado'] === 'Activo'
? '¿Desactivar este producto? No aparecerá disponible para venta.'
: '¿Activar este producto?' ?>">
<input type="hidden" name="action" value="cambiar_estado">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
<input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>">
<?php if ($p['estado'] === 'Activo'): ?>
<input type="hidden" name="nuevo_estado" value="Inactivo">
<button type="submit" class="btn btn-danger btn-sm">Desactivar</button>
<?php else: ?>
<input type="hidden" name="nuevo_estado" value="Activo">
<button type="submit" class="btn btn-success btn-sm">Activar</button>
<?php endif; ?>
</form>
<?php else: ?>
<button type="button" class="btn btn-success btn-sm"
onclick="abrirModalAgregarInventario(this)"
data-id="<?= (int) $p['id_producto'] ?>"
data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
data-codigo="<?= htmlspecialchars($p['codigo'], ENT_QUOTES) ?>"
data-categoria="<?= htmlspecialchars($p['nombre_categoria'] ?? 'Sin categoría', ENT_QUOTES) ?>"
data-precio="<?= htmlspecialchars((string) $p['precio'], ENT_QUOTES) ?>"
>+ Agregar a inventario</button>
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
Modal: Editar producto existente
============================================================ -->
<div class="modal-overlay" id="modalProducto">
<div class="modal-box">
<h2 id="tituloModalProducto">Editar producto</h2>
<form id="formProducto" method="POST" action="productos.php" enctype="multipart/form-data">
<input type="hidden" name="action" value="editar">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
<input type="hidden" name="id_producto" value="">

<div class="form-group">
<label>Nombre del producto</label>
<input type="text" name="nombre" placeholder="Ej. Coca-Cola lata 355 ml" required>
</div>

<div class="form-row">
<div class="form-group">
<label>Precio</label>
<input type="number" name="precio" min="0" step="0.01" placeholder="0.00" required>
</div>
<div class="form-group">
<label>Cantidad en inventario</label>
<input type="number" name="cantidad_inventario" min="0" step="1" value="0">
</div>
</div>

<div class="form-group">
<label>Categoría</label>
<select name="id_categoria" required>
<option value="">Selecciona una categoría</option>
<?php foreach ($categorias as $c): ?>
<option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre_categoria']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="form-group">
<label>Código de barras</label>
<input type="text" name="codigo" placeholder="Código del producto">
</div>

<div class="form-group">
<label>Descripción (opcional)</label>
<textarea name="descripcion" placeholder="Detalles del producto"></textarea>
</div>

<div class="form-group">
<label>Foto del producto</label>
<input type="file" id="inputImagen" name="imagen" accept="image/png,image/jpeg,image/webp">
<div class="imagen-preview" id="previewImagen"></div>
</div>

<div class="modal-actions">
<button type="button" class="btn btn-ghost" onclick="cerrarModal('modalProducto')">Cancelar</button>
<button type="submit" class="btn btn-primary">Guardar cambios</button>
</div>
</form>
</div>
</div>


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
<label>Precio de venta</label>
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
