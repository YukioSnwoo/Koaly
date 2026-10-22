<?php
/**
 * Arranque de los endpoints del menú de perfil (guardar.php, notificaciones.php):
 * sesión de admin, gerente o cajero activa, conexión en hora de México y
 * respuesta JSON. Deja listos $pdo y $usuario.
 */
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/perfil.php';

function perfilResponder(int $estado, array $datos): never
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function perfilError(int $estado, string $clave, ?string $mensaje = null): never
{
    perfilResponder($estado, ['ok' => false, 'error' => $clave, 'mensaje' => $mensaje ?? perfilT('error.' . $clave)]);
}

if (!isset($_SESSION['id_usuario']) || !in_array((int) ($_SESSION['id_rol'] ?? 0), [1, 2, 3], true)) {
    perfilError(401, 'sesion');
}

try {
    require_once __DIR__ . '/../admin/database.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Igual que gerente/guardia.php y la caja: fechas en hora de México
    date_default_timezone_set('America/Mexico_City');
    $pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");
    $usuario = perfilUsuario($pdo, (int) $_SESSION['id_usuario']);
} catch (PDOException $e) {
    perfilError(500, 'base_datos');
}

if ($usuario === null || $usuario['estado'] !== 'Activo' || (int) $usuario['id_rol'] !== (int) $_SESSION['id_rol']) {
    perfilError(401, 'sesion');
}
