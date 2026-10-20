<?php
/**
 * Cerrar caja (corte a ciegas): recibe las piezas contadas, los vouchers y
 * cuánto se deja en caja. Lo esperado se calcula aquí con las ventas del turno,
 * se guarda el resultado y el corte queda "Pendiente" para el gerente.
 *
 *   esperado efectivo = efectivo de apertura + Σ Ventas.monto_efectivo del turno
 *   diferencia        = (contado - esperado efectivo) + (vouchers - Σ monto_tarjeta)
 *   retiro            = contado - lo que se deja (se entrega al gerente)
 */
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/includes/i18n.php';

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['success' => false, 'message' => t('servidor.metodo_no_permitido')]);
}
if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    responder(401, ['success' => false, 'message' => t('servidor.sesion_expirada')]);
}

require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/../admin/csrf.php';
require_once __DIR__ . '/includes/caja.php';

if (!csrfValido()) {
    responder(403, ['success' => false, 'message' => t('servidor.token_invalido')]);
}

try {
    usarHoraLocal($pdo);

    $cajero = cajeroConCaja($pdo, (int) $_SESSION['id_usuario']);
    if (!$cajero) {
        responder(403, ['success' => false, 'message' => t('servidor.cuenta_inactiva')]);
    }
    if ($cajero['id_caja'] === null) {
        responder(409, ['success' => false, 'message' => t('servidor.sin_caja')]);
    }

    /* --- Entrada: todo se recalcula aquí, no se confía en totales del navegador --- */
    [$desglose, $contadoCentavos] = leerDesglose($_POST['desglose'] ?? '');

    $voucherCentavos = montoEnCentavos($_POST['vouchers'] ?? '');
    $dejadoCentavos  = montoEnCentavos($_POST['dejado'] ?? '');
    if ($voucherCentavos === null || $dejadoCentavos === null) {
        throw new DomainException(t('servidor.monto_invalido'));
    }
    if ($dejadoCentavos > $contadoCentavos) {
        throw new DomainException(t('servidor.dejado_excede'));
    }

    $idCaja = (int) $cajero['id_caja'];
    $pdo->beginTransaction();

    // Bloquea el turno: una venta en curso termina antes, y dos clics no cierran dos veces
    $turno = turnoAbierto($pdo, $idCaja, true);
    if (!$turno) {
        $pdo->rollBack();
        responder(409, ['success' => false, 'caja_cerrada' => true, 'message' => t('servidor.caja_cerrada')]);
    }
    $idTurno = (int) $turno['id_turno'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS num_ventas,
               COALESCE(SUM(monto_efectivo), 0) AS efectivo,
               COALESCE(SUM(monto_tarjeta), 0) AS tarjeta
        FROM Ventas
        WHERE id_turno = ?
    ");
    $stmt->execute([$idTurno]);
    $ventas = $stmt->fetch(PDO::FETCH_ASSOC);

    $aperturaCentavos      = bdACentavos($turno['efectivo_apertura']);
    $teoricoEfectivo       = bdACentavos($ventas['efectivo']);
    $teoricoTarjeta        = bdACentavos($ventas['tarjeta']);
    $esperadoCentavos      = $aperturaCentavos + $teoricoEfectivo;
    $diferenciaEfectivo    = $contadoCentavos - $esperadoCentavos;
    $diferenciaTarjeta     = $voucherCentavos - $teoricoTarjeta;
    $diferencia            = $diferenciaEfectivo + $diferenciaTarjeta;
    $retiroCentavos        = $contadoCentavos - $dejadoCentavos;
    $resultado             = $diferencia === 0 ? 'Cuadrada' : ($diferencia < 0 ? 'Faltante' : 'Sobrante');
    // Se conserva la marca de la apertura (si la hubo)
    $requiereRevision      = (int) $turno['requiere_revision'] === 1
        || abs($diferencia) > UMBRAL_REVISION_CENTAVOS
        || abs($diferenciaEfectivo) > UMBRAL_REVISION_CENTAVOS
        || abs($diferenciaTarjeta) > UMBRAL_REVISION_CENTAVOS;

    $stmt = $pdo->prepare("
        UPDATE Turnos_Caja SET
            estado = 'Cerrado', id_cajero_cierre = ?, fecha_cierre = NOW(),
            num_ventas = ?, teorico_efectivo = ?, teorico_tarjeta = ?,
            desglose_cierre = ?, efectivo_contado = ?, vouchers = ?,
            efectivo_esperado = ?, diferencia_efectivo = ?, diferencia_tarjeta = ?, diferencia = ?,
            resultado = ?, requiere_revision = ?,
            efectivo_dejado = ?, efectivo_retirado = ?,
            estado_revision = 'Pendiente'
        WHERE id_turno = ? AND estado = 'Abierto'
    ");
    $stmt->execute([
        (int) $cajero['id_usuario'],
        (int) $ventas['num_ventas'],
        centavosABd($teoricoEfectivo),
        centavosABd($teoricoTarjeta),
        json_encode($desglose),
        centavosABd($contadoCentavos),
        centavosABd($voucherCentavos),
        centavosABd($esperadoCentavos),
        centavosABd($diferenciaEfectivo),
        centavosABd($diferenciaTarjeta),
        centavosABd($diferencia),
        $resultado,
        $requiereRevision ? 1 : 0,
        centavosABd($dejadoCentavos),
        centavosABd($retiroCentavos),
        $idTurno,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new DomainException(t('servidor.caja_cerrada'));
    }

    $pdo->commit();

    $stmt = $pdo->prepare("SELECT fecha_apertura, fecha_cierre FROM Turnos_Caja WHERE id_turno = ?");
    $stmt->execute([$idTurno]);
    $fechas = $stmt->fetch(PDO::FETCH_ASSOC);

    $c = fn(int $centavos) => $centavos / 100;
    responder(200, [
        'success' => true,
        'corte' => [
            'folio'               => $idTurno,
            'caja'                => t('ticket.caja_n', ['n' => $cajero['numero_caja']]) . ($cajero['caja_nombre'] ? ' · ' . $cajero['caja_nombre'] : ''),
            'cajero'              => $cajero['nombre'],
            'fecha_apertura'      => $fechas['fecha_apertura'],
            'fecha_cierre'        => $fechas['fecha_cierre'],
            'num_ventas'          => (int) $ventas['num_ventas'],
            'efectivo_apertura'   => $c($aperturaCentavos),
            'teorico_efectivo'    => $c($teoricoEfectivo),
            'teorico_tarjeta'     => $c($teoricoTarjeta),
            'efectivo_esperado'   => $c($esperadoCentavos),
            'efectivo_contado'    => $c($contadoCentavos),
            'vouchers'            => $c($voucherCentavos),
            'diferencia_efectivo' => $c($diferenciaEfectivo),
            'diferencia_tarjeta'  => $c($diferenciaTarjeta),
            'diferencia'          => $c($diferencia),
            'resultado'           => $resultado,
            'requiere_revision'   => $requiereRevision,
            'dejado'              => $c($dejadoCentavos),
            'retirado'            => $c($retiroCentavos),
            'desglose'            => $desglose,
        ],
    ]);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(400, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('turno_cerrar: ' . $e->getMessage());
    responder(500, ['success' => false, 'message' => t('servidor.error_bd')]);
}
