<?php
/**
 * Guarda "Mi perfil" (POST con csrf_token): nombre(s) y apellidos, correo y
 * contraseña del propio usuario.
 *
 * La contraseña se cambia directo. El nombre y el correo:
 *   admin            se cambian directo;
 *   gerente, cajero  se manda una solicitud que aprueba la administración o su
 *                    gerente (admin/solicitudes_datos.php, sql/020). Mientras
 *                    haya una pendiente, esos campos no se tocan.
 * Cambiar el correo o la contraseña pide la contraseña actual.
 *
 * Responde {ok, mensaje, aviso, usuario} y, para gerente y cajero, además
 * {solicitud_html, pendiente, cerrar}; o {ok: false, error, mensaje}.
 */
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/../admin/profanity_filter.php';
require_once __DIR__ . '/../admin/reglas_nombre.php';
require_once __DIR__ . '/../admin/solicitudes_datos.php';
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

// ¿Cambia sus datos directo (admin) o los pide? Con una solicitud pendiente no se tocan.
$directo   = (int) $usuario['id_rol'] === 1;
$revisa    = $directo ? null : revisorSolicitudDatos($pdo, $usuario);
$ultima    = $directo ? null : ultimaSolicitudDatos($pdo, (int) $usuario['id_usuario']);
$bloqueado = $ultima !== null && $ultima['estado'] === 'Pendiente';

/* ---------- Nombre(s) y apellidos (admin/reglas_nombre.php) ---------- */
$partesNombre     = null;   // lo que se guarda directo en Usuarios (null: no se toca)
$nombreSolicitado = null;   // el nombre nuevo que se pide (gerente y cajero)
if (!$bloqueado) {
    $partesPost = partesNombrePost($_POST);
    if (faltaNombreOApellido($partesPost)) {
        perfilError(422, 'nombre_falta');
    }
    if (nombreCompleto($partesPost) !== $usuario['nombre']) {
        try {
            $partes = nombreDesdeFormulario($_POST);
        } catch (NombreInvalido $e) {
            perfilError(422, 'nombre_' . $e->regla);
        }
        try {
            rechazarSiProfano($partes['nombre']);
        } catch (RuntimeException $e) {
            perfilError(422, 'nombre_profano');
        }
    } else {
        // El nombre completo no cambió: no se vuelve a revisar (un nombre de antes de
        // las reglas no impide guardar lo demás), solo cómo quedó separado
        if (mb_strlen($partesPost['nombres']) > NOMBRE_MAX_NOMBRES
            || mb_strlen($partesPost['apellido_paterno']) > NOMBRE_MAX_APELLIDO
            || mb_strlen($partesPost['apellido_materno']) > NOMBRE_MAX_APELLIDO) {
            perfilError(422, 'nombre_largo');
        }
        $partes = apellidosVaciosANull($partesPost) + ['nombre' => $usuario['nombre']];
    }
    // Un nombre distinto se pide; separar distinto las mismas palabras se guarda directo
    if (!$directo && $partes['nombre'] !== $usuario['nombre']) {
        $nombreSolicitado = $partes;
    } else {
        $partesNombre = $partes;
    }
}

/* ---------- Correo ---------- */
$cambiaEmail = false;
if (!$bloqueado) {
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
}
$emailSolicitado = !$directo && $cambiaEmail ? $email : null;
$pideCambio = $nombreSolicitado !== null || $emailSolicitado !== null;

/* ---------- Contraseña actual y nueva ---------- */
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
    // Se compara con el nombre y el correo que tendrá la cuenta, aunque estén por aprobarse
    $nombreReglas = $nombreSolicitado['nombre'] ?? $partesNombre['nombre'] ?? $usuario['nombre'];
    $motivo = passwordIsTooSimple($nueva, $cambiaEmail ? $email : strtolower($usuario['email']), $nombreReglas);
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

/* ---------- Guardar: lo directo y, si hay, la solicitud, juntos ---------- */
$sets = [];
$params = [];
if ($partesNombre !== null) {
    $sets[] = 'nombre = ?';
    $params[] = $partesNombre['nombre'];
    if (hayColumnasApellidos($pdo)) {   // sql/017
        array_push($sets, 'nombres = ?', 'apellido_paterno = ?', 'apellido_materno = ?');
        array_push($params, $partesNombre['nombres'], $partesNombre['apellido_paterno'], $partesNombre['apellido_materno']);
    }
}
if ($directo && $cambiaEmail) {
    $sets[] = 'email = ?';
    $params[] = $email;
}
if ($nueva !== '') {
    $sets[] = 'contrasena_hash = ?';
    $params[] = password_hash($nueva, PASSWORD_DEFAULT);
}

try {
    $pdo->beginTransaction();
    if ($sets !== []) {
        $pdo->prepare("UPDATE Usuarios SET " . implode(', ', $sets) . " WHERE id_usuario = ?")
            ->execute(array_merge($params, [$usuario['id_usuario']]));
    }
    if ($pideCambio) {
        crearSolicitudDatos($pdo, $usuario, $revisa, $nombreSolicitado, $emailSolicitado);
    }
    $pdo->commit();
} catch (SolicitudDatosError $e) {
    $pdo->rollBack();
    perfilError(422, 'solicitud_pendiente');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // 1062: otra cuenta tomó el correo entre la revisión y el guardado
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        perfilError(422, 'email_usado');
    }
    // 42S02: aún no se corre sql/020
    if ($pideCambio && $e->getCode() === '42S02') {
        perfilError(500, 'sin_tabla_solicitudes');
    }
    error_log('perfil/guardar.php: ' . $e->getMessage());
    perfilError(500, 'base_datos');
}

/* ---------- Respuesta ---------- */
if ($partesNombre !== null) {
    $usuario = [
        'nombre'           => $partesNombre['nombre'],
        'nombres'          => $partesNombre['nombres'],
        'apellido_paterno' => $partesNombre['apellido_paterno'],
        'apellido_materno' => $partesNombre['apellido_materno'],
    ] + $usuario;
}
$correoAnterior = $usuario['email'];
if ($directo && $cambiaEmail) {
    $usuario['email'] = $email;
}

// Ya guardado: si el correo no sale, la contraseña nueva igual queda
$avisoEnviado = $nueva !== '' && perfilAvisarCambioContrasena($correoAnterior, $usuario['nombre'], $usuario['email']);

$mensaje = perfilT($avisoEnviado ? 'form.guardado_aviso' : 'form.guardado');
if ($pideCambio) {
    $mensaje = ($nueva !== '' ? perfilT('form.contrasena_tambien') . ' ' : '')
        . perfilT('form.solicitud_enviada', ['quien' => perfilQuienRevisa($revisa)]);
}

$respuesta = [
    'ok'      => true,
    'mensaje' => $mensaje,
    'aviso'   => $avisoEnviado,   // perfil.js deja el panel abierto más tiempo para leerlo
    'usuario' => perfilParaJs($usuario),
];
if (!$directo) {
    $ultima = ultimaSolicitudDatos($pdo, (int) $usuario['id_usuario']);
    $respuesta['solicitud_html'] = perfilHtmlSolicitud($ultima);
    $respuesta['pendiente']      = $ultima !== null && $ultima['estado'] === 'Pendiente';
    $respuesta['cerrar']         = !$pideCambio;   // con una solicitud nueva, el panel se queda para verla
}
perfilResponder(200, $respuesta);
