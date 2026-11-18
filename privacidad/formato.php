<?php
/**
 * Formato de los avisos que se editan en el panel del admin (admin/avisos.php)
 * y se guardan en avisos_versiones (sql/018). Se convierte a las mismas
 * secciones que arman las funciones del código ([título => [bloques]]): la
 * página del aviso, el correo de constancia y la huella funcionan igual.
 *
 *   # 1. Título de la sección
 *   Un párrafo. Los renglones seguidos se juntan; un renglón vacío empieza otro.
 *   - Un punto de una lista (cada renglón con guion es otro punto)
 *   | Dato | Descripción | Quién lo ve       ← fila de tabla; "quién lo ve" es opcional
 */

/** Texto del editor → secciones. Lo que esté antes del primer "# Título" se ignora. */
function seccionesDesdeTexto(string $texto): array
{
    $secciones = [];
    $titulo = null;
    $bloque = null;     // ['tipo' => 'p' | 'lista' | 'tabla', 'datos' => [...]]

    $cerrar = static function () use (&$secciones, &$titulo, &$bloque): void {
        if ($titulo !== null && $bloque !== null && $bloque['datos'] !== []) {
            $secciones[$titulo][] = $bloque['tipo'] === 'p' ? implode(' ', $bloque['datos']) : $bloque['datos'];
        }
        $bloque = null;
    };

    foreach (preg_split('/\R/u', $texto) as $linea) {
        $linea = trim($linea);
        if (str_starts_with($linea, '# ')) {
            $cerrar();
            $titulo = trim(substr($linea, 2));
            $secciones[$titulo] ??= [];
            continue;
        }
        if ($titulo === null) {
            continue;
        }
        if ($linea === '') {
            $cerrar();
            continue;
        }

        $tipo = str_starts_with($linea, '- ') ? 'lista' : (str_starts_with($linea, '|') ? 'tabla' : 'p');
        if ($bloque === null || $bloque['tipo'] !== $tipo) {
            $cerrar();
            $bloque = ['tipo' => $tipo, 'datos' => []];
        }

        if ($tipo === 'lista') {
            $bloque['datos'][] = trim(substr($linea, 2));
        } elseif ($tipo === 'tabla') {
            $celdas = array_map('trim', explode('|', trim($linea, "| \t")));
            $termino = array_shift($celdas);
            if ($termino === '' || $celdas === []) {
                continue;
            }
            $bloque['datos'][$termino] = count($celdas) > 1
                ? [$celdas[0], implode(' | ', array_slice($celdas, 1))]
                : $celdas[0];
        } else {
            $bloque['datos'][] = $linea;
        }
    }
    $cerrar();

    return array_filter($secciones, static fn(array $bloques): bool => $bloques !== []);
}

/** Secciones → texto del editor (para empezar a editar desde la versión vigente). */
function textoDesdeSecciones(array $secciones): string
{
    $partes = [];
    foreach ($secciones as $titulo => $bloques) {
        $lineas = ["# {$titulo}"];
        foreach ($bloques as $bloque) {
            $lineas[] = '';
            if (is_string($bloque)) {
                $lineas[] = $bloque;
            } elseif (array_is_list($bloque)) {
                foreach ($bloque as $item) {
                    $lineas[] = "- {$item}";
                }
            } else {
                foreach ($bloque as $termino => $descripcion) {
                    [$dato, $quienVe] = is_array($descripcion) ? $descripcion : [$descripcion, ''];
                    $lineas[] = "| {$termino} | {$dato}" . ($quienVe !== '' ? " | {$quienVe}" : '');
                }
            }
        }
        $partes[] = implode("\n", $lineas);
    }
    return implode("\n\n", $partes) . "\n";
}

/** El texto tal como quedará guardado: sin espacios de más ni renglones vacíos repetidos. */
function normalizarTextoAviso(string $texto): string
{
    $secciones = seccionesDesdeTexto($texto);
    return $secciones === [] ? '' : textoDesdeSecciones($secciones);
}
