<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$host = 'localhost';
$dbname = 'u772860605_DATAK';
$user = 'u772860605_JACEUF';
$pass = 'j.2.4.6.4.R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
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

function passwordIsTooSimple(string $password, string $email): bool
{
    $common = [
        '12345678', 'password', '123456789', 'qwerty', 'abc123',
        'password123', 'admin', 'letmein', 'welcome', '12345',
        '1234567890', 'qwerty123', '1q2w3e4r', 'iloveyou', 'monkey'
    ];

    $parts = explode('@', $email);
    $employee = $parts[0] ?? '';
    $company = isset($parts[1]) ? (explode('.', $parts[1])[0] ?? '') : '';

    $variants = [
        $employee,
        strtolower($employee),
        strtoupper($employee),
        $company,
        strtolower($company),
        strtoupper($company),
        $employee . $company,
        $company . $employee,
        $employee . '123',
        $company . '123',
        $employee . '2024',
        $company . '2024'
    ];

    $blacklist = array_unique(array_merge($common, $variants));
    $passwordLower = strtolower($password);

    if (strlen($password) < 8) {
        return true;
    }

    foreach ($blacklist as $blocked) {
        if ($blocked !== '' && strtolower($blocked) === $passwordLower) {
            return true;
        }
    }

    return false;
}

if ($action === 'change_password') {
    if (!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
        exit;
    }

    $password = (string) ($data['password'] ?? '');

    $stmt = $pdo->prepare("SELECT email FROM Usuarios WHERE id_usuario = :id_usuario LIMIT 1");
    $stmt->execute([':id_usuario' => $_SESSION['id_usuario']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    if (passwordIsTooSimple($password, $row['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La contraseña es demasiado simple. Usa al menos 8 caracteres y evita palabras comunes.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $update = $pdo->prepare(
        "UPDATE Usuarios SET contrasena_hash = :contrasena_hash, debe_cambiar = 0 WHERE id_usuario = :id_usuario"
    );
    $update->execute([
        ':contrasena_hash' => $hash,
        ':id_usuario' => $_SESSION['id_usuario']
    ]);

    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada']);
    exit;
}

$email = strtolower(trim((string) ($data['email'] ?? '')));
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

if (!$passwordIsValid && substr($usuario['contrasena_hash'], 0, 1) !== '$') {
    $passwordIsValid = hash_equals($usuario['contrasena_hash'], $password);

    if ($passwordIsValid) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare(
            'UPDATE Usuarios SET contrasena_hash = :contrasena_hash WHERE id_usuario = :id_usuario'
        );
        $update->execute([
            ':contrasena_hash' => $newHash,
            ':id_usuario' => $usuario['id_usuario']
        ]);
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

$_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
$_SESSION['id_rol'] = (int) $usuario['id_rol'];
$_SESSION['email'] = $usuario['email'];

unset($usuario['contrasena_hash']);
$usuario['debe_cambiar'] = (int) $usuario['debe_cambiar'];

echo json_encode([
    'success' => true,
    'message' => 'Inicio de sesión exitoso',
    'user' => $usuario
]);