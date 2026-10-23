<?php
require_once __DIR__ . '/../perfil/perfil.php';

$pageTitle    = $pageTitle    ?? t('panel.titulo');
$pageDesc     = $pageDesc     ?? '';

$verBase        = filemtime(__DIR__ . '/css/base.css');
$verComponentes = filemtime(__DIR__ . '/css/componentes.css');
$verPaginas     = filemtime(__DIR__ . '/css/paginas.css');
$verKoaly       = filemtime(__DIR__ . '/../estilos/koaly.css');
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
</script>
<?php if ($pageDesc !== ''): ?>
<meta name="description" content="<?= e($pageDesc) ?>">
<?php endif; ?>
<link rel="stylesheet" href="css/base.css?v=<?= $verBase ?>">
<link rel="stylesheet" href="css/componentes.css?v=<?= $verComponentes ?>">
<link rel="stylesheet" href="css/paginas.css?v=<?= $verPaginas ?>">
<!-- Estilo común de Koaly (colores, fuentes y piel táctil); va al final para que gane -->
<link rel="stylesheet" href="../estilos/koaly.css?v=<?= $verKoaly ?>">
<link rel="stylesheet" href="../perfil/perfil.css?v=<?= $verPerfil ?>">
</head>
<body>
<div class="layout">
    <!-- Barra Lateralllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllll -->
    <aside class="sidebar" id="barraLateral">
                <div class="sidebar-brand">
            <img src="../Imagenes/koala/koala-normal.png" alt="Koaly" class="sidebar-logo">
            <span>Koaly</span>
        </div>
        
                <nav class="sidebar-nav">
            <a href="index.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <img src="../Imagenes/iconos/icono-inicio.png" class="sidebar-icon" alt=""> <?= e(t('panel.titulo')) ?>
            </a>
            <a href="productos.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'productos.php' ? 'active' : '' ?>">
                <img src="../Imagenes/iconos/icono-inventario.png" class="sidebar-icon" alt=""> <?= e(t('nav.productos')) ?>
            </a>
            <a href="movimientos.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'movimientos.php' ? 'active' : '' ?>">
                <img src="../Imagenes/iconos/icono-movimientos.png" class="sidebar-icon" alt=""> <?= e(t('nav.movimientos')) ?>
            </a>
            <a href="cajeros.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'cajeros.php' ? 'active' : '' ?>">
                <img src="../Imagenes/iconos/icono-cajas.png" class="sidebar-icon" alt=""> <?= e(t('nav.cajeros')) ?>
            </a>
            <a href="cajas.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'cajas.php' ? 'active' : '' ?>">
                <img src="../Imagenes/iconos/icono-caja.png" class="sidebar-icon" alt=""> <?= e(t('nav.cajas')) ?>
            </a>
            <a href="ventas_sucursal.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'ventas_sucursal.php' ? 'active' : '' ?>">
                <img src="../Imagenes/iconos/icono-ventas.png" class="sidebar-icon" alt=""> <?= e(t('nav.ventas')) ?>
            </a>
        </nav>
     </aside> 
    <div class="sidebar-fondo" id="barraLateralFondo" aria-hidden="true"></div>
    <!-- Barra Lateralllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllllll -->

    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-wrapper">
        <header class="header">
            <div class="header-izq">
                <button type="button" class="tema-boton sidebar-toggle" id="botonMenu"
                        aria-controls="barraLateral" aria-expanded="false" aria-label="<?= e(t('menu.abrir')) ?>" title="<?= e(t('menu.abrir')) ?>">
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
