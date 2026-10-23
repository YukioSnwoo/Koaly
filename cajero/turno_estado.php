<?php
/**
 * Estado de la caja del cajero: si tiene turno abierto, lo que dejó el turno
 * anterior (para prellenar "Abrir caja") y, con el turno abierto, el arqueo:
 * lo vendido, los retiros parciales y el efectivo que debería haber en caja.
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

if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 3) {
    responder(401, ['success' => false, 'message' => t('servidor.sesion_expirada')]);
}

require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/includes/caja.php';

try {
    usarHoraLocal($pdo);

    $cajero = cajeroConCaja($pdo, (int) $_SESSION['id_usuario']);
    if (!$cajero) {
        responder(403, ['success' => false, 'message' => t('servidor.cuenta_inactiva')]);
    }
    if ($cajero['id_caja'] === null) {
        responder(200, ['success' => true, 'caja' => null, 'turno' => null, 'anterior' => null, 'arqueo' => null, 'retiros' => null]);
    }

    $idCaja   = (int) $cajero['id_caja'];
    $turno    = turnoAbierto($pdo, $idCaja);
    $anterior = ultimoTurnoCerrado($pdo, $idCaja);

    responder(200, [
        'success' => true,
        'ahora'   => date('Y-m-d H:i:s'),
        'cajero'  => $cajero['nombre'],
        'caja' => [
            'id'              => $idCaja,
            'numero'          => $cajero['numero_caja'],
            'nombre'          => $cajero['caja_nombre'],
            'etiqueta'        => nombreCaja($cajero),
            'estado'          => $cajero['caja_estado'],
            'fondo_fijo'      => (float) $cajero['fondo_fijo'],
            'limite_efectivo' => (float) $cajero['limite_efectivo'],
        ],
        'turno' => $turno ? [
            'id'                => (int) $turno['id_turno'],
            'fecha_apertura'    => $turno['fecha_apertura'],
            'efectivo_apertura' => (float) $turno['efectivo_apertura'],
        ] : null,
        'anterior' => $anterior ? [
            'efectivo_dejado' => $anterior['efectivo_dejado'] !== null ? (float) $anterior['efectivo_dejado'] : null,
            'fecha_cierre'    => $anterior['fecha_cierre'],
            'cajero'          => $anterior['cajero'],
        ] : null,
        'arqueo'  => $turno ? arqueoEnPesos(arqueoTurno($pdo, $turno), $cajero) : null,
        'retiros' => $turno ? retirosDelTurno($pdo, (int) $turno['id_turno']) : null,
    ]);
} catch (Throwable $e) {
    error_log('turno_estado: ' . $e->getMessage());
    responder(500, ['success' => false, 'message' => t('servidor.turnos_sin_configurar')]);
}
