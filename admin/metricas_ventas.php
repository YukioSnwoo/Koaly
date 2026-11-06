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

/**
 * Venta neta y número de ventas por método de pago.
 * @return list<array{etiqueta:string, num:int, neta:float}>
 */
function ventasPorMetodo(PDO $pdo, string $where, array $params): array
{
    $stmt = $pdo->prepare("
        SELECT m.nombre_metodo AS etiqueta, COUNT(*) AS num, SUM(v.total) AS cobrado
        FROM Ventas v
        INNER JOIN Metodos_Pago m ON m.id_metodo_pago = v.id_metodo_pago
        WHERE $where
        GROUP BY m.id_metodo_pago, m.nombre_metodo
        ORDER BY cobrado DESC
    ");
    $stmt->execute($params);
    return array_map(fn($f) => [
        'etiqueta' => (string) $f['etiqueta'],
        'num'      => (int) $f['num'],
        'neta'     => sinIva((float) $f['cobrado']),
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Número de ventas por día de la semana y hora (mapa de calor).
 * @return array{horas: list<int>, filas: list<list<int>>}  filas de lunes a domingo
 */
function mapaCalorVentas(PDO $pdo, string $where, array $params): array
{
    $stmt = $pdo->prepare("
        SELECT WEEKDAY(v.fecha_hora) AS dia, HOUR(v.fecha_hora) AS hora, COUNT(*) AS num
        FROM Ventas v
        WHERE $where
        GROUP BY dia, hora
    ");
    $stmt->execute($params);
    $celdas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$celdas) {
        return ['horas' => [], 'filas' => []];
    }
    $horasConVenta = array_map(fn($c) => (int) $c['hora'], $celdas);
    $horas = range(min($horasConVenta), max($horasConVenta));
    $filas = array_fill(0, 7, array_fill(0, count($horas), 0));
    foreach ($celdas as $c) {
        $filas[(int) $c['dia']][(int) $c['hora'] - $horas[0]] = (int) $c['num'];
    }
    return ['horas' => $horas, 'filas' => $filas];
}

/**
 * Análisis ABC: ordena los productos por venta y marca A (los que suman el
 * primer 80% de la venta), B (el siguiente 15%) y C (el último 5%).
 * Recibe las filas de rentabilidadPor($pdo, 'producto', …).
 * @return list<array> las mismas filas, de mayor a menor venta, con 'clase' y 'acumulado' (%)
 */
function clasificarABC(array $productos): array
{
    usort($productos, fn($a, $b) => $b['neta'] <=> $a['neta']);
    $total = array_sum(array_column($productos, 'neta'));
    $acumulado = 0.0;
    foreach ($productos as &$p) {
        $antes = $total > 0 ? $acumulado / $total * 100 : 0;
        $acumulado += $p['neta'];
        // La clase depende de dónde empieza el producto: el que cruza el 80% sigue siendo A
        $p['clase'] = $antes < 80 ? 'A' : ($antes < 95 ? 'B' : 'C');
        $p['acumulado'] = $total > 0 ? $acumulado / $total * 100 : 0;
    }
    return $productos;
}

/**
 * Serie de venta y ganancia para la gráfica de tendencia: por hora si el
 * periodo es un día, por día hasta 92 días y por mes si es más. Rellena con 0
 * los huecos sin ventas para que el eje no salte días.
 * $diasCortos: 7 nombres desde domingo; $mesesCortos: 12 nombres desde enero.
 * @return array{agrupar:string, etiquetas:list<string>, neta:list<float>, utilidad:list<?float>}
 */
function serieTendencia(PDO $pdo, DateTimeImmutable $desde, DateTimeImmutable $hasta, string $where, array $params,
                        array $diasCortos, array $mesesCortos, string $whereLinea = '', array $paramsLinea = []): array
{
    $dias = (int) $desde->diff($hasta)->days + 1;
    $agrupar = $dias === 1 ? 'hora' : ($dias <= 92 ? 'dia' : 'mes');
    $serie = seriesVentas($pdo, $agrupar, $where, $params, $whereLinea, $paramsLinea);

    $claves = [];
    if ($agrupar === 'hora') {
        $horas = array_map('intval', array_keys($serie)) ?: [9];
        foreach (range(min($horas), max($horas)) as $h) $claves[(string) $h] = $h . ':00';
    } elseif ($agrupar === 'dia') {
        for ($d = $desde; $d <= $hasta; $d = $d->modify('+1 day')) {
            $claves[$d->format('Y-m-d')] = $diasCortos[(int) $d->format('w')] . ' ' . $d->format('j');
        }
    } else {
        for ($d = $desde->modify('first day of this month'); $d <= $hasta; $d = $d->modify('+1 month')) {
            $claves[$d->format('Y-m')] = $mesesCortos[(int) $d->format('n') - 1] . ' ' . $d->format('y');
        }
    }
    return [
        'agrupar'   => $agrupar,
        'etiquetas' => array_values($claves),
        'neta'      => array_map(fn($k) => round($serie[$k]['neta'] ?? 0, 2), array_keys($claves)),
        'utilidad'  => array_map(fn($k) => isset($serie[$k]['utilidad']) ? round($serie[$k]['utilidad'], 2) : null, array_keys($claves)),
    ];
}
