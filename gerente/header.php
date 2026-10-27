<?php
require_once __DIR__ . '/../perfil/perfil.php';

$pageTitle    = $pageTitle    ?? t('panel.titulo');
$pageDesc     = $pageDesc     ?? '';

$verBase        = filemtime(__DIR__ . '/css/base.css');
$verComponentes = filemtime(__DIR__ . '/css/componentes.css');
$verPaginas     = filemtime(__DIR__ . '/css/paginas.css');
$verKoaly       = filemtime(__DIR__ . '/../estilos/koaly.css');
$verBarra       = filemtime(__DIR__ . '/../estilos/barra-lateral.css');
$verPerfil      = filemtime(__DIR__ . '/../perfil/perfil.css');

// Página actual con su query string, para volver a ella al cambiar de idioma (menú de perfil)
$paginaActual = basename($_SERVER['PHP_SELF']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
?>
<!DOCTYPE html>
<html lang="<?= idiomaActual() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> - Koaly</title>
<script>
// Tema elegido (claro/oscuro) antes de pintar, para evitar un destello. Sin elección: sigue al sistema.
// La clave es común con el cajero (koaly-tema); la anterior del gerente queda como respaldo.
try { var tema = localStorage.getItem('koaly-tema') || localStorage.getItem('koalicius-gerente-tema'); if (tema === 'claro' || tema === 'oscuro') document.documentElement.setAttribute('data-tema', tema); } catch (e) {}
// Barra lateral contraída a solo íconos (estilos/barra-lateral.js la guarda, común con el admin); antes de pintar, para que no salte
try { if (localStorage.getItem('koaly-barra') === 'contraida') document.documentElement.classList.add('barra-contraida'); } catch (e) {}
</script>
<?php if ($pageDesc !== ''): ?>
<meta name="description" content="<?= e($pageDesc) ?>">
<?php endif; ?>
<link rel="stylesheet" href="css/base.css?v=<?= $verBase ?>">
<link rel="stylesheet" href="css/componentes.css?v=<?= $verComponentes ?>">
<link rel="stylesheet" href="css/paginas.css?v=<?= $verPaginas ?>">
<!-- Estilo común de Koaly (colores, fuentes y piel táctil); va al final para que gane -->
<link rel="stylesheet" href="../estilos/koaly.css?v=<?= $verKoaly ?>">
<link rel="stylesheet" href="../estilos/barra-lateral.css?v=<?= $verBarra ?>">
<link rel="stylesheet" href="../perfil/perfil.css?v=<?= $verPerfil ?>">
</head>
<body>
<div class="layout">
    <!-- Barra lateral (común con el admin): estilos/barra-lateral.css y estilos/barra-lateral.js -->
<?php
$paginaNav = basename($_SERVER['PHP_SELF']);
$enlacesNav = [
    ['index.php',           'icono-inicio.png',      t('panel.titulo')],
    ['productos.php',       'icono-inventario.png',  t('nav.productos')],
    ['movimientos.php',     'icono-movimientos.png', t('nav.movimientos')],
    ['cajeros.php',         'icono-cajas.png',       t('nav.cajeros')],
    ['cajas.php',           'icono-caja.png',        t('nav.cajas')],
    ['ventas_sucursal.php', 'icono-ventas.png',      t('nav.ventas')],
];
?>
    <aside class="sidebar" id="barraLateral">
        <div class="sidebar-brand">
            <img src="../Imagenes/koala/koala-normal.png" alt="" class="sidebar-logo">
            <span class="sidebar-texto">Koaly</span>
        </div>

        <nav class="sidebar-nav">
            <span class="sidebar-indicador" aria-hidden="true"></span>
<?php foreach ($enlacesNav as [$archivo, $icono, $texto]): ?>
            <a href="<?= $archivo ?>" class="sidebar-link<?= $paginaNav === $archivo ? ' active' : '' ?>" data-titulo="<?= e($texto) ?>"<?= $paginaNav === $archivo ? ' aria-current="page"' : '' ?>>
                <img src="../Imagenes/iconos/<?= $icono ?>" class="sidebar-icon" alt="">
                <span class="sidebar-texto"><?= e($texto) ?></span>
            </a>
<?php endforeach; ?>
        </nav>

        <div class="sidebar-pie">
            <button type="button" class="sidebar-contraer" id="botonContraer" aria-controls="barraLateral" aria-pressed="false"
                    data-texto-contraer="<?= e(t('menu.contraer')) ?>" data-texto-expandir="<?= e(t('menu.expandir')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 6 9 12 15 18"/></svg>
                <span class="sidebar-texto"><?= e(t('menu.contraer')) ?></span>
            </button>
            <div class="sidebar-sucursal" data-titulo="<?= e($gerente['sucursal_nombre'] . ' · ' . t('menu.sucursal_activa')) ?>">
                <span class="sidebar-punto" aria-hidden="true"></span>
                <span class="sidebar-texto">
                    <strong><?= e($gerente['sucursal_nombre']) ?></strong>
                    <small><?= e(t('menu.sucursal_activa')) ?></small>
                </span>
            </div>
        </div>
    </aside>
    <div class="sidebar-fondo" id="barraLateralFondo" aria-hidden="true"></div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-wrapper">
        <header class="header">
            <div class="header-izq">
                <button type="button" class="tema-boton sidebar-toggle" id="botonMenu"
                        aria-controls="barraLateral" aria-expanded="false" aria-label="<?= e(t('menu.abrir')) ?>" title="<?= e(t('menu.abrir')) ?>"
                        data-texto-abrir="<?= e(t('menu.abrir')) ?>" data-texto-cerrar="<?= e(t('menu.cerrar')) ?>">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
                </button>
            </div>
            <?= menuPerfil($pdo, (int) $gerente['id_usuario'], [
                'idioma_accion' => 'idioma.php',
                'idioma_campos' => ['volver' => $paginaActual],
                'salir'         => ['onclick' => 'logoutConfirmado()'],
            ]) ?>
        </header>

        <main class="container">
