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
        return ['badge-danger', t('stock.agotado')];
    }
    $texto = tn('stock.unidades', $stock);
    if ($stock < STOCK_UMBRAL_CRITICO) {
        return ['badge-danger', $texto];
    }
    if ($stock < STOCK_UMBRAL_BAJO) {
        return ['badge-warning', $texto];
    }
    return ['badge-activo', $texto];
}

/**
 * Motivos de ajuste manual: código → tipo_movimiento del kardex.
 * La etiqueta es t('motivo.<código>'); en la base se guarda tEs('motivo.<código>').
 * Los que no tienen tipo propio en el ENUM quedan como AJUSTE_MANUAL.
 */
const MOTIVOS_AJUSTE = [
    'conteo'     => 'AJUSTE_MANUAL',
    'merma'      => 'AJUSTE_MERMA',
    'robo'       => 'AJUSTE_ROBO',
    'devolucion' => 'DEVOLUCION_CLIENTE',
    'error'      => 'AJUSTE_MANUAL',
    'otro'       => 'AJUSTE_MANUAL',
];

/**
 * Los cambios de precio de venta se registran en el kardex con cantidad 0 y
 * este prefijo en motivo_detalle (no hay tabla de historial de precios).
 */
const PREFIJO_CAMBIO_PRECIO = 'Cambio de precio:';

/** Valores de Movimientos_Inventario.tipo_movimiento → clase del badge. */
const TIPOS_MOVIMIENTO = [
    'VENTA'              => 'badge-metodo',
    'COMPRA_PROVEEDOR'   => 'badge-activo',
    'DEVOLUCION_CLIENTE' => 'badge-activo',
    'AJUSTE_MERMA'       => 'badge-warning',
    'AJUSTE_ROBO'        => 'badge-danger',
    'TRASLADO'           => 'badge-inactivo',
    'AJUSTE_MANUAL'      => 'badge-inactivo',
];

/** Etiqueta traducida de un tipo de movimiento. */
function etiquetaTipo(string $tipo): string
{
    return isset(TIPOS_MOVIMIENTO[$tipo]) ? t('tipo.' . $tipo) : $tipo;
}

/** ¿El movimiento es un cambio de precio? (cantidad 0 + prefijo) */
function esCambioPrecio(array $mov): bool
{
    return (int) $mov['cantidad'] === 0
        && str_starts_with((string) ($mov['motivo_detalle'] ?? ''), PREFIJO_CAMBIO_PRECIO);
}

/**
 * Registra una fila en el kardex (Movimientos_Inventario).
 * $cantidad es el delta con signo; $existencia la cantidad que quedó.
 * No maneja transacciones — se asume que el llamador lo hace.
 */
function registrarMovimiento(
    PDO $pdo,
    int $idSucursal,
    int $idProducto,
    string $tipo,
    int $cantidad,
    int $existencia,
    string $motivo,
    int $idUsuario,
    ?int $referenciaId = null
): void {
    $stmt = $pdo->prepare("
    INSERT INTO Movimientos_Inventario
    (id_sucursal, id_producto, tipo_movimiento, cantidad,
     existencia_posterior, referencia_id, motivo_detalle, realizado_por)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$idSucursal, $idProducto, $tipo, $cantidad, $existencia, $referenciaId, $motivo, $idUsuario]);
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
        throw new RuntimeException(t('error.datos_invalidos'));
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
        throw new RuntimeException(t('error.producto_no_activo'));
    }

    // Verificar que no esté ya en esta sucursal
    $stmt = $pdo->prepare("
    SELECT 1 FROM Inventario_Sucursal
    WHERE id_sucursal = ? AND id_producto = ?
    ");
    $stmt->execute([$idSucursal, $idProducto]);
    if ($stmt->fetch() !== false) {
        throw new RuntimeException(t('error.producto_ya_en_inventario'));
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
    INSERT INTO Inventario_Sucursal
    (id_sucursal, id_producto, Categoria_Producto, cantidad_disponible, precio_venta)
    VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$idSucursal, $idProducto, (string) $categoriaNombre, $cantidad, $precio]);

    registrarMovimiento($pdo, $idSucursal, $idProducto, 'AJUSTE_MANUAL', $cantidad, $cantidad, tEs('kardex.alta'), $idUsuario);

    $pdo->commit();
}
