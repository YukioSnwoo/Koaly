<?php
/**
 * Correos que recibe el empleado por los movimientos de su cuenta:
 *   - alta: bienvenida con el enlace para leer y aceptar el aviso de privacidad
 *   - cambio de estado: baja (con motivo: renuncia, jubilación...), vacaciones,
 *     incapacidad, licencia o regreso a activo
 *   - contraseña restablecida por el gerente
 *   - cambio del correo de acceso (se avisa a la dirección anterior)
 *
 * El texto del aviso y su aceptación están en privacidad/ (raíz del sitio).
 * Los correos van siempre en español: son documentos para el empleado, no
 * textos de la interfaz.
 *
 * Salen con la cuenta de login/config_correo.php (ver login/correo.php). Si un
 * correo no sale, la acción ya guardada no se deshace: quien llama avisa al gerente.
 */
require_once __DIR__ . '/../../login/correo.php';
require_once __DIR__ . '/../../privacidad/aviso.php';

/** Motivos de baja: código => texto del correo. En pantalla se traducen con motivo_baja.<código>. */
const MOTIVOS_BAJA = [
    'renuncia'         => 'Renuncia voluntaria',
    'jubilacion'       => 'Jubilación',
    'termino_contrato' => 'Término de contrato',
    'despido'          => 'Rescisión de la relación laboral',
    'otro'             => 'Otro motivo',
];

/**
 * Datos de la sucursal y del gerente que firman los correos.
 * @return array{gerente: array, sucursal: array}
 */
