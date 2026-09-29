<?php
require_once __DIR__ . '/guardia.php';

$pageTitle    = 'Cajeros';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Cajeros</h1>
        <p>Administra el personal de caja de tu sucursal</p>
    </div>
</div>

<div class="panel">
    <div class="empty-state">
        Módulo en construcción. Aquí podrás consultar, registrar y editar
        a las cajeras y cajeros de <?= htmlspecialchars($gerente['sucursal_nombre']) ?>.
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>