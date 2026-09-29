<?php
require_once __DIR__ . '/guardia.php';

$ESTADOS_VALIDOS = ['Activa', 'Inactiva', 'Mantenimiento'];

$errores = [];
$exito   = '';

/* ------------------------------------------------------------
   Flash (PRG)
   ------------------------------------------------------------ */
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $exito = $flash['msg'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }

        /* -------- Crear -------- */
        if ($action === 'crear') {
            $numeroCaja   = trim($_POST['numero_caja'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            $estado       = $_POST['estado'] ?? 'Activa';
            $idCajero     = (int) ($_POST['id_cajero_asignado'] ?? 0);

            if ($numeroCaja === '' || mb_strlen($numeroCaja) > 20) {
                throw new RuntimeException('El número de caja es obligatorio (máx. 20 caracteres).');
            }
            if (mb_strlen($nombre) > 50) {
                throw new RuntimeException('El nombre no debe superar 50 caracteres.');
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException('Estado inválido.');
            }
            if ($idCajero > 0) {
                $check = $pdo->prepare("
                    SELECT 1 FROM Usuarios
                    WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
                ");
                $check->execute([$idCajero, $idSucursalGerente]);
                if ($check->fetch() === false) {
                    throw new RuntimeException('El cajero seleccionado no es válido para tu sucursal.');
                }
            } else {
                $idCajero = null;
            }

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas WHERE id_sucursal = ? AND numero_caja = ?
            ");
            $check->execute([$idSucursalGerente, $numeroCaja]);
            if ($check->fetch() !== false) {
                throw new RuntimeException('Ya existe una caja con ese número en tu sucursal.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO Cajas
                    (id_sucursal, numero_caja, nombre, estado, id_cajero_asignado)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idSucursalGerente,
                $numeroCaja,
                $nombre !== '' ? $nombre : null,
                $estado,
                $idCajero
            ]);

            $_SESSION['flash'] = ['msg' => 'Caja registrada correctamente.'];
            header('Location: cajas.php');
            exit;

        /* -------- Editar -------- */
        } elseif ($action === 'editar') {
            $id           = (int) ($_POST['id_caja'] ?? 0);
            $numeroCaja   = trim($_POST['numero_caja'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            $estado       = $_POST['estado'] ?? 'Activa';
            $idCajero     = (int) ($_POST['id_cajero_asignado'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('Caja inválida.');
            }
            if ($numeroCaja === '' || mb_strlen($numeroCaja) > 20) {
                throw new RuntimeException('El número de caja es obligatorio (máx. 20 caracteres).');
            }
            if (mb_strlen($nombre) > 50) {
                throw new RuntimeException('El nombre no debe superar 50 caracteres.');
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException('Estado inválido.');
            }

            // IDOR: verificar que la caja pertenece a la sucursal del gerente
            $check = $pdo->prepare("
                SELECT 1 FROM Cajas WHERE id_caja = ? AND id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            if ($check->fetch() === false) {
                throw new RuntimeException('Caja inválida.');
            }

            if ($idCajero > 0) {
                $check = $pdo->prepare("
                    SELECT 1 FROM Usuarios
                    WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
                ");
                $check->execute([$idCajero, $idSucursalGerente]);
                if ($check->fetch() === false) {
                    throw new RuntimeException('El cajero seleccionado no es válido para tu sucursal.');
                }
            } else {
                $idCajero = null;
            }

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas
                WHERE id_sucursal = ? AND numero_caja = ? AND id_caja <> ?
            ");
            $check->execute([$idSucursalGerente, $numeroCaja, $id]);
            if ($check->fetch() !== false) {
                throw new RuntimeException('Ya existe otra caja con ese número en tu sucursal.');
            }

            $stmt = $pdo->prepare("
                UPDATE Cajas
                SET numero_caja = ?, nombre = ?, estado = ?, id_cajero_asignado = ?
                WHERE id_caja = ?
            ");
            $stmt->execute([
                $numeroCaja,
                $nombre !== '' ? $nombre : null,
                $estado,
                $idCajero,
                $id
            ]);

            $_SESSION['flash'] = ['msg' => 'Caja actualizada correctamente.'];
            header('Location: cajas.php');
            exit;

        /* -------- Cambiar estado -------- */
        } elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_caja'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Caja inválida.');
            }

            $check = $pdo->prepare("
                SELECT estado FROM Cajas
                WHERE id_caja = ? AND id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            $actual = $check->fetchColumn();
            if ($actual === false) {
                throw new RuntimeException('Caja inválida.');
            }

            $nuevo = $actual === 'Activa' ? 'Inactiva' : 'Activa';
            $stmt = $pdo->prepare("UPDATE Cajas SET estado = ? WHERE id_caja = ?");
            $stmt->execute([$nuevo, $id]);

            $_SESSION['flash'] = ['msg' => $nuevo === 'Activa' ? 'Caja activada.' : 'Caja desactivada.'];
            header('Location: cajas.php');
            exit;

        /* -------- Eliminar -------- */
        } elseif ($action === 'eliminar') {
            $id = (int) ($_POST['id_caja'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Caja inválida.');
            }

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas WHERE id_caja = ? AND id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            if ($check->fetch() === false) {
                throw new RuntimeException('Caja inválida.');
            }

            // Cuando exista Ventas.id_caja, validar aquí que no haya ventas asociadas.
            $stmt = $pdo->prepare("DELETE FROM Cajas WHERE id_caja = ?");
            $stmt->execute([$id]);

            $_SESSION['flash'] = ['msg' => 'Caja eliminada.'];
            header('Location: cajas.php');
            exit;
        }

    } catch (RuntimeException $e) {
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        $errores[] = 'Error al guardar en la base de datos.';
    }
}

/* ------------------------------------------------------------
   Datos
   ------------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT c.id_caja, c.numero_caja, c.nombre, c.estado,
           c.id_cajero_asignado,
           u.nombre AS cajero_nombre,
           c.creado_en
    FROM Cajas c
    LEFT JOIN Usuarios u ON u.id_usuario = c.id_cajero_asignado
    WHERE c.id_sucursal = ?
    ORDER BY c.numero_caja
");
$stmt->execute([$idSucursalGerente]);
$cajas = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT id_usuario, nombre
    FROM Usuarios
    WHERE id_rol = 3 AND id_sucursal = ? AND estado = 'Activo'
    ORDER BY nombre
");
$stmt->execute([$idSucursalGerente]);
$cajerosDisponibles = $stmt->fetchAll();

$totalActivas = 0;
foreach ($cajas as $c) {
    if ($c['estado'] === 'Activa') $totalActivas++;
}

function inicialesCorta(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
}

$pageTitle    = 'Cajas';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Cajas</h1>
        <p>
            <?= $totalActivas ?> activa<?= $totalActivas === 1 ? '' : 's' ?>
            de <?= count($cajas) ?> registrada<?= count($cajas) === 1 ? '' : 's' ?>
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalNuevaCaja()">+ Nueva caja</button>
</div>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<?php if (empty($cajas)): ?>
    <div class="panel">
        <div class="empty-state">
            Aún no hay cajas registradas en tu sucursal. Usa "+ Nueva caja" para agregar la primera.
        </div>
    </div>
<?php else: ?>
    <div class="cajas-grid">
        <?php foreach ($cajas as $c): ?>
            <?php
                $esActiva = $c['estado'] === 'Activa';
                $estadoBadge = [
                    'Activa'        => 'badge-activo',
                    'Inactiva'      => 'badge-inactivo',
                    'Mantenimiento' => 'badge-warning',
                ][$c['estado']] ?? 'badge-inactivo';
                $tieneCajero = !empty($c['id_cajero_asignado']);
            ?>
            <div class="caja-card <?= $esActiva ? '' : 'is-inactiva' ?>">
                <div class="caja-numero" aria-hidden="true">
                    <?= htmlspecialchars($c['numero_caja']) ?>
                </div>

                <?php if (!empty($c['nombre'])): ?>
                    <div class="caja-nombre"><?= htmlspecialchars($c['nombre']) ?></div>
                <?php else: ?>
                    <div class="caja-nombre caja-nombre--muted">Sin nombre</div>
                <?php endif; ?>

                <div style="margin-bottom: 0.5rem;">
                    <span class="badge <?= $estadoBadge ?>"><?= htmlspecialchars($c['estado']) ?></span>
                </div>

                <div class="caja-cajero <?= $tieneCajero ? '' : 'caja-cajero--vacio' ?>">
                    <?php if ($tieneCajero): ?>
                        <div class="caja-cajero-avatar" aria-hidden="true">
                            <?= htmlspecialchars(inicialesCorta($c['cajero_nombre'])) ?>
                        </div>
                        <div class="caja-cajero-info">
                            <span class="caja-cajero-label">Cajero</span>
                            <span class="caja-cajero-nombre"><?= htmlspecialchars($c['cajero_nombre']) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="caja-cajero-info">
                            <span class="caja-cajero-label">Cajero</span>
                            <span class="caja-cajero-nombre">Sin asignar</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="caja-actions">
                    <button type="button" class="btn btn-ghost btn-sm"
                        onclick="abrirModalEditarCaja(this)"
                        data-id="<?= (int) $c['id_caja'] ?>"
                        data-numero="<?= htmlspecialchars($c['numero_caja'], ENT_QUOTES) ?>"
                        data-nombre="<?= htmlspecialchars($c['nombre'] ?? '', ENT_QUOTES) ?>"
                        data-estado="<?= htmlspecialchars($c['estado'], ENT_QUOTES) ?>"
                        data-cajero="<?= (int) ($c['id_cajero_asignado'] ?? 0) ?>"
                    >Editar</button>

                    <form method="POST" style="display:contents"
                          data-confirm="<?= $esActiva
                              ? '¿Desactivar la caja ' . htmlspecialchars($c['numero_caja'], ENT_QUOTES) . '?'
                              : '¿Activar la caja ' . htmlspecialchars($c['numero_caja'], ENT_QUOTES) . '?' ?>"
                          data-confirm-titulo="<?= $esActiva ? 'Desactivar caja' : 'Activar caja' ?>"
                          data-confirm-aceptar="<?= $esActiva ? 'Desactivar' : 'Activar' ?>"
                          <?= $esActiva ? 'data-confirm-peligroso="1"' : '' ?>>
                        <input type="hidden" name="action" value="cambiar_estado">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="id_caja" value="<?= (int) $c['id_caja'] ?>">
                        <button type="submit" class="btn btn-sm <?= $esActiva ? 'btn-danger' : 'btn-success' ?>">
                            <?= $esActiva ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>

                    <form method="POST" style="display:contents"
                          data-confirm="Se eliminará la caja '<?= htmlspecialchars($c['numero_caja'], ENT_QUOTES) ?>' permanentemente. Esta acción no se puede deshacer."
                          data-confirm-titulo="Eliminar caja"
                          data-confirm-aceptar="Eliminar"
                          data-confirm-peligroso="1">
                        <input type="hidden" name="action" value="eliminar">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="id_caja" value="<?= (int) $c['id_caja'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Eliminar caja">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal: Nueva / Editar caja -->
<div class="modal-overlay" id="modalCaja">
    <div class="modal-box">
        <h2 id="tituloModalCaja">Nueva caja</h2>
        <form id="formCaja" method="POST">
            <input type="hidden" name="action" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_caja" value="">

            <div class="form-row">
                <div class="form-group">
                    <label>Número de caja</label>
                    <input type="text" name="numero_caja" placeholder="01, 02, TAQUILLA-A…" maxlength="20" required>
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" required>
                        <option value="Activa">Activa</option>
                        <option value="Inactiva">Inactiva</option>
                        <option value="Mantenimiento">Mantenimiento</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Nombre (opcional)</label>
                <input type="text" name="nombre" placeholder="Ej. Caja principal, Caja express" maxlength="50">
            </div>

            <div class="form-group">
                <label>Cajero asignado (opcional)</label>
                <select name="id_cajero_asignado">
                    <option value="0">Sin asignar</option>
                    <?php foreach ($cajerosDisponibles as $cj): ?>
                        <option value="<?= (int) $cj['id_usuario'] ?>"><?= htmlspecialchars($cj['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="display:block; color:#94a3b8; font-size:0.75rem; margin-top:0.3rem;">
                    Solo cajeros activos de tu sucursal.
                </small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCaja')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>