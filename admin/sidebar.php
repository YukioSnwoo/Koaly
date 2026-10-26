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

// Resumen al pie de la barra
try {
    $sucursalesActivas = (int) $pdo->query("SELECT COUNT(*) FROM Sucursales WHERE estado = 'Activa'")->fetchColumn();
} catch (PDOException $e) {
    $sucursalesActivas = 0;
}
$resumenSucursales = tn('nav.sucursales_activas', $sucursalesActivas);
?>
<!-- Barra lateral (común con el gerente): estilos/barra-lateral.css y estilos/barra-lateral.js -->
<aside class="sidebar" id="barraLateral">
    <div class="sidebar-brand">
        <img src="../Imagenes/koala/koala-normal.png" alt="" class="sidebar-logo">
        <span class="sidebar-texto">Koaly <small>Admin</small></span>
    </div>

    <nav class="sidebar-nav">
        <span class="sidebar-indicador" aria-hidden="true"></span>
        <?php foreach ($menu as [$archivo, $texto, $icono]): ?>
            <?php
            $actual = $pagina_actual === $archivo;
            $alerta = $archivo === 'inventario.php' && $inventarioEnAlerta;
            ?>
            <a href="<?= $archivo ?>"
               class="sidebar-link<?= $actual ? ' active' : '' ?><?= $alerta ? ' sidebar-link--alerta' : '' ?>"
               data-titulo="<?= e($alerta ? $texto . ' · ' . tn('nav.solicitudes_por_revisar', $solicitudesPorRevisar) : $texto) ?>"
               <?= $actual ? 'aria-current="page"' : '' ?>
               <?= $alerta ? 'title="' . e(tn('nav.solicitudes_por_revisar', $solicitudesPorRevisar)) . '"' : '' ?>>
                <i class="ti ti-<?= $icono ?> sidebar-icon" aria-hidden="true"></i>
                <span class="sidebar-texto"><?= e($texto) ?></span>
                <?php if ($alerta): ?>
                    <span class="sidebar-pill"><?= $solicitudesPorRevisar ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-pie">
        <button type="button" class="sidebar-contraer" id="botonContraer" aria-controls="barraLateral" aria-pressed="false"
                data-texto-contraer="<?= e(t('nav.contraer')) ?>" data-texto-expandir="<?= e(t('nav.expandir')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 6 9 12 15 18"/></svg>
            <span class="sidebar-texto"><?= e(t('nav.contraer')) ?></span>
        </button>
        <div class="sidebar-sucursal" data-titulo="<?= e(t('nav.administracion') . ' · ' . $resumenSucursales) ?>">
            <span class="sidebar-punto" aria-hidden="true"></span>
            <span class="sidebar-texto">
                <strong><?= e(t('nav.administracion')) ?></strong>
                <small><?= e($resumenSucursales) ?></small>
            </span>
        </div>
    </div>
</aside>
<div class="sidebar-fondo" id="barraLateralFondo" aria-hidden="true"></div>

<div class="admin-main">
    <header class="admin-topbar">
        <div class="admin-topbar__izq">
            <button type="button" class="tema-boton sidebar-toggle" id="botonMenu"
                    aria-controls="barraLateral" aria-expanded="false"
                    aria-label="<?= e(t('nav.menu_abrir')) ?>" title="<?= e(t('nav.menu_abrir')) ?>"
                    data-texto-abrir="<?= e(t('nav.menu_abrir')) ?>" data-texto-cerrar="<?= e(t('nav.menu_cerrar')) ?>">
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
