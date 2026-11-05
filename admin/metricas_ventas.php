<?php
/**
 * metricas_ventas.php — Cálculo de ventas, en un solo lugar.
 * Lo usan el panel y Ventas del gerente (y lo usarán las páginas del admin),
 * para que una misma cifra salga igual en todas las pantallas.
 *
 * Reglas (como los reportes de los puntos de venta):
 * - Los precios ya traen IVA (16%). La venta del negocio se reporta SIN IVA
 *   (venta neta): el IVA se cobra para el SAT. Lo cobrado con IVA es dato de caja.
 * - Utilidad bruta = venta neta − costo de lo vendido. El costo se toma de cada
 *   línea de venta (Detalle_Venta.costo_unitario, sql/015), el que tenía el
 *   producto ese día, también con IVA, y se le quita igual que al precio.
 * - Las líneas sin costo no cuentan en la utilidad (nunca se supone costo 0):
 *   'cobertura' dice qué parte de la venta tiene costo.
 * - Margen = utilidad ÷ venta (sobre el precio, no sobre el costo).
 */

const TASA_IVA = 0.16;

/** Quita el IVA a un monto que lo incluye. */
function sinIva(float $conIva): float
{
    return $conIva / (1 + TASA_IVA);
}

/** ¿Ya se corrió sql/015_costo_productos.sql? (las ventas guardan su costo) */
function hayCostoEnVentas(PDO $pdo): bool
{
    static $hay = null;
    return $hay ??= $pdo->query("SHOW COLUMNS FROM Detalle_Venta LIKE 'costo_unitario'")->fetch() !== false;
}

/**
 * Cifras de un conjunto de ventas.
 *
 * @param string $where      condición sobre Ventas v (sucursal, periodo, filtros)
 * @param array  $params     sus valores
 * @param string $whereLinea condición extra sobre Detalle_Venta dv, p. ej.
 *                           " AND dv.id_producto = ?" para contar solo ese producto
 * @param array  $paramsLinea sus valores
 * @return array{num_ventas:int, unidades:int, cobrado:float, venta_neta:float, iva:float,
 *               ticket:float, articulos_ticket:float, costo:?float, utilidad:?float,
 *               margen:?float, cobertura:float}
 */
function metricasVentas(PDO $pdo, string $where, array $params, string $whereLinea = '', array $paramsLinea = []): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Ventas v WHERE $where");
    $stmt->execute($params);
    $numVentas = (int) $stmt->fetchColumn();

    $conCosto = hayCostoEnVentas($pdo);
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(dv.cantidad), 0)                    AS unidades,
               COALESCE(SUM(dv.cantidad * dv.precio_unitario), 0) AS cobrado,
               " . ($conCosto
                   ? "COALESCE(SUM(CASE WHEN dv.costo_unitario IS NOT NULL THEN dv.cantidad * dv.precio_unitario END), 0) AS cobrado_con_costo,
                      SUM(dv.cantidad * dv.costo_unitario) AS costo"
                   : "0 AS cobrado_con_costo, NULL AS costo") . "
        FROM Detalle_Venta dv
        INNER JOIN Ventas v ON v.id_venta = dv.id_venta
        WHERE $where$whereLinea
    ");
    $stmt->execute(array_merge($params, $paramsLinea));
    $f = $stmt->fetch(PDO::FETCH_ASSOC);

    $cobrado   = (float) $f['cobrado'];
    $ventaNeta = sinIva($cobrado);
    $unidades  = (int) $f['unidades'];

    // Utilidad solo de lo que tiene costo
    $costo = $utilidad = $margen = null;
    if ($f['costo'] !== null && (float) $f['cobrado_con_costo'] > 0) {
        $netaConCosto = sinIva((float) $f['cobrado_con_costo']);
        $costo    = sinIva((float) $f['costo']);
        $utilidad = $netaConCosto - $costo;
        $margen   = $utilidad / $netaConCosto * 100;
    }

    return [
        'num_ventas'       => $numVentas,
        'unidades'         => $unidades,
        'cobrado'          => $cobrado,
        'venta_neta'       => $ventaNeta,
        'iva'              => $cobrado - $ventaNeta,
        'ticket'           => $numVentas > 0 ? $ventaNeta / $numVentas : 0.0,
        'articulos_ticket' => $numVentas > 0 ? $unidades / $numVentas : 0.0,
        'costo'            => $costo,
        'utilidad'         => $utilidad,
        'margen'           => $margen,
        'cobertura'        => $cobrado > 0 ? (float) $f['cobrado_con_costo'] / $cobrado * 100 : 0.0,
    ];
}

