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
            rechazarSiProfano($nombre);
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

        if ($action === 'cambiar_estado') {
            $idGerente = (int) ($_POST['id_usuario'] ?? 0);
            $estado = (string) ($_POST['estado'] ?? '');

            if (!isset(ESTADOS_GERENTE[$estado])) {
                throw new RuntimeException('Estado no válido.');
            }
            $stmt = $pdo->prepare("SELECT estado FROM Usuarios WHERE id_usuario = ? AND id_rol = 2");
            $stmt->execute([$idGerente]);
            $estadoActual = $stmt->fetchColumn();
            if ($estadoActual === false) {
                throw new RuntimeException('El gerente no existe.');
            }

            if ($estado !== $estadoActual) {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE Usuarios SET estado = ?, fecha_inicio_estado = ? WHERE id_usuario = ? AND id_rol = 2")
                    ->execute([$estado, date('Y-m-d'), $idGerente]);

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
                            <span class="badge <?= ESTADOS_GERENTE[(string) $g['estado']]['badge'] ?? 'badge--warning' ?>">
                                <?= htmlspecialchars($g['estado'] !== '' && $g['estado'] !== null ? $g['estado'] : 'Sin estado') ?>
                            </span>
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
                            <button class="icon-btn" title="Cambiar estado" aria-haspopup="menu" aria-expanded="false"
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

<!-- Menú: cambiar estado del gerente (uno solo, se coloca junto al botón ⋮ de la fila) -->
<div class="menu-estado" id="menuEstado" role="menu" hidden>
    <div class="menu-estado__titulo">Cambiar estado</div>
    <?php foreach (ESTADOS_GERENTE as $estado => $info): ?>
        <?php if ($estado === 'Baja'): ?><div class="menu-estado__sep"></div><?php endif; ?>
        <button type="button" role="menuitem" data-estado="<?= $estado ?>"
                class="menu-estado__item<?= $estado === 'Baja' ? ' menu-estado__item--peligro' : '' ?>">
            <i class="ti ti-<?= $info['icono'] ?>"></i><?= $estado === 'Baja' ? 'Dar de baja' : $estado ?>
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
            <button type="button" class="btn btn--ghost" onclick="closeModal('modalConfirmarEstado')">Cancelar</button>
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

    // Texto sin innerHTML: el nombre y la sucursal vienen de la base
    function parrafo(partes) {
        const p = document.getElementById('confirmarEstadoTexto');
        p.replaceChildren(...partes.map(([texto, fuerte]) => {
            if (!fuerte) return document.createTextNode(texto);
            const b = document.createElement('strong');
            b.textContent = texto;
            return b;
        }));
    }

    function confirmarCambio(btn, estado, icono) {
        const nombre = btn.dataset.nombre;
        const sucursal = btn.dataset.sucursal;
        const baja = estado === 'Baja';
        let titulo, texto;
        if (baja) {
            titulo = 'Dar de baja al gerente';
            texto = [['Se dará de baja a '], [nombre, true], ['. Ya no podrá iniciar sesión.']];
            if (sucursal) texto.push([' Dejará '], [sucursal, true], [', que queda inactiva hasta que le asignes otro gerente.']);
        } else if (estado === 'Activo') {
            titulo = 'Activar gerente';
            texto = [[nombre, true], [' podrá volver a iniciar sesión.']];
            if (!sucursal) texto.push([' No tiene sucursal: asígnale una para que pueda trabajar.']);
        } else {
            titulo = 'Cambiar a ' + estado;
            texto = [[nombre, true], [' quedará en '], [estado.toLowerCase(), true],
                ['. No podrá iniciar sesión mientras tanto' + (sucursal ? ', pero conserva su sucursal.' : '.')]];
        }

        document.getElementById('confirmarEstadoTitulo').textContent = titulo;
        parrafo(texto);
        const caja = document.getElementById('confirmarEstadoIcono');
        caja.classList.toggle('confirmar-estado__icono--peligro', baja);
        caja.firstElementChild.className = 'ti ti-' + icono;
        aceptar.className = 'btn ' + (baja ? 'btn--danger' : 'btn--primary');
        aceptar.textContent = baja ? 'Dar de baja' : 'Confirmar';
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
