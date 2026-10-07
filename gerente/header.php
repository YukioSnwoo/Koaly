<?php

$pageTitle    = $pageTitle    ?? 'Panel Gerente';
$pageDesc     = $pageDesc     ?? '';
$showBackLink = $showBackLink ?? false;

$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
$verGerenteJs  = filemtime(__DIR__ . '/gerente.js');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Koaly</title>
    <?php if ($pageDesc !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="gerente.css?v=<?= $verGerenteCss ?>">
</head>
<body>
    <header class="header">
        <h1>Koaly - Panel Gerente</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($gerente['nombre']) ?></span>
            <button type="button" onclick="logoutConfirmado()">Cerrar sesión</button>
        </div>
    </header>

    <main class="container">
        <?php if ($showBackLink): ?>
            <a href="index.php" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Volver al panel
            </a>
        <?php endif; ?>