<?php

$pageTitle    = $pageTitle    ?? t('panel.titulo');
$pageDesc     = $pageDesc     ?? '';
$showBackLink = $showBackLink ?? false;

$verBase        = filemtime(__DIR__ . '/css/base.css');
$verComponentes = filemtime(__DIR__ . '/css/componentes.css');
$verPaginas     = filemtime(__DIR__ . '/css/paginas.css');

// Página actual con su query string, para volver a ella al cambiar de idioma
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
try { var tema = localStorage.getItem('koalicius-gerente-tema'); if (tema === 'claro' || tema === 'oscuro') document.documentElement.setAttribute('data-tema', tema); } catch (e) {}
</script>
<?php if ($pageDesc !== ''): ?>
<meta name="description" content="<?= e($pageDesc) ?>">
<?php endif; ?>
<link rel="stylesheet" href="css/base.css?v=<?= $verBase ?>">
<link rel="stylesheet" href="css/componentes.css?v=<?= $verComponentes ?>">
<link rel="stylesheet" href="css/paginas.css?v=<?= $verPaginas ?>">
</head>
<body>
<header class="header">
<h1>Koaly <span class="header-sub"><?= e(t('panel.titulo')) ?></span></h1>
<div class="user-info">

<details class="idioma">
<summary aria-label="<?= e(t('idioma.cambiar')) ?>">
<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
</svg>
<span><?= strtoupper(idiomaActual()) ?></span>
<svg class="idioma-flecha" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
</summary>
<form method="POST" action="idioma.php" class="idioma-menu">
<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
<input type="hidden" name="volver" value="<?= e($paginaActual) ?>">
<?php foreach (IDIOMAS as $codigo => $nombre): ?>
<button type="submit" name="idioma" value="<?= $codigo ?>" lang="<?= $codigo ?>"
<?= $codigo === idiomaActual() ? 'aria-current="true" class="is-activo"' : '' ?>>
<span class="idioma-codigo"><?= strtoupper($codigo) ?></span> <?= e($nombre) ?>
</button>
<?php endforeach; ?>
</form>
</details>

<button type="button" class="tema-boton" id="botonTema" aria-label="<?= e(t('tema.cambiar')) ?>" title="<?= e(t('tema.cambiar')) ?>">
<svg class="tema-luna" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
<svg class="tema-sol" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
</button>

<span><?= e($gerente['nombre']) ?></span>
<button type="button" onclick="logoutConfirmado()"><?= e(t('sesion.cerrar')) ?></button>
</div>
</header>

<main class="container">
<?php if ($showBackLink): ?>
<a href="index.php" class="back-link">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
<line x1="19" y1="12" x2="5" y2="12"/>
<polyline points="12 19 5 12 12 5"/>
</svg>
<?= e(t('panel.volver')) ?>
</a>
<?php endif; ?>
