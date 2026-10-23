<?php
/**
 * API del inicio de sesión. Recibe JSON por POST con `action`:
 *   login (por defecto)  correo + contraseña
 *   change_password      cambio obligatorio del primer ingreso (requiere sesión)
 *   send_recover_code    envía un código de 6 dígitos al correo
 *   reset_password       correo + código + contraseña nueva
 * Responde {success, message, ...}. Las tablas están en sql/004_login.sql
 * y sql/010_aviso_privacidad.sql.
 */
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

// Recuperación de contraseña
const CODIGO_MINUTOS_VALIDEZ = 15;
const CODIGO_MAX_INTENTOS    = 5;   // fallos antes de invalidar el código
const CODIGO_SEGUNDOS_ESPERA = 60;  // entre un envío y otro al mismo correo (login.js usa lo mismo)

// Inicio de sesión
const LOGIN_MINUTOS_BLOQUEO   = 15;
const LOGIN_MAX_FALLOS_CORREO = 5;
const LOGIN_MAX_FALLOS_IP     = 20;

const RUTAS_POR_ROL = [1 => 'admin/', 2 => 'gerente/', 3 => 'cajero/', 4 => 'recursos_humanos/'];

function responder(int $estado, array $datos): never
{
    http_response_code($estado);
    echo json_encode($datos);
    exit;
}

function error(int $estado, string $mensaje): never
{
    responder($estado, ['success' => false, 'message' => $mensaje]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error(405, 'Método no permitido');
}

// Misma conexión que usan admin y gerente (admin/database.php no se sube a git)
try {
    require_once __DIR__ . '/../admin/database.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error(500, 'Error de conexión a la base de datos');
}
require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/../privacidad/aviso.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    error(400, 'Solicitud inválida');
}

$action = (string) ($data['action'] ?? 'login');
$email  = strtolower(trim((string) ($data['email'] ?? '')));

require_once __DIR__ . '/reglas_contrasena.php';

function buscarUsuarioPorCorreo(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT id_usuario, nombre, email, estado FROM Usuarios WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function guardarContrasena(PDO $pdo, int $idUsuario, string $password): void
{
    $pdo->prepare('UPDATE Usuarios SET contrasena_hash = :hash, debe_cambiar = 0 WHERE id_usuario = :id')
        ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $idUsuario]);
}

// ========== ENVIAR CÓDIGO DE RECUPERACIÓN ==========
if ($action === 'send_recover_code') {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error(400, 'Ingresa un correo válido');
    }

    // La misma respuesta exista o no el correo, para no revelar qué cuentas hay
    $respuestaGenerica = [
        'success' => true,
        'message' => 'Si el correo está registrado, recibirás un código en unos minutos.'
    ];

    $usuario = buscarUsuarioPorCorreo($pdo, $email);
    if (!$usuario || $usuario['estado'] !== 'Activo') {
        responder(200, $respuestaGenerica);
    }

    // Evita que se usen los envíos para llenar el buzón de alguien
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM password_resets
         WHERE email = :email AND creado_en > NOW() - INTERVAL ' . CODIGO_SEGUNDOS_ESPERA . ' SECOND'
    );
    $stmt->execute([':email' => $email]);
    if ((int) $stmt->fetchColumn() > 0) {
        responder(200, $respuestaGenerica);
    }

    // Solo el último código sirve
    $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE email = :email AND usado = 0')
        ->execute([':email' => $email]);

    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Se guarda solo el hash: quien lea la tabla no puede usar los códigos.
    // La expiración la calcula MySQL para no depender de la zona horaria de PHP.
    $pdo->prepare(
        'INSERT INTO password_resets (email, codigo_hash, expira_en, usado, intentos)
         VALUES (:email, :codigo_hash, NOW() + INTERVAL ' . CODIGO_MINUTOS_VALIDEZ . ' MINUTE, 0, 0)'
    )->execute([
        ':email'       => $email,
        ':codigo_hash' => password_hash($codigo, PASSWORD_DEFAULT)
    ]);
    $idReset = $pdo->lastInsertId();

    $cuerpo = "Hola {$usuario['nombre']},\n\n"
            . "Tu código para restablecer la contraseña de Koaly es:\n\n"
            . "    {$codigo}\n\n"
            . 'Es válido por ' . CODIGO_MINUTOS_VALIDEZ . " minutos.\n"
            . "Si no lo pediste, ignora este mensaje: tu contraseña no cambiará.\n\n"
            . "— Equipo Koaly";

    // El código nunca se devuelve en la respuesta: si el correo no sale, no hay recuperación
    if (!enviarCorreo($usuario['email'], 'Código de recuperación - Koaly', $cuerpo)) {
        $pdo->prepare('DELETE FROM password_resets WHERE id = :id')->execute([':id' => $idReset]);
        error(500, 'No se pudo enviar el correo. Intenta más tarde o contacta al administrador.');
    }

    responder(200, $respuestaGenerica);
}