function datosParaCorreos(PDO $pdo, int $idGerente, int $idSucursal): array
{
    $stmt = $pdo->prepare("
        SELECT s.nombre, s.direccion, s.email_contacto, g.nombre AS gerente_nombre, g.email AS gerente_email
        FROM Sucursales s INNER JOIN Usuarios g ON g.id_usuario = ?
        WHERE s.id_sucursal = ?
    ");
    $stmt->execute([$idGerente, $idSucursal]);
    $fila = $stmt->fetch();
    return [
        'gerente'  => ['nombre' => $fila['gerente_nombre'], 'email' => $fila['gerente_email']],
        'sucursal' => ['nombre' => $fila['nombre'], 'direccion' => $fila['direccion'], 'email_contacto' => $fila['email_contacto']],
    ];
}

/** Correo para dudas y derechos ARCO: el de la sucursal o, si no tiene, el del gerente. */
function _correoContacto(array $datos): string
{
    return trim((string) ($datos['sucursal']['email_contacto'] ?? '')) ?: $datos['gerente']['email'];
}

function _firma(array $datos): string
{
    return "Atentamente,\n{$datos['gerente']['nombre']}\nGerente de {$datos['sucursal']['nombre']} · Koaly\n\n"
        . 'Aviso de privacidad: ' . urlSitio() . 'privacidad/';
}

/**
 * Bienvenida con el enlace personal para leer y aceptar el aviso de privacidad.
 * Hasta que lo acepte, el cajero no puede iniciar sesión.
 * @param array $empleado ['nombre', 'email']
 */
function enviarAvisoPrivacidad(PDO $pdo, int $idUsuario, array $empleado, array $datos): bool
{
    try {
        $enlace = crearEnlaceAviso($pdo, $idUsuario, AVISO_ENLACE_DIAS * 24);
    } catch (PDOException $e) {
        // Sin la tabla (falta sql/010_aviso_privacidad.sql) no hay enlace que enviar
        error_log('correos_empleado.php: no se pudo crear el enlace del aviso: ' . $e->getMessage());
        return false;
    }
    $dias   = AVISO_ENLACE_DIAS;
    $sitio  = urlSitio();

    $texto = <<<TXT
    Hola {$empleado['nombre']}:

    Te damos la bienvenida al equipo de {$datos['sucursal']['nombre']}. Ya tienes una
    cuenta de cajero en el sistema Koaly.

    Antes de empezar, lee y acepta nuestro aviso de privacidad. Ahí también
    puedes revisar tus datos en el sistema:

        {$enlace}

    El enlace es personal y vale {$dias} días. Hasta que aceptes el aviso no
    podrás iniciar sesión en la caja. Si el enlace vence, al iniciar sesión el
    sistema te dará uno nuevo.

    Para entrar al sistema:

        Dirección: {$sitio}
        Usuario:   {$empleado['email']}

    Tu gerente te entregará la contraseña temporal; el sistema te pedirá
    cambiarla la primera vez que entres.

    TXT;

    return enviarCorreo($empleado['email'], 'Bienvenida a Koaly: acepta tu aviso de privacidad', $texto . _firma($datos));
}

/**
 * Aviso de cambio de estado: baja, vacaciones, incapacidad, licencia o regreso a activo.
 * @param array       $empleado   ['nombre', 'email']
 * @param string|null $motivoBaja código de MOTIVOS_BAJA (solo para la baja)
 */
function enviarAvisoCambioEstado(array $empleado, array $datos, string $estado, ?string $motivoBaja = null): bool
{
    $fecha    = date('d/m/Y');
    $sucursal = $datos['sucursal']['nombre'];
    $contacto = _correoContacto($datos);

    if ($estado === 'Baja') {
        $motivo = MOTIVOS_BAJA[$motivoBaja] ?? null;
        $asunto = 'Baja de tu cuenta en Koaly';
        $agradecimiento = $motivoBaja === 'jubilacion'
            ? "Te agradecemos sinceramente todos estos años de trabajo en {$sucursal}\ny te deseamos lo mejor en esta nueva etapa."
            : "Te agradecemos el tiempo que trabajaste con nosotros en {$sucursal}.";
        $cuerpo = "Te informamos que, a partir del {$fecha}, tu cuenta en el sistema Koaly\n"
            . "quedó dada de baja" . ($motivo ? " por el siguiente motivo: {$motivo}." : '.') . "\n\n"
            . "Desde hoy ya no podrás iniciar sesión.\n\n"
            . "{$agradecimiento}\n\n"
            . "Conforme al aviso de privacidad que recibiste al ingresar, tus datos\n"
            . "personales se conservarán solo el tiempo que exijan las obligaciones\n"
            . "legales y fiscales. Para ejercer tus derechos de acceso, rectificación,\n"
            . "cancelación u oposición (ARCO), escribe a {$contacto}.";
    } elseif ($estado === 'Activo') {
        $asunto = 'Tu cuenta en Koaly está activa de nuevo';
        $cuerpo = "Te informamos que, a partir del {$fecha}, tu cuenta en el sistema Koaly\n"
            . "está activa de nuevo. Ya puedes iniciar sesión en:\n\n"
            . "    " . urlSitio() . "\n\n"
            . "Te damos la bienvenida de regreso.";
    } else {
        // Vacaciones, Incapacidad o Licencia
        $situacion = ['Vacaciones' => 'de vacaciones', 'Incapacidad' => 'con incapacidad', 'Licencia' => 'con licencia'][$estado] ?? mb_strtolower($estado);
        $asunto = "Tu cuenta en Koaly: {$estado}";
        $cuerpo = "Te informamos que, a partir del {$fecha}, quedaste registrado {$situacion}\n"
            . "en el sistema Koaly.\n\n"
            . "Mientras tanto no podrás iniciar sesión. Tu caja queda reservada y\n"
            . "tu gerente reactivará tu cuenta cuando regreses.";
    }

    $texto = "Hola {$empleado['nombre']}:\n\n{$cuerpo}\n\n"
        . "Si tienes dudas, comunícate con tu gerente o escribe a {$contacto}.\n\n"
        . _firma($datos);

    return enviarCorreo($empleado['email'], $asunto, $texto);
}

/** Aviso de que el gerente restableció la contraseña. Nunca incluye la contraseña. */
function enviarAvisoPasswordRestablecida(array $empleado, array $datos): bool
{
    $fecha = date('d/m/Y H:i');
    $texto = "Hola {$empleado['nombre']}:\n\n"
        . "El {$fecha}, tu gerente restableció la contraseña de tu cuenta en el sistema\n"
        . "Koaly. Te entregará la contraseña temporal en persona; por seguridad no la\n"
        . "enviamos por correo. Deberás cambiarla la próxima vez que entres.\n\n"
        . "Si no pediste este cambio, avísale a tu gerente cuanto antes.\n\n"
        . _firma($datos);

    return enviarCorreo($empleado['email'], 'Se restableció tu contraseña de Koaly', $texto);
}

/** Aviso a la dirección anterior cuando cambia el correo con el que se inicia sesión. */
function enviarAvisoCambioCorreo(string $correoAnterior, array $empleado, array $datos): bool
{
    $texto = "Hola {$empleado['nombre']}:\n\n"
        . "Tu correo para iniciar sesión en el sistema Koaly cambió a:\n\n"
        . "    {$empleado['email']}\n\n"
        . "Desde ahora entra con esa dirección; esta ya no funcionará.\n\n"
        . "Si no esperabas este cambio, avísale a tu gerente o escribe a " . _correoContacto($datos) . ".\n\n"
        . _firma($datos);

    return enviarCorreo($correoAnterior, 'Cambió tu correo de acceso a Koaly', $texto);
}
