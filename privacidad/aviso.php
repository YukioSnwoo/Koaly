<?php
/**
 * Aviso de privacidad para empleados: texto, versión y enlaces de aceptación.
 *
 * Lo usan:
 *   - gerente/includes/correos_empleado.php  (correo de alta con el enlace)
 *   - login/login.php                        (un cajero sin aceptarlo no entra)
 *   - privacidad/index.php                   (página para leerlo y aceptarlo)
 *
 * Sigue la estructura de la Ley Federal de Protección de Datos Personales en
 * Posesión de los Particulares. Si el texto cambia de fondo, sube la versión:
 * todos los empleados tendrán que aceptarlo otra vez.
 * Tabla: sql/005_aviso_privacidad.sql
 */

const AVISO_PRIVACIDAD_VERSION = '2026-10-15';
const AVISO_ENLACE_DIAS        = 7;   // validez del enlace que llega por correo
const AVISO_ENLACE_LOGIN_HORAS = 1;   // validez del enlace que se crea al iniciar sesión

/** Roles que deben aceptar el aviso antes de usar el sistema (3 = cajero). */
const AVISO_ROLES_OBLIGADOS = [3];

/** URL de la raíz del sitio (donde están index.php y privacidad/), sin importar desde qué página se llame. */
function urlSitio(): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
    $raiz  = realpath(dirname(__DIR__));
    $docs  = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: $raiz;
    $ruta  = str_starts_with($raiz, $docs) ? str_replace('\\', '/', substr($raiz, strlen($docs))) : '';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($ruta, '/') . '/';
}

/** ¿El usuario aceptó la versión vigente del aviso? */
function avisoAceptado(PDO $pdo, int $idUsuario): bool
{
    $stmt = $pdo->prepare("
        SELECT 1 FROM avisos_privacidad
        WHERE id_usuario = ? AND version = ? AND aceptado_en IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$idUsuario, AVISO_PRIVACIDAD_VERSION]);
    return $stmt->fetchColumn() !== false;
}

/** Fecha en que aceptó la versión vigente, o null. */
function fechaAceptacionAviso(PDO $pdo, int $idUsuario): ?string
{
    $stmt = $pdo->prepare("
        SELECT MAX(aceptado_en) FROM avisos_privacidad
        WHERE id_usuario = ? AND version = ? AND aceptado_en IS NOT NULL
    ");
    $stmt->execute([$idUsuario, AVISO_PRIVACIDAD_VERSION]);
    return $stmt->fetchColumn() ?: null;
}

/**
 * Crea un enlace personal para leer y aceptar el aviso. Devuelve la URL completa.
 * Solo se guarda el hash del token: quien lea la tabla no puede usar los enlaces.
 */
function crearEnlaceAviso(PDO $pdo, int $idUsuario, int $horasValidez): string
{
    // Los enlaces vencidos que nunca se usaron ya no sirven de constancia
    $pdo->prepare("DELETE FROM avisos_privacidad WHERE id_usuario = ? AND aceptado_en IS NULL AND expira_en < NOW()")
        ->execute([$idUsuario]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("
        INSERT INTO avisos_privacidad (id_usuario, version, token_hash, expira_en)
        VALUES (?, ?, ?, NOW() + INTERVAL ? HOUR)
    ")->execute([$idUsuario, AVISO_PRIVACIDAD_VERSION, hash('sha256', $token), $horasValidez]);

    return urlSitio() . 'privacidad/?token=' . $token;
}

/**
 * Secciones del aviso: [título => [párrafos]]. La misma fuente sirve para la
 * página (HTML) y para el texto plano de los correos.
 *
 * @param array $sucursal ['nombre', 'direccion', 'email_contacto'] o null (aviso genérico)
 * @param string $contacto correo para dudas y derechos ARCO
 */
function seccionesAvisoPrivacidad(?array $sucursal, string $contacto): array
{
    $domicilio   = trim((string) ($sucursal['direccion'] ?? ''));
    $responsable = 'Koaly' . ($sucursal ? ', a través de ' . $sucursal['nombre'] . ($domicilio !== '' ? " ({$domicilio})" : '') : '');

    return [
        '1. Responsable' => [
            "{$responsable} es responsable del tratamiento de tus datos personales, conforme a la "
            . 'Ley Federal de Protección de Datos Personales en Posesión de los Particulares.',
        ],
        '2. Datos que tratamos' => [
            'Nombre completo, correo electrónico, fecha de contratación, sucursal y caja asignadas, '
            . 'situación laboral (activo, vacaciones, incapacidad, licencia o baja) y el registro de las '
            . 'ventas y movimientos de inventario que realices en el sistema.',
            'Por seguridad también registramos la dirección IP y la fecha de los intentos fallidos de '
            . 'inicio de sesión y de la aceptación de este aviso.',
            'No tratamos datos personales sensibles.',
        ],
        '3. Para qué los usamos' => [
            'a) Darte acceso al sistema de punto de venta e inventario.',
            'b) Asignarte una caja y administrar tu situación laboral (altas, bajas, vacaciones, incapacidades y licencias).',
            'c) Registrar y supervisar las ventas, para la operación de la tienda y el control interno.',
            'd) Proteger tu cuenta y avisarte por correo de los cambios en ella.',
        ],
        '4. Con quién los compartimos' => [
            'No vendemos ni compartimos tus datos con terceros. Solo los conocen el personal autorizado '
            . 'de la empresa, los proveedores de correo y alojamiento que trabajan por nuestra cuenta y, '
            . 'cuando la ley lo exija, las autoridades competentes.',
        ],
        '5. Cuánto tiempo los conservamos' => [
            'Mientras dure la relación laboral y después, solo el tiempo que exijan las obligaciones legales y fiscales.',
        ],
        '6. Tus derechos (ARCO)' => [
            'Puedes acceder a tus datos, rectificarlos, cancelarlos u oponerte a su uso, así como revocar '
            . "tu consentimiento. Escribe a {$contacto} con tu nombre, el derecho que quieres ejercer y una "
            . 'copia de tu identificación. Te responderemos en un máximo de 20 días hábiles.',
        ],
        '7. Cambios a este aviso' => [
            'Si este aviso cambia, te lo haremos saber por correo y te pediremos aceptarlo de nuevo antes '
            . 'de seguir usando el sistema.',
        ],
    ];
}

/** Fecha de la versión vigente, para mostrarla ("15/10/2026"). */
function fechaVersionAviso(): string
{
    return DateTimeImmutable::createFromFormat('!Y-m-d', AVISO_PRIVACIDAD_VERSION)->format('d/m/Y');
}
