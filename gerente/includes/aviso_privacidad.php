<?php
/**
 * Aviso de privacidad que recibe por correo cada empleado nuevo.
 *
 * Sigue la estructura de la Ley Federal de Protección de Datos Personales en
 * Posesión de los Particulares (responsable, datos, finalidades,
 * transferencias, derechos ARCO y cambios). Va siempre en español: es un
 * documento para el empleado, no un texto de la interfaz.
 *
 * El correo sale con la cuenta de login/config_correo.php (ver login/correo.php).
 */
require_once __DIR__ . '/../../login/correo.php';

/**
 * Envía la bienvenida con el aviso de privacidad. Devuelve false si el correo
 * no salió: el alta del empleado no se deshace por eso.
 *
 * @param array $empleado ['nombre', 'email']
 * @param array $gerente  ['nombre', 'email']
 * @param array $sucursal ['nombre', 'direccion', 'email_contacto']
 */
function enviarAvisoPrivacidad(array $empleado, array $gerente, array $sucursal): bool
{
    // Para ejercer los derechos ARCO: el correo de la sucursal o, si no tiene, el del gerente
    $contacto  = trim((string) ($sucursal['email_contacto'] ?? '')) ?: $gerente['email'];
    $domicilio = trim((string) ($sucursal['direccion'] ?? ''));
    $responsable = 'Koaly, a través de ' . $sucursal['nombre'] . ($domicilio !== '' ? " ({$domicilio})" : '');
    $fecha = date('d/m/Y');

    // Inicio de sesión: la raíz del sitio, un nivel arriba de gerente/
    $https    = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
    $urlSitio = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/', 2)), '/') . '/';

    $texto = <<<TXT
    Hola {$empleado['nombre']}:

    Te damos la bienvenida al equipo de {$sucursal['nombre']}. Ya tienes una cuenta
    de cajero en el sistema Koaly:

        Dirección: {$urlSitio}
        Usuario:   {$empleado['email']}

    Tu gerente, {$gerente['nombre']}, te entregará la contraseña temporal. El
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

