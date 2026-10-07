<?php
require_once __DIR__ . '/guardia.php';

$carpetaImagenes = __DIR__ . '/../Imagenes';
$rutaImagenesRelativa = 'Imagenes';

/**
 * Valida y mueve una imagen subida a la carpeta de imágenes del proyecto.
 * Devuelve la ruta relativa (desde la raíz del sitio) o null si no se subió nada.
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

function generarCodigoUnico(PDO $pdo): string
{
    do {
        $codigo = '750' . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $check = $pdo->prepare("SELECT 1 FROM Productos WHERE codigo = ?");
        $check->execute([$codigo]);
    } while ($check->fetch() !== false);

    return $codigo;
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
 * Da de alta un producto del catálogo en el inventario de la sucursal
 * y registra el movimiento. Pensada para el botón "+ Agregar a inventario"
 * de esta página (independiente de existencias.php).
 * Lanza RuntimeException con un mensaje legible si algo no cuadra.
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
   PRG: recuperar mensaje flash tras un POST exitoso
   ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $exito = $flash['msg'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rutaImagenSubida = null;   // ruta de la imagen que movimos a disco EN ESTE request
    $rutaImagenAnterior = null; // ruta de la imagen previa (solo en editar, para limpiar)

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        if ($action === 'crear' || $action === 'editar') {
            $id = (int) ($_POST['id_producto'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio = (float) ($_POST['precio'] ?? -1);
            $idCategoria = (int) ($_POST['id_categoria'] ?? 0);
            $codigoInput = trim($_POST['codigo'] ?? '');
            $cantidadInventario = max(0, (int) ($_POST['cantidad_inventario'] ?? 0));

            if ($nombre === '' || $idCategoria <= 0 || $precio < 0) {
                throw new RuntimeException('Completa nombre, categoría y un precio válido.');
            }

            // Validar categoría contra BD (evita FK error críptico)
            $catCheck = $pdo->prepare("SELECT nombre_categoria FROM Categorias WHERE id_categoria = ?");
            $catCheck->execute([$idCategoria]);
            $categoriaNombre = $catCheck->fetchColumn();
            if ($categoriaNombre === false) {
                throw new RuntimeException('Categoría inválida.');
            }
            $categoriaNombre = (string) $categoriaNombre;

            // Subir imagen nueva (fuera de la transacción — es I/O de disco)
            $rutaImagenSubida = guardarImagenProducto($_FILES['imagen'] ?? [], $carpetaImagenes, $rutaImagenesRelativa);

            if ($action === 'editar') {
                if ($id <= 0) {
                    throw new RuntimeException('Producto inválido.');
                }

                // IDOR: verificar que el producto está en el inventario de esta sucursal
                $check = $pdo->prepare("
                    SELECT 1 FROM Inventario_Sucursal
                    WHERE id_sucursal = ? AND id_producto = ?
                ");
                $check->execute([$idSucursalGerente, $id]);
                if ($check->fetch() === false) {
                    throw new RuntimeException('Este producto no está en el inventario de tu sucursal.');
                }

                // Validar código duplicado
                if ($codigoInput !== '') {
                    $check = $pdo->prepare("SELECT 1 FROM Productos WHERE codigo = ? AND id_producto <> ?");
                    $check->execute([$codigoInput, $id]);
                    if ($check->fetch() !== false) {
                        throw new RuntimeException('Ese código ya está en uso por otro producto.');
                    }
                }

                // Guardar la ruta de la imagen anterior para borrarla al final
                if ($rutaImagenSubida !== null) {
                    $stmtImg = $pdo->prepare("SELECT imagen FROM Productos WHERE id_producto = ?");
                    $stmtImg->execute([$id]);
                    $rutaImagenAnterior = $stmtImg->fetchColumn() ?: null;
                }
            }

            if ($action === 'crear') {
                if ($codigoInput !== '') {
                    $check = $pdo->prepare("SELECT 1 FROM Productos WHERE codigo = ?");
                    $check->execute([$codigoInput]);
                    if ($check->fetch() !== false) {
                        throw new RuntimeException('Ese código ya está en uso por otro producto.');
                    }
                    $codigo = $codigoInput;
                } else {
                    $codigo = generarCodigoUnico($pdo);
                }
            }

            /* ------------------------------------------------------------
               Transacción: producto + inventario se guardan juntos.
               Si algo falla, se revierte todo.
               ------------------------------------------------------------ */
            $pdo->beginTransaction();

            if ($action === 'crear') {
                $stmt = $pdo->prepare("
                    INSERT INTO Productos (codigo, nombre, descripcion, precio, imagen, id_categoria, estado, creado_en, modificado_en)
                    VALUES (?, ?, ?, ?, ?, ?, 'Activo', NOW(), NOW())
                ");
                $stmt->execute([$codigo, $nombre, $descripcion, $precio, $rutaImagenSubida, $idCategoria]);
                $idGuardado = (int) $pdo->lastInsertId();
            } else {
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
                $idGuardado = $id;
            }

            actualizarInventarioSucursal($pdo, $idGuardado, $idSucursalGerente, $cantidadInventario, $precio, $categoriaNombre);

            $pdo->commit();

            // Borrar la imagen anterior si la reemplazamos (best-effort)
            if ($rutaImagenSubida !== null && $rutaImagenAnterior !== null) {
                $f = __DIR__ . '/../' . $rutaImagenAnterior;
                if (is_file($f)) @unlink($f);
            }

            $_SESSION['flash'] = ['msg' => $action === 'crear'
                ? 'Producto registrado correctamente.'
                : 'Producto actualizado correctamente.'];
            header('Location: productos.php');
            exit;

        } elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_producto'] ?? 0);
            $nuevoEstado = ($_POST['nuevo_estado'] ?? '') === 'Activo' ? 'Activo' : 'Inactivo';

            if ($id <= 0) {
                throw new RuntimeException('Producto inválido.');
            }

            // IDOR: verificar que el producto está en el inventario de esta sucursal
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

        } elseif ($action === 'agregar_inventario') {
            agregarProductoAInventario(
                $pdo,
                (int) $idSucursalGerente,
                (int) ($_POST['id_producto'] ?? 0),
                max(0, (int) ($_POST['cantidad_inicial'] ?? 0)),
                (float) ($_POST['precio_venta'] ?? -1),
                (int) $gerente['id_usuario']
            );

            // PRG: la página recarga limpia y el modal queda cerrado.
            $_SESSION['flash'] = ['msg' => 'Producto agregado al inventario.'];
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

$pageTitle    = 'Productos';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Productos</h1>
        <p>Registra, consulta, modifica y desactiva los productos del catálogo</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalNuevo()">+ Nuevo producto</button>
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

<!-- Modal: Nuevo / Editar producto -->
<div class="modal-overlay" id="modalProducto">
    <div class="modal-box">
        <h2 id="tituloModalProducto">Nuevo producto</h2>
        <form id="formProducto" method="POST" action="productos.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="crear">
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
                <label>Código de barras (opcional)</label>
                <input type="text" name="codigo" placeholder="Se genera uno automático si lo dejas vacío">
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
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Agregar un producto del catálogo al inventario de mi sucursal -->
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

<?php require __DIR__ . '/footer.php'; ?>