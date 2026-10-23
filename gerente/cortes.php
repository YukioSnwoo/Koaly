<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/turnos.php';

/*
 * Cortes de caja de la sucursal (Turnos_Caja, sql/008 y sql/012).
 * Cada cajero cierra su caja con un corte; aquí el gerente ve lo esperado, lo
 * contado y la diferencia, y confirma que recibió el efectivo: el retiro final
 * más los retiros parciales que el cajero metió a la tómbola durante el turno.
 * De las cajas abiertas ve el arqueo al momento (lo que debería haber en caja).
 */

// Lo que el cajero entrega al cerrar: el retiro final más lo que ya está en la tómbola
const SQL_TOTAL_ENTREGA = 't.efectivo_retirado + COALESCE(t.retiros_parciales, 0)';

$errores = [];
$exito   = '';

if (!empty($_SESSION['flash'])) {
    $exito = $_SESSION['flash']['msg'] ?? '';
    unset($_SESSION['flash']);
}

/* ------------------------------------------------------------
   Recibir un corte: el gerente confirma el efectivo que le entregaron
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'recibir') {
    try {
        if (!csrfValido()) {
            throw new RuntimeException(t('error.formulario_expirado'));
        }
        $idTurno = (int) ($_POST['id_turno'] ?? 0);
        $recibidoCentavos = montoFormularioEnCentavos($_POST['monto_recibido'] ?? '');
        $nota = trim($_POST['nota'] ?? '');
        if ($recibidoCentavos === null) {
            throw new RuntimeException(t('turnos.error_monto'));
        }
        if (mb_strlen($nota) > 255) {
            $nota = mb_substr($nota, 0, 255);
        }

        // IDOR: solo cortes cerrados y pendientes de esta sucursal
        $stmt = $pdo->prepare("
            SELECT " . SQL_TOTAL_ENTREGA . " FROM Turnos_Caja t
            WHERE t.id_turno = ? AND t.id_sucursal = ? AND t.estado = 'Cerrado' AND t.estado_revision = 'Pendiente'
        ");
        $stmt->execute([$idTurno, $idSucursalGerente]);
        $porEntregar = $stmt->fetchColumn();
        if ($porEntregar === false) {
            throw new RuntimeException(t('turnos.error_corte'));
        }
        $coincide = $recibidoCentavos === (int) round((float) $porEntregar * 100);
        if (!$coincide && $nota === '') {
            throw new RuntimeException(t('turnos.error_nota'));
        }

        $stmt = $pdo->prepare("
            UPDATE Turnos_Caja SET
                estado_revision = 'Recibido', id_gerente_revision = ?, fecha_revision = NOW(),
                monto_recibido = ?, nota_revision = ?,
                requiere_revision = IF(?, requiere_revision, 1)
            WHERE id_turno = ? AND id_sucursal = ? AND estado_revision = 'Pendiente'
        ");
        $stmt->execute([
            (int) $gerente['id_usuario'],
            number_format($recibidoCentavos / 100, 2, '.', ''),
            $nota !== '' ? $nota : null,
            $coincide ? 1 : 0,
            $idTurno,
            $idSucursalGerente,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(t('turnos.error_corte'));
        }

        $_SESSION['flash'] = ['msg' => t('turnos.recibido_ok', ['folio' => $idTurno])];
        header('Location: cortes.php' . (isset($_GET['ver']) ? '?ver=' . urlencode((string) $_GET['ver']) : ''));
        exit;
    } catch (RuntimeException $e) {
        $errores[] = $e->getMessage();
    } catch (PDOException $e) {
        $errores[] = t('error.base_datos');
    }
}

/* ------------------------------------------------------------
   Datos
   ------------------------------------------------------------ */
$filtros = [
    'pendientes'  => [t('turnos.filtro_pendientes'),  "t.estado_revision = 'Pendiente'"],
    'diferencias' => [t('turnos.filtro_diferencias'), "t.requiere_revision = 1"],
    'todos'       => [t('turnos.filtro_todos'),       "1 = 1"],
];
$ver = $_GET['ver'] ?? 'pendientes';
if (!isset($filtros[$ver])) {
    $ver = 'pendientes';
}

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(t.estado_revision = 'Pendiente'), 0)                                   AS pendientes,
        COALESCE(SUM(IF(t.estado_revision = 'Pendiente', " . SQL_TOTAL_ENTREGA . ", 0)), 0) AS por_recibir,
        COALESCE(SUM(t.estado = 'Cerrado' AND t.requiere_revision = 1), 0)                   AS diferencias,
        COALESCE(SUM(t.estado = 'Abierto'), 0)                                              AS abiertas
    FROM Turnos_Caja t
    WHERE t.id_sucursal = ?
