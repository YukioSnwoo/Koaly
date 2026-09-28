<?php
require_once __DIR__ . '/guardia.php';
$verGerenteCss = filemtime(__DIR__ . '/gerente.css');
$verGerenteJs  = filemtime(__DIR__ . '/gerente.js');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Existencias - Panel Gerente</title>
    <link rel="stylesheet" href="gerente.css?v=<?= $verGerenteCss ?>">
</head>
<body>
    <header class="header">
        <h1>Koaly - Panel Gerente</h1>
        <div class="user-info">
            <span><?= htmlspecialchars($gerente['nombre']) ?></span>
            <button type="button" onclick="if (confirm('¿Seguro que deseas cerrar sesión?')) logout();">Cerrar sesión</button>
        </div>
    </header>

    <main class="container">
        <a href="index.php" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Volver al panel
        </a>

        <div class="page-header">
            <div>
                <h1>Existencias</h1>
                <p>Controla las existencias de los productos en tu sucursal</p>
            </div>
        </div>

        <div class="panel">
            <div class="empty-state">
                Módulo en construcción. Aquí podrás consultar y ajustar el stock
                de cada producto en <?= htmlspecialchars($gerente['sucursal_nombre']) ?>.
            </div>
        </div>
    </main>

    <script src="../auth.js"></script>
    <script src="gerente.js?v=<?= $verGerenteJs ?>"></script>
</body>
</html>