<?php
/**
 * Estado de la caja del cajero: si tiene turno abierto y lo que dejó el turno
 * anterior (para prellenar "Abrir caja"). Nunca manda lo vendido ni lo
 * esperado del turno: el corte es a ciegas.
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
        responder(200, ['success' => true, 'caja' => null, 'turno' => null, 'anterior' => null]);
    }

    $idCaja   = (int) $cajero['id_caja'];
    $turno    = turnoAbierto($pdo, $idCaja);
    $anterior = ultimoTurnoCerrado($pdo, $idCaja);

    responder(200, [
        'success' => true,
        'caja' => [
            'id'         => $idCaja,
            'numero'     => $cajero['numero_caja'],
            'nombre'     => $cajero['caja_nombre'],
            'estado'     => $cajero['caja_estado'],
            'fondo_fijo' => (float) $cajero['fondo_fijo'],
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
    ]);
} catch (Throwable $e) {
    error_log('turno_estado: ' . $e->getMessage());
    responder(500, ['success' => false, 'message' => t('servidor.turnos_sin_configurar')]);
}
