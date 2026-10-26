<?php
/**
 * Correos que recibe el empleado por los movimientos de su cuenta:
 *   - alta y "reenviar acceso": la cuenta, la contraseña temporal (la genera el
 *     sistema; el gerente nunca la ve) y el enlace para aceptar el aviso de privacidad
 *   - cambio de estado: baja (con motivo: renuncia, jubilación...), vacaciones,
 *     incapacidad, licencia o regreso a activo
 *   - contraseña restablecida por el gerente (con la contraseña temporal)
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
 * Datos de acceso con la contraseña temporal, para los correos.
 * @param array $acceso ['password', 'vence' ('Y-m-d H:i:s' o null)] de ponerPasswordTemporal()
 */
function _bloqueAcceso(array $empleado, array $acceso, bool $reenvio): string
{
    $vence = $acceso['vence'] ? date('d/m/Y', strtotime($acceso['vence'])) : null;
    return "    Dirección:            " . urlSitio() . "\n"
        . "    Correo (usuario):     {$empleado['email']}\n"
        . "    Contraseña temporal:  {$acceso['password']}\n\n"
        . "La contraseña temporal solo sirve para tu primer ingreso"
        . ($vence ? " y vence el {$vence}" : '') . ".\n"
        . ($reenvio ? "Reemplaza a la que te enviamos antes: esa ya no funciona.\n" : '')
        . "Al entrar, el sistema te pedirá elegir tu propia contraseña.\n"
        . "No la compartas con nadie; nadie de Koaly te la pedirá.";
}

/**
 * Bienvenida (o reenvío del acceso) con el enlace personal para leer y aceptar
 * el aviso de privacidad y, si se da, la contraseña temporal. Hasta que acepte
 * el aviso, el cajero no puede iniciar sesión.
 *
 * La contraseña temporal la genera el sistema y solo llega aquí: el gerente
 * nunca la ve. Sirve para el primer ingreso, vence y el sistema obliga a
 * cambiarla antes de dejarlo entrar a la caja.
 * @param array      $empleado ['nombre', 'email']
 * @param array|null $acceso   de ponerPasswordTemporal(); null: solo se le recuerda cómo entrar
 * @param bool       $reenvio  true: no es la bienvenida, el gerente le reenvió el acceso
 */
function enviarAvisoPrivacidad(PDO $pdo, int $idUsuario, array $empleado, array $datos, ?array $acceso = null, bool $reenvio = false): bool
{
    try {
        $enlace = crearEnlaceAviso($pdo, $idUsuario, AVISO_ENLACE_DIAS * 24);
    } catch (PDOException $e) {
        // Sin la tabla (falta sql/010_aviso_privacidad.sql) no hay enlace que enviar
        error_log('correos_empleado.php: no se pudo crear el enlace del aviso: ' . $e->getMessage());
        return false;
    }
    $dias = AVISO_ENLACE_DIAS;
    $paso2 = $acceso
        ? "2. Entra al sistema con estos datos:\n\n" . _bloqueAcceso($empleado, $acceso, $reenvio)
        : "2. Entra al sistema en " . urlSitio() . " con tu correo ({$empleado['email']})\n"
          . "   y tu contraseña. Si no la recuerdas, usa «Olvidé mi contraseña».";
    $saludo = $reenvio
        ? "Te reenviamos tu acceso al sistema Koaly de {$datos['sucursal']['nombre']}."
        : "Te damos la bienvenida al equipo de {$datos['sucursal']['nombre']}. Ya tienes una\ncuenta de cajero en el sistema Koaly.";

    $texto = <<<TXT
    Hola {$empleado['nombre']}:

    {$saludo}

    1. Lee y acepta nuestro aviso de privacidad. Ahí también puedes revisar
       tus datos en el sistema:

        {$enlace}

       El enlace es personal y vale {$dias} días. Hasta que aceptes el aviso no
       podrás iniciar sesión en la caja.

    {$paso2}


    TXT;

    $asunto = $reenvio ? 'Tu acceso a Koaly: acepta tu aviso de privacidad' : 'Bienvenida a Koaly: acepta tu aviso de privacidad';
    return enviarCorreo($empleado['email'], $asunto, $texto . "\n" . _firma($datos));
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

/**
 * Contraseña temporal nueva cuando el aviso ya está aceptado: el gerente la
 * restableció o le reenvió el acceso. Como en el alta, solo llega por correo y
 * el sistema obliga a cambiarla al entrar.
 * @param array $acceso de ponerPasswordTemporal()
 */
function enviarAvisoPasswordRestablecida(array $empleado, array $datos, array $acceso, bool $reenvio = false): bool
{
    $fecha = date('d/m/Y H:i');
    $texto = "Hola {$empleado['nombre']}:\n\n"
        . ($reenvio
            ? "Te reenviamos tu acceso al sistema Koaly. Entra con estos datos:\n\n"
            : "El {$fecha}, tu gerente restableció la contraseña de tu cuenta en el sistema\nKoaly. Entra con estos datos:\n\n")
        . _bloqueAcceso($empleado, $acceso, $reenvio) . "\n\n"
        . "Si no pediste este cambio, avísale a tu gerente cuanto antes.\n\n"
        . _firma($datos);

    return enviarCorreo($empleado['email'], $reenvio ? 'Tu acceso a Koaly' : 'Se restableció tu contraseña de Koaly', $texto);
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
