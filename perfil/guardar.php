<?php
/**
 * Guarda "Mi perfil" (POST multipart con csrf_token): foto, nombre(s) y apellidos, correo y
 * contraseña del propio usuario. Cambiar el correo o la contraseña pide la
 * contraseña actual. Responde {ok, mensaje, usuario} o {ok: false, error, mensaje}.
 */
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/../admin/solicitudes_comun.php';   // guardarImagenProducto()
require_once __DIR__ . '/../admin/profanity_filter.php';
require_once __DIR__ . '/../admin/reglas_nombre.php';
require_once __DIR__ . '/../login/reglas_contrasena.php';
require_once __DIR__ . '/../login/correo.php';

/**
 * Avisa por correo que la contraseña cambió (diseño y "Si no fuiste tú" en
 * login/correo.php). Va al correo que tenía la cuenta antes de guardar: el del
 * dueño, aunque en el mismo guardado se cambie.
 */
function perfilAvisarCambioContrasena(string $para, string $nombre, string $correoCuenta): bool
{
    $ahora = new DateTimeImmutable();
    $vars  = ['fecha' => $ahora->format(perfilIdioma() === 'en' ? 'm/d/Y' : 'd/m/Y'), 'hora' => $ahora->format('H:i')];

    return enviarAvisoContrasenaCambiada($para, $correoCuenta, [
        'asunto'          => perfilT('correo.asunto'),
        'saludo'          => perfilT('correo.saludo', ['nombre' => $nombre]),
        'cuerpo'          => perfilT('correo.cuerpo', $vars),
        'si_fuiste'       => perfilT('correo.si_fuiste'),
        'no_fuiste'       => perfilT('correo.no_fuiste'),
        'aqui'            => perfilT('correo.aqui'),
        'no_fuiste_texto' => perfilT('correo.no_fuiste_texto'),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    perfilError(405, 'base_datos');
}
if (!csrfValido()) {
    perfilError(403, 'csrf');
}

$email     = strtolower(trim((string) ($_POST['email'] ?? '')));
$actual    = (string) ($_POST['actual'] ?? '');
$nueva     = (string) ($_POST['nueva'] ?? '');
$confirmar = (string) ($_POST['confirmar'] ?? '');
$quitarFoto = ($_POST['quitar_foto'] ?? '0') === '1';

// Nombre(s) y apellidos por separado (admin/reglas_nombre.php). Si el nombre
// completo no cambió no se vuelve a revisar: un nombre de antes de las reglas
// no impide guardar lo demás (aunque sí se guarda cómo quedó separado).
$partesNombre = partesNombrePost($_POST);
if (faltaNombreOApellido($partesNombre)) {
    perfilError(422, 'nombre_falta');
}
if (nombreCompleto($partesNombre) !== $usuario['nombre']) {
    try {
        $partesNombre = nombreDesdeFormulario($_POST);
    } catch (NombreInvalido $e) {
        perfilError(422, 'nombre_' . $e->regla);
    }
    try {
        rechazarSiProfano($partesNombre['nombre']);
    } catch (RuntimeException $e) {
        perfilError(422, 'nombre_profano');
    }
} else {
    if (mb_strlen($partesNombre['nombres']) > NOMBRE_MAX_NOMBRES
        || mb_strlen($partesNombre['apellido_paterno']) > NOMBRE_MAX_APELLIDO
        || mb_strlen($partesNombre['apellido_materno']) > NOMBRE_MAX_APELLIDO) {
        perfilError(422, 'nombre_largo');
    }
    $partesNombre = apellidosVaciosANull($partesNombre);
    $partesNombre['nombre'] = $usuario['nombre'];
}
$nombre = $partesNombre['nombre'];
if (mb_strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    perfilError(422, 'email');
}

$cambiaEmail = $email !== strtolower($usuario['email']);
if ($cambiaEmail) {
    try {
        rechazarCorreoSiProfano($email);
    } catch (RuntimeException $e) {
        perfilError(422, 'email_profano');
    }
}
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
if (hayColumnasApellidos($pdo)) {   // sql/017
    array_push($sets, 'nombres = ?', 'apellido_paterno = ?', 'apellido_materno = ?');
    array_push($params, $partesNombre['nombres'], $partesNombre['apellido_paterno'], $partesNombre['apellido_materno']);
}
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

// Ya guardado: si el correo no sale, la contraseña nueva igual queda
$avisoEnviado = $nueva !== '' && perfilAvisarCambioContrasena($usuario['email'], $nombre, $email);

$usuario = [
    'nombre'           => $nombre,
    'nombres'          => $partesNombre['nombres'],
    'apellido_paterno' => $partesNombre['apellido_paterno'],
    'apellido_materno' => $partesNombre['apellido_materno'],
    'email'            => $email,
    'foto'             => $foto,
] + $usuario;
perfilResponder(200, [
    'ok'      => true,
    'mensaje' => perfilT($avisoEnviado ? 'form.guardado_aviso' : 'form.guardado'),
    'aviso'   => $avisoEnviado,   // perfil.js deja el panel abierto más tiempo para leerlo
    'usuario' => perfilParaJs($usuario),
]);
