<?php

/**
 * Descarga un CSV y termina la ejecución.
 * Lleva BOM UTF-8 para que Excel muestre bien acentos y "ñ".
 *
 * @param string   $archivo      Nombre sugerido, p. ej. "ventas_2026-10-01_2026-10-14.csv"
 * @param string[] $encabezados  Títulos de las columnas
 * @param iterable $filas        Cada fila es un array con el mismo orden que $encabezados
 */
function enviarCsv(string $archivo, array $encabezados, iterable $filas): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $archivo) . '"');
    header('Cache-Control: no-store');

    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, $encabezados, ",", "\"", "");
    foreach ($filas as $fila) {
        fputcsv($salida, $fila, ",", "\"", "");
    }
    fclose($salida);
    exit;
}
