<?php
/**
 * Retiro parcial (como en OXXO): durante el turno el cajero mete a la tómbola el
 * efectivo que sobra, para no tener un excedente en caja. Recibe las piezas que
 * se retiran; el monto se descuenta de lo esperado en caja y, al cerrar, se suma
 * a lo que se entrega al gerente.
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

    [$desglose, $montoCentavos] = leerDesglose($_POST['desglose'] ?? '');
    if ($montoCentavos <= 0) {
        throw new DomainException(t('servidor.retiro_vacio'));
    }

    $idCaja = (int) $cajero['id_caja'];
    $pdo->beginTransaction();

    // Bloquea el turno: una venta o un corte en curso terminan antes
    $turno = turnoAbierto($pdo, $idCaja, true);
    if (!$turno) {
        $pdo->rollBack();
        responder(409, ['success' => false, 'caja_cerrada' => true, 'message' => t('servidor.caja_cerrada')]);
    }
    $idTurno = (int) $turno['id_turno'];

    // No se puede sacar más de lo que debería haber en caja
    $esperado = arqueoTurno($pdo, $turno)['esperado'];
    if ($montoCentavos > $esperado) {
        throw new DomainException(t('servidor.retiro_excede', ['monto' => dinero($esperado / 100)]));
    }

    $pdo->prepare("
        INSERT INTO Retiros_Caja (id_turno, id_cajero, fecha, monto, desglose)
        VALUES (?, ?, NOW(), ?, ?)
    ")->execute([
        $idTurno,
        (int) $cajero['id_usuario'],
        centavosABd($montoCentavos),
        json_encode($desglose),
    ]);
    $idRetiro = (int) $pdo->lastInsertId();

    $pdo->commit();

    $stmt = $pdo->prepare("SELECT fecha FROM Retiros_Caja WHERE id_retiro = ?");
    $stmt->execute([$idRetiro]);
    $arqueo = arqueoTurno($pdo, $turno);

    responder(200, [
        'success' => true,
        'retiro' => [
            'folio'    => $idRetiro,
            'turno'    => $idTurno,
            'caja'     => nombreCaja($cajero),
            'cajero'   => $cajero['nombre'],
            'fecha'    => $stmt->fetchColumn(),
            'monto'    => $montoCentavos / 100,
            'en_caja'  => $arqueo['esperado'] / 100,
            'desglose' => $desglose,
        ],
        'arqueo'  => arqueoEnPesos($arqueo, $cajero),
        'retiros' => retirosDelTurno($pdo, $idTurno),
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
    error_log('turno_retiro: ' . $e->getMessage());
    responder(500, ['success' => false, 'message' => t('servidor.error_bd')]);
}
