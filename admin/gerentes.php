<?php
ob_start();
require 'header.php';
?>
<?php require 'sidebar.php'; ?>

<?php
require_once __DIR__ . '/sucursal_gerente.php';
require_once __DIR__ . '/profanity_filter.php';

$error = '';

// Mismos estados que usa el gerente para sus cajeros. Solo "Activo" puede iniciar sesión.
const ESTADOS_GERENTE = [
    'Activo'      => ['icono' => 'user-check',  'badge' => 'badge--success'],
    'Vacaciones'  => ['icono' => 'beach',       'badge' => 'badge--warning'],
    'Incapacidad' => ['icono' => 'first-aid-kit', 'badge' => 'badge--warning'],
    'Licencia'    => ['icono' => 'user-pause',  'badge' => 'badge--warning'],
    'Baja'        => ['icono' => 'user-off',    'badge' => 'badge--danger'],
];

// Guarda en el historial cada cambio de estado. Si la tabla aún no existe (falta correr
// sql/009), solo se anota en el log y el cambio de estado sigue funcionando.
function registrarCambioEstado(PDO $pdo, int $idGerente, ?string $anterior, string $nuevo): void
{
    try {
        $pdo->prepare("INSERT INTO Historial_Estado_Gerente (id_gerente, estado_anterior, estado_nuevo, id_admin) VALUES (?, ?, ?, ?)")
            ->execute([$idGerente, $anterior, $nuevo, (int) ($_SESSION['id_usuario'] ?? 0) ?: null]);
    } catch (PDOException $e) {
        error_log('gerentes.php historial: ' . $e->getMessage());
    }
}

