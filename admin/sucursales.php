<?php
ob_start();
require 'header.php';
?>
<?php require 'sidebar.php'; ?>

<?php
require_once __DIR__ . '/sucursal_gerente.php';

$error = '';

// Procesar cambios antes de enviar cualquier contenido al navegador.
// Regla: una sucursal solo está activa mientras tenga un gerente asignado.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        $nombre = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $idGerenteNuevo = (int) ($_POST['id_gerente'] ?? 0);

        if ($action === 'crear') {
            if ($nombre === '' || $direccion === '') {
                throw new RuntimeException('Nombre y dirección son obligatorios.');
            }
            if ($idGerenteNuevo > 0 && !gerenteAsignable($pdo, $idGerenteNuevo)) {
                throw new RuntimeException('El gerente seleccionado no está disponible.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO Sucursales (nombre, direccion, telefono, estado, fecha_inicio_estado) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $nombre,
                $direccion,
                $telefono,
                $idGerenteNuevo > 0 ? SUCURSAL_ACTIVA : SUCURSAL_INACTIVA,
                date('Y-m-d')
            ]);

            if ($idGerenteNuevo > 0) {
                $idSucursal = (int) $pdo->lastInsertId();
                $pdo->prepare("UPDATE Usuarios SET id_sucursal = ? WHERE id_usuario = ? AND id_rol = 2")
                    ->execute([$idSucursal, $idGerenteNuevo]);
            }
            $pdo->commit();

            header('Location: sucursales.php');
            exit;
        }

        if ($action === 'editar') {
            $idSucursal = (int) ($_POST['id_sucursal'] ?? 0);
            $estadoSolicitado = ($_POST['estado'] ?? '') === 'Activa' ? SUCURSAL_ACTIVA : SUCURSAL_INACTIVA;

            $stmt = $pdo->prepare("
                SELECT s.estado,
                       (SELECT u.id_usuario FROM Usuarios u WHERE u.id_sucursal = s.id_sucursal AND u.id_rol = 2 LIMIT 1) AS id_gerente
                FROM Sucursales s
                WHERE s.id_sucursal = ?
            ");
            $stmt->execute([$idSucursal]);
            $actual = $stmt->fetch();

            if (!$actual) {
                throw new RuntimeException('La sucursal no existe.');
            }
            if ($nombre === '' || $direccion === '') {
                throw new RuntimeException('Nombre y dirección son obligatorios.');
            }
            if ($idGerenteNuevo > 0 && !gerenteAsignable($pdo, $idGerenteNuevo, $idSucursal)) {
                throw new RuntimeException('El gerente seleccionado no está disponible.');
            }

            $idGerenteActual = (int) ($actual['id_gerente'] ?? 0);

            if ($idGerenteNuevo === 0) {
                if ($actual['estado'] !== SUCURSAL_ACTIVA && $estadoSolicitado === SUCURSAL_ACTIVA) {
                    throw new RuntimeException('Asigna un gerente para poder activar la sucursal.');
                }
                $estadoFinal = SUCURSAL_INACTIVA;
            } elseif ($idGerenteNuevo !== $idGerenteActual) {
                $estadoFinal = SUCURSAL_ACTIVA;
            } else {
                $estadoFinal = $estadoSolicitado;
            }

            $fechaEstado = $actual['estado'] === $estadoFinal ? null : date('Y-m-d');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE Sucursales
                SET nombre = ?, direccion = ?, telefono = ?, estado = ?,
                    fecha_inicio_estado = COALESCE(?, fecha_inicio_estado)
                WHERE id_sucursal = ?
            ");
            $stmt->execute([$nombre, $direccion, $telefono, $estadoFinal, $fechaEstado, $idSucursal]);

            // Una sucursal solo puede tener un gerente responsable.
            $pdo->prepare("UPDATE Usuarios SET id_sucursal = NULL WHERE id_sucursal = ? AND id_rol = 2")->execute([$idSucursal]);
            if ($idGerenteNuevo > 0) {
                $pdo->prepare("UPDATE Usuarios SET id_sucursal = ? WHERE id_usuario = ? AND id_rol = 2")
                    ->execute([$idSucursal, $idGerenteNuevo]);
            }
            $pdo->commit();

            header('Location: sucursales.php?id=' . $idSucursal);
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
        error_log('sucursales.php: ' . $e->getMessage());
        $error = 'No se pudo guardar. Inténtalo de nuevo.';
    }
}

