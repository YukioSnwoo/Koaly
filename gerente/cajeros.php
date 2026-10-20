<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/vistas.php';

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
            throw new RuntimeException(t('error.formulario_expirado'));
        }

        if ($action === 'crear') {
            $nombre   = trim($_POST['nombre'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $estado   = $_POST['estado'] ?? 'Activo';
            $fechaContratacion = $_POST['fecha_contratacion'] ?? date('Y-m-d');

            if ($nombre === '' || mb_strlen($nombre) > 100) {
                throw new RuntimeException(t('error.nombre_cajero'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                throw new RuntimeException(t('error.email_invalido'));
            }
            if (mb_strlen($password) < 8) {
                throw new RuntimeException(t('error.password_corta'));
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException(t('error.estado_invalido'));
            }
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $fechaContratacion);
            if (!$fecha || $fecha->format('Y-m-d') !== $fechaContratacion) {
                throw new RuntimeException(t('error.fecha_contratacion'));
            }
            if ($fecha > new DateTimeImmutable('today')) {
                throw new RuntimeException(t('error.fecha_contratacion_futura'));
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('error.email_duplicado'));
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO Usuarios
                    (nombre, email, contrasena_hash, id_rol, id_sucursal, estado,
                     fecha_inicio_estado, fecha_contratacion, debe_cambiar)
                VALUES (?, ?, ?, 3, ?, ?, CURDATE(), ?, 1)
            ");
            $stmt->execute([$nombre, $email, $hash, $idSucursalGerente, $estado, $fechaContratacion]);
            $mensajeExito = t('cajeros.creado');

        } elseif ($action === 'editar') {
            $id     = (int) ($_POST['id_usuario'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $estado = $_POST['estado'] ?? 'Activo';

            if ($id <= 0) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }
            if ($nombre === '' || mb_strlen($nombre) > 100) {
                throw new RuntimeException(t('error.nombre_cajero'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                throw new RuntimeException(t('error.email_invalido'));
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException(t('error.estado_invalido'));
            }

            // Verificar que el cajero pertenece a esta sucursal y es rol 3 (evita IDOR)
            $check = $pdo->prepare("
                SELECT estado FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $estadoAnterior = $check->fetchColumn();
            if ($estadoAnterior === false) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ?");
            $check->execute([$email, $id]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('error.email_duplicado'));
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

            $mensajeExito = t('cajeros.actualizado');
            if ($estado === 'Baja' && $estadoAnterior !== 'Baja'
                && liberarCajasDeCajero($pdo, $id, $idSucursalGerente) > 0) {
                $mensajeExito .= ' ' . t('cajeros.cajas_inactivas');
            } elseif ($estado !== 'Activo' && $estado !== 'Baja') {
                // Vacaciones, incapacidad o licencia: su caja no puede seguir activa,
                // pero conserva la asignación para cuando regrese.
                $stmt = $pdo->prepare("
                    UPDATE Cajas SET estado = 'Inactiva'
                    WHERE id_cajero_asignado = ? AND id_sucursal = ? AND estado = 'Activa'
                ");
                $stmt->execute([$id, $idSucursalGerente]);
                if ($stmt->rowCount() > 0) {
                    $mensajeExito .= ' ' . t('cajeros.caja_inactiva_temporal');
                }
            }

            $pdo->commit();

        } elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_usuario'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException(t('error.cajero_invalido'));
            }

            $check = $pdo->prepare("
                SELECT estado FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $actual = $check->fetchColumn();
            if ($actual === false) {
                throw new RuntimeException(t('error.cajero_invalido'));
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
                $mensajeExtra = ' ' . t('cajeros.cajas_inactivas');
            }

            $pdo->commit();

            $mensajeExito = t($nuevo === 'Activo' ? 'cajeros.reactivado' : 'cajeros.baja') . $mensajeExtra;

        } elseif ($action === 'restablecer_password') {
            $id = (int) ($_POST['id_usuario'] ?? 0);

            $check = $pdo->prepare("
                SELECT nombre FROM Usuarios
                WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
            ");
            $check->execute([$id, $idSucursalGerente]);
            $nombreCajero = $check->fetchColumn();
            if ($nombreCajero === false) {
                throw new RuntimeException(t('error.cajero_invalido'));
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

            $mensajeExito  = t('cajeros.password_restablecida', ['nombre' => $nombreCajero]);
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
        $errores[] = t('error.base_datos');
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

$pageTitle    = t('cajeros.titulo');
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('cajeros.titulo')) ?></h1>
        <p>
            <?= e(t('cajeros.resumen', ['activos' => numero($totalActivos), 'total' => numero(count($cajeros))])) ?>
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalNuevoCajero()">+ <?= e(t('cajeros.nuevo')) ?></button>
</div>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php if ($passwordTemporal): ?>
    <div class="alert alert-warning">
        <?= e(t('cajeros.password_temporal')) ?>: <code class="password-temporal"><?= e($passwordTemporal) ?></code>
        · <?= e(t('cajeros.password_temporal_aviso')) ?>
    </div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<?php if (empty($cajeros)): ?>
    <div class="panel">
        <div class="empty-state">
            <?= e(t('cajeros.vacio')) ?>
        </div>
    </div>
<?php else: ?>
    <div class="vista-barra"><?= selectorVistas('gridCajeros', 'cajeros') ?></div>
    <div class="cajeros-grid vista-coleccion" id="gridCajeros" data-vista="iconos">
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
            <div class="cajero-card <?= $esActivo ? '' : 'is-inactivo' ?>"
                 data-columna="<?= e(t('estado_usuario.' . $c['estado'])) ?>"
                 data-columna-orden="<?= (int) array_search($c['estado'], $ESTADOS_VALIDOS, true) ?>">
                <div class="cajero-avatar" aria-hidden="true">
                    <?= htmlspecialchars(iniciales($c['nombre'])) ?>
                </div>
                <div class="cajero-name"><?= htmlspecialchars($c['nombre']) ?></div>
                <div class="cajero-email"><?= htmlspecialchars($c['email']) ?></div>
                <div class="cajero-estado">
                    <span class="badge <?= $estadoBadge ?>"><?= e(t('estado_usuario.' . $c['estado'])) ?></span>
                </div>

                <div class="cajero-stats">
                    <div class="cajero-stat">
                        <span class="cajero-stat-value"><?= (int) $c['total_ventas'] ?></span>
                        <span class="cajero-stat-label"><?= e(t('cajeros.ventas_mes')) ?></span>
                    </div>
                    <div class="cajero-stat">
                        <span class="cajero-stat-value"><?= dinero($c['monto_total']) ?></span>
                        <span class="cajero-stat-label"><?= e(t('cajeros.monto_mes')) ?></span>
                    </div>
                </div>

                <a class="cajero-ver-ventas" href="ventas_sucursal.php?periodo=mes&amp;id_cajero=<?= (int) $c['id_usuario'] ?>"><?= e(t('cajeros.ver_ventas')) ?></a>

                <div class="cajero-actions">
                    <button type="button" class="btn btn-ghost btn-sm"
                        onclick="abrirModalEditarCajero(this)"
                        data-id="<?= (int) $c['id_usuario'] ?>"
                        data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($c['email'], ENT_QUOTES) ?>"
                        data-estado="<?= htmlspecialchars($c['estado'], ENT_QUOTES) ?>"
                    ><?= e(t('comun.editar')) ?></button>

                    <?php if ($c['estado'] !== 'Baja'): ?>
                    <form method="POST" style="display:contents"
                          data-confirm="<?= e(t('cajeros.confirmar_password', ['nombre' => $c['nombre']])) ?>"
                          data-confirm-titulo="<?= e(t('cajeros.restablecer_titulo')) ?>"
                          data-confirm-aceptar="<?= e(t('cajeros.restablecer')) ?>">
                        <input type="hidden" name="action" value="restablecer_password">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="id_usuario" value="<?= (int) $c['id_usuario'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('cajeros.boton_password')) ?></button>
                    </form>
                    <?php endif; ?>

                    <form method="POST" style="display:contents"
                          data-confirm="<?= e(t($esActivo ? 'cajeros.confirmar_baja' : 'cajeros.confirmar_reactivar', ['nombre' => $c['nombre']])) ?>"
                          data-confirm-titulo="<?= e(t($esActivo ? 'cajeros.baja_titulo' : 'cajeros.reactivar_titulo')) ?>"
                          data-confirm-aceptar="<?= e(t($esActivo ? 'cajeros.baja_titulo' : 'cajeros.reactivar_titulo')) ?>"
                          <?= $esActivo ? 'data-confirm-peligroso="1"' : '' ?>>
                        <input type="hidden" name="action" value="cambiar_estado">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="id_usuario" value="<?= (int) $c['id_usuario'] ?>">
                        <button type="submit" class="btn btn-sm <?= $esActivo ? 'btn-danger' : 'btn-success' ?>">
                            <?= e(t($esActivo ? 'cajeros.baja_titulo' : 'cajeros.reactivar_titulo')) ?>
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
        <h2 id="tituloModalCajero"><?= e(t('cajeros.nuevo')) ?></h2>
        <form id="formCajero" method="POST">
            <input type="hidden" name="action" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_usuario" value="">

            <div class="form-group">
                <label><?= e(t('cajeros.campo_nombre')) ?></label>
                <input type="text" name="nombre" placeholder="<?= e(t('cajeros.campo_nombre_ej')) ?>" maxlength="100" required>
            </div>

            <div class="form-group">
                <label><?= e(t('cajeros.campo_email')) ?></label>
                <input type="email" name="email" placeholder="<?= e(t('cajeros.campo_email_ej')) ?>" maxlength="100" required>
            </div>

            <div class="form-group" id="grupoPassword">
                <label><?= e(t('cajeros.password_temporal')) ?></label>
                <input type="text" name="password" placeholder="<?= e(t('cajeros.campo_password_ej')) ?>" minlength="8">
                <small class="texto-tenue" style="display:block; font-size:0.75rem; margin-top:0.3rem;">
                    <?= e(t('cajeros.campo_password_ayuda')) ?>
                </small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= e(t('cat.col_estado')) ?></label>
                    <select name="estado" required>
                        <?php foreach ($ESTADOS_VALIDOS as $est): ?>
                            <option value="<?= $est ?>"><?= e(t('estado_usuario.' . $est)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="grupoFechaContratacion">
                    <label><?= e(t('cajeros.campo_fecha')) ?></label>
                    <input type="date" name="fecha_contratacion" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCajero')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('comun.guardar')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>