// ========== RESTABLECER CONTRASEÑA CON CÓDIGO ==========
if ($action === 'reset_password') {
    $codigo   = trim((string) ($data['code'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $codigo === '' || $password === '') {
        error(400, 'Completa todos los campos');
    }

    // Código vigente más reciente de ese correo
    $stmt = $pdo->prepare(
        'SELECT id, codigo_hash FROM password_resets
         WHERE email = :email AND usado = 0 AND expira_en > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        error(400, 'Código inválido o expirado');
    }

    if (!password_verify($codigo, $reset['codigo_hash'])) {
        // Al llegar al límite el código deja de servir: 6 dígitos no aguantan fuerza bruta.
        // `usado` va primero porque MySQL aplica el SET de izquierda a derecha.
        $pdo->prepare(
            'UPDATE password_resets
             SET usado = IF(intentos + 1 >= ' . CODIGO_MAX_INTENTOS . ', 1, usado), intentos = intentos + 1
             WHERE id = :id'
        )->execute([':id' => $reset['id']]);
        error(400, 'Código inválido o expirado');
    }

    $usuario = buscarUsuarioPorCorreo($pdo, $email);
    if (!$usuario) {
        error(400, 'Código inválido o expirado');
    }

    // Después de validar el código: una contraseña débil no consume el código
    $errorPass = passwordIsTooSimple($password, $email, $usuario['nombre']);
    if ($errorPass !== null) {
        error(400, $errorPass);
    }

    $pdo->beginTransaction();
    try {
        guardarContrasena($pdo, (int) $usuario['id_usuario'], $password);
        $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE email = :email AND usado = 0')
            ->execute([':email' => $email]);
        // Quien recupera su contraseña ya no debe seguir bloqueado por intentos fallidos
        $pdo->prepare('DELETE FROM login_intentos WHERE email = :email')
            ->execute([':email' => $email]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error(500, 'No se pudo actualizar la contraseña');
    }

    responder(200, [
        'success' => true,
        'message' => 'Contraseña restablecida. Ya puedes iniciar sesión.'
    ]);
}

// ========== CAMBIO DE CONTRASEÑA (primer ingreso) ==========
// Solo con sesión iniciada: sin ella, cualquiera podría cambiar la contraseña
// de otra cuenta sabiendo su correo. Quien no tenga sesión usa la recuperación.
if ($action === 'change_password') {
    if (!isset($_SESSION['id_usuario'])) {
        error(401, 'Sesión no válida. Vuelve a iniciar sesión.');
    }

    $password = (string) ($data['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id_usuario, nombre, email, id_rol, debe_cambiar FROM Usuarios WHERE id_usuario = :id LIMIT 1');
    $stmt->execute([':id' => $_SESSION['id_usuario']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        error(404, 'Usuario no encontrado');
    }
    // Solo para el cambio obligatorio: no pide la contraseña actual
    if ((int) $usuario['debe_cambiar'] !== 1) {
        error(403, 'Tu contraseña ya está actualizada. Para cambiarla usa «Olvidé mi contraseña».');
    }

    $errorPass = passwordIsTooSimple($password, $usuario['email'], $usuario['nombre']);
    if ($errorPass !== null) {
        error(400, $errorPass);
    }

    guardarContrasena($pdo, (int) $usuario['id_usuario'], $password);

    // Hasta aquí la sesión no tenía rol (ver el login): ahora sí puede entrar a su panel
    session_regenerate_id(true);
    $_SESSION['id_rol'] = (int) $usuario['id_rol'];

    responder(200, [
        'success' => true,
        'message' => 'Contraseña actualizada correctamente',
        'destino' => RUTAS_POR_ROL[(int) $usuario['id_rol']] ?? 'index.php'
    ]);
}

// ========== LOGIN NORMAL ==========
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error(400, 'Ingresa un correo y contraseña válidos');
}

// Límite de intentos fallidos, por correo y por IP
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(email = :email), 0), COALESCE(SUM(ip = :ip), 0) FROM login_intentos
     WHERE creado_en > NOW() - INTERVAL ' . LOGIN_MINUTOS_BLOQUEO . ' MINUTE AND (email = :email2 OR ip = :ip2)'
);
$stmt->execute([':email' => $email, ':ip' => $ip, ':email2' => $email, ':ip2' => $ip]);
[$fallosCorreo, $fallosIp] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));

