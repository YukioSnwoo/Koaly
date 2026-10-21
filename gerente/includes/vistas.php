<?php
/**
 * Selector de vista estilo Finder (íconos, lista, columnas, galería) para
 * las colecciones de tarjetas de cajas y cajeros. Lo maneja initVistas()
 * en js/componentes.js, que recuerda la elección en este navegador.
 *
 * $objetivo: id del contenedor de tarjetas. $clave: nombre con que se guarda.
 * En la vista de columnas las tarjetas se agrupan por su data-columna.
 */
function selectorVistas(string $objetivo, string $clave): string
{
    $iconos = [
        'iconos'   => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5"/>',
        'lista'    => '<line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'columnas' => '<rect x="3" y="4" width="18" height="16" rx="2"/><line x1="9" y1="4" x2="9" y2="20"/><line x1="15" y1="4" x2="15" y2="20"/>',
        'galeria'  => '<rect x="3" y="3" width="18" height="12" rx="2"/><line x1="4" y1="19.5" x2="6" y2="19.5"/><line x1="9" y1="19.5" x2="11" y2="19.5"/><line x1="14" y1="19.5" x2="16" y2="19.5"/><line x1="19" y1="19.5" x2="20" y2="19.5"/>',
    ];

    $html = '<div class="segmentado segmentado--iconos" role="group" aria-label="' . e(t('vista.aria')) . '"'
        . ' data-vistas="' . e($objetivo) . '" data-vistas-clave="' . e($clave) . '">';
    foreach ($iconos as $vista => $svg) {
        $activo = $vista === 'iconos';
        $html .= '<button type="button" data-vista="' . $vista . '"'
            . ' class="' . ($activo ? 'is-activo' : '') . '" aria-pressed="' . ($activo ? 'true' : 'false') . '"'
            . ' title="' . e(t('vista.' . $vista)) . '" aria-label="' . e(t('vista.' . $vista)) . '">'
            . '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $svg . '</svg>'
            . '</button>';
    }
    return $html . '</div>';
}
