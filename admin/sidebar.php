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
<aside class="sidebar">
    <div class="sidebar__brand">
        <div class="sidebar__brand-icon"><i class="ti ti-building-store"></i></div>
        <span>Koalicius admin</span>
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

    <div class="sidebar__footer">
        <a href="config.php" class="sidebar__link <?= $pagina_actual === 'config.php' ? 'sidebar__link--active' : '' ?>">
            <i class="ti ti-settings"></i>
            <span><?= htmlspecialchars($t['configuracion']) ?></span>
        </a>
        <a href="../logout.php" class="sidebar__link">
            <i class="ti ti-logout"></i>
            <span><?= htmlspecialchars($t['cerrar_sesion']) ?></span>
        </a>
    </div>
</aside>

<main class="admin-content">