/** Variación en % de $actual frente a $anterior; null si no hay con qué comparar. */
function variacionPorcentual(?float $actual, ?float $anterior): ?float
{
    if ($actual === null || $anterior === null || $anterior <= 0) {
        return null;
    }
    return ($actual - $anterior) / $anterior * 100;
}

/**
 * Mermas y robos del periodo (kardex), valuados al costo actual del producto
 * (el kardex no guarda costo). Sin IVA, como la utilidad.
 * @return array{unidades:int, costo:?float, sin_costo:int}
 */
function perdidasInventario(PDO $pdo, int $idSucursal, string $inicio, string $fin, int $idProducto = 0): array
{
    $hayCosto = $pdo->query("SHOW COLUMNS FROM Inventario_Sucursal LIKE 'costo'")->fetch() !== false;
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(-m.cantidad), 0) AS unidades,
               " . ($hayCosto
                   ? "SUM(-m.cantidad * i.costo) AS costo,
                      COALESCE(SUM(CASE WHEN i.costo IS NULL THEN -m.cantidad END), 0) AS sin_costo"
                   : "NULL AS costo, COALESCE(SUM(-m.cantidad), 0) AS sin_costo") . "
        FROM Movimientos_Inventario m
        LEFT JOIN Inventario_Sucursal i ON i.id_sucursal = m.id_sucursal AND i.id_producto = m.id_producto
        WHERE m.id_sucursal = ? AND m.fecha_hora >= ? AND m.fecha_hora < ?
          AND m.tipo_movimiento IN ('AJUSTE_MERMA', 'AJUSTE_ROBO') AND m.cantidad < 0
          " . ($idProducto > 0 ? 'AND m.id_producto = ?' : '') . "
    ");
    $stmt->execute(array_merge([$idSucursal, $inicio, $fin], $idProducto > 0 ? [$idProducto] : []));
    $f = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'unidades'  => (int) $f['unidades'],
        'costo'     => $f['costo'] !== null ? sinIva((float) $f['costo']) : null,
        'sin_costo' => (int) $f['sin_costo'],
    ];
}

/**
 * Venta neta, ventas y ganancia agrupadas en el tiempo, para las gráficas.
 * $agrupar: 'hora' (0–23), 'dia' (AAAA-MM-DD), 'semana' (1 = domingo … 7) o 'mes' (AAAA-MM).
 * La ganancia de cada grupo sale solo de las líneas con costo (null si no hay).
 * @return array<string, array{num:int, neta:float, utilidad:?float}>
 */
function seriesVentas(PDO $pdo, string $agrupar, string $where, array $params, string $whereLinea = '', array $paramsLinea = []): array
{
    $grupos = [
        'hora'   => 'HOUR(v.fecha_hora)',
        'dia'    => 'DATE(v.fecha_hora)',
        'semana' => 'DAYOFWEEK(v.fecha_hora)',
        'mes'    => "DATE_FORMAT(v.fecha_hora, '%Y-%m')",
    ];
    if (!isset($grupos[$agrupar])) {
        throw new InvalidArgumentException("Agrupación desconocida: $agrupar");
    }
    $conCosto = hayCostoEnVentas($pdo);
    $stmt = $pdo->prepare("
        SELECT {$grupos[$agrupar]} AS clave,
               COUNT(DISTINCT v.id_venta) AS num,
               SUM(dv.cantidad * dv.precio_unitario) AS cobrado,
               " . ($conCosto
                   ? "SUM(CASE WHEN dv.costo_unitario IS NOT NULL THEN dv.cantidad * (dv.precio_unitario - dv.costo_unitario) END) AS margen_iva"
                   : "NULL AS margen_iva") . "
        FROM Detalle_Venta dv
        INNER JOIN Ventas v ON v.id_venta = dv.id_venta
        WHERE $where$whereLinea
        GROUP BY clave
        ORDER BY clave
    ");
    $stmt->execute(array_merge($params, $paramsLinea));

    $serie = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $serie[(string) $f['clave']] = [
            'num'      => (int) $f['num'],
            'neta'     => sinIva((float) $f['cobrado']),
            'utilidad' => $f['margen_iva'] !== null ? sinIva((float) $f['margen_iva']) : null,
        ];
    }
    return $serie;
}

