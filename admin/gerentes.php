<?php require 'header.php'; ?>
<?php require 'sidebar.php'; ?>

<?php
// Consultar gerentes con su sucursal
$stmt = $pdo->query("
    SELECT u.id_usuario, u.nombre, u.email, u.estado,
           s.nombre AS sucursal_nombre, s.id_sucursal
    FROM Usuarios u
    LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.id_rol = 2
    ORDER BY u.nombre
");
$gerentes = $stmt->fetchAll();

// Sucursales sin gerente (para el select de asignar)
$sucursales = $pdo->query("
    SELECT s.id_sucursal, s.nombre
    FROM Sucursales s
    LEFT JOIN Usuarios g ON g.id_sucursal = s.id_sucursal AND g.id_rol = 2
    WHERE g.id_usuario IS NULL AND s.estado = 'Activa'
    ORDER BY s.nombre
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['gerentes']) ?></h1>
        <p>Registra gerentes y asígnalos a una sucursal</p>
    </div>
    <button class="btn btn--primary" onclick="openModal('modalGerente')"><i class="ti ti-user-plus"></i>Registrar gerente</button>
</div>

<div class="table-wrap" style="margin-top:1rem">
    <table class="table" id="tblGerentes">
        <thead><tr>
            <th>Nombre</th><th>Correo</th><th>Sucursal asignada</th><th>Estado</th><th>Acciones</th>
        </tr></thead>
        <tbody>
            <?php if (empty($gerentes)): ?>
                <tr><td colspan="5" style="color:var(--text-muted);text-align:center">No hay gerentes registrados.</td></tr>
            <?php else: ?>
                <?php foreach ($gerentes as $g):
                    $palabras = explode(' ', $g['nombre']);
                    $ini = '';
                    foreach ($palabras as $p) $ini .= mb_strtoupper(mb_substr($p, 0, 1));
                    $ini = mb_substr($ini, 0, 2);
                ?>
                    <tr>
                        <td style="display:flex;align-items:center;gap:8px">
                            <div class="avatar"><?= $ini ?></div>
                            <?= htmlspecialchars($g['nombre']) ?>
                        </td>
                        <td style="color:var(--text-secondary)"><?= htmlspecialchars($g['email']) ?></td>
                        <td><?= $g['sucursal_nombre'] ? htmlspecialchars($g['sucursal_nombre']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                        <td>
                            <?php if ($g['estado'] === 'Activo'): ?>
                                <span class="badge badge--success">Activo</span>
                            <?php else: ?>
                                <span class="badge badge--warning">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="icon-btn" title="Editar" onclick="editarGerente(<?= $g['id_usuario'] ?>)"><i class="ti ti-edit"></i></button>
                            <button class="icon-btn" title="Asignar sucursal" onclick="asignarSucursal(<?= $g['id_usuario'] ?>)"><i class="ti ti-arrows-exchange"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Registrar Gerente -->
<div id="modalGerente" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 1rem">Registrar gerente</h2>
        <form method="POST" action="gerentes.php">
            <input type="hidden" name="action" value="crear">
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Nombre completo</label>
                <input type="text" name="nombre" class="input" style="width:100%" placeholder="Nombre y apellidos" required>
            </div>
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Correo electrónico</label>
                <input type="email" name="email" class="input" style="width:100%" placeholder="correo@koalicius.com" required>
            </div>
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Contraseña</label>
                <input type="password" name="password" class="input" style="width:100%" placeholder="Mínimo 8 caracteres" required>
            </div>
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Sucursal</label>
                <select name="id_sucursal" class="select" style="width:100%">
                    <option value="">Sin asignar</option>
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalGerente')">Cancelar</button>
                <button type="submit" class="btn btn--primary">Registrar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarGerente(id) { /* TODO: modal editar */ }
function asignarSucursal(id) { /* TODO: modal asignar */ }
</script>

<?php
// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'crear') {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO Usuarios (nombre, email, contrasena_hash, id_rol, id_sucursal, estado, fecha_inicio_estado, fecha_contratacion) VALUES (?, ?, ?, 2, ?, 'Activo', ?, ?)");
        $hoy = date('Y-m-d');
        $stmt->execute([
            $_POST['nombre'],
            $_POST['email'],
            $hash,
            $_POST['id_sucursal'] ?: null,
            $hoy,
            $hoy
        ]);
        header('Location: gerentes.php');
        exit;
    }
}
?>

<?php require 'footer.php'; ?>
