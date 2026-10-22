<?php
$esAjax = isset($_GET['ajax']);

if ($esAjax) {
    session_start();
    if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] !== 1) {
        http_response_code(403);
        exit;
    }
    require_once __DIR__ . '/database.php';
} else {
    ob_start();
    require 'header.php';
    require 'sidebar.php';
    require_once __DIR__ . '/csrf.php';
}

$vista = ($_GET['vista'] ?? '') === 'solicitudes' ? 'solicitudes' : 'existencias';
$error = '';
$exito = '';
$reabrir = null; // tras un error al aprobar/rechazar: reabre el modal con lo escrito

/* ------------------------------------------------------------
   Solicitudes de productos del gerente: aprobar o rechazar.
   El catálogo lo controla solo el admin (issue #36).
   ------------------------------------------------------------ */
if (!$esAjax && !empty($_SESSION['flash_inv'])) {
    $exito = $_SESSION['flash_inv'];
    unset($_SESSION['flash_inv']);
}

if (!$esAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }
        if (!in_array($action, ['aprobar_agregar', 'aprobar_eliminar', 'rechazar'], true)) {
            throw new RuntimeException('Acción no válida.');
        }

        $pdo->beginTransaction();

        // Bloquea la solicitud: dos clics o dos admins no la resuelven dos veces.
        $stmt = $pdo->prepare("SELECT * FROM Solicitudes_Productos WHERE id_solicitud = ? FOR UPDATE");
        $stmt->execute([$idSolicitud]);
        $sol = $stmt->fetch();
        if ($sol === false) {
            throw new RuntimeException('La solicitud no existe.');
        }
        if ($sol['estado'] !== 'Pendiente') {
            throw new RuntimeException('La solicitud ya fue resuelta.');
        }
        $esEliminar = ($sol['tipo'] ?? 'Agregar') === 'Eliminar';
        $idCreado = null;

        if ($action === 'rechazar') {
            $motivo = trim($_POST['motivo_rechazo'] ?? '');
            if ($motivo === '') {
                throw new RuntimeException('Escribe el motivo del rechazo.');
            }
            $stmt = $pdo->prepare("UPDATE Solicitudes_Productos SET estado = 'Rechazada', motivo_rechazo = ?, resuelto_en = NOW(), resuelto_por = ? WHERE id_solicitud = ?");
            $stmt->execute([$motivo, $admin['id_usuario'], $idSolicitud]);
            $mensaje = 'Solicitud rechazada.';

        } elseif ($action === 'aprobar_eliminar') {
            if (!$esEliminar || empty($sol['id_producto'])) {
                throw new RuntimeException('Esta solicitud no es de eliminación.');
            }
            // Baja lógica: conserva ventas e inventario históricos.
            $pdo->prepare("UPDATE Productos SET estado = 'Descontinuado', modificado_en = NOW() WHERE id_producto = ?")
                ->execute([$sol['id_producto']]);
            $stmt = $pdo->prepare("UPDATE Solicitudes_Productos SET estado = 'Aprobada', resuelto_en = NOW(), resuelto_por = ? WHERE id_solicitud = ?");
            $stmt->execute([$admin['id_usuario'], $idSolicitud]);
            $mensaje = 'Producto dado de baja del catálogo.';

        } else { // aprobar_agregar: el admin acepta la sugerencia tal cual o la rechaza
            if ($esEliminar) {
                throw new RuntimeException('Esta solicitud no es de alta de producto.');
            }
            $nombre = trim((string) $sol['nombre_producto']);
            $descripcion = trim((string) ($sol['descripcion'] ?? ''));
            $idCategoria = (int) $sol['id_categoria'];
            $precio = is_numeric($sol['precio_sugerido']) ? round((float) $sol['precio_sugerido'], 2) : -1;

            if ($nombre === '' || mb_strlen($nombre) > 100) {
                throw new RuntimeException('El nombre de la solicitud no es válido. Recházala.');
            }
            if ($precio <= 0) {
                throw new RuntimeException('La solicitud no trae un precio válido. Recházala.');
            }
            $check = $pdo->prepare("SELECT 1 FROM Categorias WHERE id_categoria = ?");
            $check->execute([$idCategoria]);
            if ($check->fetch() === false) {
                throw new RuntimeException('La solicitud no trae una categoría válida. Recházala.');
            }
            $check = $pdo->prepare("SELECT id_producto, estado FROM Productos WHERE LOWER(nombre) = LOWER(?) ORDER BY (estado = 'Activo') DESC LIMIT 1 FOR UPDATE");
            $check->execute([$nombre]);
            $existente = $check->fetch();
            if ($existente !== false && $existente['estado'] === 'Activo') {
                throw new RuntimeException('Ya existe un producto activo con ese nombre. Rechaza la solicitud.');
            }

            if ($existente !== false) {
                // Producto descontinuado con el mismo nombre: se reactiva tal como estaba,
                // sin sobrescribir sus datos, en lugar de duplicarlo en el catálogo.
                $idCreado = (int) $existente['id_producto'];
                $pdo->prepare("UPDATE Productos SET estado = 'Activo', modificado_en = NOW() WHERE id_producto = ?")
                    ->execute([$idCreado]);
                $mensaje = 'Producto descontinuado reactivado en el catálogo (ID ' . $idCreado . ').';
            } else {
                // Código provisional único; el definitivo se genera como en sql/002.
                $stmt = $pdo->prepare("INSERT INTO Productos (codigo, nombre, descripcion, precio, id_categoria, estado, creado_en, modificado_en) VALUES (?, ?, ?, ?, ?, 'Activo', NOW(), NOW())");
                $stmt->execute(['TMP' . bin2hex(random_bytes(8)), $nombre, $descripcion !== '' ? $descripcion : null, $precio, $idCategoria]);
                $idCreado = (int) $pdo->lastInsertId();
                $pdo->prepare("UPDATE Productos SET codigo = ? WHERE id_producto = ?")
                    ->execute(['750' . str_pad((string) $idCreado, 10, '0', STR_PAD_LEFT), $idCreado]);
                $mensaje = 'Producto agregado al catálogo (ID ' . $idCreado . ').';
            }

            $stmt = $pdo->prepare("UPDATE Solicitudes_Productos SET estado = 'Aprobada', id_producto_creado = ?, resuelto_en = NOW(), resuelto_por = ? WHERE id_solicitud = ?");
            $stmt->execute([$idCreado, $admin['id_usuario'], $idSolicitud]);
        }

        $pdo->commit();
        $_SESSION['flash_inv'] = $mensaje;
        header('Location: inventario.php?vista=solicitudes');
        exit;
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
        $vista = 'solicitudes';
        $reabrir = ['action' => $action, 'id' => $idSolicitud, 'motivo' => (string) ($_POST['motivo_rechazo'] ?? '')];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('inventario.php: ' . $e->getMessage());
        $error = 'No se pudo guardar. Inténtalo de nuevo.';
        $vista = 'solicitudes';
        $reabrir = ['action' => $action, 'id' => $idSolicitud, 'motivo' => (string) ($_POST['motivo_rechazo'] ?? '')];
    }
}

