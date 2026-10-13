<?php
require_once __DIR__ . '/guardia.php';

/**
 * Calcula las iniciales de un nombre (máx. 2 letras).
 */
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
}

$ESTADOS_VALIDOS = ['Activo', 'Vacaciones', 'Incapacidad', 'Licencia', 'Baja'];

$errores = [];
$exito   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        if ($action === 'crear') {
            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $estado   = $_POST['estado'] ?? 'Activo';
            $fechaContratacion = $_POST['fecha_contratacion'] ?? date('Y-m-d');

            if ($nombre === '' || mb_strlen($nombre) > 100) {
                throw new RuntimeException('El nombre es obligatorio (máx. 100 caracteres).');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                throw new RuntimeException('El email no es válido.');
            }
            if (mb_strlen($password) < 8) {
                throw new RuntimeException('La contraseña temporal debe tener al menos 8 caracteres.');
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException('Estado inválido.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaContratacion)) {
                throw new RuntimeException('La fecha de contratación no es válida.');
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch() !== false) {
                throw new RuntimeException('Ese email ya está registrado por otro usuario.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO Usuarios
                    (nombre, email, contrasena_hash, id_rol, id_sucursal, estado,
                     fecha_inicio_estado, fecha_contratacion, debe_cambiar)
                VALUES (?, ?, ?, 3, ?, ?, CURDATE(), ?, 1)
            ");
            $stmt->execute([$nombre, $email, $hash, $idSucursalGerente, $estado, $fechaContratacion]);
            $exito = 'Cajero registrado correctamente.';

        } elseif ($action === 'editar') {
            $id     = (int) ($_POST['id_usuario'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $estado = $_POST['estado'] ?? 'Activo';

            if ($id <= 0) {
                throw new RuntimeException('Cajero inválido.');
            }
            if ($nombre === '' || mb_strlen($nombre) > 100) {
                throw new RuntimeException('El nombre es obligatorio (máx. 100 caracteres).');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                throw new RuntimeException('El email no es válido.');
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException('Estado inválido.');
            }

            // Verificar que el cajero pertenece a esta sucursal y es rol 3 (evita IDOR)
            $check = $pdo->prepare("
                SELECT 1 FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            if ($check->fetch() === false) {
                throw new RuntimeException('Cajero inválido.');
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ?");
            $check->execute([$email, $id]);
            if ($check->fetch() !== false) {
                throw new RuntimeException('Ese email ya está en uso por otro usuario.');
            }

            $stmt = $pdo->prepare("
                UPDATE Usuarios
                SET nombre = ?, email = ?, estado = ?, fecha_inicio_estado = CURDATE()
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombre, $email, $estado, $id]);
            $exito = 'Cajero actualizado correctamente.';

        } elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_usuario'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Cajero inválido.');
            }

            $check = $pdo->prepare("
                SELECT estado FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $actual = $check->fetchColumn();
            if ($actual === false) {
                throw new RuntimeException('Cajero inválido.');
            }

            $nuevo = $actual === 'Activo' ? 'Baja' : 'Activo';
            $stmt = $pdo->prepare("
                UPDATE Usuarios
                SET estado = ?, fecha_inicio_estado = CURDATE()
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nuevo, $id]);
            $exito = $nuevo === 'Activo' ? 'Cajero reactivado.' : 'Cajero dado de baja.';
        }
    } catch (RuntimeException $e) {
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        $errores[] = 'Error al guardar en la base de datos.';
    }
}

$stmt = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.email, u.estado, u.fecha_contratacion,
           COUNT(v.id_venta) AS total_ventas,
           COALESCE(SUM(v.total), 0) AS monto_total
    FROM Usuarios u
    LEFT JOIN Ventas v
           ON v.id_cajero = u.id_usuario AND v.id_sucursal = ?
    WHERE u.id_rol = 3 AND u.id_sucursal = ?
    GROUP BY u.id_usuario, u.nombre, u.email, u.estado, u.fecha_contratacion
    ORDER BY u.nombre
");
$stmt->execute([$idSucursalGerente, $idSucursalGerente]);
$cajeros = $stmt->fetchAll();

$totalActivos = 0;
foreach ($cajeros as $c) {
    if ($c['estado'] === 'Activo') $totalActivos++;
}

$pageTitle    = 'Cajeros';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Cajeros</h1>
        <p>
            <?= $totalActivos ?> activo<?= $totalActivos === 1 ? '' : 's' ?>
            de <?= count($cajeros) ?> registrado<?= count($cajeros) === 1 ? '' : 's' ?>
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalNuevoCajero()">+ Nuevo cajero</button>
</div>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<?php if (empty($cajeros)): ?>
    <div class="panel">
        <div class="empty-state">
            Aún no hay cajeros registrados en tu sucursal. Usa "+ Nuevo cajero" para agregar el primero.
        </div>
    </div>
<?php else: ?>
    <div class="cajeros-grid">
        <?php foreach ($cajeros as $c): ?>
            <?php
                $esActivo   = $c['estado'] === 'Activo';
                $estadoBadge = [
                    'Activo'       => 'badge-activo',
                    'Vacaciones'   => 'badge-warning',
                    'Incapacidad'  => 'badge-warning',
                    'Licencia'     => 'badge-warning',
                    'Baja'         => 'badge-danger',
                ][$c['estado']] ?? 'badge-inactivo';
            ?>
            <div class="cajero-card <?= $esActivo ? '' : 'is-inactivo' ?>">
                <div class="cajero-avatar" aria-hidden="true">
                    <?= htmlspecialchars(iniciales($c['nombre'])) ?>
                </div>
                <div class="cajero-name"><?= htmlspecialchars($c['nombre']) ?></div>
                <div class="cajero-email"><?= htmlspecialchars($c['email']) ?></div>
                <div style="margin-bottom: 0.85rem;">
                    <span class="badge <?= $estadoBadge ?>"><?= htmlspecialchars($c['estado']) ?></span>
                </div>

                <div class="cajero-stats">
                    <div class="cajero-stat">
                        <span class="cajero-stat-value"><?= (int) $c['total_ventas'] ?></span>
                        <span class="cajero-stat-label">Ventas</span>
                    </div>
                    <div class="cajero-stat">
                        <span class="cajero-stat-value">$<?= number_format((float) $c['monto_total'], 0) ?></span>
                        <span class="cajero-stat-label">Total</span>
                    </div>
                </div>

                <div class="cajero-actions">
                    <button type="button" class="btn btn-ghost btn-sm"
                        onclick="abrirModalEditarCajero(this)"
                        data-id="<?= (int) $c['id_usuario'] ?>"
                        data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($c['email'], ENT_QUOTES) ?>"
                        data-estado="<?= htmlspecialchars($c['estado'], ENT_QUOTES) ?>"
                    >Editar</button>

                    <form method="POST" style="display:contents"
                          data-confirm="<?= $esActivo
                              ? '¿Dar de baja a ' . htmlspecialchars($c['nombre'], ENT_QUOTES) . '? No podrá iniciar sesión.'
                              : '¿Reactivar a ' . htmlspecialchars($c['nombre'], ENT_QUOTES) . '?' ?>"
                          data-confirm-titulo="<?= $esActivo ? 'Dar de baja' : 'Reactivar' ?>"
                          data-confirm-aceptar="<?= $esActivo ? 'Dar de baja' : 'Reactivar' ?>"
                          <?= $esActivo ? 'data-confirm-peligroso="1"' : '' ?>>
                        <input type="hidden" name="action" value="cambiar_estado">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="id_usuario" value="<?= (int) $c['id_usuario'] ?>">
                        <button type="submit" class="btn btn-sm <?= $esActivo ? 'btn-danger' : 'btn-success' ?>">
                            <?= $esActivo ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal: Nuevo / Editar cajero -->
<div class="modal-overlay" id="modalCajero">
    <div class="modal-box">
        <h2 id="tituloModalCajero">Nuevo cajero</h2>
        <form id="formCajero" method="POST">
            <input type="hidden" name="action" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_usuario" value="">

            <div class="form-group">
                <label>Nombre completo</label>
                <input type="text" name="nombre" placeholder="Ej. María López Hernández" maxlength="100" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="correo@tienda.com" maxlength="100" required>
            </div>

            <div class="form-group" id="grupoPassword">
                <label>Contraseña temporal</label>
                <input type="text" name="password" placeholder="Mínimo 8 caracteres" minlength="8">
                <small style="display:block; color:#94a3b8; font-size:0.75rem; margin-top:0.3rem;">
                    El cajero deberá cambiarla al iniciar sesión por primera vez.
                </small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" required>
                        <option value="Activo">Activo</option>
                        <option value="Vacaciones">Vacaciones</option>
                        <option value="Incapacidad">Incapacidad</option>
                        <option value="Licencia">Licencia</option>
                        <option value="Baja">Baja</option>
                    </select>
                </div>

                <div class="form-group" id="grupoFechaContratacion">
                    <label>Fecha de contratación</label>
                    <input type="date" name="fecha_contratacion" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCajero')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>