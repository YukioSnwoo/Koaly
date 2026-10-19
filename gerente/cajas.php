<?php
require_once __DIR__ . '/guardia.php';

$ESTADOS_VALIDOS = ['Activa', 'Inactiva', 'Mantenimiento'];

/**
 * Valida el cajero elegido para una caja y devuelve su id (o null).
 * Regla: una caja Activa necesita un cajero asignado y en estado Activo.
 */
function validarCajeroDeCaja(PDO $pdo, int $idCajero, int $idSucursal, string $estadoCaja): ?int
{
    if ($idCajero <= 0) {
        if ($estadoCaja === 'Activa') {
            throw new RuntimeException(t('error.caja_sin_cajero'));
        }
        return null;
    }

    $check = $pdo->prepare("
        SELECT estado FROM Usuarios
        WHERE id_usuario = ? AND id_sucursal = ? AND id_rol = 3
    ");
    $check->execute([$idCajero, $idSucursal]);
    $estadoCajero = $check->fetchColumn();
    if ($estadoCajero === false) {
        throw new RuntimeException(t('error.cajero_invalido_sucursal'));
    }
    if ($estadoCaja === 'Activa' && $estadoCajero !== 'Activo') {
        throw new RuntimeException(t('error.cajero_no_activo', ['estado' => t('estado_usuario.' . $estadoCajero)]));
    }
    return $idCajero;
}

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
            throw new RuntimeException(t('error.formulario_expirado'));
        }

        /* -------- Crear -------- */
        if ($action === 'crear') {
            $numeroCaja   = trim($_POST['numero_caja'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            $estado       = $_POST['estado'] ?? 'Activa';
            $idCajero     = (int) ($_POST['id_cajero_asignado'] ?? 0);

            if ($numeroCaja === '' || mb_strlen($numeroCaja) > 20) {
                throw new RuntimeException(t('error.numero_caja'));
            }
            if (mb_strlen($nombre) > 50) {
                throw new RuntimeException(t('error.nombre_caja'));
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException(t('error.estado_invalido'));
            }
            $idCajero = validarCajeroDeCaja($pdo, $idCajero, $idSucursalGerente, $estado);

            if ($idCajero !== null) {
                $check = $pdo->prepare("SELECT numero_caja FROM Cajas WHERE id_cajero_asignado = ? AND id_sucursal = ?");
                $check->execute([$idCajero, $idSucursalGerente]);
                $otraCaja = $check->fetchColumn();
                if ($otraCaja !== false) {
                    throw new RuntimeException(t('error.cajero_ya_asignado', ['caja' => $otraCaja]));
                }
            }

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas WHERE id_sucursal = ? AND numero_caja = ?
            ");
            $check->execute([$idSucursalGerente, $numeroCaja]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('error.caja_duplicada'));
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

            $_SESSION['flash'] = ['msg' => t('cajas.creada')];
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
                throw new RuntimeException(t('error.caja_invalida'));
            }
            if ($numeroCaja === '' || mb_strlen($numeroCaja) > 20) {
                throw new RuntimeException(t('error.numero_caja'));
            }
            if (mb_strlen($nombre) > 50) {
                throw new RuntimeException(t('error.nombre_caja'));
            }
            if (!in_array($estado, $ESTADOS_VALIDOS, true)) {
                throw new RuntimeException(t('error.estado_invalido'));
            }

            // IDOR: verificar que la caja pertenece a la sucursal del gerente
            $check = $pdo->prepare("
                SELECT 1 FROM Cajas WHERE id_caja = ? AND id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            if ($check->fetch() === false) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            $idCajero = validarCajeroDeCaja($pdo, $idCajero, $idSucursalGerente, $estado);

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas
                WHERE id_sucursal = ? AND numero_caja = ? AND id_caja <> ?
            ");
            $check->execute([$idSucursalGerente, $numeroCaja, $id]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('error.caja_duplicada'));
            }

            // Desasignar de otra caja + actualizar esta: todo o nada
            $pdo->beginTransaction();

            // Si el cajero ya estaba en OTRA caja, esa caja se queda sin cajero:
            // se desasigna y pasa a Inactiva (una caja activa necesita cajero).
            $mensajeExtra = '';
            if ($idCajero !== null) {
                $stmt = $pdo->prepare("
                    UPDATE Cajas
                    SET id_cajero_asignado = NULL,
                        estado = IF(estado = 'Activa', 'Inactiva', estado)
                    WHERE id_cajero_asignado = ? AND id_sucursal = ? AND id_caja <> ?
                ");
                $stmt->execute([$idCajero, $idSucursalGerente, $id]);
                if ($stmt->rowCount() > 0) {
                    $mensajeExtra = ' ' . t('cajas.anterior_inactiva');
                }
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

            $pdo->commit();

            $_SESSION['flash'] = ['msg' => t('cajas.actualizada') . $mensajeExtra];
            header('Location: cajas.php');
            exit;

        /* -------- Cambiar estado -------- */
        } elseif ($action === 'cambiar_estado') {
            $id = (int) ($_POST['id_caja'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            $check = $pdo->prepare("
                SELECT c.estado, c.id_cajero_asignado
                FROM Cajas c
                WHERE c.id_caja = ? AND c.id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            $caja = $check->fetch();
            if ($caja === false) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            $nuevo = $caja['estado'] === 'Activa' ? 'Inactiva' : 'Activa';
            if ($nuevo === 'Activa') {
                validarCajeroDeCaja($pdo, (int) $caja['id_cajero_asignado'], $idSucursalGerente, 'Activa');
            }
            $stmt = $pdo->prepare("UPDATE Cajas SET estado = ? WHERE id_caja = ?");
            $stmt->execute([$nuevo, $id]);

            $_SESSION['flash'] = ['msg' => $nuevo === 'Activa' ? t('cajas.activada') : t('cajas.desactivada')];
            header('Location: cajas.php');
            exit;

        /* -------- Eliminar -------- */
        } elseif ($action === 'eliminar') {
            $id = (int) ($_POST['id_caja'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            $check = $pdo->prepare("
                SELECT 1 FROM Cajas WHERE id_caja = ? AND id_sucursal = ?
            ");
            $check->execute([$id, $idSucursalGerente]);
            if ($check->fetch() === false) {
                throw new RuntimeException(t('error.caja_invalida'));
            }

            // Una caja con ventas no se borra: perdería el historial (y la FK lo impide).
            $check = $pdo->prepare("SELECT COUNT(*) FROM Ventas WHERE id_caja = ?");
            $check->execute([$id]);
            $numVentas = (int) $check->fetchColumn();
            if ($numVentas > 0) {
                throw new RuntimeException(tn('error.caja_con_ventas', $numVentas));
            }

            $stmt = $pdo->prepare("DELETE FROM Cajas WHERE id_caja = ?");
            $stmt->execute([$id]);

            $_SESSION['flash'] = ['msg' => t('cajas.eliminada')];
            header('Location: cajas.php');
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

/* ------------------------------------------------------------
   Datos
   ------------------------------------------------------------ */
$stmt = $pdo->prepare("
    SELECT c.id_caja, c.numero_caja, c.nombre, c.estado,
           c.id_cajero_asignado,
           u.nombre AS cajero_nombre,
           u.estado AS cajero_estado,
           c.creado_en,
           (SELECT COUNT(*) FROM Ventas v WHERE v.id_caja = c.id_caja) AS num_ventas,
           (SELECT COALESCE(SUM(v.total), 0) FROM Ventas v
             WHERE v.id_caja = c.id_caja AND v.fecha_hora >= CURDATE()) AS monto_hoy
    FROM Cajas c
    LEFT JOIN Usuarios u ON u.id_usuario = c.id_cajero_asignado
    WHERE c.id_sucursal = ?
    ORDER BY c.numero_caja
");
$stmt->execute([$idSucursalGerente]);
$cajas = $stmt->fetchAll();

// Cajeros activos + los que ya tienen caja aunque no estén activos (vacaciones,
// licencia…): si no aparecieran, editar la caja los desasignaría sin avisar.
$stmt = $pdo->prepare("
    SELECT id_usuario, nombre, estado
    FROM Usuarios
    WHERE id_rol = 3 AND id_sucursal = ?
      AND (estado = 'Activo'
           OR id_usuario IN (SELECT id_cajero_asignado FROM Cajas
                             WHERE id_sucursal = ? AND id_cajero_asignado IS NOT NULL))
    ORDER BY nombre
");
$stmt->execute([$idSucursalGerente, $idSucursalGerente]);
$cajerosDisponibles = $stmt->fetchAll();

// Mapa de cajeros ya asignados a alguna caja de esta sucursal
// (para avisar cuando se intente reasignar)
$stmt = $pdo->prepare("
    SELECT id_cajero_asignado, id_caja, numero_caja
    FROM Cajas
    WHERE id_sucursal = ? AND id_cajero_asignado IS NOT NULL
");
$stmt->execute([$idSucursalGerente]);
$cajerosConCaja = [];
foreach ($stmt->fetchAll() as $row) {
    $cajerosConCaja[(int) $row['id_cajero_asignado']] = [
        'id_caja'     => (int) $row['id_caja'],
        'numero_caja' => $row['numero_caja'],
    ];
}

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

$pageTitle    = t('cajas.titulo');
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e(t('cajas.titulo')) ?></h1>
        <p>
            <?= e(t('cajas.resumen', ['activas' => numero($totalActivas), 'total' => numero(count($cajas))])) ?>
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalNuevaCaja()">+ <?= e(t('cajas.nueva')) ?></button>
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
                    <div class="caja-nombre caja-nombre--muted"><?= e(t('cajas.sin_nombre')) ?></div>
                <?php endif; ?>

                <div style="margin-bottom: 0.5rem;">
                    <span class="badge <?= $estadoBadge ?>"><?= e(t('estado_caja.' . $c['estado'])) ?></span>
                    <?php if ($esActiva && !$tieneCajero): ?>
                        <span class="badge badge-danger" title="<?= e(t('cajas.sin_cajero_ayuda')) ?>"><?= e(t('cajas.sin_cajero')) ?></span>
                    <?php elseif ($esActiva && $c['cajero_estado'] !== 'Activo'): ?>
                        <span class="badge badge-warning" title="<?= e(t('cajas.cajero_inactivo_ayuda')) ?>"><?= e(t('cajas.cajero_en', ['estado' => t('estado_usuario.' . $c['cajero_estado'])])) ?></span>
                    <?php endif; ?>
                </div>

                <div class="caja-cajero <?= $tieneCajero ? '' : 'caja-cajero--vacio' ?>">
                    <?php if ($tieneCajero): ?>
                        <div class="caja-cajero-avatar" aria-hidden="true">
                            <?= htmlspecialchars(inicialesCorta($c['cajero_nombre'])) ?>
                        </div>
                        <div class="caja-cajero-info">
                            <span class="caja-cajero-label"><?= e(t('cajas.cajero')) ?></span>
                            <span class="caja-cajero-nombre"><?= htmlspecialchars($c['cajero_nombre']) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="caja-cajero-info">
                            <span class="caja-cajero-label"><?= e(t('cajas.cajero')) ?></span>
                            <span class="caja-cajero-nombre"><?= e(t('cajas.sin_asignar')) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="caja-ventas">
                    <span><?= e(t('cajas.ventas_hoy')) ?>: <strong><?= dinero($c['monto_hoy']) ?></strong></span>
                    <span>·</span>
                    <span><?= e(tn('cajas.ventas_total', (int) $c['num_ventas'])) ?></span>
                </div>

                <div class="caja-actions">
                    <button type="button" class="btn btn-ghost btn-sm"
                        onclick="abrirModalEditarCaja(this)"
                        data-id="<?= (int) $c['id_caja'] ?>"
                        data-numero="<?= htmlspecialchars($c['numero_caja'], ENT_QUOTES) ?>"
                        data-nombre="<?= htmlspecialchars($c['nombre'] ?? '', ENT_QUOTES) ?>"
                        data-estado="<?= htmlspecialchars($c['estado'], ENT_QUOTES) ?>"
                        data-cajero="<?= (int) ($c['id_cajero_asignado'] ?? 0) ?>"
                    ><?= e(t('comun.editar')) ?></button>

                    <form method="POST" style="display:contents"
                          data-confirm="<?= e(t($esActiva ? 'cajas.confirmar_desactivar' : 'cajas.confirmar_activar', ['caja' => $c['numero_caja']])) ?>"
                          data-confirm-titulo="<?= e(t($esActiva ? 'cajas.desactivar_titulo' : 'cajas.activar_titulo')) ?>"
                          data-confirm-aceptar="<?= e(t($esActiva ? 'comun.desactivar' : 'comun.activar')) ?>"
                          <?= $esActiva ? 'data-confirm-peligroso="1"' : '' ?>>
                        <input type="hidden" name="action" value="cambiar_estado">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="id_caja" value="<?= (int) $c['id_caja'] ?>">
                        <button type="submit" class="btn btn-sm <?= $esActiva ? 'btn-danger' : 'btn-success' ?>">
                            <?= e(t($esActiva ? 'comun.desactivar' : 'comun.activar')) ?>
                        </button>
                    </form>

                    <?php if ((int) $c['num_ventas'] === 0): ?>
                    <form method="POST" style="display:contents"
                          data-confirm="<?= e(t('cajas.confirmar_eliminar', ['caja' => $c['numero_caja']])) ?>"
                          data-confirm-titulo="<?= e(t('cajas.eliminar_titulo')) ?>"
                          data-confirm-aceptar="<?= e(t('comun.eliminar')) ?>"
                          data-confirm-peligroso="1">
                        <input type="hidden" name="action" value="eliminar">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="id_caja" value="<?= (int) $c['id_caja'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="<?= e(t('cajas.eliminar_titulo')) ?>" aria-label="<?= e(t('cajas.eliminar_titulo')) ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal: Nueva / Editar caja -->
<div class="modal-overlay" id="modalCaja">
    <div class="modal-box">
        <h2 id="tituloModalCaja"><?= e(t('cajas.nueva')) ?></h2>
        <form id="formCaja" method="POST">
            <input type="hidden" name="action" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_caja" value="">

            <div class="form-row">
                <div class="form-group">
                    <label><?= e(t('cajas.campo_numero')) ?></label>
                    <input type="text" name="numero_caja" placeholder="<?= e(t('cajas.campo_numero_ej')) ?>" maxlength="20" required>
                </div>
                <div class="form-group">
                    <label><?= e(t('cat.col_estado')) ?></label>
                    <select name="estado" required>
                        <?php foreach ($ESTADOS_VALIDOS as $est): ?>
                            <option value="<?= $est ?>"><?= e(t('estado_caja.' . $est)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label><?= e(t('cajas.campo_nombre')) ?></label>
                <input type="text" name="nombre" placeholder="<?= e(t('cajas.campo_nombre_ej')) ?>" maxlength="50">
            </div>

            <div class="form-group">
                <label><?= e(t('cajas.campo_cajero')) ?></label>
                <select name="id_cajero_asignado">
                    <option value="0"><?= e(t('cajas.sin_asignar')) ?></option>
                    <?php foreach ($cajerosDisponibles as $cj):
                        $info = $cajerosConCaja[(int) $cj['id_usuario']] ?? null;
                    ?>
                        <option value="<?= (int) $cj['id_usuario'] ?>"
                                data-caja-actual="<?= $info ? htmlspecialchars($info['numero_caja'], ENT_QUOTES) : '' ?>"
                                data-caja-id="<?= $info ? (int) $info['id_caja'] : 0 ?>">
                            <?= e($cj['nombre']) ?><?= $cj['estado'] !== 'Activo' ? ' (' . e(t('estado_usuario.' . $cj['estado'])) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="avisoReasignacion" class="aviso-reasignacion" aria-live="polite"></div>
                <small class="texto-tenue" style="display:block; font-size:0.75rem; margin-top:0.3rem;">
                    <?= e(t('cajas.campo_cajero_ayuda')) ?>
                </small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalCaja')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('comun.guardar')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>