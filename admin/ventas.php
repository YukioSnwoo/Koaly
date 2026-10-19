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

$fSucursal = $_GET['sucursal'] ?? '';
$fFecha = $_GET['fecha'] ?? date('Y-m-d');

function obtenerStatsVentas(PDO $pdo, string $fFecha, string $fSucursal): array
{
    $sql = "SELECT COALESCE(SUM(total), 0) AS total_ventas,
                    COALESCE(AVG(total), 0) AS ticket_promedio,
                    COUNT(*) AS num_transacciones
             FROM Ventas WHERE DATE(fecha_hora) = ?";
    $params = [$fFecha];
    if ($fSucursal !== '') {
        $sql .= " AND id_sucursal = ?";
        $params[] = $fSucursal;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function obtenerVentas(PDO $pdo, string $fFecha, string $fSucursal): array
{
    $sql = "SELECT v.id_venta, s.nombre AS sucursal,
                   u.nombre AS cajero, v.fecha_hora, mp.nombre_metodo, v.total
            FROM Ventas v
            INNER JOIN Sucursales s ON s.id_sucursal = v.id_sucursal
            INNER JOIN Usuarios u ON u.id_usuario = v.id_cajero
            INNER JOIN Metodos_Pago mp ON mp.id_metodo_pago = v.id_metodo_pago
            WHERE DATE(v.fecha_hora) = ?";
    $params = [$fFecha];
    if ($fSucursal !== '') {
        $sql .= " AND v.id_sucursal = ?";
        $params[] = $fSucursal;
    }
    $sql .= " ORDER BY v.fecha_hora DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function renderFilasVentas(array $ventas): string
{
    if (empty($ventas)) {
        return '<tr><td colspan="6" style="color:var(--text-muted);text-align:center">No hay ventas registradas para esta fecha.</td></tr>';
    }

    $html = '';
    foreach ($ventas as $v) {
        $html .= '<tr>'
            . '<td>#' . str_pad((string) $v['id_venta'], 5, '0', STR_PAD_LEFT) . '</td>'
            . '<td style="color:var(--text-secondary)">' . htmlspecialchars($v['sucursal']) . '</td>'
            . '<td style="color:var(--text-secondary)">' . htmlspecialchars($v['cajero']) . '</td>'
            . '<td style="color:var(--text-secondary)">' . date('H:i', strtotime($v['fecha_hora'])) . '</td>'
            . '<td style="color:var(--text-secondary)">' . htmlspecialchars($v['nombre_metodo']) . '</td>'
            . '<td style="text-align:right">$' . number_format((float) $v['total'], 2) . '</td>'
            . '</tr>';
    }

    return $html;
}

$stats = obtenerStatsVentas($pdo, $fFecha, $fSucursal);
$ventas = obtenerVentas($pdo, $fFecha, $fSucursal);

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'total_ventas' => number_format($stats['total_ventas'], 0, '.', ','),
        'ticket_promedio' => number_format($stats['ticket_promedio'], 0, '.', ','),
        'num_transacciones' => (int) $stats['num_transacciones'],
        'filas' => renderFilasVentas($ventas),
    ]);
    exit;
}

$sucursales = $pdo->query("SELECT id_sucursal, nombre FROM Sucursales WHERE estado = 'Activa' ORDER BY nombre")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['ventas']) ?></h1>
        <p>Historial consolidado de todas las sucursales <span id="ventasActualizado" style="color:var(--text-muted);font-size:.85em"></span></p>
    </div>
</div>

<div class="stats-grid stats-grid--3" style="margin-top:1rem">
    <div class="stat-card">
        <p class="stat-card__label">Ventas de hoy</p>
        <p class="stat-card__value">$<span id="ventasHoyValor"><?= number_format($stats['total_ventas'], 0, '.', ',') ?></span></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Ticket promedio</p>
        <p class="stat-card__value">$<span id="ticketPromedioValor"><?= number_format($stats['ticket_promedio'], 0, '.', ',') ?></span></p>
    </div>
    <div class="stat-card">
        <p class="stat-card__label">Transacciones hoy</p>
        <p class="stat-card__value"><span id="transaccionesHoyValor"><?= $stats['num_transacciones'] ?></span></p>
    </div>
</div>

<div class="filters">
    <form method="GET" id="formFiltrosVentas" style="display:flex;gap:8px;flex-wrap:wrap;width:100%">
        <select name="sucursal" class="select">
            <option value="">Todas las sucursales</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id_sucursal'] ?>" <?= $fSucursal == $s['id_sucursal'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="fecha" class="input" value="<?= $fFecha ?>">
        <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
    </form>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>Folio</th><th>Sucursal</th><th>Cajero</th><th>Hora</th><th>Pago</th><th>Total</th>
        </tr></thead>
        <tbody id="tblVentasBody"><?= renderFilasVentas($ventas) ?></tbody>
    </table>
</div>

<script>
(function () {
    const tbody = document.getElementById('tblVentasBody');
    const indicador = document.getElementById('ventasActualizado');
    const valorVentasHoy = document.getElementById('ventasHoyValor');
    const valorTicketPromedio = document.getElementById('ticketPromedioValor');
    const valorTransacciones = document.getElementById('transaccionesHoyValor');
    const INTERVALO_MS = 8000;
    let temporizador = null;

    function urlActual() {
        const params = new URLSearchParams(new FormData(document.getElementById('formFiltrosVentas')));
        params.set('ajax', '1');
        return 'ventas.php?' + params.toString();
    }

    async function refrescar() {
        try {
            const res = await fetch(urlActual(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const datos = await res.json();
            tbody.innerHTML = datos.filas;
            valorVentasHoy.textContent = datos.total_ventas;
            valorTicketPromedio.textContent = datos.ticket_promedio;
            valorTransacciones.textContent = datos.num_transacciones;
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
