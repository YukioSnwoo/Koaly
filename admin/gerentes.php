<?php
ob_start();
require 'header.php';
?>
<?php require 'sidebar.php'; ?>

<?php
require_once __DIR__ . '/sucursal_gerente.php';

$error = '';

// Procesar acciones POST antes de enviar contenido al navegador.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        if ($action === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $idSucursal = (int) ($_POST['id_sucursal'] ?? 0);

            if ($nombre === '') {
                throw new RuntimeException('El nombre es obligatorio.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Ingresa un correo electrónico válido.');
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            }
            if ($idSucursal > 0 && !sucursalSinGerente($pdo, $idSucursal)) {
                throw new RuntimeException('La sucursal seleccionada no existe o ya tiene gerente.');
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch() !== false) {
                throw new RuntimeException('Ya existe un usuario con ese correo.');
            }

            $hoy = date('Y-m-d');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO Usuarios (nombre, email, contrasena_hash, id_rol, id_sucursal, estado, fecha_inicio_estado, fecha_contratacion) VALUES (?, ?, ?, 2, ?, 'Activo', ?, ?)");
            $stmt->execute([
                $nombre,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $idSucursal > 0 ? $idSucursal : null,
                $hoy,
                $hoy
            ]);

            // Al asignarle gerente, la sucursal pasa a activa.
            if ($idSucursal > 0) {
                $pdo->prepare("UPDATE Sucursales SET estado = ?, fecha_inicio_estado = ? WHERE id_sucursal = ? AND estado <> ?")
                    ->execute([SUCURSAL_ACTIVA, $hoy, $idSucursal, SUCURSAL_ACTIVA]);
            }
            $pdo->commit();

            header('Location: gerentes.php');
            exit;
        }

        if ($action === 'editar') {
            $idGerente = (int) ($_POST['id_usuario'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($nombre === '') {
                throw new RuntimeException('El nombre es obligatorio.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Ingresa un correo electrónico válido.');
            }

            $existe = $pdo->prepare("SELECT 1 FROM Usuarios WHERE id_usuario = ? AND id_rol = 2");
            $existe->execute([$idGerente]);
            if ($existe->fetch() === false) {
                throw new RuntimeException('El gerente no existe.');
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ?");
            $check->execute([$email, $idGerente]);
            if ($check->fetch() !== false) {
                throw new RuntimeException('Ya existe un usuario con ese correo.');
            }

            $pdo->prepare("UPDATE Usuarios SET nombre = ?, email = ? WHERE id_usuario = ? AND id_rol = 2")
                ->execute([$nombre, $email, $idGerente]);

            header('Location: gerentes.php');
            exit;
        }

        if ($action === 'asignar') {
            asignarSucursalAGerente(
                $pdo,
                (int) ($_POST['id_usuario'] ?? 0),
                (int) ($_POST['id_sucursal'] ?? 0)
            );

            header('Location: gerentes.php');
            exit;
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('gerentes.php: ' . $e->getMessage());
        $error = 'No se pudo guardar. Inténtalo de nuevo.';
    }
}

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

// Sucursales sin gerente (para el select de asignar). Incluye las inactivas:
// una sucursal sin gerente está inactiva y se activa al asignarle uno.
$sucursales = $pdo->query("
    SELECT s.id_sucursal, s.nombre
    FROM Sucursales s
    LEFT JOIN Usuarios g ON g.id_sucursal = s.id_sucursal AND g.id_rol = 2
    WHERE g.id_usuario IS NULL
    ORDER BY s.nombre
")->fetchAll();

// Todas las sucursales con su gerente actual (para el modal de asignar).
$todasSucursales = $pdo->query("
    SELECT s.id_sucursal, s.nombre, g.id_usuario AS id_gerente
    FROM Sucursales s
    LEFT JOIN Usuarios g ON g.id_sucursal = s.id_sucursal AND g.id_rol = 2
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

<?php if ($error !== ''): ?>
    <div style="margin-top:1rem;padding:10px 14px;border-radius:var(--radius-lg);background:var(--bg-danger);color:var(--text-danger)"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

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
                            <button class="icon-btn" title="Editar" onclick="editarGerente(this)"
                                data-id="<?= (int) $g['id_usuario'] ?>"
                                data-nombre="<?= htmlspecialchars($g['nombre'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($g['email'], ENT_QUOTES) ?>"><i class="ti ti-edit"></i></button>
                            <button class="icon-btn" title="Asignar sucursal" onclick="asignarSucursal(this)"
                                data-id="<?= (int) $g['id_usuario'] ?>"
                                data-nombre="<?= htmlspecialchars($g['nombre'], ENT_QUOTES) ?>"
                                data-sucursal="<?= $g['id_sucursal'] !== null ? (int) $g['id_sucursal'] : '' ?>"><i class="ti ti-arrows-exchange"></i></button>
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
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
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
                <input type="password" name="password" class="input" style="width:100%" placeholder="Mínimo 8 caracteres" minlength="8" required>
            </div>
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Sucursal</label>
                <select name="id_sucursal" class="select" style="width:100%">
                    <option value="">Sin asignar</option>
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="display:block;color:var(--text-muted);font-size:12px;margin-top:4px">Al asignar una sucursal, esta pasa a Activa.</span>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalGerente')">Cancelar</button>
                <button type="submit" class="btn btn--primary">Registrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Gerente -->
<div id="modalEditarGerente" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 1rem">Editar gerente</h2>
        <form method="POST" action="gerentes.php" id="formEditarGerente">
            <input type="hidden" name="action" value="editar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_usuario" value="">
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Nombre completo</label>
                <input type="text" name="nombre" class="input" style="width:100%" required>
            </div>
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Correo electrónico</label>
                <input type="email" name="email" class="input" style="width:100%" required>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalEditarGerente')">Cancelar</button>
                <button type="submit" class="btn btn--primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Asignar sucursal -->
<div id="modalAsignarSucursal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 .5rem">Asignar sucursal</h2>
        <p style="color:var(--text-secondary);font-size:13px;margin:0 0 1rem">Gerente: <strong id="asignarNombreGerente"></strong></p>
        <form method="POST" action="gerentes.php" id="formAsignarSucursal">
            <input type="hidden" name="action" value="asignar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_usuario" value="">
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Sucursal</label>
                <select name="id_sucursal" class="select" style="width:100%">
                    <option value="">Sin asignar</option>
                    <?php foreach ($todasSucursales as $s): ?>
                        <option value="<?= (int) $s['id_sucursal'] ?>" data-gerente="<?= $s['id_gerente'] !== null ? (int) $s['id_gerente'] : '' ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="display:block;color:var(--text-muted);font-size:12px;margin-top:4px">La sucursal elegida pasa a Activa. Si el gerente tenía otra, esa queda Inactiva.</span>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalAsignarSucursal')">Cancelar</button>
                <button type="submit" class="btn btn--primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarGerente(btn) {
    const form = document.getElementById('formEditarGerente');
    form.querySelector('[name="id_usuario"]').value = btn.dataset.id;
    form.querySelector('[name="nombre"]').value = btn.dataset.nombre;
    form.querySelector('[name="email"]').value = btn.dataset.email;
    openModal('modalEditarGerente');
}

function asignarSucursal(btn) {
    const form = document.getElementById('formAsignarSucursal');
    const select = form.querySelector('[name="id_sucursal"]');

    form.querySelector('[name="id_usuario"]').value = btn.dataset.id;
    document.getElementById('asignarNombreGerente').textContent = btn.dataset.nombre;

    // Solo se ofrecen sucursales libres o la que ya tiene este gerente.
    select.querySelectorAll('option[data-gerente]').forEach(opt => {
        const ocupada = opt.dataset.gerente !== '' && opt.dataset.gerente !== btn.dataset.id;
        opt.disabled = ocupada;
        opt.hidden = ocupada;
    });
    select.value = btn.dataset.sucursal;

    openModal('modalAsignarSucursal');
}
</script>

<?php require 'footer.php'; ?>
