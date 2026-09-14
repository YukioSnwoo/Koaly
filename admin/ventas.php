<?php require 'header.php'; ?>
<?php require 'sidebar.php'; ?>

<?php
$fSucursal = $_GET['sucursal'] ?? '';
$fFecha = $_GET['fecha'] ?? date('Y-m-d');

// Stats del día
$sqlTotal = "SELECT COALESCE(SUM(total), 0) AS total_ventas,
                    COALESCE(AVG(total), 0) AS ticket_promedio,
                    COUNT(*) AS num_transacciones
             FROM Ventas WHERE DATE(fecha_hora) = ?";
$paramsTotal = [$fFecha];
if ($fSucursal !== '') {
    $sqlTotal .= " AND id_sucursal = ?";
    $paramsTotal[] = $fSucursal;
}
$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($paramsTotal);
$stats = $stmtTotal->fetch();

// Listado de ventas
$sql = "SELECT v.id_venta, s.nombre AS sucursal,
               u.nombre AS cajero, v.fecha_hora, mp.nombre_metodo, v.total
        FROM Ventas v
        INNER JOIN Sucursales s ON s.id_sucursal = v.id_sucursal
        INNER JOIN Usuarios u ON u.id_usuario = v.id_cajero
        INNER JOIN Metodos_Pago mp ON mp.id_metodo_pago = v.id_metodo_pago
        WHERE DATE(v.fecha_hora) = ?";
$params = [$fFecha];
if ($fSucursal !== '') {
    $sql .= " AND v.id_sucursal = ?";
    $params[] = $fSucursal;
}
$sql .= " ORDER BY v.fecha_hora DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll();

$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM Sucursales WHERE estado = 'Activa' ORDER BY nombre")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['ventas']) ?></h1>
        <p>Historial consolidado de todas las sucursales</p>
    </div>
</div>

<div class="stats-grid stats-grid--3" style="margin-top:1rem">
    <div class="stat-card">
        <p class="stat-card__label">Ventas de hoy</p>
        <p class="stat-card__value">$<?= number_format($stats['total_ventas'], 0, '.', ',') ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Ticket promedio</p>
        <p class="stat-card__value">$<?= number_format($stats['ticket_promedio'], 0, '.', ',') ?></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Transacciones hoy</p>
        <p class="stat-card__value"><?= $stats['num_transacciones'] ?></p>
    </div>
</div>

<div class="filters">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;width:100%">
        <select name="sucursal" class="select">
            <option value="">Todas las sucursales</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id_sucursal'] ?>" <?= $fSucursal == $s['id_sucursal'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="fecha" class="input" value="<?= $fFecha ?>">
        <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
    </form>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>Folio</th><th>Sucursal</th><th>Cajero</th><th>Hora</th><th>Pago</th><th>Total</th>
        </tr></thead>
        <tbody>
            <?php if (empty($ventas)): ?>
                <tr><td colspan="6" style="color:var(--text-muted);text-align:center">No hay ventas registradas para esta fecha.</td></tr>
            <?php else: ?>
                <?php foreach ($ventas as $v): ?>
                    <tr>
                        <td>#<?= str_pad($v['id_venta'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td style="color:var(--text-secondary)"><?= htmlspecialchars($v['sucursal']) ?></td>
                        <td style="color:var(--text-secondary)"><?= htmlspecialchars($v['cajero']) ?></td>
                        <td style="color:var(--text-secondary)"><?= date('H:i', strtotime($v['fecha_hora'])) ?></td>
                        <td style="color:var(--text-secondary)"><?= htmlspecialchars($v['nombre_metodo']) ?></td>
                        <td style="text-align:right">$<?= number_format($v['total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'footer.php'; ?>