$idSeleccionada = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// La lista incluye los indicadores que también se muestran en el detalle.
$stmt = $pdo->query("
    SELECT s.id_sucursal, s.nombre, s.direccion, s.telefono, s.estado,
           g.id_usuario AS id_gerente, g.nombre AS gerente_nombre,
           (SELECT COUNT(*) FROM Usuarios u WHERE u.id_sucursal = s.id_sucursal AND u.id_rol = 3) AS num_cajeros,
           (SELECT COALESCE(SUM(i.cantidad_disponible), 0) FROM Inventario_Sucursal i WHERE i.id_sucursal = s.id_sucursal) AS inventario_total,
           (SELECT COALESCE(SUM(v.total), 0) FROM Ventas v WHERE v.id_sucursal = s.id_sucursal) AS ventas_totales
    FROM Sucursales s
    LEFT JOIN Usuarios g ON g.id_sucursal = s.id_sucursal AND g.id_rol = 2
    ORDER BY s.nombre
");
$sucursales = $stmt->fetchAll();

$sucursalSeleccionada = null;
foreach ($sucursales as $sucursal) {
    if ((int) $sucursal['id_sucursal'] === $idSeleccionada) {
        $sucursalSeleccionada = $sucursal;
        break;
    }
}

// Se incluyen gerentes libres y el gerente actual para poder conservarlo.
$stmt = $pdo->prepare("
    SELECT id_usuario, nombre
    FROM Usuarios
    WHERE id_rol = 2 AND (id_sucursal IS NULL OR id_sucursal = ?)
    ORDER BY nombre
");
$stmt->execute([$idSeleccionada]);
$gerentes = $stmt->fetchAll();

$cajeros = [];
if ($sucursalSeleccionada) {
    $stmt = $pdo->prepare("SELECT nombre FROM Usuarios WHERE id_sucursal = ? AND id_rol = 3 ORDER BY nombre");
    $stmt->execute([$idSeleccionada]);
    $cajeros = $stmt->fetchAll();
}
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['sucursales']) ?></h1>
        <p>Administra las sedes de la empresa</p>
    </div>
    <button class="btn btn--primary" onclick="openModal('modalSucursal')"><i class="ti ti-plus"></i>Nueva sucursal</button>
</div>

<?php if ($error !== ''): ?>
    <div style="margin-top:1rem;padding:10px 14px;border-radius:var(--radius-lg);background:var(--bg-danger);color:var(--text-danger)"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($sucursalSeleccionada): ?>
    <div class="page-header" style="margin-top:1rem">
        <div>
            <h2 style="font-size:16px;font-weight:500">Detalle de <?= htmlspecialchars($sucursalSeleccionada['nombre']) ?></h2>
            <p>Los indicadores de esta ficha son de solo lectura.</p>
        </div>
        <a href="sucursales.php" class="btn btn--ghost btn--sm">Volver a sucursales</a>
    </div>

    <div class="stats-grid stats-grid--3">
        <div class="stat-card">
            <p class="stat-card__label">Inventario propio</p>
            <p class="stat-card__value"><?= number_format((int) $sucursalSeleccionada['inventario_total']) ?> unidades</p>
        </div>
        <div class="stat-card">
            <p class="stat-card__label">Cajeros asignados</p>
            <p class="stat-card__value"><?= (int) $sucursalSeleccionada['num_cajeros'] ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-card__label">Ventas totales</p>
            <p class="stat-card__value">$<?= number_format((float) $sucursalSeleccionada['ventas_totales'], 2, '.', ',') ?></p>
        </div>
    </div>

    <div class="table-wrap" style="margin-bottom:1rem">
        <table class="table">
            <thead><tr><th>Cajero asignado</th></tr></thead>
            <tbody>
                <?php if (!$cajeros): ?>
                    <tr><td style="color:var(--text-muted)">No hay cajeros asignados.</td></tr>
                <?php else: ?>
                    <?php foreach ($cajeros as $cajero): ?>
                        <tr><td><?= htmlspecialchars($cajero['nombre']) ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-wrap" style="margin-bottom:1.5rem">
        <form method="POST" style="padding:1rem">
            <input type="hidden" name="action" value="editar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_sucursal" value="<?= $sucursalSeleccionada['id_sucursal'] ?>">
            <h3 style="font-size:15px;font-weight:500;margin-bottom:1rem">Editar sucursal</h3>
            <div class="grid-2">
                <label>Nombre<input type="text" name="nombre" class="input" value="<?= htmlspecialchars($sucursalSeleccionada['nombre']) ?>" required></label>
                <label>Dirección<input type="text" name="direccion" class="input" value="<?= htmlspecialchars($sucursalSeleccionada['direccion'] ?? '') ?>" required></label>
                <label>Teléfono<input type="tel" name="telefono" class="input" value="<?= htmlspecialchars($sucursalSeleccionada['telefono'] ?? '') ?>"></label>
                <label>Estado
                    <select name="estado" class="select">
                        <option value="Activa" <?= $sucursalSeleccionada['estado'] === 'Activa' ? 'selected' : '' ?>>Activa</option>
                        <option value="Inactiva" <?= $sucursalSeleccionada['estado'] !== 'Activa' ? 'selected' : '' ?>>Inactiva</option>
                    </select>
                </label>
                <label>Gerente responsable
                    <select name="id_gerente" class="select">
                        <option value="">Sin asignar</option>
                        <?php foreach ($gerentes as $gerente): ?>
                            <option value="<?= $gerente['id_usuario'] ?>" <?= (int) $sucursalSeleccionada['id_gerente'] === (int) $gerente['id_usuario'] ? 'selected' : '' ?>><?= htmlspecialchars($gerente['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <p style="color:var(--text-muted);font-size:13px;margin-top:8px">Una sucursal solo puede estar activa si tiene un gerente asignado. Si la dejas sin gerente, pasa a Inactiva.</p>
            <div style="display:flex;justify-content:flex-end;margin-top:1rem">
                <button type="submit" class="btn btn--primary">Guardar cambios</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="filters" style="margin-top:1rem">
    <input type="text" class="input" placeholder="Buscar sucursal" id="searchSucursal">
    <select class="select" id="filterEstado">
        <option value="">Todos los estados</option>
        <option value="Activa">Activa</option>
        <option value="Inactiva">Inactiva</option>
    </select>
</div>

<div class="branch-grid" id="tblSucursales">
    <?php if (empty($sucursales)): ?>
        <p style="color:var(--text-muted);grid-column:1/-1">No hay sucursales registradas.</p>
    <?php else: ?>
        <?php foreach ($sucursales as $s): ?>
            <?php $estadoVisual = $s['estado'] === 'Activa' ? 'Activa' : 'Inactiva'; ?>
            <div class="branch-card" data-nombre="<?= htmlspecialchars(strtolower($s['nombre'])) ?>" data-estado="<?= $estadoVisual ?>">
                <div class="branch-card__header">
                    <p class="branch-card__name"><?= htmlspecialchars($s['nombre']) ?></p>
                    <span class="badge <?= $estadoVisual === 'Activa' ? 'badge--success' : 'badge--danger' ?>"><?= $estadoVisual ?></span>
                </div>
                <p class="branch-card__detail"><i class="ti ti-map-pin"></i><?= htmlspecialchars($s['direccion'] ?? 'Sin dirección') ?></p>
                <p class="branch-card__detail"><i class="ti ti-phone"></i><?= htmlspecialchars($s['telefono'] ?? 'Sin teléfono') ?></p>
                <p class="branch-card__detail"><i class="ti ti-user"></i><?= $s['gerente_nombre'] ? 'Gerente: ' . htmlspecialchars($s['gerente_nombre']) : 'Sin gerente asignado' ?></p>
                <div class="branch-card__footer">
                    <span class="branch-card__count"><?= (int) $s['num_cajeros'] ?> cajero<?= $s['num_cajeros'] != 1 ? 's' : '' ?></span>
                    <div class="branch-card__actions">
                        <a href="sucursales.php?id=<?= $s['id_sucursal'] ?>" class="btn btn--ghost btn--sm">Ver y editar</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: Nueva sucursal -->
<div id="modalSucursal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 1rem">Nueva sucursal</h2>
        <form method="POST" action="sucursales.php">
            <input type="hidden" name="action" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <label style="display:block;margin-bottom:10px">Nombre<input type="text" name="nombre" class="input" style="width:100%" required></label>
            <label style="display:block;margin-bottom:10px">Dirección<input type="text" name="direccion" class="input" style="width:100%" required></label>
            <label style="display:block;margin-bottom:10px">Teléfono<input type="tel" name="telefono" class="input" style="width:100%"></label>
            <label style="display:block;margin-bottom:1rem">Gerente
                <select name="id_gerente" class="select" style="width:100%">
                    <option value="">Sin asignar</option>
                    <?php foreach ($gerentes as $g): ?><option value="<?= $g['id_usuario'] ?>"><?= htmlspecialchars($g['nombre']) ?></option><?php endforeach; ?>
                </select>
                <span style="display:block;color:var(--text-muted);font-size:12px;margin-top:4px">Sin gerente, la sucursal queda Inactiva hasta que se asigne uno.</span>
            </label>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalSucursal')">Cancelar</button>
                <button type="submit" class="btn btn--primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function filtrarSucursales() {
    const q = document.getElementById('searchSucursal').value.toLowerCase();
    const estado = document.getElementById('filterEstado').value;
    document.querySelectorAll('#tblSucursales .branch-card').forEach(card => {
        card.style.display = card.dataset.nombre.includes(q) && (!estado || card.dataset.estado === estado) ? '' : 'none';
    });
}
document.getElementById('searchSucursal')?.addEventListener('input', filtrarSucursales);
document.getElementById('filterEstado')?.addEventListener('change', filtrarSucursales);
</script>

<?php require 'footer.php'; ?>
