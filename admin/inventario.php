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
    require 'header.php';
    require 'sidebar.php';
}

// Filtros
$fSucursal = $_GET['sucursal'] ?? '';
$fCategoria = $_GET['categoria'] ?? '';
$fBuscar = $_GET['buscar'] ?? '';

function obtenerInventario(PDO $pdo, string $fSucursal, string $fCategoria, string $fBuscar): array
{
    $sql = "
        SELECT p.nombre AS producto, c.nombre_categoria AS categoria,
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
            . '<td>' . htmlspecialchars($p['producto']) . '</td>'
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
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['inventario']) ?></h1>
        <p>Consulta de solo lectura entre todas las sucursales <span id="inventarioActualizado" style="color:var(--text-muted);font-size:.85em"></span></p>
    </div>
</div>

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

<?php require 'footer.php'; ?>
