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
    require_once __DIR__ . '/solicitudes_comun.php';
}

$vista = ($_GET['vista'] ?? '') === 'solicitudes' ? 'solicitudes' : 'existencias';
$error = '';
$exito = '';
$reabrir = null; // tras un error: reabre el modal con lo que el admin había escrito

/* ------------------------------------------------------------
   Solicitudes de productos del gerente: aceptar, rechazar o editar.
   El catálogo lo controla solo el admin (issue #36). Si el admin
   edita, la solicitud vuelve al gerente (estado Devuelta) con los
   cambios marcados; la lógica común está en solicitudes_comun.php.
   ------------------------------------------------------------ */
// Claves de error de solicitudes_comun.php → texto para el admin
$mensajesSolicitud = [
    'no_existe'      => 'La solicitud no existe.',
    'turno'          => 'La solicitud ya no espera tu revisión (ya se resolvió o está con el gerente). Recarga la página.',
    'nombre'         => 'El nombre del producto es obligatorio (máx. 100 caracteres).',
    'descripcion'    => 'La descripción es muy larga (máx. 255 caracteres).',
    'precio'         => 'El precio debe ser mayor a 0.',
    'categoria'      => 'Elige una categoría válida.',
    'duplicado'      => 'Ya existe un producto activo con ese nombre. Cambia el nombre con «Editar» o rechaza la solicitud.',
    'imagen_tamano'  => 'La imagen no debe pasar de 5 MB.',
    'imagen_formato' => 'Formato de imagen no permitido. Usa PNG, JPG o WEBP.',
    'imagen_subida'  => 'Ocurrió un error al subir la imagen.',
    'imagen_guardar' => 'No se pudo guardar la imagen en el servidor.',
    'nota'           => 'Escribe una nota para el gerente explicando los cambios.',
    'sin_cambios'    => 'No cambiaste nada. Si la solicitud está bien, acéptala.',
];

if (!$esAjax && !empty($_SESSION['flash_inv'])) {
    $exito = $_SESSION['flash_inv'];
    unset($_SESSION['flash_inv']);
}

