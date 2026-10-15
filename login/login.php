<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Misma conexión que usan admin y gerente (admin/database.php no se sube a git)
try {
    require_once __DIR__ . '/../admin/database.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
    exit;
}

$action = (string) ($data['action'] ?? 'login');

// Recuperación de contraseña
const CODIGO_MINUTOS_VALIDEZ = 15;
const CODIGO_MAX_INTENTOS    = 5;   // intentos fallidos antes de invalidar el código
const CODIGO_SEGUNDOS_ESPERA = 60;  // entre un envío y otro al mismo correo
const CORREO_REMITENTE       = 'noreply@koalicius.com';

// ---------- helpers ----------
function passwordIsTooSimple(string $password, string $email, ?string $nombre = null): ?string
{
    if (strlen($password) < 8) {
        return 'La contraseña debe tener mínimo 8 caracteres';
    }
    if (preg_match('/^[a-zA-Z]+$/', $password)) {
        return 'La contraseña no puede ser solo letras';
    }
    if (preg_match('/^[0-9]+$/', $password)) {
        return 'La contraseña no puede ser solo números';
    }
    if (preg_match('/^[^a-zA-Z0-9]+$/', $password)) {
        return 'La contraseña no puede ser solo símbolos';
    }

    $common = [
        '12345678', 'password', '123456789', 'qwerty', 'abc123',
        'password123', 'admin', 'letmein', 'welcome', '12345',
        '1234567890', 'qwerty123', '1q2w3e4r', 'iloveyou', 'monkey'
    ];
    $passwordLower = mb_strtolower($password);
    foreach ($common as $c) {
        if ($passwordLower === $c) {
            return 'La contraseña es demasiado común';
        }
    }

    // Palabras del nombre de 3 letras o más (un nombre vacío o "de" no debe bloquear todo)
    if ($nombre) {
        foreach (preg_split('/\s+/', mb_strtolower(trim($nombre))) as $parte) {
            if (mb_strlen($parte) >= 3 && str_contains($passwordLower, $parte)) {
                return 'La contraseña no puede incluir tu nombre';
            }
        }
    }

    $local = mb_strtolower(explode('@', $email)[0] ?? '');
    if (mb_strlen($local) >= 3 && str_contains($passwordLower, $local)) {
        return 'La contraseña no puede incluir tu correo';
    }

    return null;
}

