<?php
require_once __DIR__ . '/guardia.php';

$pageTitle    = 'Cajas';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Cajas</h1>
        <p>Registra y administra las cajas de tu sucursal</p>
    </div>
</div>

<div class="panel">
    <div class="empty-state">
        Módulo en construcción. Aquí podrás registrar, editar y eliminar
        las cajas de <?= htmlspecialchars($gerente['sucursal_nombre']) ?>,
        una vez que la tabla <code>Cajas</code> esté aprobada con el equipo de Punto de Venta.
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>