if (!$esAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
    $idAdmin = (int) $admin['id_usuario'];
    $imagenNueva = null; // se borra si el guardado falla

    try {
        if (!csrfValido()) {
            throw new RuntimeException('El formulario expiró. Recarga la página e inténtalo de nuevo.');
        }
        if (!in_array($action, ['aprobar_agregar', 'aprobar_eliminar', 'rechazar', 'editar_devolver'], true)) {
            throw new RuntimeException('Acción no válida.');
        }

        // Lo que escribe el admin se valida antes de bloquear la solicitud
        if ($action === 'rechazar') {
            $motivo = trim($_POST['motivo_rechazo'] ?? '');
            if ($motivo === '') {
                throw new RuntimeException('Escribe el motivo del rechazo.');
            }
        } elseif ($action === 'editar_devolver') {
            // El proveedor es del gerente: el admin no lo cambia
            $nuevos = leerFichaSolicitud($pdo, $_POST);
            $nota = trim($_POST['nota'] ?? '');
            if ($nota === '') {
                throw new RuntimeException('nota');
            }
        }

        $pdo->beginTransaction();

        $sol = leerSolicitudBloqueada($pdo, $idSolicitud);
        if ($sol['estado'] !== 'Pendiente') {
            throw new RuntimeException('turno');
        }
        $esEliminar = ($sol['tipo'] ?? 'Agregar') === 'Eliminar';

        if ($action === 'rechazar') {
            $stmt = $pdo->prepare("UPDATE Solicitudes_Productos SET estado = 'Rechazada', motivo_rechazo = ?, resuelto_en = NOW(), resuelto_por = ?, actualizado_en = NOW() WHERE id_solicitud = ?");
            $stmt->execute([$motivo, $idAdmin, $idSolicitud]);
            registrarHistorialSolicitud($pdo, $idSolicitud, $idAdmin, 'Admin', 'Rechazada', [], $motivo);
            $mensaje = 'Solicitud rechazada.';

        } elseif ($action === 'aprobar_eliminar') {
            if (!$esEliminar || empty($sol['id_producto'])) {
                throw new RuntimeException('Esta solicitud no es de eliminación.');
            }
            // Baja lógica: conserva ventas e inventario históricos.
            $pdo->prepare("UPDATE Productos SET estado = 'Descontinuado', modificado_en = NOW() WHERE id_producto = ?")
                ->execute([$sol['id_producto']]);
            $stmt = $pdo->prepare("UPDATE Solicitudes_Productos SET estado = 'Aprobada', resuelto_en = NOW(), resuelto_por = ?, actualizado_en = NOW() WHERE id_solicitud = ?");
            $stmt->execute([$idAdmin, $idSolicitud]);
            registrarHistorialSolicitud($pdo, $idSolicitud, $idAdmin, 'Admin', 'Aprobada');
            $mensaje = 'Producto dado de baja del catálogo.';

        } elseif ($action === 'aprobar_agregar') { // el admin acepta la solicitud tal como está
            if ($esEliminar) {
                throw new RuntimeException('Esta solicitud no es de alta de producto.');
            }
            [$idCreado, $reactivado] = crearProductoDesdeSolicitud($pdo, $sol);
            marcarSolicitudAprobada($pdo, $idSolicitud, $idCreado, $idAdmin);
            registrarHistorialSolicitud($pdo, $idSolicitud, $idAdmin, 'Admin', 'Aprobada');
            $mensaje = $reactivado
                ? 'Producto descontinuado reactivado en el catálogo (ID ' . $idCreado . ').'
                : 'Producto agregado al catálogo (ID ' . $idCreado . ').';

        } else { // editar_devolver: los cambios regresan al gerente para que los apruebe
            if ($esEliminar) {
                throw new RuntimeException('Las solicitudes de eliminación no se editan.');
            }
            $imagenNueva = guardarImagenSolicitud($_FILES['imagen'] ?? []);
            if ($imagenNueva !== null) {
                $nuevos['imagen'] = $imagenNueva;
            }
            $cambios = diferenciasSolicitud($sol, $nuevos, CAMPOS_FICHA_SOLICITUD);
            if (!$cambios) {
                throw new RuntimeException('sin_cambios');
            }
            actualizarSolicitud($pdo, $idSolicitud, $cambios, 'Devuelta');
            registrarHistorialSolicitud($pdo, $idSolicitud, $idAdmin, 'Admin', 'Editada', $cambios, $nota);
            $mensaje = 'Cambios enviados al gerente. Si los aprueba, el producto se agrega al catálogo.';
        }

        $pdo->commit();
        $_SESSION['flash_inv'] = $mensaje;
        header('Location: inventario.php?vista=solicitudes');
        exit;
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        borrarImagenSolicitud($imagenNueva);
        $error = $mensajesSolicitud[$e->getMessage()] ?? $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        borrarImagenSolicitud($imagenNueva);
        error_log('inventario.php: ' . $e->getMessage());
        $error = 'No se pudo guardar. Inténtalo de nuevo.';
    }

    if ($error !== '') {
        $vista = 'solicitudes';
        $reabrir = [
            'action' => $action,
            'id'     => $idSolicitud,
            'motivo' => (string) ($_POST['motivo_rechazo'] ?? ''),
            'nota'   => (string) ($_POST['nota'] ?? ''),
            'campos' => [
                'nombre_producto' => (string) ($_POST['nombre_producto'] ?? ''),
                'descripcion'     => (string) ($_POST['descripcion'] ?? ''),
                'id_categoria'    => (string) ($_POST['id_categoria'] ?? ''),
                'precio_sugerido' => (string) ($_POST['precio_sugerido'] ?? ''),
            ],
        ];
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

// Solicitudes que esperan al admin. El botón "Solicitudes" se pinta según cuántas hay:
// amarillo 1–3, rojo 4 o más (con 10 o más también se ilumina "Inventario global").
$totalPendientes = contarSolicitudesParaAdmin($pdo);
$claseAvisoSolicitudes = [
    'aviso'   => 'btn--aviso',
    'alerta'  => 'btn--alerta',
    'critico' => 'btn--alerta',
][nivelAvisoSolicitudes($totalPendientes)] ?? '';

$estadosSolicitud = [
    'Pendiente' => ['Por revisar', 'badge--warning'],
    'Devuelta'  => ['Esperando al gerente', 'badge--muted'],
    'Aprobada'  => ['Aprobada', 'badge--success'],
    'Rechazada' => ['Rechazada', 'badge--danger'],
    'Cancelada' => ['Cancelada por el gerente', 'badge--muted'],
];

// Bandeja de solicitudes (filtros por estado, tipo y sucursal)
if ($vista === 'solicitudes') {
    $fEstado = $_GET['estado'] ?? 'Pendiente';
    if ($fEstado !== '' && !isset($estadosSolicitud[$fEstado])) {
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
    $historiales = historialSolicitudes($pdo, array_column($solicitudes, 'id_solicitud'));

    // Valor legible de un campo de la ficha (también para "antes" de un cambio)
    $nombresCategoria = array_column($categorias, 'nombre_categoria', 'id_categoria');
    $valorFicha = static function (string $campo, $valor) use ($nombresCategoria): string {
        if ($valor === null || $valor === '') {
            return '—';
        }
        return match ($campo) {
            'id_categoria'    => $nombresCategoria[(int) $valor] ?? 'Sin categoría',
            'precio_sugerido' => '$' . number_format((float) $valor, 2),
            default           => (string) $valor,
        };
    };
    $etiquetasCampo = [
        'nombre_producto'    => 'Nombre',
        'descripcion'        => 'Descripción',
        'id_categoria'       => 'Categoría',
        'precio_sugerido'    => 'Precio',
        'imagen'             => 'Imagen',
        'proveedor_sugerido' => 'Proveedor',
        'proveedor_empresa'  => 'Empresa',
        'proveedor_telefono' => 'Teléfono',
        'proveedor_correo'   => 'Correo',
    ];
    $accionesHistorial = [
        'Enviada'   => 'envió la solicitud',
        'Editada'   => 'hizo cambios',
        'Aprobada'  => 'aprobó',
        'Rechazada' => 'rechazó',
        'Cancelada' => 'canceló',
    ];
    $inicialesProducto = static function (string $nombre): string {
        $ini = '';
        foreach (array_slice(preg_split('/\s+/', trim($nombre)), 0, 2) as $palabra) {
            $ini .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }
        return $ini ?: '?';
    };

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

<div class="vista-tabs">
    <a href="inventario.php" class="btn btn--sm <?= $vista === 'existencias' ? 'btn--primary is-activo' : 'btn--ghost' ?>">
        <i class="ti ti-package"></i>Existencias
    </a>
    <a href="inventario.php?vista=solicitudes"
       class="btn btn--sm <?= $claseAvisoSolicitudes ?: ($vista === 'solicitudes' ? 'btn--primary' : 'btn--ghost') ?> <?= $vista === 'solicitudes' ? 'is-activo' : '' ?>"
       <?= $totalPendientes > 0 ? 'title="' . $totalPendientes . ' por revisar"' : '' ?>>
        <i class="ti ti-inbox"></i>Solicitudes
        <?php if ($totalPendientes > 0): ?><span class="btn__conteo"><?= $totalPendientes ?></span><?php endif; ?>
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

    async function refrescar() {
        try {
            const res = await fetch(urlActual(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
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

    iniciar();
})();
</script>

<?php else: ?>
<div class="filters" style="margin-top:1rem">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;width:100%">
        <input type="hidden" name="vista" value="solicitudes">
        <select name="estado" class="select">
            <?php foreach ($estadosSolicitud as $v => [$txt]): ?>
                <option value="<?= $v ?>" <?= $fEstado === $v ? 'selected' : '' ?>><?= $txt ?></option>
            <?php endforeach; ?>
            <option value="" <?= $fEstado === '' ? 'selected' : '' ?>>Todas</option>
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

<?php if (empty($solicitudes)): ?>
    <div class="table-wrap solicitudes-vacio">No hay solicitudes.</div>
<?php else: ?>
<div class="solicitudes-grid">
    <?php foreach ($solicitudes as $s):
        $id = (int) $s['id_solicitud'];
        $esEliminar = ($s['tipo'] ?? 'Agregar') === 'Eliminar';
        [$textoEstado, $claseEstado] = $estadosSolicitud[$s['estado']] ?? [$s['estado'], ''];
        $edicion = ultimaEdicionSolicitud($historiales[$id] ?? []);
        // Cambios del gerente que el admin aún no revisa: la ficha va en amarillo
        $cambiosGerente = $s['estado'] === 'Pendiente' && $edicion !== null && $edicion['rol'] === 'Gerente';
    ?>
        <article class="solicitud-ficha <?= $cambiosGerente ? 'solicitud-ficha--cambios' : '' ?>">
            <div class="solicitud-ficha__imagen">
                <?php if (!empty($s['imagen'])): ?>
                    <img src="../<?= htmlspecialchars($s['imagen']) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="solicitud-ficha__iniciales" aria-hidden="true"><?= htmlspecialchars($inicialesProducto($s['nombre_producto'])) ?></span>
                <?php endif; ?>
                <div class="solicitud-ficha__badges">
                    <span class="badge <?= $claseEstado ?>"><?= htmlspecialchars($textoEstado) ?></span>
                    <?php if ($esEliminar): ?><span class="badge badge--danger">Eliminar</span><?php endif; ?>
                </div>
            </div>
            <div class="solicitud-ficha__cuerpo">
                <h3 class="solicitud-ficha__nombre"><?= htmlspecialchars($s['nombre_producto']) ?></h3>
                <?php if (!$esEliminar): ?>
                    <p class="solicitud-ficha__meta">
                        <?= htmlspecialchars($s['nombre_categoria'] ?? 'Sin categoría') ?>
                        · <strong>$<?= number_format((float) $s['precio_sugerido'], 2) ?></strong>
                    </p>
                <?php endif; ?>
                <p class="solicitud-ficha__meta">
                    <i class="ti ti-building-store"></i><?= htmlspecialchars($s['sucursal']) ?> · <?= htmlspecialchars($s['solicitante']) ?>
                    · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($s['creado_en']))) ?>
                </p>
                <?php if (!empty($similares[$id])): ?>
                    <p class="aviso-duplicado"><i class="ti ti-alert-triangle"></i>Posible duplicado: <?= htmlspecialchars(implode(', ', $similares[$id])) ?></p>
                <?php endif; ?>
                <?php if ($cambiosGerente): ?>
                    <div class="chips-cambio">
                        <span class="chips-cambio__titulo">El gerente cambió:</span>
                        <?php foreach (array_keys($edicion['cambios']) as $campo): ?>
                            <span class="chip-cambio"><?= htmlspecialchars($etiquetasCampo[$campo] ?? $campo) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php $textoNota = $cambiosGerente && !empty($edicion['nota']) ? $edicion['nota'] : ($s['justificacion'] ?? ''); ?>
                <?php if ($textoNota !== ''): ?>
                    <p class="solicitud-ficha__justificacion">“<?= htmlspecialchars($textoNota) ?>”</p>
                <?php endif; ?>
                <?php if ($s['estado'] === 'Rechazada' && !empty($s['motivo_rechazo'])): ?>
                    <p class="solicitud-ficha__meta" style="color:var(--text-danger)">Rechazo: <?= htmlspecialchars($s['motivo_rechazo']) ?></p>
                <?php elseif ($s['estado'] === 'Cancelada' && !empty($s['motivo_rechazo'])): ?>
                    <p class="solicitud-ficha__meta">Motivo del gerente: <?= htmlspecialchars($s['motivo_rechazo']) ?></p>
                <?php elseif ($s['estado'] === 'Aprobada' && !empty($s['id_producto_creado'])): ?>
                    <p class="solicitud-ficha__meta" style="color:var(--text-success)">Producto en el catálogo (ID <?= (int) $s['id_producto_creado'] ?>)</p>
                <?php endif; ?>
                <div class="solicitud-ficha__pie">
                    <?php if ($s['estado'] === 'Pendiente'): ?>
                        <button type="button" class="btn btn--primary btn--sm" onclick="abrirFicha(<?= $id ?>)"><i class="ti ti-eye-check"></i>Revisar</button>
                    <?php else: ?>
                        <button type="button" class="btn btn--ghost btn--sm" onclick="abrirFicha(<?= $id ?>)"><i class="ti ti-eye"></i>Ver ficha</button>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     Ficha de cada solicitud. El mismo formulario sirve para ver y editar:
     en modo "ver" los campos están deshabilitados y se leen como texto.
     Lo que cambió en la última edición va en amarillo.
     ============================================================ -->
<?php foreach ($solicitudes as $s):
    $id = (int) $s['id_solicitud'];
    $esEliminar = ($s['tipo'] ?? 'Agregar') === 'Eliminar';
    [$textoEstado, $claseEstado] = $estadosSolicitud[$s['estado']] ?? [$s['estado'], ''];
    $pasos = $historiales[$id] ?? [];
    $edicion = ultimaEdicionSolicitud($pasos);
    $cambiados = $edicion['cambios'] ?? [];
    $pendiente = $s['estado'] === 'Pendiente';
    $clase = static fn (string $campo): string => isset($cambiados[$campo]) ? 'campo-cambiado' : '';
?>
<div id="ficha<?= $id ?>" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="fichaTitulo<?= $id ?>"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center">
    <div class="ficha-admin" data-modo="ver">
        <form method="POST" action="inventario.php?vista=solicitudes" enctype="multipart/form-data"
              onsubmit="this.querySelectorAll('[type=submit]').forEach(b => b.disabled = true);">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_solicitud" value="<?= $id ?>">

            <div class="ficha-admin__encabezado">
                <h2 id="fichaTitulo<?= $id ?>"><?= htmlspecialchars($s['nombre_producto']) ?></h2>
                <span class="badge <?= $claseEstado ?>"><?= htmlspecialchars($textoEstado) ?></span>
            </div>
            <p class="ficha-admin__meta">
                <?= htmlspecialchars($s['sucursal']) ?> · <?= htmlspecialchars($s['solicitante']) ?>
                · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($s['creado_en']))) ?>
            </p>

            <?php if ($edicion !== null): ?>
                <div class="ficha-admin__aviso">
                    <strong><?= $edicion['rol'] === 'Gerente' ? 'El gerente regresó la solicitud con cambios (en amarillo)' : 'Tus cambios enviados al gerente (en amarillo)' ?></strong>
                    <?php if (!empty($edicion['nota'])): ?><p>“<?= htmlspecialchars($edicion['nota']) ?>”</p><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ficha-admin__cuerpo">
                <div>
                    <figure class="ficha-admin__imagen <?= $clase('imagen') ?>">
                        <?php if (!empty($s['imagen'])): ?>
                            <img src="../<?= htmlspecialchars($s['imagen']) ?>" alt="<?= htmlspecialchars($s['nombre_producto']) ?>" loading="lazy" data-vista-previa>
                        <?php else: ?>
                            <img src="" alt="" hidden data-vista-previa>
                            <span class="solicitud-ficha__iniciales" aria-hidden="true"><?= htmlspecialchars($inicialesProducto($s['nombre_producto'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($cambiados['imagen']['antes'])): ?>
                            <figcaption class="ficha-admin__antes">
                                <img src="../<?= htmlspecialchars($cambiados['imagen']['antes']) ?>" alt="" loading="lazy">Antes
                            </figcaption>
                        <?php endif; ?>
                    </figure>
                    <label class="solo-editar ficha-admin__subir">
                        <i class="ti ti-photo-up"></i>Cambiar imagen
                        <input type="file" name="imagen" accept="image/png,image/jpeg,image/webp" disabled data-editable>
                    </label>
                </div>

                <div class="ficha-admin__campos">
                    <label class="ficha-admin__campo <?= $clase('nombre_producto') ?>">
                        <span>Nombre</span>
                        <input type="text" name="nombre_producto" class="input" maxlength="100" required disabled data-editable
                               value="<?= htmlspecialchars($s['nombre_producto']) ?>">
                        <?php if (isset($cambiados['nombre_producto'])): ?><del>Antes: <?= htmlspecialchars($valorFicha('nombre_producto', $cambiados['nombre_producto']['antes'])) ?></del><?php endif; ?>
                    </label>
                    <?php if (!$esEliminar): ?>
                        <div class="ficha-admin__fila">
                            <label class="ficha-admin__campo <?= $clase('id_categoria') ?>">
                                <span>Categoría</span>
                                <select name="id_categoria" class="select" required disabled data-editable>
                                    <?php foreach ($categorias as $c): ?>
                                        <option value="<?= (int) $c['id_categoria'] ?>" <?= (int) $s['id_categoria'] === (int) $c['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre_categoria']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($cambiados['id_categoria'])): ?><del>Antes: <?= htmlspecialchars($valorFicha('id_categoria', $cambiados['id_categoria']['antes'])) ?></del><?php endif; ?>
                            </label>
                            <label class="ficha-admin__campo <?= $clase('precio_sugerido') ?>">
                                <span>Precio sugerido</span>
                                <input type="number" name="precio_sugerido" class="input" min="0.01" step="0.01" required disabled data-editable
                                       value="<?= htmlspecialchars(number_format((float) $s['precio_sugerido'], 2, '.', '')) ?>">
                                <?php if (isset($cambiados['precio_sugerido'])): ?><del>Antes: <?= htmlspecialchars($valorFicha('precio_sugerido', $cambiados['precio_sugerido']['antes'])) ?></del><?php endif; ?>
                            </label>
                        </div>
                        <label class="ficha-admin__campo <?= $clase('descripcion') ?>">
                            <span>Descripción</span>
                            <textarea name="descripcion" class="input" maxlength="255" rows="2" disabled data-editable
                                      placeholder="Sin descripción"><?= htmlspecialchars($s['descripcion'] ?? '') ?></textarea>
                            <?php if (isset($cambiados['descripcion'])): ?><del>Antes: <?= htmlspecialchars($valorFicha('descripcion', $cambiados['descripcion']['antes'])) ?></del><?php endif; ?>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$esEliminar): ?>
                <section class="ficha-admin__seccion">
                    <h3><i class="ti ti-truck"></i>Proveedor sugerido <small>(solo lo cambia el gerente)</small></h3>
                    <?php if (empty($s['proveedor_sugerido'])): ?>
                        <p class="ficha-admin__vacio">El gerente no sugirió proveedor.</p>
                    <?php else: ?>
                        <dl class="ficha-admin__proveedor">
                            <?php foreach (CAMPOS_PROVEEDOR_SOLICITUD as $campo): ?>
                                <div class="<?= $clase($campo) ?>">
                                    <dt><?= $etiquetasCampo[$campo] ?></dt>
                                    <dd><?= htmlspecialchars($valorFicha($campo, $s[$campo] ?? null)) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="ficha-admin__seccion">
                <h3><i class="ti ti-message"></i>Justificación del gerente</h3>
                <p><?= htmlspecialchars($s['justificacion'] ?? '') ?></p>
            </section>

            <?php if ($pasos): ?>
                <section class="ficha-admin__seccion">
                    <h3><i class="ti ti-history"></i>Historial</h3>
                    <ol class="ficha-admin__historial">
                        <?php foreach ($pasos as $paso): ?>
                            <li>
                                <strong><?= htmlspecialchars($paso['autor'] ?? $paso['rol']) ?></strong>
                                <span class="ficha-admin__vacio">(<?= $paso['rol'] === 'Admin' ? 'Administrador' : 'Gerente' ?>)</span>
                                <?= $accionesHistorial[$paso['accion']] ?? htmlspecialchars($paso['accion']) ?>
                                · <span class="ficha-admin__vacio"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($paso['creado_en']))) ?></span>
                                <?php if ($paso['cambios']): ?>
                                    <span class="ficha-admin__vacio">— <?= htmlspecialchars(implode(', ', array_map(static fn ($c) => $etiquetasCampo[$c] ?? $c, array_keys($paso['cambios'])))) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($paso['nota'])): ?><q><?= htmlspecialchars($paso['nota']) ?></q><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>

            <label class="solo-editar ficha-admin__nota">
                <span>Nota para el gerente *</span>
                <textarea name="nota" class="input" maxlength="500" rows="2" required disabled data-editable
                          placeholder="Qué cambiaste y por qué. El gerente la verá junto a tus cambios."></textarea>
            </label>

            <div class="ficha-admin__acciones">
                <button type="button" class="btn btn--ghost solo-ver" onclick="closeModal('ficha<?= $id ?>')">Cerrar</button>
                <?php if ($pendiente): ?>
                    <button type="button" class="btn btn--danger solo-ver" onclick="rechazarSolicitud(this)"
                            data-id="<?= $id ?>" data-nombre="<?= htmlspecialchars($s['nombre_producto'], ENT_QUOTES) ?>">
                        <i class="ti ti-x"></i>Rechazar
                    </button>
                    <?php if ($esEliminar): ?>
                        <button type="submit" name="action" value="aprobar_eliminar" class="btn btn--primary solo-ver"
                                onclick="return confirm('¿Dar de baja este producto del catálogo?')">
                            <i class="ti ti-check"></i>Aprobar baja
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn--ghost solo-ver" onclick="editarFicha(<?= $id ?>)"><i class="ti ti-pencil"></i>Editar</button>
                        <button type="submit" name="action" value="aprobar_agregar" class="btn btn--primary solo-ver"
                                onclick="return confirm('¿Agregar este producto al catálogo tal como está en la ficha?')">
                            <i class="ti ti-check"></i>Aceptar
                        </button>
                        <button type="button" class="btn btn--ghost solo-editar" onclick="cancelarEdicionFicha(<?= $id ?>)">Cancelar edición</button>
                        <button type="submit" name="action" value="editar_devolver" class="btn btn--primary solo-editar">
                            <i class="ti ti-send"></i>Enviar cambios al gerente
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- Modal: rechazar solicitud -->
<div id="modalRechazar" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:110;align-items:center;justify-content:center">
    <div style="background:var(--surface-1);border-radius:var(--radius-lg);padding:1.5rem;width:400px;max-width:90vw">
        <h2 style="font-size:16px;font-weight:600;margin:0 0 .5rem">Rechazar solicitud</h2>
        <p style="color:var(--text-secondary);font-size:13px;margin:0 0 1rem">Producto: <strong id="rechazarNombre"></strong></p>
        <form method="POST" action="inventario.php?vista=solicitudes" id="formRechazar"
              onsubmit="this.querySelector('[type=submit]').disabled = true;">
            <input type="hidden" name="action" value="rechazar">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="id_solicitud" value="">
            <div style="margin-bottom:1rem">
                <label style="font-size:13px;color:var(--text-secondary);display:block;margin-bottom:4px">Motivo del rechazo</label>
                <textarea name="motivo_rechazo" class="input" style="width:100%;min-height:80px" required></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" class="btn btn--ghost" onclick="closeModal('modalRechazar')">Cancelar</button>
                <button type="submit" class="btn btn--danger">Rechazar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modo "ver": campos deshabilitados (se leen como texto). Modo "editar": se pueden cambiar.
