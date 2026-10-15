<?php
/**
 * Correos que recibe el empleado por los movimientos de su cuenta:
 *   - alta: bienvenida con el aviso de privacidad
 *   - cambio de estado: baja (con motivo: renuncia, jubilación...), vacaciones,
 *     incapacidad, licencia o regreso a activo
 *   - contraseña restablecida por el gerente
 *   - cambio del correo de acceso (se avisa a la dirección anterior)
 *
 * El aviso de privacidad sigue la estructura de la Ley Federal de Protección
 * de Datos Personales en Posesión de los Particulares. Los correos van siempre
 * en español: son documentos para el empleado, no textos de la interfaz.
 *
 * Salen con la cuenta de login/config_correo.php (ver login/correo.php). Si un
 * correo no sale, la acción ya guardada no se deshace: quien llama avisa al gerente.
 */
require_once __DIR__ . '/../../login/correo.php';

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

/** Dirección del inicio de sesión: la raíz del sitio, un nivel arriba de gerente/. */
function _urlSitio(): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/', 2)), '/') . '/';
}

function _firma(array $datos): string
{
    return "Atentamente,\n{$datos['gerente']['nombre']}\nGerente de {$datos['sucursal']['nombre']} · Koaly";
}

/**
 * Bienvenida con el aviso de privacidad (alta de un empleado).
 * @param array $empleado ['nombre', 'email']
 */
function enviarAvisoPrivacidad(array $empleado, array $datos): bool
{
    $sucursal  = $datos['sucursal'];
    $contacto  = _correoContacto($datos);
    $domicilio = trim((string) ($sucursal['direccion'] ?? ''));
    $responsable = 'Koaly, a través de ' . $sucursal['nombre'] . ($domicilio !== '' ? " ({$domicilio})" : '');
    $fecha    = date('d/m/Y');
    $urlSitio = _urlSitio();

    $texto = <<<TXT
    Hola {$empleado['nombre']}:

    Te damos la bienvenida al equipo de {$sucursal['nombre']}. Ya tienes una cuenta
    de cajero en el sistema Koaly:

        Dirección: {$urlSitio}
        Usuario:   {$empleado['email']}

    Tu gerente, {$datos['gerente']['nombre']}, te entregará la contraseña temporal. El
    sistema te pedirá cambiarla la primera vez que entres.

    Como parte de tu alta, te compartimos nuestro aviso de privacidad.

    ------------------------------------------------------------
    AVISO DE PRIVACIDAD PARA EMPLEADOS
    ------------------------------------------------------------

    1. Responsable
    {$responsable} es responsable del tratamiento de tus datos personales,
    conforme a la Ley Federal de Protección de Datos Personales en Posesión
    de los Particulares.

    2. Datos que tratamos
    Nombre completo, correo electrónico, fecha de contratación, sucursal y
    caja asignadas, situación laboral (activo, vacaciones, incapacidad,
    licencia o baja) y el registro de las ventas y movimientos de inventario
    que realices en el sistema. No tratamos datos personales sensibles.

    3. Para qué los usamos
    a) Darte acceso al sistema de punto de venta e inventario.
    b) Asignarte una caja y administrar tu situación laboral (altas, bajas,
       vacaciones, incapacidades y licencias).
    c) Registrar y supervisar las ventas, para la operación de la tienda y el
       control interno.
    d) Avisarte por correo de los cambios en tu cuenta.

    4. Con quién los compartimos
    No vendemos ni compartimos tus datos con terceros. Solo los conocen el
    personal autorizado de la empresa, los proveedores de correo y
    alojamiento que trabajan por nuestra cuenta y, cuando la ley lo exija,
    las autoridades competentes.

    5. Cuánto tiempo los conservamos
    Mientras dure la relación laboral y después, solo el tiempo que exijan
    las obligaciones legales y fiscales.

    6. Tus derechos (ARCO)
    Puedes acceder a tus datos, rectificarlos, cancelarlos u oponerte a su
    uso, así como revocar tu consentimiento. Escribe a {$contacto} con tu
    nombre, el derecho que quieres ejercer y una copia de tu identificación.
    Te responderemos en un máximo de 20 días hábiles.

    7. Cambios a este aviso
    Si este aviso cambia, te lo haremos saber por este mismo medio.

    Fecha del aviso: {$fecha}
    TXT;

    return enviarCorreo($empleado['email'], 'Bienvenida a Koaly y aviso de privacidad', $texto);
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
            . "    " . _urlSitio() . "\n\n"
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