// ========== ENVIAR CÓDIGO DE RECUPERACIÓN ==========
if ($action === 'send_recover_code') {
    $email = strtolower(trim((string) ($data['email'] ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ingresa un correo válido']);
        exit;
    }

    // La misma respuesta exista o no el correo, para no revelar qué cuentas hay
    $respuestaGenerica = [
        'success' => true,
        'message' => 'Si el correo está registrado, recibirás un código en unos minutos.'
    ];

    $stmt = $pdo->prepare('SELECT id_usuario, nombre, email FROM Usuarios WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode($respuestaGenerica);
        exit;
    }

    // Evita que se usen los envíos para llenar el buzón de alguien
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM password_resets
         WHERE email = :email AND creado_en > NOW() - INTERVAL ' . CODIGO_SEGUNDOS_ESPERA . ' SECOND'
    );
    $stmt->execute([':email' => $email]);
    if ((int) $stmt->fetchColumn() > 0) {
        echo json_encode($respuestaGenerica);
        exit;
    }

    // Invalidar códigos anteriores
    $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE email = :email AND usado = 0')
        ->execute([':email' => $email]);

    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Se guarda solo el hash: quien lea la tabla no puede usar los códigos.
    // La expiración la calcula MySQL para no depender de la zona horaria de PHP.
    $ins = $pdo->prepare(
        'INSERT INTO password_resets (email, codigo_hash, expira_en, usado, intentos)
         VALUES (:email, :codigo_hash, NOW() + INTERVAL ' . CODIGO_MINUTOS_VALIDEZ . ' MINUTE, 0, 0)'
    );
    $ins->execute([
        ':email'       => $email,
        ':codigo_hash' => password_hash($codigo, PASSWORD_DEFAULT)
    ]);

    $asunto = mb_encode_mimeheader('Código de recuperación - Koaly', 'UTF-8');
    $cuerpo = "Hola {$usuario['nombre']},\n\n"
            . "Tu código de recuperación es: {$codigo}\n\n"
            . 'Válido por ' . CODIGO_MINUTOS_VALIDEZ . " minutos.\n"
            . "Si no pediste esto, ignora el mensaje.\n\n"
            . "— Equipo Koaly";
    $headers = 'From: Koaly <' . CORREO_REMITENTE . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    // El código nunca se devuelve en la respuesta: si el correo no sale, no hay recuperación
    if (!@mail($usuario['email'], $asunto, $cuerpo, $headers)) {
        $pdo->prepare('DELETE FROM password_resets WHERE id = :id')->execute([':id' => $pdo->lastInsertId()]);
        error_log('login.php: mail() falló al enviar código de recuperación');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo. Intenta más tarde o contacta al administrador.']);
        exit;
    }

    echo json_encode($respuestaGenerica);
    exit;
}

// ========== RESTABLECER CONTRASEÑA CON CÓDIGO ==========
if ($action === 'reset_password') {
    $email    = strtolower(trim((string) ($data['email'] ?? '')));
    $codigo   = trim((string) ($data['code'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $codigo === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Completa todos los campos']);
        exit;
    }

    $invalido = function (): void {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Código inválido o expirado']);
        exit;
    };

    // Código vigente más reciente de ese correo
    $stmt = $pdo->prepare(
        'SELECT id, codigo_hash, intentos FROM password_resets
         WHERE email = :email AND usado = 0 AND expira_en > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        $invalido();
    }

    if (!password_verify($codigo, $reset['codigo_hash'])) {
        // Al llegar al límite el código deja de servir: 6 dígitos no aguantan fuerza bruta.
        // `usado` va primero porque MySQL aplica el SET de izquierda a derecha.
        $pdo->prepare(
            'UPDATE password_resets
             SET usado = IF(intentos + 1 >= ' . CODIGO_MAX_INTENTOS . ', 1, usado), intentos = intentos + 1
             WHERE id = :id'
        )->execute([':id' => $reset['id']]);
        $invalido();
    }

    $stmt = $pdo->prepare('SELECT id_usuario, nombre, email FROM Usuarios WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $invalido();
    }

    // Después de validar el código: una contraseña débil no consume el código
    $errorPass = passwordIsTooSimple($password, $email, $usuario['nombre']);
    if ($errorPass !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $errorPass]);
        exit;
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare(
            'UPDATE Usuarios SET contrasena_hash = :hash, debe_cambiar = 0 WHERE id_usuario = :id'
        );
        $upd->execute([':hash' => $newHash, ':id' => $usuario['id_usuario']]);

        $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE email = :email AND usado = 0')
            ->execute([':email' => $email]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la contraseña']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.'
    ]);
    exit;
}

// ========== CAMBIO DE CONTRASEÑA (primer login) ==========
// Solo con sesión iniciada: sin ella, cualquiera podría cambiar la contraseña
// de otra cuenta sabiendo su correo. Quien no tenga sesión usa la recuperación.
if ($action === 'change_password') {
    if (!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesión no válida. Vuelve a iniciar sesión.']);
        exit;
    }

    $password = (string) ($data['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id_usuario, nombre, email FROM Usuarios WHERE id_usuario = :id LIMIT 1');
    $stmt->execute([':id' => $_SESSION['id_usuario']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    $errorPass = passwordIsTooSimple($password, $usuario['email'], $usuario['nombre']);
    if ($errorPass !== null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $errorPass]);
        exit;
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $upd = $pdo->prepare(
        'UPDATE Usuarios SET contrasena_hash = :hash, debe_cambiar = 0 WHERE id_usuario = :id'
    );
    $upd->execute([':hash' => $newHash, ':id' => $usuario['id_usuario']]);

    echo json_encode([
        'success' => true,
        'message' => 'Contraseña actualizada correctamente'
    ]);
    exit;
}

// ========== LOGIN NORMAL ==========
$email    = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ingresa un correo y contraseña válidos']);
    exit;
}

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
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
    exit;
}

if ($usuario['estado'] !== 'Activo') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "Usuario en estado: {$usuario['estado']}. No puede iniciar sesión."]);
    exit;
}

$passwordIsValid = password_verify($password, $usuario['contrasena_hash']);

// Migra registros antiguos sin hash
if (!$passwordIsValid && substr($usuario['contrasena_hash'], 0, 1) !== '$') {
    $passwordIsValid = hash_equals($usuario['contrasena_hash'], $password);
    if ($passwordIsValid) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE Usuarios SET contrasena_hash = :h WHERE id_usuario = :id')
            ->execute([':h' => $newHash, ':id' => $usuario['id_usuario']]);
    }
}

if (!$passwordIsValid) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
    exit;
}

if ((int) $usuario['id_rol'] !== 1 && $usuario['id_sucursal'] === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'La cuenta no tiene una sucursal asignada']);
    exit;
}

if ($usuario['id_sucursal'] !== null && $usuario['sucursal_estado'] !== 'Activa') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'La sucursal no está disponible']);
    exit;
}

// Nuevo id de sesión al autenticar (evita la fijación de sesión)
session_regenerate_id(true);

// Enteros: admin compara con === 1, y PDO devuelve las columnas como texto
$_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
$_SESSION['id_rol']     = (int) $usuario['id_rol'];
$_SESSION['email']      = $usuario['email'];

unset($usuario['contrasena_hash']);
$usuario['debe_cambiar'] = (int) $usuario['debe_cambiar'];

$debeCambiar = (bool) $usuario['debe_cambiar'];

echo json_encode([
    'success'      => true,
    'message'      => $debeCambiar ? 'Debes cambiar tu contraseña' : 'Inicio de sesión exitoso',
    'debe_cambiar' => $debeCambiar,
    'user'         => $usuario
]);