function modoFicha(id, editar) {
    const caja = document.querySelector('#ficha' + id + ' .ficha-admin');
    caja.dataset.modo = editar ? 'editar' : 'ver';
    caja.querySelectorAll('[data-editable]').forEach(c => { c.disabled = !editar; });
    // Solo los botones del modo actual: así Enter en un campo no acepta la solicitud mientras se edita
    caja.querySelectorAll('[type=submit]').forEach(b => { b.disabled = b.classList.contains('solo-ver') === editar; });
    return caja;
}

function abrirFicha(id) {
    const caja = document.querySelector('#ficha' + id + ' .ficha-admin');
    caja.querySelector('form').reset();
    modoFicha(id, false);
    const vista = caja.querySelector('[data-vista-previa]');
    if (vista && vista.dataset.original !== undefined) {
        vista.src = vista.dataset.original;
        vista.hidden = vista.dataset.original === '';
        const iniciales = vista.parentElement.querySelector('.solicitud-ficha__iniciales');
        if (iniciales) iniciales.hidden = !vista.hidden;
    }
    openModal('ficha' + id);
}

function editarFicha(id) {
    modoFicha(id, true).querySelector('[name="nombre_producto"]').focus();
}

function cancelarEdicionFicha(id) {
    abrirFicha(id);
}