");
$stmt->execute([$idSucursalGerente]);
$resumen = $stmt->fetch();

// Cajas abiertas ahora, con su arqueo: apertura + efectivo de las ventas − retiros parciales
$stmt = $pdo->prepare("
    SELECT t.id_turno, t.fecha_apertura, t.efectivo_apertura, t.diferencia_apertura,
           c.numero_caja, c.nombre AS caja_nombre, c.limite_efectivo, u.nombre AS cajero,
           (SELECT COALESCE(SUM(v.monto_efectivo), 0) FROM Ventas v WHERE v.id_turno = t.id_turno) AS ventas_efectivo,
           (SELECT COUNT(*) FROM Retiros_Caja r WHERE r.id_turno = t.id_turno) AS num_retiros,
           (SELECT COALESCE(SUM(r.monto), 0) FROM Retiros_Caja r WHERE r.id_turno = t.id_turno) AS retiros
    FROM Turnos_Caja t
    JOIN Cajas c ON c.id_caja = t.id_caja
    LEFT JOIN Usuarios u ON u.id_usuario = t.id_cajero_apertura
    WHERE t.id_sucursal = ? AND t.estado = 'Abierto'
    ORDER BY c.numero_caja
");
$stmt->execute([$idSucursalGerente]);
$abiertas = $stmt->fetchAll();
foreach ($abiertas as &$a) {
    $a['esperado'] = (float) $a['efectivo_apertura'] + (float) $a['ventas_efectivo'] - (float) $a['retiros'];
}
unset($a);

// Cortes (turnos cerrados)
$stmt = $pdo->prepare("
    SELECT t.*, " . SQL_TOTAL_ENTREGA . " AS total_entrega, c.numero_caja, c.nombre AS caja_nombre,
           uc.nombre AS cajero, ug.nombre AS gerente_revision
    FROM Turnos_Caja t
    JOIN Cajas c ON c.id_caja = t.id_caja
    LEFT JOIN Usuarios uc ON uc.id_usuario = t.id_cajero_cierre
    LEFT JOIN Usuarios ug ON ug.id_usuario = t.id_gerente_revision
    WHERE t.id_sucursal = ? AND t.estado = 'Cerrado' AND {$filtros[$ver][1]}
    ORDER BY t.fecha_cierre DESC, t.id_turno DESC
    LIMIT 100
");
$stmt->execute([$idSucursalGerente]);
$cortes = $stmt->fetchAll();

$badgeResultado = ['Cuadrada' => 'badge-activo', 'Faltante' => 'badge-danger', 'Sobrante' => 'badge-warning'];

$pageTitle    = t('turnos.cortes');
require __DIR__ . '/header.php';
?>

<style>
    /* Solo para esta página */
    .tabla-productos tr.corte-revisar td { background: var(--peligro-suave); }
    .tabla-productos tr.corte-revisar td:first-child { box-shadow: inset 4px 0 0 var(--peligro); }
    .corte-sub { display: block; font-size: 0.78rem; color: var(--texto-3); }
    .corte-filtros { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem; }
    .corte-abiertas { margin-bottom: 1.5rem; }
    .corte-abiertas h2 { font-size: 1rem; font-weight: 700; padding: 1rem 1.25rem 0; color: var(--texto); }
</style>

<div class="page-header">
    <div>
        <h1><?= e(t('turnos.cortes')) ?></h1>
        <p><?= e(t('turnos.cortes_desc')) ?></p>
    </div>
</div>

<?php if ($exito): ?>
    <div class="alert alert-success"><?= e($exito) ?></div>
<?php endif; ?>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="kpi-grid">
    <div class="kpi <?= (int) $resumen['pendientes'] > 0 ? 'kpi--warn' : 'kpi--ok' ?>">
        <span class="kpi-label"><?= e(t('turnos.kpi_pendientes')) ?></span>
        <span class="kpi-value"><?= numero((int) $resumen['pendientes']) ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label"><?= e(t('turnos.kpi_por_recibir')) ?></span>
        <span class="kpi-value"><?= dinero($resumen['por_recibir']) ?></span>
        <span class="kpi-sub"><?= e(t('turnos.kpi_por_recibir_sub')) ?></span>
    </div>
    <div class="kpi <?= (int) $resumen['diferencias'] > 0 ? 'kpi--danger' : '' ?>">
        <span class="kpi-label"><?= e(t('turnos.kpi_diferencias')) ?></span>
        <span class="kpi-value <?= (int) $resumen['diferencias'] > 0 ? 'kpi-value--danger' : '' ?>"><?= numero((int) $resumen['diferencias']) ?></span>
        <span class="kpi-sub"><?= e(t('turnos.kpi_diferencias_sub')) ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label"><?= e(t('turnos.kpi_abiertas')) ?></span>
        <span class="kpi-value"><?= numero((int) $resumen['abiertas']) ?></span>
        <span class="kpi-sub"><?= e(t('turnos.kpi_abiertas_sub')) ?></span>
    </div>
</section>

<?php if ($abiertas): ?>
    <div class="panel corte-abiertas">
        <h2><?= e(t('turnos.abiertas_titulo')) ?></h2>
        <div class="tabla-scroll">
            <table class="tabla-productos">
                <thead>
                    <tr>
                        <th><?= e(t('turnos.col_caja')) ?></th>
                        <th><?= e(t('turnos.col_cajero')) ?></th>
                        <th><?= e(t('turnos.desde')) ?></th>
                        <th class="num"><?= e(t('turnos.inicio_con')) ?></th>
                        <th class="num"><?= e(t('turnos.col_ventas_efectivo')) ?></th>
                        <th class="num"><?= e(t('turnos.col_retiros')) ?></th>
                        <th class="num"><?= e(t('turnos.col_esperado_ahora')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($abiertas as $a): ?>
                        <tr>
                            <td><?= e($a['numero_caja']) ?><?= $a['caja_nombre'] ? ' · ' . e($a['caja_nombre']) : '' ?></td>
                            <td><?= e($a['cajero'] ?? '—') ?></td>
                            <td><?= e(fecha($a['fecha_apertura'])) ?></td>
                            <td class="num">
                                <?= dinero($a['efectivo_apertura']) ?>
                                <?php if ((float) $a['diferencia_apertura'] != 0): ?>
                                    <span class="corte-sub texto-peligro"><?= e(t('turnos.al_abrir', ['monto' => dineroConSigno($a['diferencia_apertura'])])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= dinero($a['ventas_efectivo']) ?></td>
                            <td class="num">
                                <?= dinero($a['retiros']) ?>
                                <span class="corte-sub"><?= e(tn('turnos.n_retiros', (int) $a['num_retiros'])) ?></span>
                            </td>
                            <td class="num">
                                <strong><?= dinero($a['esperado']) ?></strong>
                                <?php if ($a['esperado'] > (float) $a['limite_efectivo']): ?>
                                    <span class="corte-sub"><span class="badge badge-warning"><?= e(t('turnos.excedente')) ?></span></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="corte-filtros">
    <div class="segmentado" role="group" aria-label="<?= e(t('turnos.filtro_aria')) ?>">
        <?php foreach ($filtros as $clave => [$texto]): ?>
            <a href="?ver=<?= $clave ?>" class="<?= $clave === $ver ? 'is-activo' : '' ?>" <?= $clave === $ver ? 'aria-current="true"' : '' ?>><?= e($texto) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel">
    <div class="tabla-scroll">
        <table class="tabla-productos">
            <thead>
                <tr>
                    <th><?= e(t('turnos.col_folio')) ?></th>
                    <th><?= e(t('turnos.col_caja')) ?></th>
                    <th><?= e(t('turnos.col_turno')) ?></th>
                    <th class="num"><?= e(t('turnos.col_ventas')) ?></th>
                    <th class="num"><?= e(t('turnos.col_efectivo')) ?></th>
                    <th class="num"><?= e(t('turnos.col_tarjeta')) ?></th>
                    <th><?= e(t('turnos.col_diferencia')) ?></th>
                    <th class="num"><?= e(t('turnos.col_entrega')) ?></th>
                    <th><?= e(t('turnos.col_revision')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$cortes): ?>
                    <tr><td colspan="9" class="empty-state"><?= e(t('turnos.sin_cortes')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($cortes as $c): ?>
                    <tr class="<?= (int) $c['requiere_revision'] === 1 ? 'corte-revisar' : '' ?>">
                        <td>#<?= (int) $c['id_turno'] ?></td>
                        <td>
                            <?= e($c['numero_caja']) ?><?= $c['caja_nombre'] ? ' · ' . e($c['caja_nombre']) : '' ?>
                            <span class="corte-sub"><?= e($c['cajero'] ?? '—') ?></span>
                        </td>
                        <td>
                            <?= e(fecha($c['fecha_apertura'])) ?>
                            <span class="corte-sub">→ <?= e(fecha($c['fecha_cierre'])) ?></span>
                        </td>
                        <td class="num"><?= numero((int) $c['num_ventas']) ?></td>
                        <td class="num">
                            <?= dinero($c['efectivo_esperado']) ?>
                            <span class="corte-sub"><?= dinero($c['efectivo_contado']) ?></span>
                        </td>
                        <td class="num">
                            <?= dinero($c['teorico_tarjeta']) ?>
                            <span class="corte-sub"><?= dinero($c['vouchers']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $badgeResultado[$c['resultado']] ?? 'badge-inactivo' ?>"><?= e(t('resultado.' . $c['resultado'])) ?></span>
                            <span class="corte-sub"><?= e(dineroConSigno($c['diferencia'])) ?></span>
                            <?php if ($c['diferencia_apertura'] !== null && (float) $c['diferencia_apertura'] != 0): ?>
                                <span class="corte-sub texto-peligro"><?= e(t('turnos.al_abrir', ['monto' => dineroConSigno($c['diferencia_apertura'])])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num">
                            <strong><?= dinero($c['total_entrega']) ?></strong>
                            <?php if ((float) $c['retiros_parciales'] > 0): ?>
                                <span class="corte-sub"><?= e(t('turnos.incluye_retiros', ['monto' => dinero($c['retiros_parciales'])])) ?></span>
                            <?php endif; ?>
                            <span class="corte-sub"><?= e(t('turnos.col_dejado')) ?>: <?= dinero($c['efectivo_dejado']) ?></span>
                        </td>
                        <td>
                            <?php if ($c['estado_revision'] === 'Pendiente'): ?>
                                <button type="button" class="btn btn-primary btn-sm"
                                    onclick="abrirRecibirCorte(this)"
                                    data-id="<?= (int) $c['id_turno'] ?>"
                                    data-monto="<?= e(number_format((float) $c['total_entrega'], 2, '.', '')) ?>"
                                    data-cajero="<?= e($c['cajero'] ?? '—') ?>"
                                    data-caja="<?= e($c['numero_caja']) ?>"
                                ><?= e(t('turnos.recibir')) ?></button>
                            <?php else: ?>
                                <span class="badge badge-activo"><?= e(t('revision.Recibido')) ?></span>
                                <span class="corte-sub"><?= e(t('turnos.recibido_por', ['gerente' => $c['gerente_revision'] ?? '—', 'fecha' => $c['fecha_revision'] ? fecha($c['fecha_revision']) : ''])) ?></span>
                                <?php if (round((float) $c['monto_recibido'], 2) != round((float) $c['total_entrega'], 2)): ?>
                                    <span class="corte-sub texto-peligro"><?= e(t('turnos.recibido_distinto', ['monto' => dinero($c['monto_recibido'])])) ?></span>
                                <?php endif; ?>
                                <?php if ($c['nota_revision']): ?>
                                    <span class="corte-sub"><?= e($c['nota_revision']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: recibir el efectivo de un corte -->
<div class="modal-overlay" id="modalRecibir">
    <div class="modal-box modal-box--sm">
        <h2 id="tituloRecibir"></h2>
        <p class="modal-ayuda" id="ayudaRecibir"></p>
        <form method="POST" id="formRecibir">
            <input type="hidden" name="action" value="recibir">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id_turno" value="">
            <div class="form-group">
                <label for="montoRecibido"><?= e(t('turnos.monto_recibido')) ?></label>
                <input type="number" id="montoRecibido" name="monto_recibido" min="0" step="0.01" inputmode="decimal" required>
            </div>
            <div class="form-group">
                <label for="notaRecibir"><?= e(t('turnos.nota')) ?></label>
                <textarea id="notaRecibir" name="nota" maxlength="255" placeholder="<?= e(t('turnos.nota_ej')) ?>"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalRecibir')"><?= e(t('comun.cancelar')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(t('turnos.recibir')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    // Textos del modal (con la cantidad que se debe entregar ya puesta)
    const TEXTOS_RECIBIR = <?= json_encode([
        'titulo' => t('turnos.recibir_titulo'),
        'ayuda'  => t('turnos.recibir_ayuda'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function abrirRecibirCorte(boton) {
        const form = document.getElementById('formRecibir');
        form.reset();
        form.id_turno.value = boton.dataset.id;
        form.monto_recibido.value = boton.dataset.monto;
        document.getElementById('tituloRecibir').textContent = TEXTOS_RECIBIR.titulo.replace('{folio}', boton.dataset.id);
        document.getElementById('ayudaRecibir').textContent = TEXTOS_RECIBIR.ayuda
            .replace('{cajero}', boton.dataset.cajero).replace('{caja}', boton.dataset.caja);
        abrirModal('modalRecibir');
        form.monto_recibido.focus();
    }
</script>

<?php require __DIR__ . '/footer.php'; ?>