/**
 * Ganancia por categoría o por producto: venta neta, costo, ganancia, margen
 * y qué parte de la venta tiene costo (cobertura).
 * $por: 'categoria' o 'producto'. Ordena por ganancia (los que no tienen costo, al final).
 * @return list<array{id:int, etiqueta:string, codigo:?string, unidades:int, neta:float,
 *                    utilidad:?float, margen:?float, cobertura:float}>
 */
function rentabilidadPor(PDO $pdo, string $por, string $where, array $params, string $whereLinea = '', array $paramsLinea = [], int $limite = 0): array
{
    [$id, $etiqueta, $codigo, $join] = match ($por) {
        'categoria' => ['c.id_categoria', 'c.nombre_categoria', 'NULL', 'LEFT JOIN Categorias c ON c.id_categoria = p.id_categoria'],
        'producto'  => ['p.id_producto', 'p.nombre', 'p.codigo', ''],
    };
    $conCosto = hayCostoEnVentas($pdo);
    // MariaDB no deja usar el alias de una suma dentro de otra expresión del ORDER BY
    $margenIva = $conCosto ? 'SUM(dv.cantidad * (dv.precio_unitario - dv.costo_unitario))' : 'NULL';
    $stmt = $pdo->prepare("
        SELECT $id AS id, $etiqueta AS etiqueta, $codigo AS codigo,
               SUM(dv.cantidad) AS unidades,
               SUM(dv.cantidad * dv.precio_unitario) AS cobrado,
               " . ($conCosto
                   ? "SUM(CASE WHEN dv.costo_unitario IS NOT NULL THEN dv.cantidad * dv.precio_unitario END) AS cobrado_con_costo,
                      $margenIva AS margen_iva"
                   : "NULL AS cobrado_con_costo, NULL AS margen_iva") . "
        FROM Detalle_Venta dv
        INNER JOIN Ventas v    ON v.id_venta = dv.id_venta
        INNER JOIN Productos p ON p.id_producto = dv.id_producto
        $join
        WHERE $where$whereLinea
        GROUP BY $id, $etiqueta" . ($por === 'producto' ? ', p.codigo' : '') . "
        ORDER BY ($margenIva IS NULL), $margenIva DESC, SUM(dv.cantidad * dv.precio_unitario) DESC
        " . ($limite > 0 ? 'LIMIT ' . (int) $limite : '') . "
    ");
    $stmt->execute(array_merge($params, $paramsLinea));

    $filas = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $cobrado   = (float) $f['cobrado'];
        $conCostoF = (float) $f['cobrado_con_costo'];
        $utilidad  = $f['margen_iva'] !== null ? sinIva((float) $f['margen_iva']) : null;
        $filas[] = [
            'id'        => (int) $f['id'],
            'etiqueta'  => (string) $f['etiqueta'],
            'codigo'    => $f['codigo'],
            'unidades'  => (int) $f['unidades'],
            'neta'      => sinIva($cobrado),
            'utilidad'  => $utilidad,
            'margen'    => $utilidad !== null && $conCostoF > 0 ? $utilidad / sinIva($conCostoF) * 100 : null,
            'cobertura' => $cobrado > 0 ? $conCostoF / $cobrado * 100 : 0.0,
        ];
    }
    return $filas;
}