function rechazarSolicitud(btn) {
    const f = document.getElementById('formRechazar');
    f.reset();
    f.querySelector('[type=submit]').disabled = false;
    f.querySelector('[name="id_solicitud"]').value = btn.dataset.id;
    document.getElementById('rechazarNombre').textContent = btn.dataset.nombre;
    closeModal('ficha' + btn.dataset.id);
    openModal('modalRechazar');
}

// Vista previa de la imagen nueva en la ficha
document.querySelectorAll('.ficha-admin [name="imagen"]').forEach(input => {
    input.addEventListener('change', () => {
        const vista = input.closest('.ficha-admin').querySelector('[data-vista-previa]');
        if (!vista || !input.files[0]) return;
        if (vista.dataset.original === undefined) vista.dataset.original = vista.hidden ? '' : vista.getAttribute('src');
        vista.src = URL.createObjectURL(input.files[0]);
        vista.hidden = false;
        const iniciales = vista.parentElement.querySelector('.solicitud-ficha__iniciales');
        if (iniciales) iniciales.hidden = true;
    });
});

<?php if ($reabrir !== null): ?>
// Un error al guardar reabre el modal con lo que el admin había escrito.
document.addEventListener('DOMContentLoaded', () => {
    const r = <?= json_encode($reabrir, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (r.action === 'rechazar') {
        const boton = document.querySelector('[onclick="rechazarSolicitud(this)"][data-id="' + r.id + '"]');
        if (!boton) return;
        rechazarSolicitud(boton);
        document.querySelector('#formRechazar [name="motivo_rechazo"]').value = r.motivo;
    } else if (document.getElementById('ficha' + r.id)) {
        abrirFicha(r.id);
        if (r.action === 'editar_devolver') {
            const caja = modoFicha(r.id, true);
            Object.entries(r.campos).forEach(([nombre, valor]) => {
                const campo = caja.querySelector('[name="' + nombre + '"]');
                if (campo) campo.value = valor;
            });
            caja.querySelector('[name="nota"]').value = r.nota;
        }
    }
});
<?php endif; ?>
</script>

<?php endif; ?>

<?php require 'footer.php'; ?>
