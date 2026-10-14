<?php
// Hora local de las tiendas. Las columnas de fecha son TIMESTAMP: MariaDB las
// guarda en UTC y las convierte a la zona de la conexión, así que "hoy",
// CURDATE() y las fechas que se muestran quedan en hora de México.
const ZONA_HORARIA = 'America/Mexico_City';
// Minutos sin actividad antes de cerrar la sesión del gerente.
const MINUTOS_INACTIVIDAD = 30;

date_default_timezone_set(ZONA_HORARIA);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario']) || (int) $_SESSION['id_rol'] !== 2) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/../admin/csrf.php';

$pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");

$stmt = $pdo->prepare("
SELECT u.id_usuario, u.nombre, u.id_rol, u.estado, u.id_sucursal, u.debe_cambiar,
s.nombre AS sucursal_nombre, s.estado AS sucursal_estado
FROM Usuarios u
LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
WHERE u.id_usuario = ? AND u.id_rol = 2
LIMIT 1
");
$stmt->execute([$_SESSION['id_usuario']]);
$gerente = $stmt->fetch();

$inactivo = isset($_SESSION['ultima_actividad'])
    && time() - (int) $_SESSION['ultima_actividad'] > MINUTOS_INACTIVIDAD * 60;

if (
    $inactivo
    || !$gerente
    || $gerente['estado'] !== 'Activo'
    || $gerente['id_sucursal'] === null
    || $gerente['sucursal_estado'] !== 'Activa'
) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Forzar cambio de contraseña si aún no se ha hecho.
// No destruimos sesión: el usuario está autenticado, solo debe cambiar su
// contraseña antes de operar.
if ((int) $gerente['debe_cambiar'] === 1) {
    header('Location: ../index.php');
    exit;
}

$_SESSION['ultima_actividad'] = time();

$idSucursalGerente = (int) $gerente['id_sucursal'];
