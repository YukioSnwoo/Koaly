<?php
/**
 * Barra de filtros unificada (ver includes/filtros.php).
 * Espera $filtros = [
 *   'accion'  => 'pagina.php',            // a dónde se envía (GET)
 *   'ancla'   => '#tabla' | '',
 *   'ocultos' => ['orden' => …],          // estado que se conserva al filtrar
 *   'periodo' => ['desde' => DTI, 'hasta' => DTI, 'inicio' => DTI, 'hoy' => DTI,
 *                 'atajos' => atajosDePeriodo(), 'dias' => ['Y-m-d', …]],
 *   'facetas' => [[
 *       'nombre' => 'id_caja', 'etiqueta' => 'Caja', 'todos' => 'Todas', 'vacio' => '0',
 *       'valor' => '3', 'depende' => null | 'categoria',
 *       'opciones' => [['valor' => '3', 'texto' => 'Caja 01', 'rango' => ?array, 'padre' => ?string]],
 *   ]],
 *   'limpiar' => url sin facetas ni periodo | null,
 * ]
 * Sin JavaScript sigue funcionando: los select envían el formulario y el
 * periodo se elige con los atajos (noscript).
 */
$periodo   = $filtros['periodo'];
$idBarra   = 'filtros-' . substr(md5($filtros['accion']), 0, 6);
$datosJs   = [
    'inicio' => $periodo['inicio']->format('Y-m-d'),
    'hoy'    => $periodo['hoy']->format('Y-m-d'),
    'dias'   => array_values($periodo['dias']),
    'atajos' => array_map(fn($clave, $a) => [
        'clave' => $clave,
        'texto' => $a['texto'],
        'desde' => $a['desde']->format('Y-m-d'),
        'hasta' => $a['hasta']->format('Y-m-d'),
    ], array_keys($periodo['atajos']), $periodo['atajos']),
];
?>
<form method="GET" action="<?= e($filtros['accion'] . ($filtros['ancla'] ?? '')) ?>" class="filtros" id="<?= $idBarra ?>" data-filtros>
    <?php foreach ($filtros['ocultos'] ?? [] as $k => $v): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
    <?php endforeach; ?>
    <input type="hidden" name="desde" value="<?= $periodo['desde']->format('Y-m-d') ?>" data-filtros-desde>
    <input type="hidden" name="hasta" value="<?= $periodo['hasta']->format('Y-m-d') ?>" data-filtros-hasta>

    <div class="filtros-campo filtros-campo--periodo">
        <span class="filtros-etiqueta" id="<?= $idBarra ?>-periodo"><?= e(t('filtros.periodo')) ?></span>
        <div class="filtros-periodo">
            <button type="button" class="filtros-flecha" data-periodo-mover="-1"
                    aria-label="<?= e(t('filtros.anterior')) ?>" title="<?= e(t('filtros.anterior')) ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <button type="button" class="filtros-valor" data-periodo-abrir aria-haspopup="dialog" aria-expanded="false"
                    aria-describedby="<?= $idBarra ?>-periodo">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span data-periodo-texto><?= e(fecha($periodo['desde'], false)) ?><?= $periodo['desde'] != $periodo['hasta'] ? ' – ' . e(fecha($periodo['hasta'], false)) : '' ?></span>
            </button>
            <button type="button" class="filtros-flecha" data-periodo-mover="1"
                    aria-label="<?= e(t('filtros.siguiente')) ?>" title="<?= e(t('filtros.siguiente')) ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
        <noscript>
            <div class="filtros-atajos-sinjs">
                <?php foreach ($periodo['atajos'] as $clave => $a): ?>
                    <button type="submit" name="periodo" value="<?= e($clave) ?>" class="btn btn-ghost btn-sm"><?= e($a['texto']) ?></button>
                <?php endforeach; ?>
            </div>
        </noscript>
    </div>

    <?php foreach ($filtros['facetas'] as $f): ?>
        <?php $idCampo = $idBarra . '-' . $f['nombre']; ?>
        <div class="filtros-campo">
            <label class="filtros-etiqueta" for="<?= e($idCampo) ?>"><?= e($f['etiqueta']) ?></label>
            <select name="<?= e($f['nombre']) ?>" id="<?= e($idCampo) ?>" data-faceta
                    <?= !empty($f['depende']) ? 'data-depende="' . e($f['depende']) . '"' : '' ?>>
                <option value="<?= e($f['vacio'] ?? '0') ?>"><?= e($f['todos']) ?></option>
                <?php foreach ($f['opciones'] as $o): ?>
                    <?php $rango = $o['rango'] ?? null; ?>
                    <option value="<?= e((string) $o['valor']) ?>"
                        <?= (string) $o['valor'] === (string) $f['valor'] ? 'selected' : '' ?>
                        <?php if ($rango): ?>data-desde="<?= e($rango['desde']) ?>" data-hasta="<?= e($rango['hasta']) ?>"<?php endif; ?>
                        <?= isset($o['padre']) ? 'data-padre="' . e((string) $o['padre']) . '"' : '' ?>
                        data-detalle="<?= e(textoRangoActividad($rango)) ?>"
                        <?= $rango ? '' : 'disabled' ?>><?= e($o['texto']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>

    <div class="filtros-pie">
        <?php if (!empty($filtros['limpiar'])): ?>
            <a class="filtros-limpiar" href="<?= e($filtros['limpiar']) ?>"><?= e(t('comun.quitar_filtros')) ?></a>
        <?php endif; ?>
        <p class="filtros-aviso" data-filtros-aviso role="status" aria-live="polite"></p>
    </div>

    <script type="application/json" data-filtros-datos><?= json_encode($datosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</form>