// Procesar acciones POST antes de enviar contenido al navegador.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfValido()) {
            throw new RuntimeException(t('error.formulario_expirado'));
        }

        if ($action === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $idSucursal = (int) ($_POST['id_sucursal'] ?? 0);

            if ($nombre === '') {
                throw new RuntimeException(t('ger.error_nombre'));
            }
            try {
                rechazarSiProfano($nombre);
            } catch (RuntimeException) {
                throw new RuntimeException(t('error.profano'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(t('ger.error_email'));
            }
            if (strlen($password) < 8) {
                throw new RuntimeException(t('ger.error_password'));
            }
            if ($idSucursal > 0 && !sucursalSinGerente($pdo, $idSucursal)) {
                throw new RuntimeException(t('error.sucursal_ocupada'));
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('ger.error_email_duplicado'));
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
            registrarCambioEstado($pdo, (int) $pdo->lastInsertId(), null, 'Activo');

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
                throw new RuntimeException(t('ger.error_nombre'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(t('ger.error_email'));
            }

            $existe = $pdo->prepare("SELECT 1 FROM Usuarios WHERE id_usuario = ? AND id_rol = 2");
            $existe->execute([$idGerente]);
            if ($existe->fetch() === false) {
                throw new RuntimeException(t('error.gerente_no_existe'));
            }

            $check = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ?");
            $check->execute([$email, $idGerente]);
            if ($check->fetch() !== false) {
                throw new RuntimeException(t('ger.error_email_duplicado'));
            }

            $pdo->prepare("UPDATE Usuarios SET nombre = ?, email = ? WHERE id_usuario = ? AND id_rol = 2")
                ->execute([$nombre, $email, $idGerente]);

            header('Location: gerentes.php');
            exit;
        }

        if ($action === 'cambiar_estado') {
            $idGerente = (int) ($_POST['id_usuario'] ?? 0);
            $estado = (string) ($_POST['estado'] ?? '');

            if (!isset(ESTADOS_GERENTE[$estado])) {
                throw new RuntimeException(t('ger.error_estado'));
            }
            $stmt = $pdo->prepare("SELECT estado FROM Usuarios WHERE id_usuario = ? AND id_rol = 2");
            $stmt->execute([$idGerente]);
            $estadoActual = $stmt->fetchColumn();
            if ($estadoActual === false) {
                throw new RuntimeException(t('error.gerente_no_existe'));
            }

            if ($estado !== $estadoActual) {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE Usuarios SET estado = ?, fecha_inicio_estado = ? WHERE id_usuario = ? AND id_rol = 2")
                    ->execute([$estado, date('Y-m-d'), $idGerente]);
                registrarCambioEstado($pdo, $idGerente, (string) $estadoActual, $estado);

                // Con la baja deja su sucursal (que queda inactiva hasta tener otro gerente).
                // En vacaciones, incapacidad o licencia la conserva: es temporal.
                if ($estado === 'Baja') {
                    asignarSucursalAGerente($pdo, $idGerente, 0);
                }
                $pdo->commit();
            }

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
        $error = t('error.guardar');
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

// Historial de estados por gerente (el más reciente primero) para el modal de cada fila.
$historial = [];
try {
    $filasHist = $pdo->query("
        SELECT h.id_gerente, h.estado_anterior, h.estado_nuevo, h.fecha_cambio,
               a.nombre AS admin_nombre
        FROM Historial_Estado_Gerente h
        LEFT JOIN Usuarios a ON a.id_usuario = h.id_admin
        ORDER BY h.fecha_cambio DESC, h.id_historial DESC
    ")->fetchAll();
    foreach ($filasHist as $h) {
        $historial[(int) $h['id_gerente']][] = [
            'anterior' => $h['estado_anterior'],
            'nuevo'    => $h['estado_nuevo'],
            'fecha'    => fecha((string) $h['fecha_cambio']),
            'admin'    => $h['admin_nombre'],
        ];
    }
} catch (PDOException $e) {
    error_log('gerentes.php historial: ' . $e->getMessage());
}

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
        <h1><?= e(t('nav.gerentes')) ?></h1>
        <p><?= e(t('ger.subtitulo')) ?></p>
    </div>
    <button class="btn btn--primary" onclick="openModal('modalGerente')"><i class="ti ti-user-plus"></i><?= e(t('ger.registrar')) ?></button>
</div>

<?php if ($error !== ''): ?>
    <div style="margin-top:1rem;padding:10px 14px;border-radius:var(--radius-lg);background:var(--bg-danger);color:var(--text-danger)"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-wrap" style="margin-top:1rem">
    <table class="table" id="tblGerentes">
        <thead><tr>
            <th><?= e(t('comun.nombre')) ?></th><th><?= e(t('comun.correo')) ?></th><th><?= e(t('ger.col_sucursal')) ?></th><th><?= e(t('comun.estado')) ?></th><th><?= e(t('ger.col_acciones')) ?></th>
        </tr></thead>
        <tbody>
            <?php if (empty($gerentes)): ?>
                <tr><td colspan="5" style="color:var(--text-muted);text-align:center"><?= e(t('ger.vacio')) ?></td></tr>
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
                        <td><?= $g['sucursal_nombre'] ? htmlspecialchars($g['sucursal_nombre']) : '<span style="color:var(--text-muted)">' . e(t('comun.sin_asignar')) . '</span>' ?></td>
                        <td>
                            <span class="badge <?= ESTADOS_GERENTE[(string) $g['estado']]['badge'] ?? 'badge--warning' ?>">
                                <?= e(tValor('estado_usuario', $g['estado'], t('ger.sin_estado'))) ?>
                            </span>
                        </td>
                        <td>
                            <button class="icon-btn" title="<?= e(t('comun.editar')) ?>" onclick="editarGerente(this)"
                                data-id="<?= (int) $g['id_usuario'] ?>"
                                data-nombre="<?= htmlspecialchars($g['nombre'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($g['email'], ENT_QUOTES) ?>"><i class="ti ti-edit"></i></button>
                            <button class="icon-btn" title="<?= e(t('ger.asignar_sucursal')) ?>" onclick="asignarSucursal(this)"
                                data-id="<?= (int) $g['id_usuario'] ?>"
                                data-nombre="<?= htmlspecialchars($g['nombre'], ENT_QUOTES) ?>"
                                data-sucursal="<?= $g['id_sucursal'] !== null ? (int) $g['id_sucursal'] : '' ?>"><i class="ti ti-arrows-exchange"></i></button>
                            <button class="icon-btn" title="<?= e(t('ger.historial')) ?>" onclick="verHistorial(this)"
                                data-id="<?= (int) $g['id_usuario'] ?>"
                                data-nombre="<?= htmlspecialchars($g['nombre'], ENT_QUOTES) ?>"><i class="ti ti-history"></i></button>
                            <button class="icon-btn" title="<?= e(t('ger.cambiar_estado')) ?>" aria-haspopup="menu" aria-expanded="false"
                                onclick="abrirMenuEstado(this)"
                                data-id="<?= (int) $g['id_usuario'] ?>"
                                data-nombre="<?= htmlspecialchars($g['nombre'], ENT_QUOTES) ?>"
                                data-estado="<?= htmlspecialchars((string) $g['estado'], ENT_QUOTES) ?>"
                                data-sucursal="<?= htmlspecialchars((string) ($g['sucursal_nombre'] ?? ''), ENT_QUOTES) ?>"><i class="ti ti-dots-vertical"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal: historial de estados del gerente -->
<div id="modalHistorial" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="historialTitulo"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:460px;max-width:90vw;max-height:85vh;display:flex;flex-direction:column">
        <h2 id="historialTitulo" style="font-size:16px;font-weight:500;margin:0 0 .25rem"><?= e(t('ger.historial')) ?></h2>
        <p id="historialNombre" style="font-size:13px;color:var(--text-secondary);margin:0 0 1rem"></p>
        <div id="historialLista" style="overflow-y:auto;display:flex;flex-direction:column;gap:10px"></div>
        <div style="display:flex;justify-content:flex-end;margin-top:1.25rem">
            <button type="button" class="btn btn--ghost" onclick="closeModal('modalHistorial')"><?= e(t('comun.cerrar')) ?></button>
        </div>
    </div>
</div>

<!-- Modal: Registrar Gerente -->
<div id="modalGerente" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 1rem"><?= e(t('ger.registrar')) ?></h2>
        <form method="POST" action="gerentes.php">
            <input type="hidden" name="action" value="crear">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px"><?= e(t('ger.nombre_completo')) ?></label>
                <input type="text" name="nombre" class="input" style="width:100%" placeholder="<?= e(t('ger.nombre_ej')) ?>" required>
            </div>
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px"><?= e(t('ger.email')) ?></label>
                <input type="email" name="email" class="input" style="width:100%" placeholder="<?= e(t('ger.email_ej')) ?>" required>
            </div>
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px"><?= e(t('ger.password')) ?></label>
                <div class="campo-contrasena">
                    <input type="password" name="password" class="input" style="width:100%" placeholder="<?= e(t('ger.password_ej')) ?>" minlength="8" required>
                    <button type="button" class="ver-contrasena" aria-pressed="false" aria-label="<?= e(t('comun.ver_contrasena')) ?>" title="<?= e(t('comun.ver_contrasena')) ?>">
                        <i class="ti ti-eye" aria-hidden="true"></i><i class="ti ti-eye-off" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px"><?= e(t('comun.sucursal')) ?></label>
                <select name="id_sucursal" class="select" style="width:100%">
                    <option value=""><?= e(t('comun.sin_asignar')) ?></option>
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id_sucursal'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="display:block;color:var(--text-muted);font-size:12px;margin-top:4px"><?= e(t('ger.registrar_ayuda')) ?></span>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalGerente')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn--primary"><?= e(t('ger.registrar_boton')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Gerente -->
<div id="modalEditarGerente" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 1rem"><?= e(t('ger.editar')) ?></h2>
        <form method="POST" action="gerentes.php" id="formEditarGerente">
            <input type="hidden" name="action" value="editar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_usuario" value="">
            <div style="margin-bottom:10px">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px"><?= e(t('ger.nombre_completo')) ?></label>
                <input type="text" name="nombre" class="input" style="width:100%" required>
            </div>
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px"><?= e(t('ger.email')) ?></label>
                <input type="email" name="email" class="input" style="width:100%" required>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalEditarGerente')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn--primary"><?= e(t('comun.guardar')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Asignar sucursal -->
<div id="modalAsignarSucursal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 .5rem"><?= e(t('ger.asignar_sucursal')) ?></h2>
        <p style="color:var(--text-secondary);font-size:13px;margin:0 0 1rem"><?= e(t('ger.asignar_gerente')) ?> <strong id="asignarNombreGerente"></strong></p>
        <form method="POST" action="gerentes.php" id="formAsignarSucursal">
            <input type="hidden" name="action" value="asignar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_usuario" value="">
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px"><?= e(t('comun.sucursal')) ?></label>
                <select name="id_sucursal" class="select" style="width:100%">
                    <option value=""><?= e(t('comun.sin_asignar')) ?></option>
                    <?php foreach ($todasSucursales as $s): ?>
                        <option value="<?= (int) $s['id_sucursal'] ?>" data-gerente="<?= $s['id_gerente'] !== null ? (int) $s['id_gerente'] : '' ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="display:block;color:var(--text-muted);font-size:12px;margin-top:4px"><?= e(t('ger.asignar_ayuda')) ?></span>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalAsignarSucursal')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn--primary"><?= e(t('comun.guardar')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Menú: cambiar estado del gerente (uno solo, se coloca junto al botón ⋮ de la fila) -->
<div class="menu-estado" id="menuEstado" role="menu" hidden>
    <div class="menu-estado__titulo"><?= e(t('ger.cambiar_estado')) ?></div>
    <?php foreach (ESTADOS_GERENTE as $estado => $info): ?>
        <?php if ($estado === 'Baja'): ?><div class="menu-estado__sep"></div><?php endif; ?>
        <button type="button" role="menuitem" data-estado="<?= $estado ?>"
                class="menu-estado__item<?= $estado === 'Baja' ? ' menu-estado__item--peligro' : '' ?>">
            <i class="ti ti-<?= $info['icono'] ?>"></i><?= e($estado === 'Baja' ? t('ger.dar_baja') : tValor('estado_usuario', $estado)) ?>
        </button>
    <?php endforeach; ?>
</div>
<form method="POST" action="gerentes.php" id="formEstado" hidden>
    <input type="hidden" name="action" value="cambiar_estado">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="id_usuario" value="">
    <input type="hidden" name="estado" value="">
</form>

<!-- Modal: confirmar el cambio de estado -->
<div id="modalConfirmarEstado" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="confirmarEstadoTitulo"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:130;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <div class="confirmar-estado__icono" id="confirmarEstadoIcono"><i class="ti"></i></div>
        <h2 id="confirmarEstadoTitulo" style="font-size:16px;font-weight:500;margin:0 0 .5rem"></h2>
        <p class="confirmar-estado__texto" id="confirmarEstadoTexto"></p>
        <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="btn btn--ghost" onclick="closeModal('modalConfirmarEstado')"><?= e(t('comun.cancelar')) ?></button>
            <button type="button" class="btn btn--primary" id="confirmarEstadoAceptar"></button>
        </div>
    </div>
</div>

<script>
(function () {
    const menu = document.getElementById('menuEstado');
    const form = document.getElementById('formEstado');
    const modal = document.getElementById('modalConfirmarEstado');
    const aceptar = document.getElementById('confirmarEstadoAceptar');
    let boton = null;

    // Texto sin innerHTML: el nombre y la sucursal vienen de la base.
    // Cada {variable} de la plantilla traducida se pinta en negritas.
    function parrafo(plantilla, vars) {
        const p = document.getElementById('confirmarEstadoTexto');
        p.replaceChildren(...plantilla.split(/(\{\w+\})/).map(parte => {
            const clave = parte.match(/^\{(\w+)\}$/);
            if (!clave || !(clave[1] in vars)) return document.createTextNode(parte);
            const b = document.createElement('strong');
            b.textContent = vars[clave[1]];
            return b;
        }));
    }

    function confirmarCambio(btn, estado, icono) {
        const nombre = btn.dataset.nombre;
        const sucursal = btn.dataset.sucursal;
        const nombreEstado = NOMBRES_ESTADO[estado] || estado;
        const baja = estado === 'Baja';
        let titulo, texto;
        if (baja) {
            titulo = tj('baja_titulo');
            texto = tj('baja_texto') + (sucursal ? ' ' + tj('baja_sucursal') : '');
        } else if (estado === 'Activo') {
            titulo = tj('activar_titulo');
            texto = tj('activar_texto') + (sucursal ? '' : ' ' + tj('activar_sin_sucursal'));
        } else {
            titulo = tj('estado_titulo', { estado: nombreEstado });
            texto = tj(sucursal ? 'estado_texto_sucursal' : 'estado_texto');
        }

        document.getElementById('confirmarEstadoTitulo').textContent = titulo;
        parrafo(texto, { nombre, sucursal, estado: nombreEstado.toLowerCase() });
        const caja = document.getElementById('confirmarEstadoIcono');
        caja.classList.toggle('confirmar-estado__icono--peligro', baja);
        caja.firstElementChild.className = 'ti ti-' + icono;
        aceptar.className = 'btn ' + (baja ? 'btn--danger' : 'btn--primary');
        aceptar.textContent = tj(baja ? 'dar_baja' : 'confirmar');
        aceptar.onclick = () => {
            aceptar.disabled = true;
            form.id_usuario.value = btn.dataset.id;
            form.estado.value = estado;
            form.submit();
        };
        aceptar.disabled = false;
        openModal('modalConfirmarEstado');
        aceptar.focus();
    }

    function cerrar() {
        if (!boton) return;
        menu.hidden = true;
        boton.setAttribute('aria-expanded', 'false');
        boton = null;
    }

    window.abrirMenuEstado = function (btn) {
        if (boton === btn) { cerrar(); return; }
        cerrar();
        boton = btn;
        // El estado actual queda marcado y no se puede volver a elegir
        menu.querySelectorAll('[data-estado]').forEach(item => {
            item.disabled = item.dataset.estado === btn.dataset.estado;
        });
        menu.hidden = false;
        // Alineado al borde derecho del botón; hacia arriba si no cabe abajo
        const r = btn.getBoundingClientRect();
        const alto = menu.offsetHeight;
        const top = r.bottom + 4 + alto > window.innerHeight ? r.top - alto - 4 : r.bottom + 4;
        menu.style.top = Math.max(8, top) + 'px';
        menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
        btn.setAttribute('aria-expanded', 'true');
        const primero = menu.querySelector('[data-estado]:not(:disabled)');
        if (primero) primero.focus();
    };

    menu.addEventListener('click', e => {
        const item = e.target.closest('[data-estado]');
        if (!item || item.disabled || !boton) return;
        const btn = boton;
        cerrar();
        confirmarCambio(btn, item.dataset.estado, item.querySelector('i').className.replace('ti ti-', ''));
    });

    document.addEventListener('click', e => {
        if (boton && !menu.contains(e.target) && !boton.contains(e.target)) cerrar();
    });
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (modal.style.display === 'flex') closeModal('modalConfirmarEstado');
        else if (boton) { const b = boton; cerrar(); b.focus(); }
    });
    window.addEventListener('scroll', cerrar, true);
    window.addEventListener('resize', cerrar);
})();

function editarGerente(btn) {
    const form = document.getElementById('formEditarGerente');
    form.querySelector('[name="id_usuario"]').value = btn.dataset.id;
    form.querySelector('[name="nombre"]').value = btn.dataset.nombre;
    form.querySelector('[name="email"]').value = btn.dataset.email;
    openModal('modalEditarGerente');
}

const HISTORIAL_ESTADOS = <?= json_encode($historial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const BADGES_ESTADO = <?= json_encode(array_map(fn($i) => $i['badge'], ESTADOS_GERENTE)) ?>;
const NOMBRES_ESTADO = <?= json_encode(array_combine(array_keys(ESTADOS_GERENTE), array_map(fn($estado) => tValor('estado_usuario', $estado), array_keys(ESTADOS_GERENTE))), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function verHistorial(btn) {
    const lista = document.getElementById('historialLista');
    const registros = HISTORIAL_ESTADOS[btn.dataset.id] || [];
    document.getElementById('historialNombre').textContent = btn.dataset.nombre;
    lista.replaceChildren();

    if (registros.length === 0) {
        const vacio = document.createElement('p');
        vacio.style.cssText = 'color:var(--text-muted);font-size:13px';
        vacio.textContent = tj('historial_vacio');
        lista.appendChild(vacio);
    }

    registros.forEach(r => {
        const fila = document.createElement('div');
        fila.style.cssText = 'display:flex;flex-direction:column;gap:4px;padding-bottom:10px;border-bottom:1px solid var(--border)';

        const cambio = document.createElement('div');
        cambio.style.cssText = 'display:flex;align-items:center;gap:8px;flex-wrap:wrap';
        const etiqueta = (estado) => {
            const b = document.createElement('span');
            b.className = 'badge ' + (BADGES_ESTADO[estado] || 'badge--warning');
            b.textContent = NOMBRES_ESTADO[estado] || estado;
            return b;
        };
        if (r.anterior) {
            cambio.appendChild(etiqueta(r.anterior));
            const flecha = document.createElement('i');
            flecha.className = 'ti ti-arrow-right';
            flecha.style.color = 'var(--text-muted)';
            cambio.appendChild(flecha);
        } else {
            const inicio = document.createElement('span');
            inicio.style.cssText = 'font-size:12px;color:var(--text-secondary)';
            inicio.textContent = tj('historial_inicial');
            cambio.appendChild(inicio);
        }
        cambio.appendChild(etiqueta(r.nuevo));

        const detalle = document.createElement('div');
        detalle.style.cssText = 'font-size:12px;color:var(--text-secondary)';
        detalle.textContent = r.fecha + (r.admin ? ' · ' + tj('historial_por', { admin: r.admin }) : '');

        fila.append(cambio, detalle);
        lista.appendChild(fila);
    });

    openModal('modalHistorial');
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
