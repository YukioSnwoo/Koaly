<?php require 'header.php'; ?>
<?php require 'sidebar.php'; ?>

<?php
// Consultar sucursales con gerente y conteo de cajeros
$stmt = $pdo->query("
    SELECT s.id_sucursal, s.nombre, s.direccion, s.telefono, s.estado,
           CONCAT(g.nombre, ' ') AS gerente_nombre,
           (SELECT COUNT(*) FROM Usuarios u WHERE u.id_sucursal = s.id_sucursal AND u.id_rol = 3) AS num_cajeros
    FROM Sucursales s
    LEFT JOIN Usuarios g ON g.id_sucursal = s.id_sucursal AND g.id_rol = 2
    ORDER BY s.nombre
");
$sucursales = $stmt->fetchAll();

// Listado de gerentes sin sucursal (para el select del modal)
$gerentes = $pdo->query("SELECT id_usuario, CONCAT(nombre, ' ') AS nombre_completo FROM Usuarios WHERE id_rol = 2 AND id_sucursal IS NULL")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['sucursales']) ?></h1>
        <p>Administra las sedes de la empresa</p>
    </div>
    <button class="btn btn--primary" onclick="openModal('modalSucursal')"><i class="ti ti-plus"></i>Nueva sucursal</button>
</div>

<div class="filters" style="margin-top:1rem">
    <input type="text" class="input" placeholder="Buscar sucursal" id="searchSucursal">
    <select class="select" id="filterEstado">
        <option value="">Todos los estados</option>
        <option value="Activa">Activa</option>
        <option value="Mantenimiento">Mantenimiento</option>
        <option value="Cierre_Temporal">Cierre temporal</option>
        <option value="Cierre_Definitivo">Cierre definitivo</option>
    </select>
</div>

<div class="branch-grid" id="tblSucursales">
    <?php if (empty($sucursales)): ?>
        <p style="color:var(--text-muted);grid-column:1/-1">No hay sucursales registradas.</p>
    <?php else: ?>
        <?php foreach ($sucursales as $s): ?>
            <div class="branch-card" data-nombre="<?= htmlspecialchars(strtolower($s['nombre'])) ?>" data-estado="<?= $s['estado'] ?>">
                <div class="branch-card__header">
                    <p class="branch-card__name"><?= htmlspecialchars($s['nombre']) ?></p>
                    <?php if ($s['estado'] === 'Activa'): ?>
                        <span class="badge badge--success">Activa</span>
                    <?php else: ?>
                        <span class="badge badge--danger">Inactiva</span>
                    <?php endif; ?>
                </div>
                <p class="branch-card__detail"><i class="ti ti-map-pin"></i><?= htmlspecialchars($s['direccion'] ?? 'Sin dirección') ?></p>
                <p class="branch-card__detail"><i class="ti ti-phone"></i><?= htmlspecialchars($s['telefono'] ?? 'Sin teléfono') ?></p>
                <?php if ($s['gerente_nombre']): ?>
                    <p class="branch-card__detail"><i class="ti ti-user"></i>Gerente: <?= htmlspecialchars($s['gerente_nombre']) ?></p>
                <?php else: ?>
                    <p class="branch-card__detail" style="color:var(--text-muted)"><i class="ti ti-user-off"></i>Sin asignar</p>
                <?php endif; ?>
                <div class="branch-card__footer">
                    <span class="branch-card__count"><?= $s['num_cajeros'] ?> cajero<?= $s['num_cajeros'] != 1 ? 's' : '' ?></span>
                    <div class="branch-card__actions">
                        <a href="sucursales.php?id=<?= $s['id_sucursal'] ?>" class="btn btn--ghost btn--sm">Ver</a>
                        <button class="btn btn--ghost btn--sm" onclick="editarSucursal(<?= $s['id_sucursal'] ?>)">Editar</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: Nueva Sucursal -->
<div id="modalSucursal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 1rem">Nueva sucursal</h2>
        <form method="POST" action="sucursales.php">
            <input type="hidden" name="action" value="crear">
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Nombre</label>
                <input type="text" name="nombre" class="input" style="width:100%" placeholder="Ej. Sede Oriente" required>
            </div>
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Dirección</label>
                <input type="text" name="direccion" class="input" style="width:100%" placeholder="Calle, ciudad" required>
            </div>
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Teléfono</label>
                <input type="tel" name="telefono" class="input" style="width:100%" placeholder="55 0000 0000">
            </div>
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Gerente</label>
                <select name="id_gerente" class="select" style="width:100%">
                    <option value="">Sin asignar</option>
                    <?php foreach ($gerentes as $g): ?>
                        <option value="<?= $g['id_usuario'] ?>"><?= htmlspecialchars($g['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalSucursal')">Cancelar</button>
                <button type="submit" class="btn btn--primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarSucursal(id) { window.location.href = 'sucursales.php?id=' + id; }

document.getElementById('searchSucursal')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#tblSucursales .branch-card').forEach(card => {
        card.style.display = card.dataset.nombre.includes(q) ? '' : 'none';
    });
});
document.getElementById('filterEstado')?.addEventListener('change', function() {
    const v = this.value;
    document.querySelectorAll('#tblSucursales .branch-card').forEach(card => {
        card.style.display = (!v || card.dataset.estado === v) ? '' : 'none';
    });
});
</script>

<?php
// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'crear') {
        $stmt = $pdo->prepare("INSERT INTO Sucursales (nombre, direccion, telefono, estado, fecha_inicio_estado) VALUES (?, ?, ?, 'Activa', ?)");
        $stmt->execute([$_POST['nombre'], $_POST['direccion'], $_POST['telefono'], date('Y-m-d')]);
        $idSucursal = $pdo->lastInsertId();
        if (!empty($_POST['id_gerente'])) {
            $upd = $pdo->prepare("UPDATE Usuarios SET id_sucursal = ? WHERE id_usuario = ?");
            $upd->execute([$idSucursal, $_POST['id_gerente']]);
        }
        header('Location: sucursales.php');
        exit;
    }
}
?>

<?php require 'footer.php'; ?>
