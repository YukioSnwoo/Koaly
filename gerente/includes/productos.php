<?php

/** Por debajo de este stock el producto cuenta como "bajo stock". */
const STOCK_UMBRAL_BAJO = 10;
/** Por debajo de este stock el badge se pinta en rojo. */
const STOCK_UMBRAL_CRITICO = 5;

/**
 * Clase CSS y texto del badge de stock, para que todas las páginas coincidan.
 *
 * @return array{0: string, 1: string} [clase, texto]
 */
function badgeStock(int $stock): array
{
    if ($stock === 0) {
        return ['badge-danger', 'Agotado'];
    }
    $texto = $stock . ' ' . ($stock === 1 ? 'unidad' : 'unidades');
    if ($stock < STOCK_UMBRAL_CRITICO) {
        return ['badge-danger', $texto];
    }
    if ($stock < STOCK_UMBRAL_BAJO) {
        return ['badge-warning', $texto];
    }
    return ['badge-activo', $texto];
}

/**
 * Lee el stock de un producto en una sucursal bloqueando la fila (FOR UPDATE)
 * para que ninguna venta lo cambie hasta el commit.
 * Debe llamarse dentro de una transacción. Devuelve null si no existe la fila.
 */
function leerStockParaActualizar(PDO $pdo, int $idSucursal, int $idProducto): ?int
{
    $stmt = $pdo->prepare("
    SELECT cantidad_disponible
    FROM Inventario_Sucursal
    WHERE id_sucursal = ? AND id_producto = ?
    FOR UPDATE
    ");
    $stmt->execute([$idSucursal, $idProducto]);
    $cantidad = $stmt->fetchColumn();

    return $cantidad === false ? null : (int) $cantidad;
}

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

/**
 * Genera un código de barras único con prefijo 750.
 */
function generarCodigoUnico(PDO $pdo): string
{
    do {
        $codigo = '750' . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $check = $pdo->prepare("SELECT 1 FROM Productos WHERE codigo = ?");
        $check->execute([$codigo]);
    } while ($check->fetch() !== false);

    return $codigo;
}

/**
 * Da de alta un producto del catálogo en el inventario de una sucursal
 * y registra el movimiento en Movimientos_Inventario.
 *
 * Hace su propia transacción. Lanza RuntimeException con mensaje legible
 * si algo no cuadra.
 */
function agregarProductoAInventario(PDO $pdo, int $idSucursal, int $idProducto, int $cantidad, float $precio, int $idUsuario): void
{
    if ($idProducto <= 0 || $precio < 0) {
        throw new RuntimeException('Datos inválidos.');
    }

    // Verificar que el producto existe y está activo
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

    // Verificar que no esté ya en esta sucursal
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
