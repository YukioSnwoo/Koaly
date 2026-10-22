<?php
/**
 * Barra de filtros unificada: periodo + facetas (caja, cajero, producto…).
 *
 * Las fechas posibles salen de la actividad real de la sucursal: no se puede
 * elegir antes de la primera actividad ni después de hoy. Cada opción de una
 * faceta lleva su propio rango [primera, última actividad]; al elegirla, el
 * calendario solo deja escoger dentro de ese rango (p. ej. una caja
 * desactivada hace tres meses llega hasta su última venta).
 *
 * El servidor acota el periodo a [primera actividad, hoy]; el ajuste por
 * faceta lo hace el navegador al elegirla (js/componentes.js → initFiltros).
 * La vista se pinta con includes/barra_filtros.php.
 */

/** Fecha Y-m-d válida o null. */
function leerFechaFiltro(string $valor): ?DateTimeImmutable
{
    $f = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return ($f && $f->format('Y-m-d') === $valor) ? $f : null;
}

/**
 * Rango de actividad por valor de una faceta.
 * $sql debe devolver las columnas: clave, desde, hasta, num (desde/hasta DATETIME o DATE).
 * Devuelve [clave => ['desde' => 'Y-m-d', 'hasta' => 'Y-m-d', 'num' => int]].
 */
function rangosDeActividad(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rangos = [];
    foreach ($stmt->fetchAll() as $fila) {
        if ($fila['desde'] === null) continue;
        $rangos[(string) $fila['clave']] = [
            'desde' => substr((string) $fila['desde'], 0, 10),
            'hasta' => substr((string) $fila['hasta'], 0, 10),
            'num'   => (int) $fila['num'],
        ];
    }
    return $rangos;
}

/** Días (Y-m-d) con actividad, para marcarlos en el calendario. $sql devuelve una columna "dia". */
function diasConActividad(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map(fn($d) => substr((string) $d, 0, 10), $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Atajos de periodo: [clave => [texto, desde]]; todos terminan hoy salvo "ayer" y "mes_pasado".
 * Devuelve [clave => ['texto', 'desde' => DTI, 'hasta' => DTI]].
 */
function atajosDePeriodo(DateTimeImmutable $hoy, DateTimeImmutable $inicio): array
{
    $mesPasado = $hoy->modify('first day of last month');
    return [
        'hoy'        => ['texto' => t('filtros.hoy'),        'desde' => $hoy,                            'hasta' => $hoy],
        'ayer'       => ['texto' => t('filtros.ayer'),       'desde' => $hoy->modify('-1 day'),          'hasta' => $hoy->modify('-1 day')],
        '7'          => ['texto' => t('filtros.ultimos_7'),  'desde' => $hoy->modify('-6 days'),         'hasta' => $hoy],
        '30'         => ['texto' => t('filtros.ultimos_30'), 'desde' => $hoy->modify('-29 days'),        'hasta' => $hoy],
        'mes'        => ['texto' => t('filtros.este_mes'),   'desde' => $hoy->modify('first day of this month'), 'hasta' => $hoy],
        'mes_pasado' => ['texto' => t('filtros.mes_pasado'), 'desde' => $mesPasado,                      'hasta' => $mesPasado->modify('last day of this month')],
        'anio'       => ['texto' => t('filtros.este_anio'),  'desde' => $hoy->setDate((int) $hoy->format('Y'), 1, 1), 'hasta' => $hoy],
        'todo'       => ['texto' => t('filtros.todo'),       'desde' => min($inicio, $hoy),              'hasta' => $hoy],
    ];
}

/**
 * Periodo pedido (?desde=&hasta= o ?periodo=atajo), acotado a [$inicio, $hoy].
 * Devuelve ['desde' => DTI, 'hasta' => DTI, 'atajo' => ?string, 'atajos' => array].
 */
function leerPeriodo(array $get, DateTimeImmutable $hoy, DateTimeImmutable $inicio, string $atajoPorDefecto): array
{
    $inicio = min($inicio, $hoy);
    $atajos = atajosDePeriodo($hoy, $inicio);

    // Un atajo (?periodo=mes) manda sobre las fechas: así funcionan los enlaces
    // y los botones de atajo sin JavaScript, que también envían las fechas ocultas
    $desde = leerFechaFiltro((string) ($get['desde'] ?? ''));
    $hasta = leerFechaFiltro((string) ($get['hasta'] ?? ''));
    $clave = (string) ($get['periodo'] ?? '');
    if (isset($atajos[$clave]) || !$desde || !$hasta) {
        $clave = isset($atajos[$clave]) ? $clave : $atajoPorDefecto;
        $desde = $atajos[$clave]['desde'];
        $hasta = $atajos[$clave]['hasta'];
    }
    if ($desde > $hasta) {
        [$desde, $hasta] = [$hasta, $desde];
    }

    // Nada antes de la primera actividad ni después de hoy
    $desde = max($desde, $inicio);
    $hasta = min($hasta, $hoy);
    if ($desde > $hasta) {
        $desde = $hasta;
    }

    $atajo = null;
    foreach ($atajos as $clave => $a) {
        if ($desde == max($a['desde'], $inicio) && $hasta == min($a['hasta'], $hoy)) {
            $atajo = $clave;
            break;
        }
    }
    return ['desde' => $desde, 'hasta' => $hasta, 'atajo' => $atajo, 'atajos' => $atajos];
}

/**
 * Texto corto con el rango de actividad de una opción, para mostrarlo en la lista.
 */
function textoRangoActividad(?array $rango): string
{
    if (!$rango) {
        return t('filtros.sin_actividad');
    }
    if ($rango['desde'] === $rango['hasta']) {
        return t('filtros.actividad_dia', ['fecha' => fecha($rango['desde'], false)]);
    }
    return t('filtros.actividad_rango', ['desde' => fecha($rango['desde'], false), 'hasta' => fecha($rango['hasta'], false)]);
}