// Filtros
$fSucursal = $_GET['sucursal'] ?? '';
$fCategoria = $_GET['categoria'] ?? '';
$fBuscar = $_GET['buscar'] ?? '';

function obtenerInventario(PDO $pdo, string $fSucursal, string $fCategoria, string $fBuscar): array
{
    $sql = "
        SELECT p.nombre AS producto, p.estado AS estado_producto, c.nombre_categoria AS categoria,
               s.nombre AS sucursal, ip.cantidad_disponible,
               CASE WHEN ip.cantidad_disponible <= 5 THEN 'Bajo stock' ELSE 'Suficiente' END AS estado
        FROM Inventario_Sucursal ip
        INNER JOIN Productos p ON p.id_producto = ip.id_producto
        INNER JOIN Categorias c ON c.id_categoria = p.id_categoria
        INNER JOIN Sucursales s ON s.id_sucursal = ip.id_sucursal
        WHERE 1=1
    ";
    $params = [];

    if ($fSucursal !== '') {
        $sql .= " AND ip.id_sucursal = ?";
        $params[] = $fSucursal;
    }
    if ($fCategoria !== '') {
        $sql .= " AND p.id_categoria = ?";
        $params[] = $fCategoria;
    }
    if ($fBuscar !== '') {
        $sql .= " AND p.nombre LIKE ?";
        $params[] = "%$fBuscar%";
    }

    $sql .= " ORDER BY p.nombre, s.nombre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function renderFilasInventario(array $productos): string
{
    if (empty($productos)) {
        return '<tr><td colspan="5" style="color:var(--text-muted);text-align:center">No hay productos en inventario.</td></tr>';
    }

    $html = '';
    foreach ($productos as $p) {
        $estadoBadge = $p['estado'] === 'Bajo stock'
            ? '<span class="badge badge--danger">Bajo stock</span>'
            : '<span class="badge badge--success">Suficiente</span>';
        $estiloCantidad = $p['cantidad_disponible'] <= 5 ? 'color:var(--text-danger);font-weight:500' : '';

        $html .= '<tr>'
            . '<td>' . htmlspecialchars($p['producto'])
            . ($p['estado_producto'] === 'Descontinuado' ? ' <span class="badge badge--danger">Descontinuado</span>' : '')
            . '</td>'
            . '<td style="color:var(--text-secondary)">' . htmlspecialchars($p['categoria']) . '</td>'
            . '<td style="color:var(--text-secondary)">' . htmlspecialchars($p['sucursal']) . '</td>'
            . '<td style="text-align:right;' . $estiloCantidad . '">' . (int) $p['cantidad_disponible'] . '</td>'
            . '<td>' . $estadoBadge . '</td>'
            . '</tr>';
    }

    return $html;
}

$productos = obtenerInventario($pdo, $fSucursal, $fCategoria, $fBuscar);

if ($esAjax) {
    header('Content-Type: text/html; charset=utf-8');
    echo renderFilasInventario($productos);
    exit;
}

// Listados para filtros
$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM Sucursales WHERE estado = 'Activa' ORDER BY nombre")->fetchAll();
$categorias = $pdo->query("SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria")->fetchAll();

$totalPendientes = (int) $pdo->query("SELECT COUNT(*) FROM Solicitudes_Productos WHERE estado = 'Pendiente'")->fetchColumn();

// Bandeja de solicitudes (filtros por estado, tipo y sucursal)
if ($vista === 'solicitudes') {
    $fEstado = $_GET['estado'] ?? 'Pendiente';
    if (!in_array($fEstado, ['Pendiente', 'Aprobada', 'Rechazada', ''], true)) {
        $fEstado = 'Pendiente';
    }
    $fTipo = in_array($_GET['tipo'] ?? '', ['Agregar', 'Eliminar'], true) ? $_GET['tipo'] : '';

    $sql = "
        SELECT s.*, su.nombre AS sucursal, u.nombre AS solicitante, c.nombre_categoria
        FROM Solicitudes_Productos s
        INNER JOIN Sucursales su ON su.id_sucursal = s.id_sucursal
        INNER JOIN Usuarios u ON u.id_usuario = s.id_usuario
        LEFT JOIN Categorias c ON c.id_categoria = s.id_categoria
        WHERE 1=1
    ";
    $params = [];
    if ($fEstado !== '') { $sql .= " AND s.estado = ?"; $params[] = $fEstado; }
    if ($fTipo !== '')   { $sql .= " AND s.tipo = ?";   $params[] = $fTipo; }
    if ($fSucursal !== '') { $sql .= " AND s.id_sucursal = ?"; $params[] = $fSucursal; }
    $sql .= " ORDER BY (s.estado = 'Pendiente') DESC, s.creado_en DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll();

    // Posibles duplicados del catálogo para cada alta pendiente
    $similares = [];
    $stmtSim = $pdo->prepare("SELECT nombre FROM Productos WHERE nombre LIKE ? LIMIT 3");
    foreach ($solicitudes as $s) {
        if ($s['estado'] === 'Pendiente' && ($s['tipo'] ?? 'Agregar') === 'Agregar') {
            $stmtSim->execute(['%' . addcslashes($s['nombre_producto'], '%_\\') . '%']);
            $similares[$s['id_solicitud']] = $stmtSim->fetchAll(PDO::FETCH_COLUMN);
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['inventario']) ?></h1>
        <?php if ($vista === 'existencias'): ?>
            <p>Consulta de solo lectura entre todas las sucursales <span id="inventarioActualizado" style="color:var(--text-muted);font-size:.85em"></span></p>
        <?php else: ?>
            <p>Solicitudes de los gerentes para agregar o eliminar productos del catálogo</p>
        <?php endif; ?>
    </div>
</div>

<div style="display:flex;gap:8px;margin-top:1rem">
    <a href="inventario.php" class="btn <?= $vista === 'existencias' ? 'btn--primary' : 'btn--ghost' ?> btn--sm">Existencias</a>
    <a href="inventario.php?vista=solicitudes" class="btn <?= $vista === 'solicitudes' ? 'btn--primary' : 'btn--ghost' ?> btn--sm">
        Solicitudes<?php if ($totalPendientes > 0): ?> <span class="badge badge--warning"><?= $totalPendientes ?></span><?php endif; ?>
    </a>
</div>

<?php if ($exito !== ''): ?>
    <div style="margin-top:1rem;padding:10px 14px;border-radius:var(--radius-lg);background:var(--bg-success);color:var(--text-success)"><?= htmlspecialchars($exito) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div style="margin-top:1rem;padding:10px 14px;border-radius:var(--radius-lg);background:var(--bg-danger);color:var(--text-danger)"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($vista === 'existencias'): ?>
<div class="filters" style="margin-top:1rem">
    <form method="GET" id="formFiltrosInventario" style="display:flex;gap:8px;flex-wrap:wrap;width:100%">
        <input type="text" name="buscar" class="input" placeholder="Buscar producto" value="<?= htmlspecialchars($fBuscar) ?>" style="flex:1;min-width:160px">
        <select name="sucursal" class="select">
            <option value="">Todas las sucursales</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id_sucursal'] ?>" <?= $fSucursal == $s['id_sucursal'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="categoria" class="select">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id_categoria'] ?>" <?= $fCategoria == $c['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre_categoria']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
    </form>
</div>

<div class="table-wrap">
    <table class="table" id="tblInventario">
        <thead><tr>
            <th>Producto</th><th>Categoría</th><th>Sucursal</th><th>Existencias</th><th>Estado</th>
        </tr></thead>
        <tbody id="tblInventarioBody"><?= renderFilasInventario($productos) ?></tbody>
    </table>
</div>

<script>
(function () {
    const tbody = document.getElementById('tblInventarioBody');
    const indicador = document.getElementById('inventarioActualizado');
    const INTERVALO_MS = 8000;
    let temporizador = null;

    function urlActual() {
        const params = new URLSearchParams(new FormData(document.getElementById('formFiltrosInventario')));
        params.set('ajax', '1');
        return 'inventario.php?' + params.toString();
    }

    const form = document.getElementById('formFiltrosInventario');
    let peticion = null;

    async function refrescar() {
        // Cancela la petición anterior: solo cuenta la respuesta más reciente.
        if (peticion) peticion.abort();
        peticion = new AbortController();
        try {
            const res = await fetch(urlActual(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: peticion.signal });
            if (!res.ok) return;
            tbody.innerHTML = await res.text();
            if (indicador) {
                const ahora = new Date();
                indicador.textContent = '· actualizado ' + ahora.toLocaleTimeString();
            }
        } catch (e) {
            // Silencioso: se reintenta en el siguiente ciclo
        }
    }

    function iniciar() {
        detener();
        temporizador = setInterval(refrescar, INTERVALO_MS);
    }

    function detener() {
        if (temporizador) clearInterval(temporizador);
        temporizador = null;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            detener();
        } else {
            refrescar();
            iniciar();
        }
    });

    // Búsqueda en vivo: filtra mientras se escribe (con una pausa breve para no saturar).
    let espera = null;
    form.elements['buscar'].addEventListener('input', () => {
        clearTimeout(espera);
        espera = setTimeout(refrescar, 250);
    });
    form.elements['sucursal'].addEventListener('change', refrescar);
    form.elements['categoria'].addEventListener('change', refrescar);
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        refrescar();
    });

    iniciar();
})();
</script>

