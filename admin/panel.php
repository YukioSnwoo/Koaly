<?php require 'header.php'; ?>
<?php require 'sidebar.php'; ?>

<?php
$hoy = date('Y-m-d');

// Stats
$numSucursales = $pdo->query("SELECT COUNT(*) FROM Sucursales WHERE estado = 'Activa'")->fetchColumn();
$numGerentes = $pdo->query("SELECT COUNT(*) FROM Usuarios WHERE id_rol = 2")->fetchColumn();
$bajoStock = $pdo->query("SELECT COUNT(*) FROM Inventario_Sucursal WHERE cantidad_disponible <= 5")->fetchColumn();

$ventasHoy = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM Ventas WHERE DATE(fecha_hora) = ?");
$ventasHoy->execute([$hoy]);
$totalVentas = $ventasHoy->fetchColumn();

// Sucursales con gerente y ventas del día
$sucursales = $pdo->prepare("
    SELECT s.id_sucursal, s.nombre, s.estado,
           g.nombre AS gerente_nombre,
           COALESCE(v.total_ventas, 0) AS ventas_hoy
    FROM Sucursales s
    LEFT JOIN Usuarios g ON g.id_sucursal = s.id_sucursal AND g.id_rol = 2
    LEFT JOIN (
        SELECT id_sucursal, SUM(total) AS total_ventas
        FROM Ventas WHERE DATE(fecha_hora) = ?
        GROUP BY id_sucursal
    ) v ON v.id_sucursal = s.id_sucursal
    ORDER BY s.nombre
");
$sucursales->execute([$hoy]);
$listaSucursales = $sucursales->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['panel_general']) ?></h1>
        <p>Vista consolidada de todas las sucursales</p>
    </div>
</div>

<?php
// Ticket de corte: las sucursales con más ventas primero; el resto se suma en una sola línea.
$ordenadas = $listaSucursales;
usort($ordenadas, fn($a, $b) => (float) $b['ventas_hoy'] <=> (float) $a['ventas_hoy']);
$lineasTicket = array_slice($ordenadas, 0, 6);
$restantes = array_slice($ordenadas, 6);
$sinGerente = count(array_filter($listaSucursales, fn($s) => !$s['gerente_nombre']));
$inactivas = count(array_filter($listaSucursales, fn($s) => $s['estado'] !== 'Activa'));
$pendientes = $bajoStock + $sinGerente;
?>

<div class="hero">
    <div class="ticket">
        <div class="ticket__top">
            <b>Corte de la red</b>
            <span><?= date('d/m/Y H:i') ?></span>
        </div>
        <?php if (empty($listaSucursales)): ?>
            <p class="ticket__vacio">Aún no hay sucursales registradas.</p>
        <?php else: ?>
            <?php foreach ($lineasTicket as $s): ?>
                <div class="ticket__row"><?= htmlspecialchars($s['nombre']) ?><i></i><em>$<?= number_format((float) $s['ventas_hoy'], 0, '.', ',') ?></em></div>
            <?php endforeach; ?>
            <?php if ($restantes): ?>
                <div class="ticket__row">Otras <?= count($restantes) ?><i></i><em>$<?= number_format(array_sum(array_column($restantes, 'ventas_hoy')), 0, '.', ',') ?></em></div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="ticket__total">
            <span>Total del día</span>
            <b>$<?= number_format((float) $totalVentas, 0, '.', ',') ?></b>
        </div>
    </div>

    <div class="pend">
        <p class="section-title">Para atender</p>
        <?php if ($bajoStock > 0): ?>
            <a class="pend__it" href="inventario.php">
                <span class="pend__ic pend__ic--danger"><i class="ti ti-alert-triangle"></i></span>
                <span class="pend__txt"><b><?= (int) $bajoStock ?> producto<?= $bajoStock != 1 ? 's' : '' ?> con stock bajo</b><small>Cinco unidades o menos en alguna sucursal</small></span>
                <span class="btn btn--sm">Ver</span>
            </a>
        <?php endif; ?>
        <?php if ($sinGerente > 0): ?>
            <a class="pend__it" href="gerentes.php">
                <span class="pend__ic pend__ic--warn"><i class="ti ti-user-off"></i></span>
                <span class="pend__txt"><b><?= $sinGerente ?> sucursal<?= $sinGerente != 1 ? 'es' : '' ?> sin gerente</b><small>Asigna a alguien para activarla<?= $inactivas > 0 ? ' · ' . $inactivas . ' inactiva' . ($inactivas != 1 ? 's' : '') : '' ?></small></span>
                <span class="btn btn--primary btn--sm">Asignar</span>
            </a>
        <?php endif; ?>
        <?php if ($pendientes === 0): ?>
            <div class="pend__it pend__it--ok">
                <span class="pend__ic pend__ic--ok"><i class="ti ti-check"></i></span>
                <span class="pend__txt"><b>Todo en orden</b><small>No hay nada pendiente por ahora</small></span>
            </div>
        <?php endif; ?>
        <div class="pend__mini">
            <div class="stat-card"><p class="stat-card__label">Sucursales activas</p><p class="stat-card__value"><?= $numSucursales ?></p></div>
            <div class="stat-card"><p class="stat-card__label">Gerentes registrados</p><p class="stat-card__value"><?= $numGerentes ?></p></div>
        </div>
    </div>
</div>

<div style="margin-top:1rem;display:flex;gap:8px">
    <a href="sucursales.php" class="btn btn--primary"><i class="ti ti-plus"></i>Nueva sucursal</a>
    <a href="gerentes.php" class="btn btn--primary"><i class="ti ti-user-plus"></i>Registrar gerente</a>
</div>

<?php require 'footer.php'; ?>
