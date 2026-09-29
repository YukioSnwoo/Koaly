<?php
require_once __DIR__ . '/guardia.php';

$pageTitle    = 'Existencias';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

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

<?php require __DIR__ . '/footer.php'; ?>