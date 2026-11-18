<?php
/**
 * "Cancelar solicitud" en Mi perfil: cancela el cambio de nombre o correo que el
 * usuario tiene pendiente (admin/solicitudes_datos.php). POST con csrf_token.
 * Responde {ok, mensaje, solicitud_html, pendiente} (también si ya no estaba
 * pendiente: así el panel se pone al día) o {ok: false, error, mensaje}.
 */
require_once __DIR__ . '/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    perfilError(405, 'base_datos');
}
if (!csrfValido()) {
    perfilError(403, 'csrf');
}

try {
    // false: ya la habían resuelto o cancelado; se responde con cómo quedó
    $cancelada = cancelarSolicitudDatos($pdo, (int) $usuario['id_usuario']);
} catch (PDOException $e) {
    perfilError(500, $e->getCode() === '42S02' ? 'sin_tabla_solicitudes' : 'base_datos');
}

$ultima = ultimaSolicitudDatos($pdo, (int) $usuario['id_usuario']);
perfilResponder(200, [
    'ok'             => true,
    'mensaje'        => perfilT($cancelada ? 'form.solicitud_cancelada' : 'error.sin_solicitud'),
    'solicitud_html' => perfilHtmlSolicitud($ultima),
    'pendiente'      => $ultima !== null && $ultima['estado'] === 'Pendiente',
]);
