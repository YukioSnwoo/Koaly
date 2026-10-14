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

/**
 * Al dar de baja a un cajero se liberan e inactivan sus cajas.
 * Devuelve cuántas cajas se afectaron. Llamar dentro de una transacción.
 */
function liberarCajasDeCajero(PDO $pdo, int $idCajero, int $idSucursal): int
{
    $stmt = $pdo->prepare("
        UPDATE Cajas
        SET estado = 'Inactiva', id_cajero_asignado = NULL
        WHERE id_cajero_asignado = ? AND id_sucursal = ?
    ");
    $stmt->execute([$idCajero, $idSucursal]);
    return $stmt->rowCount();
}

$ESTADOS_VALIDOS = ['Activo', 'Vacaciones', 'Incapacidad', 'Licencia', 'Baja'];

$errores = [];
$exito   = '';
$passwordTemporal = null;

/* ------------------------------------------------------------
   Flash (PRG)
   ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    $exito = $_SESSION['flash']['msg'] ?? '';
    // La contraseña temporal se muestra una sola vez
    $passwordTemporal = $_SESSION['flash']['password'] ?? null;
    unset($_SESSION['flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mensajeExito = '';
    $flashPassword = null;

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
            $mensajeExito = 'Cajero registrado correctamente.';

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
                SELECT estado FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $estadoAnterior = $check->fetchColumn();
            if ($estadoAnterior === false) {
                throw new RuntimeException('Cajero inválido.');
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ?");
            $check->execute([$email, $id]);
            if ($check->fetch() !== false) {
                throw new RuntimeException('Ese email ya está en uso por otro usuario.');
            }

            $pdo->beginTransaction();

            // fecha_inicio_estado solo cambia si el estado cambia. Va ANTES de
            // "estado = ?": MySQL evalúa el SET de izquierda a derecha.
            $stmt = $pdo->prepare("
                UPDATE Usuarios
                SET nombre = ?, email = ?,
                    fecha_inicio_estado = IF(estado = ?, fecha_inicio_estado, CURDATE()),
                    estado = ?
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombre, $email, $estado, $estado, $id]);

            $mensajeExito = 'Cajero actualizado correctamente.';
            if ($estado === 'Baja' && $estadoAnterior !== 'Baja'
                && liberarCajasDeCajero($pdo, $id, $idSucursalGerente) > 0) {
                $mensajeExito .= ' Sus cajas quedaron inactivas.';
            }

            $pdo->commit();

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

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE Usuarios
                SET estado = ?, fecha_inicio_estado = CURDATE()
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nuevo, $id]);

            $mensajeExtra = '';
            if ($nuevo === 'Baja' && liberarCajasDeCajero($pdo, $id, $idSucursalGerente) > 0) {
                $mensajeExtra = ' Sus cajas quedaron inactivas.';
            }

            $pdo->commit();

            $mensajeExito = ($nuevo === 'Activo' ? 'Cajero reactivado.' : 'Cajero dado de baja.') . $mensajeExtra;

        } elseif ($action === 'restablecer_password') {
            $id = (int) ($_POST['id_usuario'] ?? 0);

            $check = $pdo->prepare("
                SELECT nombre FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $nombreCajero = $check->fetchColumn();
            if ($nombreCajero === false) {
                throw new RuntimeException('Cajero inválido.');
            }

            // Sin caracteres ambiguos (0/O, 1/l/I) para dictarla sin errores
            $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $temporal = '';
            for ($k = 0; $k < 10; $k++) {
                $temporal .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }

            $stmt = $pdo->prepare("
                UPDATE Usuarios SET contrasena_hash = ?, debe_cambiar = 1 WHERE id_usuario = ?
            ");
            $stmt->execute([password_hash($temporal, PASSWORD_DEFAULT), $id]);

            $mensajeExito  = "Contraseña de {$nombreCajero} restablecida. Deberá cambiarla al iniciar sesión.";
            $flashPassword = $temporal;
        }

        // PRG: tras guardar, redirigir para que F5 no reenvíe el formulario
        if ($mensajeExito !== '') {
            $_SESSION['flash'] = ['msg' => $mensajeExito, 'password' => $flashPassword];
            header('Location: cajeros.php');
            exit;
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
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
          AND v.fecha_hora >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
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
<?php if ($passwordTemporal): ?>
    <div class="alert alert-warning">
        Contraseña temporal: <code class="password-temporal"><?= htmlspecialchars($passwordTemporal) ?></code>
        &middot; Entrégala al cajero ahora: no se volverá a mostrar.
    </div>
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
                        <span class="cajero-stat-label">Ventas del mes</span>
                    </div>
                    <div class="cajero-stat">
                        <span class="cajero-stat-value">$<?= number_format((float) $c['monto_total'], 0) ?></span>
                        <span class="cajero-stat-label">Vendido del mes</span>
                    </div>
                </div>

                <a class="cajero-ver-ventas" href="ventas_sucursal.php?periodo=mes&amp;id_cajero=<?= (int) $c['id_usuario'] ?>">Ver sus ventas →</a>

                <div class="cajero-actions">
                    <button type="button" class="btn btn-ghost btn-sm"
                        onclick="abrirModalEditarCajero(this)"
                        data-id="<?= (int) $c['id_usuario'] ?>"
                        data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($c['email'], ENT_QUOTES) ?>"
                        data-estado="<?= htmlspecialchars($c['estado'], ENT_QUOTES) ?>"
                    >Editar</button>

                    <?php if ($c['estado'] !== 'Baja'): ?>
                    <form method="POST" style="display:contents"
                          data-confirm="Se generará una contraseña temporal para <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>. La actual dejará de funcionar."
                          data-confirm-titulo="Restablecer contraseña"
                          data-confirm-aceptar="Restablecer">
                        <input type="hidden" name="action" value="restablecer_password">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="id_usuario" value="<?= (int) $c['id_usuario'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm">Contraseña</button>
                    </form>
                    <?php endif; ?>

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