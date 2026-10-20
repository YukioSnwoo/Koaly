<?php
/**
 * Abrir caja: el cajero confirma o corrige cuánto hay (lo que dejó el turno
 * anterior viene prellenado). Si no coincide, queda la diferencia de apertura
 * y, si pasa del umbral, el turno se marca para que lo revise el gerente.
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
    if ($cajero['caja_estado'] !== 'Activa') {
        responder(409, ['success' => false, 'message' => t('servidor.caja_no_activa')]);
    }

    // Cuánto hay: con el conteo de piezas (si se contó) o el monto escrito
    $desglose = null;
    if (!empty($_POST['desglose'])) {
        [$desglose, $aperturaCentavos] = leerDesglose($_POST['desglose']);
    } else {
        $aperturaCentavos = montoEnCentavos($_POST['efectivo'] ?? '');
        if ($aperturaCentavos === null) {
            throw new DomainException(t('servidor.monto_invalido'));
        }
    }

    $idCaja = (int) $cajero['id_caja'];
    $pdo->beginTransaction();

    // Bloquea la caja: dos clics en "Abrir caja" no crean dos turnos
    $pdo->prepare("SELECT id_caja FROM Cajas WHERE id_caja = ? FOR UPDATE")->execute([$idCaja]);

    $turno = turnoAbierto($pdo, $idCaja);
    if (!$turno) {
        $anterior = ultimoTurnoCerrado($pdo, $idCaja);
        $anteriorCentavos = ($anterior && $anterior['efectivo_dejado'] !== null) ? bdACentavos($anterior['efectivo_dejado']) : null;
        $diferenciaCentavos = $anteriorCentavos !== null ? $aperturaCentavos - $anteriorCentavos : null;

        $pdo->prepare("
            INSERT INTO Turnos_Caja
                (id_caja, id_sucursal, estado, id_cajero_apertura, fecha_apertura,
                 efectivo_anterior, efectivo_apertura, desglose_apertura, diferencia_apertura, requiere_revision)
            VALUES (?, ?, 'Abierto', ?, NOW(), ?, ?, ?, ?, ?)
        ")->execute([
            $idCaja,
            (int) $cajero['id_sucursal'],
            (int) $cajero['id_usuario'],
            $anteriorCentavos !== null ? centavosABd($anteriorCentavos) : null,
            centavosABd($aperturaCentavos),
            $desglose !== null ? json_encode($desglose) : null,
            $diferenciaCentavos !== null ? centavosABd($diferenciaCentavos) : null,
            ($diferenciaCentavos !== null && abs($diferenciaCentavos) > UMBRAL_REVISION_CENTAVOS) ? 1 : 0,
        ]);
        $turno = turnoAbierto($pdo, $idCaja);
    }

    $pdo->commit();

    responder(200, [
        'success' => true,
        'turno' => [
            'id'                => (int) $turno['id_turno'],
            'fecha_apertura'    => $turno['fecha_apertura'],
            'efectivo_apertura' => (float) $turno['efectivo_apertura'],
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
    error_log('turno_abrir: ' . $e->getMessage());
    responder(500, ['success' => false, 'message' => t('servidor.error_bd')]);
}
