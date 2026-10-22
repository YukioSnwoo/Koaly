<?php
// sidebar.php — Se incluye después de header.php

$menu = [
    ['panel.php',       $t['panel_general'], 'layout-dashboard'],
    ['sucursales.php',  $t['sucursales'],    'building'],
    ['gerentes.php',    $t['gerentes'],      'users'],
    ['inventario.php',  $t['inventario'],    'package'],
    ['ventas.php',      $t['ventas'],        'receipt'],
    ['reportes.php',    $t['reportes'],      'chart-bar'],
];
?>
<aside class="sidebar" id="barraLateral">
    <div class="sidebar__brand">
        <img src="../Imagenes/koala/koala-normal.png" alt="" class="sidebar__logo">
        <span>Koaly <small>Admin</small></span>
    </div>

    <nav class="sidebar__nav">
        <?php foreach ($menu as [$archivo, $texto, $icono]): ?>
            <a href="<?= $archivo ?>"
               class="sidebar__link <?= $pagina_actual === $archivo ? 'sidebar__link--active' : '' ?>">
                <i class="ti ti-<?= $icono ?>"></i>
                <span><?= htmlspecialchars($texto) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="sidebar-fondo" id="barraLateralFondo" aria-hidden="true"></div>

<div class="admin-main">
    <header class="admin-topbar">
        <div class="admin-topbar__izq">
            <button type="button" class="tema-boton sidebar-toggle" id="botonMenu"
                    aria-controls="barraLateral" aria-expanded="false"
                    aria-label="<?= htmlspecialchars($t['menu_abrir']) ?>" title="<?= htmlspecialchars($t['menu_abrir']) ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
            </button>
            <p class="admin-topbar__titulo">Koaly <span><?= htmlspecialchars($t['administracion']) ?></span></p>
        </div>
        <div class="admin-topbar__der">
            <details class="idioma">
                <summary aria-label="<?= htmlspecialchars($t['idioma_cambiar']) ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    <span><?= strtoupper($idioma) ?></span>
                    <svg class="idioma-flecha" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </summary>
                <form method="POST" action="cambiar_idioma.php" class="idioma-menu">
                    <?php foreach ($idiomas as $codigo => $nombre): ?>
                        <button type="submit" name="idioma" value="<?= $codigo ?>" lang="<?= $codigo ?>"
                            <?= $codigo === $idioma ? 'aria-current="true" class="is-activo"' : '' ?>>
                            <span class="idioma-codigo"><?= strtoupper($codigo) ?></span> <?= htmlspecialchars($nombre) ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            </details>

            <button type="button" class="tema-boton" id="botonTema"
                    data-a-claro="<?= htmlspecialchars($t['tema_a_claro']) ?>"
                    data-a-oscuro="<?= htmlspecialchars($t['tema_a_oscuro']) ?>">
                <svg class="tema-luna" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <svg class="tema-sol" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            </button>

            <span class="admin-topbar__nombre"><?= htmlspecialchars($admin['nombre']) ?></span>
            <a href="../logout.php" class="admin-topbar__salir"><?= htmlspecialchars($t['cerrar_sesion']) ?></a>
        </div>
    </header>

    <main class="admin-content">
