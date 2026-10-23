<?php
// sidebar.php — Se incluye después de header.php
require_once __DIR__ . '/solicitudes_comun.php';
require_once __DIR__ . '/../perfil/perfil.php';

$menu = [
    ['panel.php',       t('nav.panel'),      'layout-dashboard'],
    ['sucursales.php',  t('nav.sucursales'), 'building'],
    ['gerentes.php',    t('nav.gerentes'),   'users'],
    ['inventario.php',  t('nav.inventario'), 'package'],
    ['ventas.php',      t('nav.ventas'),     'receipt'],
    ['reportes.php',    t('nav.reportes'),   'chart-bar'],
];

// Con muchas solicitudes de producto sin atender, "Inventario global" se ilumina en rojo
try {
    $solicitudesPorRevisar = contarSolicitudesParaAdmin($pdo);
} catch (PDOException $e) {
    $solicitudesPorRevisar = 0;
}
$inventarioEnAlerta = nivelAvisoSolicitudes($solicitudesPorRevisar) === 'critico';
?>
<aside class="sidebar" id="barraLateral">
    <div class="sidebar__brand">
        <img src="../Imagenes/koala/koala-normal.png" alt="" class="sidebar__logo">
        <span>Koaly <small>Admin</small></span>
    </div>

    <nav class="sidebar__nav">
        <?php foreach ($menu as [$archivo, $texto, $icono]): ?>
            <?php $alerta = $archivo === 'inventario.php' && $inventarioEnAlerta; ?>
            <a href="<?= $archivo ?>"
               class="sidebar__link <?= $pagina_actual === $archivo ? 'sidebar__link--active' : '' ?> <?= $alerta ? 'sidebar__link--alerta' : '' ?>"
               <?= $alerta ? 'title="' . e(tn('nav.solicitudes_por_revisar', $solicitudesPorRevisar)) . '"' : '' ?>>
                <i class="ti ti-<?= $icono ?>"></i>
                <span><?= htmlspecialchars($texto) ?></span>
                <?php if ($alerta): ?>
                    <span class="sidebar__pill"><?= $solicitudesPorRevisar ?></span>
                <?php endif; ?>
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
                    aria-label="<?= e(t('nav.menu_abrir')) ?>" title="<?= e(t('nav.menu_abrir')) ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
            </button>
            <p class="admin-topbar__titulo">Koaly <span><?= e(t('nav.administracion')) ?></span></p>
        </div>
        <div class="admin-topbar__der">
            <?= menuPerfil($pdo, (int) $admin['id_usuario'], [
                'idioma_accion' => 'cambiar_idioma.php',
                'salir'         => ['href' => '../logout.php'],
            ]) ?>
        </div>
    </header>

    <main class="admin-content">