if ($fallosCorreo >= LOGIN_MAX_FALLOS_CORREO || $fallosIp >= LOGIN_MAX_FALLOS_IP) {
    error(429, 'Demasiados intentos fallidos. Espera ' . LOGIN_MINUTOS_BLOQUEO . ' minutos o restablece tu contraseña.');
}

$registrarFallo = function () use ($pdo, $email, $ip): never {
    $pdo->prepare('INSERT INTO login_intentos (email, ip) VALUES (:email, :ip)')
        ->execute([':email' => $email, ':ip' => $ip]);
    $pdo->exec('DELETE FROM login_intentos WHERE creado_en < NOW() - INTERVAL 1 DAY');
    error(401, 'Correo o contraseña incorrectos');
};

$stmt = $pdo->prepare(
    "SELECT u.id_usuario, u.nombre, u.email, u.contrasena_hash, u.id_rol,
            u.id_sucursal, u.estado, u.debe_cambiar, r.nombre_rol,
            s.nombre AS sucursal_nombre, s.estado AS sucursal_estado
     FROM Usuarios u
     INNER JOIN Roles r ON r.id_rol = u.id_rol
     LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
     WHERE LOWER(u.email) = :email
     LIMIT 1"
);
$stmt->execute([':email' => $email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    $registrarFallo();
}

$passwordIsValid = password_verify($password, $usuario['contrasena_hash']);

// Migra registros antiguos sin hash
if (!$passwordIsValid && !str_starts_with($usuario['contrasena_hash'], '$')) {
    $passwordIsValid = hash_equals($usuario['contrasena_hash'], $password);
    if ($passwordIsValid) {
        $pdo->prepare('UPDATE Usuarios SET contrasena_hash = :h WHERE id_usuario = :id')
            ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $usuario['id_usuario']]);
    }
}

if (!$passwordIsValid) {
    $registrarFallo();
}

// El estado se revisa después de la contraseña: así no se revela a quien no la sabe
if ($usuario['estado'] !== 'Activo') {
    error(403, "Tu usuario está en estado «{$usuario['estado']}». Consulta con tu gerente o el administrador.");
}

if ((int) $usuario['id_rol'] !== 1 && $usuario['id_sucursal'] === null) {
    error(403, 'La cuenta no tiene una sucursal asignada');
}

if ($usuario['id_sucursal'] !== null && $usuario['sucursal_estado'] !== 'Activa') {
    error(403, 'La sucursal no está disponible');
}

$destino = RUTAS_POR_ROL[(int) $usuario['id_rol']] ?? null;
if ($destino === null) {
    error(403, 'La cuenta no tiene un rol válido configurado');
}

$pdo->prepare('DELETE FROM login_intentos WHERE email = :email')->execute([':email' => $email]);

// Un cajero no entra hasta aceptar la versión vigente del aviso de privacidad.
// No se crea la sesión: se le manda a privacidad/ con un enlace nuevo y, al
// aceptarlo, vuelve a iniciar sesión.
if (in_array((int) $usuario['id_rol'], AVISO_ROLES_OBLIGADOS, true)) {
    try {
        $aceptado = avisoAceptado($pdo, (int) $usuario['id_usuario']);
    } catch (PDOException $e) {
        error_log('login.php: falta la tabla avisos_privacidad (sql/010_aviso_privacidad.sql)');
        error(500, 'El sistema no está listo para cajeros. Avisa al administrador.');
    }
    if (!$aceptado) {
        responder(403, [
            'success'        => false,
            'requiere_aviso' => true,
            'enlace'         => crearEnlaceAviso($pdo, (int) $usuario['id_usuario'], AVISO_ENLACE_LOGIN_HORAS),
            'message'        => 'Antes de entrar debes leer y aceptar el aviso de privacidad. Te llevamos a él…',
        ]);
    }
}

// Nuevo id de sesión al autenticar (evita la fijación de sesión)
session_regenerate_id(true);

unset($usuario['contrasena_hash']);
$usuario['debe_cambiar'] = (int) $usuario['debe_cambiar'];

$debeCambiar = $usuario['debe_cambiar'] === 1;

// Enteros: admin compara con === 1, y PDO devuelve las columnas como texto.
// Con el cambio de contraseña pendiente la sesión queda con rol 0: ningún panel
// (admin, gerente, cajero) la deja pasar aunque se escriba la dirección a mano.
// change_password le pone el rol al guardar la contraseña nueva.
$_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
$_SESSION['id_rol']     = $debeCambiar ? 0 : (int) $usuario['id_rol'];
$_SESSION['email']      = $usuario['email'];

responder(200, [
    'success'      => true,
    'message'      => $debeCambiar ? 'Debes cambiar tu contraseña' : 'Inicio de sesión exitoso',
    'debe_cambiar' => $debeCambiar,
    'destino'      => $destino,
    'user'         => $usuario
]);
