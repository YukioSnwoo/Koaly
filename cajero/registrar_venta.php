<?php
session_start();

header('Content-Type: application/json');

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['success' => false, 'message' => 'Método no permitido']);
}

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    responder(401, ['success' => false, 'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.']);
}

require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/../admin/csrf.php';

if (!csrfValido()) {
    responder(403, ['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
}

/* ------------------------------------------------------------
   Cajero y sucursal: siempre desde la BD, nunca desde el cliente
   ------------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.estado, u.id_sucursal, s.estado AS sucursal_estado
    FROM Usuarios u
    LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.id_usuario = ? AND u.id_rol = 3
    LIMIT 1
");
$stmt->execute([$_SESSION['id_usuario']]);
$cajero = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$cajero
    || $cajero['estado'] !== 'Activo'
    || $cajero['id_sucursal'] === null
    || $cajero['sucursal_estado'] !== 'Activa'
) {
    responder(403, ['success' => false, 'message' => 'Tu cuenta o sucursal no está activa.']);
}

$idCajero   = (int) $cajero['id_usuario'];
$idSucursal = (int) $cajero['id_sucursal'];

/* ------------------------------------------------------------
   Entrada
   ------------------------------------------------------------ */
$metodo = $_POST['metodo'] ?? '';
if (!in_array($metodo, ['Efectivo', 'Tarjeta'], true)) {
    responder(400, ['success' => false, 'message' => 'Método de pago inválido.']);
}

$items = json_decode($_POST['items'] ?? '', true);
if (!is_array($items) || count($items) === 0) {
    responder(400, ['success' => false, 'message' => 'La venta no tiene productos.']);
}

// Agrupa por producto por si el cliente manda uno repetido
$cantidades = [];
foreach ($items as $item) {
    $idProducto = (int) ($item['id_producto'] ?? 0);
    $cantidad   = (int) ($item['cantidad'] ?? 0);
    if ($idProducto <= 0 || $cantidad <= 0) {
        responder(400, ['success' => false, 'message' => 'Hay productos con cantidad inválida.']);
    }
    $cantidades[$idProducto] = ($cantidades[$idProducto] ?? 0) + $cantidad;
}

/* ------------------------------------------------------------
   Inserción tolerante al esquema.
   El esquema de Ventas / Detalle_Venta no está versionado en sql/,
   así que se leen las columnas reales y solo se llenan las que existen.
   ------------------------------------------------------------ */
function columnasTabla(PDO $pdo, string $tabla): array
{
    static $cache = [];
    if (!isset($cache[$tabla])) {
        $filas = $pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_ASSOC);
        $cache[$tabla] = array_column($filas, null, 'Field');
    }
    return $cache[$tabla];
}

function insertarFila(PDO $pdo, string $tabla, array $valores, array $expresiones = []): int
{
    $columnas = columnasTabla($pdo, $tabla);

    $valores     = array_intersect_key($valores, $columnas);
    $expresiones = array_intersect_key($expresiones, $columnas);

    foreach ($columnas as $nombre => $col) {
        $obligatoria = $col['Null'] === 'NO'
            && $col['Default'] === null
            && stripos($col['Extra'], 'auto_increment') === false;
        if ($obligatoria && !array_key_exists($nombre, $valores) && !array_key_exists($nombre, $expresiones)) {
            throw new RuntimeException("La tabla $tabla requiere la columna '$nombre' y la caja no sabe cómo llenarla.");
        }
    }

    $nombres = array_merge(array_keys($valores), array_keys($expresiones));
    $marcas  = array_merge(array_fill(0, count($valores), '?'), array_values($expresiones));

    $sql = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $tabla,
        implode('`, `', $nombres),
        implode(', ', $marcas)
    );
    $pdo->prepare($sql)->execute(array_values($valores));

    return (int) $pdo->lastInsertId();
}

$centavosADinero = fn(int $c): string => number_format($c / 100, 2, '.', '');