<?php else: ?>

<div class="filters" style="margin-top:1rem">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;width:100%">
        <input type="hidden" name="vista" value="solicitudes">
        <select name="estado" class="select">
            <?php foreach (['Pendiente' => 'Pendientes', 'Aprobada' => 'Aprobadas', 'Rechazada' => 'Rechazadas', '' => 'Todas'] as $v => $txt): ?>
                <option value="<?= $v ?>" <?= $fEstado === $v ? 'selected' : '' ?>><?= $txt ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tipo" class="select">
            <option value="">Agregar y eliminar</option>
            <option value="Agregar" <?= $fTipo === 'Agregar' ? 'selected' : '' ?>>Solo agregar</option>
            <option value="Eliminar" <?= $fTipo === 'Eliminar' ? 'selected' : '' ?>>Solo eliminar</option>
        </select>
        <select name="sucursal" class="select">
            <option value="">Todas las sucursales</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id_sucursal'] ?>" <?= $fSucursal == $s['id_sucursal'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
    </form>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>Tipo</th><th>Producto</th><th>Sucursal</th><th>Detalle</th><th>Fecha</th><th>Estado</th><th>Acciones</th>
        </tr></thead>
        <tbody>
            <?php if (empty($solicitudes)): ?>
                <tr><td colspan="7" style="color:var(--text-muted);text-align:center">No hay solicitudes.</td></tr>
            <?php else: foreach ($solicitudes as $s):
                $esEliminar = ($s['tipo'] ?? 'Agregar') === 'Eliminar';
                $claseEstado = ['Pendiente' => 'badge--warning', 'Aprobada' => 'badge--success', 'Rechazada' => 'badge--danger'][$s['estado']] ?? '';
            ?>
                <tr>
                    <td><span class="badge <?= $esEliminar ? 'badge--danger' : 'badge--success' ?>"><?= $esEliminar ? 'Eliminar' : 'Agregar' ?></span></td>
                    <td>
                        <?= htmlspecialchars($s['nombre_producto']) ?>
                        <?php if (!$esEliminar): ?>
                            <div style="color:var(--text-secondary);font-size:12px">
                                <?= htmlspecialchars($s['nombre_categoria'] ?? 'Sin categoría') ?>
                                · $<?= number_format((float) $s['precio_sugerido'], 2) ?>
                                <?php if (!empty($s['proveedor_sugerido'])): ?> · <?= htmlspecialchars($s['proveedor_sugerido']) ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($similares[$s['id_solicitud']])): ?>
                            <div style="color:var(--text-warning);font-size:12px">Posible duplicado: <?= htmlspecialchars(implode(', ', $similares[$s['id_solicitud']])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-secondary)">
                        <?= htmlspecialchars($s['sucursal']) ?>
                        <div style="font-size:12px"><?= htmlspecialchars($s['solicitante']) ?></div>
                    </td>
                    <td style="color:var(--text-secondary);max-width:280px">
                        <?php if (!$esEliminar && !empty($s['descripcion'])): ?><?= htmlspecialchars($s['descripcion']) ?><br><?php endif; ?>
                        <em><?= htmlspecialchars($s['justificacion'] ?? '') ?></em>
                        <?php if ($s['estado'] === 'Rechazada' && !empty($s['motivo_rechazo'])): ?>
                            <div style="color:var(--text-danger);font-size:12px">Rechazo: <?= htmlspecialchars($s['motivo_rechazo']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-secondary)"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($s['creado_en']))) ?></td>
                    <td><span class="badge <?= $claseEstado ?>"><?= htmlspecialchars($s['estado']) ?></span></td>
                    <td style="white-space:nowrap">
                        <?php if ($s['estado'] === 'Pendiente'): ?>
                            <?php if ($esEliminar): ?>
                                <form method="POST" action="inventario.php?vista=solicitudes" style="display:inline"
                                      onsubmit="return confirm('¿Dar de baja este producto del catálogo?')">
                                    <input type="hidden" name="action" value="aprobar_eliminar">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                    <input type="hidden" name="id_solicitud" value="<?= (int) $s['id_solicitud'] ?>">
                                    <button type="submit" class="btn btn--primary btn--sm">Aprobar</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="inventario.php?vista=solicitudes" style="display:inline"
                                      onsubmit="return confirm('¿Agregar este producto al catálogo tal como lo sugirió el gerente?')">
                                    <input type="hidden" name="action" value="aprobar_agregar">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                    <input type="hidden" name="id_solicitud" value="<?= (int) $s['id_solicitud'] ?>">
                                    <button type="submit" class="btn btn--primary btn--sm">Aprobar</button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn--ghost btn--sm" onclick="rechazarSolicitud(this)"
                                data-id="<?= (int) $s['id_solicitud'] ?>"
                                data-nombre="<?= htmlspecialchars($s['nombre_producto'], ENT_QUOTES) ?>">Rechazar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal: rechazar solicitud -->
