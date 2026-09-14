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
    <div class="page-header__actions">
        <form action="cambiar_idioma.php" method="POST" style="display:inline">
            <select name="idioma" class="select" onchange="this.form.submit()">
                <option value="es" <?= $idioma === 'es' ? 'selected' : '' ?>>Español</option>
                <option value="en" <?= $idioma === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        </form>
        <div class="user-pill">
            <div class="user-pill__avatar"><?= $iniciales ?></div>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <p class="stat-card__label">Sucursales activas</p>
        <p class="stat-card__value"><?= $numSucursales ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Ventas de hoy</p>
        <p class="stat-card__value">$<?= number_format($totalVentas, 0, '.', ',') ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Gerentes registrados</p>
        <p class="stat-card__value"><?= $numGerentes ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Productos con bajo stock</p>
        <p class="stat-card__value stat-card__value--danger"><?= $bajoStock ?></p>
    </div>
</div>

<p class="section-title">Sucursales</p>
<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>Sucursal</th><th>Gerente</th><th>Estado</th><th>Ventas hoy</th>
        </tr></thead>
        <tbody>
            <?php if (empty($listaSucursales)): ?>
                <tr><td colspan="4" style="color:var(--text-muted);text-align:center">No hay sucursales.</td></tr>
            <?php else: ?>
                <?php foreach ($listaSucursales as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['nombre']) ?></td>
                        <td><?= $s['gerente_nombre'] ? htmlspecialchars($s['gerente_nombre']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                        <td>
                            <?php if ($s['estado'] === 'Activa' && $s['gerente_nombre']): ?>
                                <span class="badge badge--success">Activa</span>
                            <?php elseif ($s['estado'] === 'Activa'): ?>
                                <span class="badge badge--warning">Sin gerente asignado</span>
                            <?php else: ?>
                                <span class="badge badge--danger">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right">$<?= number_format($s['ventas_hoy'], 0, '.', ',') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;display:flex;gap:8px">
    <a href="sucursales.php" class="btn btn--primary"><i class="ti ti-plus"></i>Nueva sucursal</a>
    <a href="gerentes.php" class="btn btn--primary"><i class="ti ti-user-plus"></i>Registrar gerente</a>
</div>

<?php require 'footer.php'; ?>