try {
    $pdo->beginTransaction();

    /* --- Método de pago --- */
    $stmt = $pdo->prepare("SELECT id_metodo_pago FROM Metodos_Pago WHERE LOWER(nombre_metodo) LIKE ? LIMIT 1");
    $stmt->execute([strtolower($metodo) . '%']);
    $idMetodoPago = $stmt->fetchColumn();
    if ($idMetodoPago === false) {
        throw new RuntimeException("No existe el método de pago '$metodo' en la tabla Metodos_Pago.");
    }

    /* --- Productos, precio y existencias (bloqueadas hasta el commit) --- */
    $ids = array_keys($cantidades);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.id_producto, p.nombre, p.precio, i.cantidad_disponible
        FROM Productos p
        LEFT JOIN Inventario_Sucursal i ON i.id_producto = p.id_producto AND i.id_sucursal = ?
        WHERE p.id_producto IN ($marcas) AND p.estado = 'Activo'
        FOR UPDATE
    ");
    $stmt->execute(array_merge([$idSucursal], $ids));
    $productos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), null, 'id_producto');

    $totalCentavos = 0;
    $lineas = [];
    foreach ($cantidades as $idProducto => $cantidad) {
        $p = $productos[$idProducto] ?? null;
        if (!$p) {
            throw new DomainException('Uno de los productos ya no está disponible. Quítalo de la venta.');
        }
        $disponible = (int) ($p['cantidad_disponible'] ?? 0);
        if ($disponible < $cantidad) {
            throw new DomainException("Existencias insuficientes de \"{$p['nombre']}\" en esta sucursal (quedan $disponible).");
        }

        $precioCentavos  = (int) round((float) $p['precio'] * 100);
        $importeCentavos = $precioCentavos * $cantidad;
        $totalCentavos  += $importeCentavos;

        $lineas[] = [
            'id_producto'      => $idProducto,
            'cantidad'         => $cantidad,
            'existencia_final' => $disponible - $cantidad,
            'precio_centavos'  => $precioCentavos,
            'importe_centavos' => $importeCentavos,
        ];
    }

    // Los precios ya incluyen IVA (LFPC art. 7 bis): el total es la suma de los
    // importes y el IVA 16% se desglosa hacia atrás.
    $subtotalCentavos  = (int) round($totalCentavos / 1.16);
    $impuestosCentavos = $totalCentavos - $subtotalCentavos;

    /* --- Efectivo recibido --- */
    $recibidoCentavos = null;
    $cambioCentavos   = null;
    if ($metodo === 'Efectivo') {
        $recibidoCentavos = (int) round((float) ($_POST['efectivo_recibido'] ?? 0) * 100);
        if ($recibidoCentavos < $totalCentavos) {
            throw new DomainException('El efectivo recibido no cubre el total de $' . $centavosADinero($totalCentavos) . '.');
        }
        $cambioCentavos = $recibidoCentavos - $totalCentavos;
    }

    /* --- Caja asignada (solo si Ventas ya tiene la columna id_caja) --- */
    $idCaja = null;
    if (isset(columnasTabla($pdo, 'Ventas')['id_caja'])) {
        $stmt = $pdo->prepare("SELECT id_caja FROM Cajas WHERE id_cajero_asignado = ? AND id_sucursal = ? LIMIT 1");
        $stmt->execute([$idCajero, $idSucursal]);
        $idCaja = $stmt->fetchColumn() ?: null;
    }

    /* --- Venta --- */
    $valoresVenta = [
        'id_sucursal'       => $idSucursal,
        'id_cajero'         => $idCajero,
        'id_metodo_pago'    => (int) $idMetodoPago,
        'subtotal'          => $centavosADinero($subtotalCentavos),
        'impuestos'         => $centavosADinero($impuestosCentavos),
        'iva'               => $centavosADinero($impuestosCentavos),
        'total'             => $centavosADinero($totalCentavos),
    ];
    if ($idCaja !== null) {
        $valoresVenta['id_caja'] = (int) $idCaja;
    }
    if ($recibidoCentavos !== null) {
        $valoresVenta['efectivo_recibido'] = $centavosADinero($recibidoCentavos);
        $valoresVenta['cambio']            = $centavosADinero($cambioCentavos);
    }

    $idVenta = insertarFila($pdo, 'Ventas', $valoresVenta, ['fecha_hora' => 'NOW()']);

    /* --- Tipo de movimiento para el kardex ---
       tipo_movimiento es un ENUM; se usa el valor de salida por venta que exista. */
    $tipoCol = columnasTabla($pdo, 'Movimientos_Inventario')['tipo_movimiento']['Type'] ?? '';
    preg_match_all("/'((?:[^']|'')*)'/", $tipoCol, $m);
    $tiposPermitidos = $m[1];
    $tipoMovimiento = null;
    foreach (['VENTA', 'SALIDA_VENTA', 'SALIDA'] as $candidato) {
        if (in_array($candidato, $tiposPermitidos, true)) {
            $tipoMovimiento = $candidato;
            break;
        }
    }
    if ($tipoMovimiento === null) {
        throw new RuntimeException(
            "Movimientos_Inventario.tipo_movimiento no acepta 'VENTA'. Valores actuales: "
            . implode(', ', $tiposPermitidos) . ". Agrega 'VENTA' al ENUM."
        );
    }

    $registrarMovimiento = $pdo->prepare("
        INSERT INTO Movimientos_Inventario
            (id_sucursal, id_producto, tipo_movimiento, cantidad,
             existencia_posterior, referencia_id, motivo_detalle, realizado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    /* --- Detalle, descuento de inventario y movimiento --- */
    $descontar = $pdo->prepare("
        UPDATE Inventario_Sucursal
        SET cantidad_disponible = cantidad_disponible - ?
        WHERE id_sucursal = ? AND id_producto = ? AND cantidad_disponible >= ?
    ");

    foreach ($lineas as $l) {
        $precio  = $centavosADinero($l['precio_centavos']);
        $importe = $centavosADinero($l['importe_centavos']);

        insertarFila($pdo, 'Detalle_Venta', [
            'id_venta'        => $idVenta,
            'id_producto'     => $l['id_producto'],
            'cantidad'        => $l['cantidad'],
            'precio_unitario' => $precio,
            'precio'          => $precio,
            'precio_venta'    => $precio,
            'subtotal'        => $importe,
            'importe'         => $importe,
        ]);

        $descontar->execute([$l['cantidad'], $idSucursal, $l['id_producto'], $l['cantidad']]);
        if ($descontar->rowCount() !== 1) {
            throw new DomainException('Las existencias cambiaron mientras se cobraba. Intenta de nuevo.');
        }

        $registrarMovimiento->execute([
            $idSucursal,
            $l['id_producto'],
            $tipoMovimiento,
            -$l['cantidad'],
            $l['existencia_final'],
            $idVenta,
            "Venta #$idVenta",
            $idCajero,
        ]);
    }

    $pdo->commit();
} catch (DomainException $e) {
    $pdo->rollBack();
    responder(409, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('registrar_venta: ' . $e->getMessage());
    // Los RuntimeException propios describen un problema de configuración
    // (método de pago o columna faltante); los de PDO no se exponen.
    $mensaje = ($e instanceof RuntimeException && !($e instanceof PDOException))
        ? $e->getMessage()
        : 'No se pudo guardar la venta en la base de datos.';
    responder(500, ['success' => false, 'message' => $mensaje]);
}

responder(200, [
    'success'  => true,
    'id_venta' => $idVenta,
    'subtotal' => $subtotalCentavos / 100,
    'total'    => $totalCentavos / 100,
    'recibido' => $recibidoCentavos !== null ? $recibidoCentavos / 100 : null,
    'cambio'   => $cambioCentavos !== null ? $cambioCentavos / 100 : null,
]);
