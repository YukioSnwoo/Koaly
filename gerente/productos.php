<?php
session_start();

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 2) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/database.php';

$stmt = $pdo->prepare("SELECT id_usuario, nombre, id_sucursal FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->execute([$_SESSION['id_usuario']]);
$gerente = $stmt->fetch();

if (!$gerente) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$idSucursalGerente = $gerente['id_sucursal'] !== null ? (int) $gerente['id_sucursal'] : null;

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

$errores = [];
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
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

            $rutaImagen = guardarImagenProducto($_FILES['imagen'] ?? [], $carpetaImagenes, $rutaImagenesRelativa);

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

                $stmt = $pdo->prepare("
                    INSERT INTO Productos (codigo, nombre, descripcion, precio, imagen, id_categoria, estado, creado_en, modificado_en)
                    VALUES (?, ?, ?, ?, ?, ?, 'Activo', NOW(), NOW())
                ");
                $stmt->execute([$codigo, $nombre, $descripcion, $precio, $rutaImagen, $idCategoria]);
                $idGuardado = (int) $pdo->lastInsertId();
                $exito = 'Producto registrado correctamente.';
            } else {
                if ($id <= 0) {
                    throw new RuntimeException('Producto inválido.');
                }

                if ($codigoInput !== '') {
                    $check = $pdo->prepare("SELECT 1 FROM Productos WHERE codigo = ? AND id_producto <> ?");
                    $check->execute([$codigoInput, $id]);
                    if ($check->fetch() !== false) {
                        throw new RuntimeException('Ese código ya está en uso por otro producto.');
                    }
                }

                if ($rutaImagen !== null) {
                    $anterior = $pdo->prepare("SELECT imagen FROM Productos WHERE id_producto = ?");
                    $anterior->execute([$id]);
                    $rutaAnterior = $anterior->fetchColumn();
                }

                if ($codigoInput !== '' && $rutaImagen !== null) {
                    $stmt = $pdo->prepare("UPDATE Productos SET codigo=?, nombre=?, descripcion=?, precio=?, imagen=?, id_categoria=?, modificado_en=NOW() WHERE id_producto=?");
                    $stmt->execute([$codigoInput, $nombre, $descripcion, $precio, $rutaImagen, $idCategoria, $id]);
                } elseif ($codigoInput !== '') {
                    $stmt = $pdo->prepare("UPDATE Productos SET codigo=?, nombre=?, descripcion=?, precio=?, id_categoria=?, modificado_en=NOW() WHERE id_producto=?");
                    $stmt->execute([$codigoInput, $nombre, $descripcion, $precio, $idCategoria, $id]);
                } elseif ($rutaImagen !== null) {
                    $stmt = $pdo->prepare("UPDATE Productos SET nombre=?, descripcion=?, precio=?, imagen=?, id_categoria=?, modificado_en=NOW() WHERE id_producto=?");
                    $stmt->execute([$nombre, $descripcion, $precio, $rutaImagen, $idCategoria, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE Productos SET nombre=?, descripcion=?, precio=?, id_categoria=?, modificado_en=NOW() WHERE id_producto=?");
                    $stmt->execute([$nombre, $descripcion, $precio, $idCategoria, $id]);
                }

                if ($rutaImagen !== null && !empty($rutaAnterior)) {
                    $rutaFisicaAnterior = __DIR__ . '/../' . $rutaAnterior;
                    if (is_file($rutaFisicaAnterior)) {
                        unlink($rutaFisicaAnterior);
                    }
                }

                $idGuardado = $id;
                $exito = 'Producto actualizado correctamente.';
            }

            if ($idSucursalGerente !== null) {
                try {
                    $catStmt = $pdo->prepare("SELECT nombre_categoria FROM Categorias WHERE id_categoria = ?");
                    $catStmt->execute([$idCategoria]);
                    $categoriaNombre = (string) $catStmt->fetchColumn();

                    actualizarInventarioSucursal($pdo, $idGuardado, $idSucursalGerente, $cantidadInventario, $precio, $categoriaNombre);
                } catch (PDOException $e) {
                    $errores[] = 'El producto se guardó, pero no se pudo actualizar la cantidad en inventario.';
                }
            } else {
                $errores[] = 'Tu cuenta no tiene una sucursal asignada, así que no se registró inventario para este producto.';
            }
        } elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_producto'] ?? 0);
            $nuevoEstado = ($_POST['nuevo_estado'] ?? '') === 'Activo' ? 'Activo' : 'Inactivo';

            if ($id <= 0) {
                throw new RuntimeException('Producto inválido.');
            }

            $stmt = $pdo->prepare("UPDATE Productos SET estado=?, modificado_en=NOW() WHERE id_producto=?");
            $stmt->execute([$nuevoEstado, $id]);
            $exito = 'Estado del producto actualizado.';
        }
    } catch (RuntimeException $e) {
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        $errores[] = 'Error al guardar en la base de datos.';
    }
}

$categorias = $pdo->query("SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria")->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre_categoria, COALESCE(inv.cantidad_disponible, 0) AS stock_sucursal
    FROM Productos p
    LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria
    LEFT JOIN Inventario_Sucursal inv ON inv.id_producto = p.id_producto AND inv.id_sucursal = ?
    ORDER BY p.nombre
");
$stmt->execute([$idSucursalGerente]);
$productos = $stmt->fetchAll();

$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
$verGerenteJs = filemtime(__DIR__ . '/gerente.js');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Panel Gerente</title>
    <link rel="stylesheet" href="gerente.css?v=<?= $verGerenteCss ?>">
</head>
<body>
    <div class="header">
        <h1>Koaly - Panel Gerente</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($gerente['nombre']) ?></span>
            <button onclick="logout()">Cerrar sesion</button>
        </div>
    </div>
    <div class="container">
        <a href="index.php" class="back-link">&larr; Volver al panel</a>

        <div class="page-header">
            <div>
                <h1>Productos</h1>
                <p>Registra, consulta, modifica y desactiva los productos del catálogo</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="abrirModalNuevo()">+ Nuevo producto</button>
        </div>

        <?php if (!$idSucursalGerente): ?>
            <div class="alert alert-error">Tu cuenta no tiene una sucursal asignada. Podrás registrar productos, pero no se guardará inventario hasta que un administrador te asigne una sucursal.</div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>
        <?php foreach ($errores as $error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <div class="toolbar">
            <input type="text" id="buscarProducto" placeholder="Buscar producto por nombre...">
        </div>

        <div class="panel">
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
                        <tr><td colspan="8" class="empty-state">No hay productos registrados todavía.</td></tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <tr data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre'])) ?>">
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
                                <td><?= (int) $p['stock_sucursal'] ?></td>
                                <td>
                                    <?php if ($p['estado'] === 'Activo'): ?>
                                        <span class="badge badge-activo">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactivo">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="acciones">
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

                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="cambiar_estado">
                                            <input type="hidden" name="id_producto" value="<?= $p['id_producto'] ?>">
                                            <?php if ($p['estado'] === 'Activo'): ?>
                                                <input type="hidden" name="nuevo_estado" value="Inactivo">
                                                <button type="submit" class="btn btn-danger btn-sm">Desactivar</button>
                                            <?php else: ?>
                                                <input type="hidden" name="nuevo_estado" value="Activo">
                                                <button type="submit" class="btn btn-success btn-sm">Activar</button>
                                            <?php endif; ?>
                                        </form>
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
            <form id="formProducto" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="crear">
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

    <script src="../auth.js"></script>
    <script src="gerente.js?v=<?= $verGerenteJs ?>"></script>
</body>
</html>
