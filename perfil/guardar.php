<?php
/**
 * Guarda "Mi perfil" (POST multipart con csrf_token): foto, nombre, correo y
 * contraseña del propio usuario. Cambiar el correo o la contraseña pide la
 * contraseña actual. Responde {ok, mensaje, usuario} o {ok: false, error, mensaje}.
 */
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/../admin/solicitudes_comun.php';   // guardarImagenProducto()
require_once __DIR__ . '/../admin/profanity_filter.php';
require_once __DIR__ . '/../login/reglas_contrasena.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    perfilError(405, 'base_datos');
}
if (!csrfValido()) {
    perfilError(403, 'csrf');
}

$nombre    = trim((string) ($_POST['nombre'] ?? ''));
$email     = strtolower(trim((string) ($_POST['email'] ?? '')));
$actual    = (string) ($_POST['actual'] ?? '');
$nueva     = (string) ($_POST['nueva'] ?? '');
$confirmar = (string) ($_POST['confirmar'] ?? '');
$quitarFoto = ($_POST['quitar_foto'] ?? '0') === '1';

if ($nombre === '' || mb_strlen($nombre) > 100) {
    perfilError(422, 'nombre');
}
if ($nombre !== $usuario['nombre']) {
    try {
        rechazarSiProfano($nombre);
    } catch (RuntimeException $e) {
        perfilError(422, 'nombre_profano');
    }
}
if (mb_strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    perfilError(422, 'email');
}

$cambiaEmail = $email !== strtolower($usuario['email']);
if ($cambiaEmail || $nueva !== '') {
    if ($actual === '') {
        perfilError(422, 'actual_falta');
    }
    $stmt = $pdo->prepare("SELECT contrasena_hash FROM Usuarios WHERE id_usuario = ?");
    $stmt->execute([$usuario['id_usuario']]);
    if (!password_verify($actual, (string) $stmt->fetchColumn())) {
        perfilError(422, 'actual');
    }
}
if ($nueva !== '') {
    if ($nueva !== $confirmar) {
        perfilError(422, 'confirmar');
    }
    $motivo = passwordIsTooSimple($nueva, $email, $nombre);
    if ($motivo !== null) {
        // Los motivos vienen en español; en otro idioma se explica la regla completa
        perfilError(422, 'contrasena_debil', perfilIdioma() === 'es' ? $motivo : null);
    }
}
if ($cambiaEmail) {
    $stmt = $pdo->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ?");
    $stmt->execute([$email, $usuario['id_usuario']]);
    if ($stmt->fetch() !== false) {
        perfilError(422, 'email_usado');
    }
}

$fotoNueva = null;
try {
    $fotoNueva = guardarImagenProducto(
        $_FILES['foto'] ?? [],
        __DIR__ . '/../' . PERFIL_CARPETA_FOTOS,
        PERFIL_CARPETA_FOTOS,
        'u' . $usuario['id_usuario'] . '_'
    );
} catch (RuntimeException $e) {
    perfilError(422, $e->getMessage());
}

$foto = $fotoNueva ?? ($quitarFoto ? null : $usuario['foto']);
$sets = ['nombre = ?', 'email = ?'];
$params = [$nombre, $email];
if ($foto !== $usuario['foto']) {
    $sets[] = 'foto = ?';
    $params[] = $foto;
}
if ($nueva !== '') {
    $sets[] = 'contrasena_hash = ?';
    $params[] = password_hash($nueva, PASSWORD_DEFAULT);
}
$params[] = $usuario['id_usuario'];

try {
    $pdo->prepare("UPDATE Usuarios SET " . implode(', ', $sets) . " WHERE id_usuario = ?")->execute($params);
} catch (PDOException $e) {
    if ($fotoNueva !== null) {
        @unlink(__DIR__ . '/../' . $fotoNueva);
    }
    // 1062: otra cuenta tomó el correo entre la revisión y el guardado
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        perfilError(422, 'email_usado');
    }
    // Sin sql/010 no existe la columna foto
    perfilError(500, $foto !== $usuario['foto'] ? 'sin_migracion' : 'base_datos');
}

// La foto anterior ya no se usa (solo se borran las de Imagenes/perfiles)
if ($foto !== $usuario['foto'] && $usuario['foto'] && str_starts_with($usuario['foto'], PERFIL_CARPETA_FOTOS . '/')) {
    @unlink(__DIR__ . '/../' . $usuario['foto']);
}

$usuario = ['nombre' => $nombre, 'email' => $email, 'foto' => $foto] + $usuario;
perfilResponder(200, ['ok' => true, 'mensaje' => perfilT('form.guardado'), 'usuario' => perfilParaJs($usuario)]);