<div id="modalRechazar" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:500;margin:0 0 .5rem">Rechazar solicitud</h2>
        <p style="color:var(--text-secondary);font-size:13px;margin:0 0 1rem">Producto: <strong id="rechazarNombre"></strong></p>
        <form method="POST" action="inventario.php?vista=solicitudes" id="formRechazar">
            <input type="hidden" name="action" value="rechazar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_solicitud" value="">
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Motivo del rechazo</label>
                <textarea name="motivo_rechazo" class="input" style="width:100%;min-height:80px" required></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalRechazar')">Cancelar</button>
                <button type="submit" class="btn btn--primary">Rechazar</button>
            </div>
        </form>
    </div>
</div>

<script>
function rechazarSolicitud(btn) {
    const f = document.getElementById('formRechazar');
    f.reset();
    f.querySelector('[name="id_solicitud"]').value = btn.dataset.id;
    document.getElementById('rechazarNombre').textContent = btn.dataset.nombre;
    openModal('modalRechazar');
}

<?php if ($reabrir !== null && $reabrir['action'] === 'rechazar'): ?>
// Un error al rechazar reabre el modal con el motivo que el admin había escrito.
(function () {
    const r = <?= json_encode($reabrir, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const f = document.getElementById('formRechazar');
    f.querySelector('[name="id_solicitud"]').value = r.id;
    f.querySelector('[name="motivo_rechazo"]').value = r.motivo;
    const fila = document.querySelector('[onclick="rechazarSolicitud(this)"][data-id="' + r.id + '"]');
    if (fila) document.getElementById('rechazarNombre').textContent = fila.dataset.nombre;
    openModal('modalRechazar');
})();
<?php endif; ?>
</script>

<?php endif; ?>

<?php require 'footer.php'; ?>
