<?php
require_once __DIR__ . '/guardia.php';
require_once __DIR__ . '/includes/turnos.php';
require_once __DIR__ . '/includes/vistas.php';

/*
 * Cortes de caja de la sucursal (Turnos_Caja, sql/008 y sql/012).
 * Cada cajero cierra su caja con un corte; aquí el gerente ve lo esperado, lo
 * contado y la diferencia, y confirma que recibió el efectivo: el retiro final
 * más los retiros parciales que el cajero metió a la tómbola durante el turno.
 * La página es solo la lista: una fila corta por corte y, al tocarla, el detalle.
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

// Cuántos hay en cada pestaña
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(t.estado_revision = 'Pendiente'), 0) AS pendientes,
        COALESCE(SUM(t.requiere_revision = 1), 0)          AS diferencias
    FROM Turnos_Caja t
    WHERE t.id_sucursal = ? AND t.estado = 'Cerrado'
");
$stmt->execute([$idSucursalGerente]);
$resumen = $stmt->fetch();
$conteoFiltro = ['pendientes' => (int) $resumen['pendientes'], 'diferencias' => (int) $resumen['diferencias']];

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

<div class="corte-filtros">
    <div class="segmentado" role="group" aria-label="<?= e(t('turnos.filtro_aria')) ?>">
        <?php foreach ($filtros as $clave => [$texto]): ?>
            <a href="?ver=<?= $clave ?>" class="<?= $clave === $ver ? 'is-activo' : '' ?>" <?= $clave === $ver ? 'aria-current="true"' : '' ?>>
                <?= e($texto) ?><?php if (!empty($conteoFiltro[$clave])): ?> <span class="corte-conteo"><?= numero($conteoFiltro[$clave]) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
// Botón para recibir un corte pendiente (en la fila y en el detalle)
$botonRecibir = fn (array $c): string => '<button type="button" class="btn btn-primary btn-sm" onclick="abrirRecibirCorte(this)"'
    . ' data-id="' . (int) $c['id_turno'] . '"'
    . ' data-monto="' . e(number_format((float) $c['total_entrega'], 2, '.', '')) . '"'
    . ' data-cajero="' . e($c['cajero'] ?? '—') . '"'
    . ' data-caja="' . e($c['numero_caja']) . '">' . e(t('turnos.recibir')) . '</button>';
?>

<div class="vista-tabla corte-lista">
    <table class="tabla-tarjetas">
        <thead><tr>
            <th><?= e(t('turnos.col_corte')) ?></th>
            <th><?= e(t('turnos.col_cierre')) ?></th>
            <th><?= e(t('turnos.col_diferencia')) ?></th>
            <th class="num"><?= e(t('turnos.col_entrega')) ?></th>
            <th><?= e(t('turnos.col_revision')) ?></th>
        </tr></thead>
        <tbody>
        <?php if (!$cortes): ?>
            <tr><td colspan="5" class="empty-state"><?= e(t('turnos.sin_cortes')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($cortes as $c):
            $pendiente = $c['estado_revision'] === 'Pendiente';
            $revisar   = (int) $c['requiere_revision'] === 1;
            $difAbrir  = $c['diferencia_apertura'] !== null && (float) $c['diferencia_apertura'] != 0;
            $distinto  = !$pendiente && round((float) $c['monto_recibido'], 2) != round((float) $c['total_entrega'], 2);
        ?>
            <tr class="fila-desplegable<?= $revisar ? ' corte-revisar' : '' ?>" aria-expanded="false">
                <td><div class="fila-id">
                    <button type="button" class="fila-abrir" aria-expanded="false" aria-label="<?= e(t('turnos.ver_detalle', ['folio' => (int) $c['id_turno']])) ?>">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
                    </button>
                    <div class="pz-tecla" aria-hidden="true"><?= e($c['numero_caja']) ?></div>
                    <div class="pz-nombres">
                        <span class="pz-nombre"><?= e($c['cajero'] ?? '—') ?></span>
                        <span class="fila-segunda"><?= e(t('turnos.folio_caja', ['folio' => (int) $c['id_turno'], 'caja' => $c['numero_caja'] . ($c['caja_nombre'] ? ' · ' . $c['caja_nombre'] : '')])) ?></span>
                    </div>
                </div></td>
                <td><?= e(fecha($c['fecha_cierre'])) ?><span class="fila-segunda"><?= e(tn('tarjeta.ventas', (int) $c['num_ventas'])) ?></span></td>
                <td>
                    <span class="badge <?= $badgeResultado[$c['resultado']] ?? 'badge-inactivo' ?>"><?= e(t('resultado.' . $c['resultado'])) ?></span>
                    <span class="fila-segunda<?= $revisar ? ' texto-peligro' : '' ?>"><?= e(dineroConSigno($c['diferencia'])) ?></span>
                </td>
                <td class="num"><strong><?= dinero($c['total_entrega']) ?></strong></td>
                <td class="fila-acciones">
                    <?php if ($pendiente): ?>
                        <?= $botonRecibir($c) ?>
                    <?php else: ?>
                        <span class="badge <?= $distinto ? 'badge-warning' : 'badge-activo' ?>"><?= e(t('revision.Recibido')) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="fila-detalle" hidden>
                <td colspan="5">
                    <div class="det-rejilla">
                        <?= datoDetalle(t('turnos.col_turno'), '<span>' . e(fecha($c['fecha_apertura'])) . '</span><span class="fila-segunda">→ ' . e(fecha($c['fecha_cierre'])) . '</span>') ?>
                        <?= datoDetalle(t('turnos.efectivo'),
                            '<span>' . e(t('turnos.esperado', ['monto' => dinero($c['efectivo_esperado'])])) . '</span>'
                            . '<span>' . e(t('turnos.contado', ['monto' => dinero($c['efectivo_contado'])])) . '</span>') ?>
                        <?= datoDetalle(t('turnos.tarjeta'),
                            '<span>' . e(t('turnos.esperado', ['monto' => dinero($c['teorico_tarjeta'])])) . '</span>'
                            . '<span>' . e(t('turnos.vouchers', ['monto' => dinero($c['vouchers'])])) . '</span>') ?>
                        <?= datoDetalle(t('turnos.col_entrega'),
                            '<strong>' . dinero($c['total_entrega']) . '</strong>'
                            . ((float) $c['retiros_parciales'] > 0 ? '<span class="fila-segunda">' . e(t('turnos.incluye_retiros', ['monto' => dinero($c['retiros_parciales'])])) . '</span>' : '')) ?>
                        <?= datoDetalle(t('turnos.col_dejado'), '<span>' . dinero($c['efectivo_dejado']) . '</span>') ?>
                        <?= datoDetalle(t('turnos.col_diferencia'),
                            '<span class="' . ($revisar ? 'texto-peligro' : '') . '">' . e(dineroConSigno($c['diferencia'])) . '</span>'
                            . ($difAbrir ? '<span class="fila-segunda texto-peligro">' . e(t('turnos.al_abrir', ['monto' => dineroConSigno($c['diferencia_apertura'])])) . '</span>' : '')) ?>
                        <?= datoDetalle(t('turnos.col_revision'), $pendiente
                            ? '<span>' . e(t('turnos.sin_recibir')) . '</span>'
                            : '<span>' . e(t('turnos.recibido_por', ['gerente' => $c['gerente_revision'] ?? '—', 'fecha' => $c['fecha_revision'] ? fecha($c['fecha_revision']) : ''])) . '</span>'
                                . ($distinto ? '<span class="texto-peligro">' . e(t('turnos.recibido_distinto', ['monto' => dinero($c['monto_recibido'])])) . '</span>' : '')
                                . ($c['nota_revision'] ? '<span class="det-largo">' . e($c['nota_revision']) . '</span>' : ''), 'det-lateral') ?>
                    </div>
                    <?php if ($pendiente): ?>
                        <div class="pz-acciones det-acciones"><?= $botonRecibir($c) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
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
