<?php
/**
 * Aviso de privacidad para empleados: texto, versión y enlaces de aceptación.
 *
 * Lo usan:
 *   - gerente/includes/correos_empleado.php  (correo de alta con el enlace)
 *   - login/login.php                        (un cajero sin aceptarlo no entra)
 *   - privacidad/index.php                   (página para leerlo y aceptarlo,
 *                                             y la constancia por correo)
 *
 * Sigue la estructura de la Ley Federal de Protección de Datos Personales en
 * Posesión de los Particulares. Si el texto cambia de fondo, sube la versión:
 * todos los empleados tendrán que aceptarlo otra vez.
 * Tabla: sql/010_aviso_privacidad.sql
 *
 * Idiomas: el aviso en inglés está en aviso_en.php (traducción; prevalece el
 * español) y los textos de la página y del correo en lang/{es,en}.php.
 */

require_once __DIR__ . '/aviso_en.php';

const AVISO_PRIVACIDAD_VERSION = '2026-10-26';
const AVISO_IDIOMAS            = ['es', 'en'];
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
 * Texto de la página o del correo del aviso en $idioma (si falta, en español);
 * {nombre} se reemplaza con $vars. Se llama tAviso() y no t() porque este
 * archivo lo cargan login/ y gerente/, que ya tienen su propio t().
 */
function tAviso(string $clave, string $idioma, array $vars = []): string
{
    static $textos = [];
    foreach (array_unique(['es', $idioma]) as $codigo) {
        $textos[$codigo] ??= require __DIR__ . "/lang/{$codigo}.php";
    }
    $texto = $textos[$idioma][$clave] ?? $textos['es'][$clave] ?? $clave;
    foreach ($vars as $k => $v) {
        $texto = str_replace('{' . $k . '}', (string) $v, $texto);
    }
    return $texto;
}

/**
 * Secciones del aviso: [título => [bloques]]. Un bloque es un párrafo (texto),
 * una lista (arreglo de textos) o una tabla de términos ([término => texto], o
 * [término => [texto, quién lo ve]]).
 * La misma fuente sirve para la página (HTML) y para el texto de los correos.
 *
 * Los datos de identificación, domicilio, NSS y contacto de emergencia están en
 * Empleados_Datos (sql/014_datos_personales.sql). Si se agrega un dato que no
 * está aquí, se escribe en este aviso, se anota en cambiosAvisoPrivacidad() y
 * se sube la versión.
 *
 * @param array $sucursal ['nombre', 'direccion'] o null (aviso genérico)
 * @param string $contacto correo para dudas y derechos ARCO
 * @param string $idioma 'es' o 'en' (la traducción, en aviso_en.php)
 */
function seccionesAvisoPrivacidad(?array $sucursal, string $contacto, string $idioma = 'es'): array
{
    if ($idioma === 'en') {
        return seccionesAvisoPrivacidadEn($sucursal, $contacto);
    }

    $domicilio   = trim((string) ($sucursal['direccion'] ?? ''));
    $responsable = 'Koaly' . ($sucursal ? ', a través de ' . $sucursal['nombre'] : '');
    // "escribe a x@y.com", pero sin correo "escribe al correo de contacto…" (no "a el")
    $escribeA    = str_contains($contacto, '@') ? "a {$contacto}" : 'al correo de contacto de tu sucursal';

    return [
        '1. Quién es responsable de tus datos' => [
            "{$responsable}" . ($domicilio !== '' ? ", con domicilio en {$domicilio}," : '')
            . ' es responsable del tratamiento de tus datos personales como empleado, conforme a la '
            . 'Ley Federal de Protección de Datos Personales en Posesión de los Particulares.',
            "Para cualquier duda sobre este aviso escribe {$escribeA}.",
        ],
        '2. Qué datos tratamos' => [
            [
                'Contacto' => ['Nombre completo, correo electrónico y teléfono.',
                    'tú, tu gerente y la administración.'],
                'Identificación y domicilio' => ['Fecha de nacimiento, CURP, RFC, número de seguridad '
                    . 'social (NSS) y domicilio.',
                    'tú y la administración; tu gerente no los ve.'],
                'Laborales' => ['Puesto, sucursal y caja asignadas, fecha de contratación y situación '
                    . 'laboral (activo, vacaciones, incapacidad, licencia o baja, con su motivo).',
                    'tú, tu gerente y la administración.'],
                'Contacto de emergencia' => ['Nombre, parentesco y teléfono de la persona a quien avisar '
                    . 'en una emergencia. Son datos de otra persona: antes de dárnoslos, avísale y '
                    . 'compártele este aviso.',
                    'tú, tu gerente y la administración, para poder avisarle a tiempo.'],
                'Uso del sistema' => ['Ventas, cobros, turnos y cortes de caja, y movimientos de '
                    . 'inventario que registres con tu cuenta.',
                    'tú, tu gerente y la administración.'],
                'Seguridad' => ['Dirección IP, navegador y fecha de los inicios de sesión fallidos, de '
                    . 'los cambios de contraseña y de la aceptación de este aviso.',
                    'solo la administración.'],
            ],
            'La administración captura tus datos de identificación, domicilio y contacto de emergencia '
            . 'para tu expediente laboral. Si alguno está mal, pide que se corrija.',
            'No te pedimos datos sensibles (salud, religión, afiliación sindical, origen étnico, etc.). '
            . 'De una incapacidad solo registramos que estás incapacitado y las fechas, nunca el diagnóstico.',
        ],
        '3. Para qué los usamos' => [
            'Solo para finalidades necesarias para tu trabajo:',
            [
                'Darte acceso al sistema de punto de venta e inventario y proteger tu cuenta.',
                'Administrar tu relación laboral: altas, bajas, vacaciones, incapacidades, licencias y asignación de caja.',
                'Registrar, supervisar y revisar las ventas, los cortes de caja y el inventario.',
                'Avisarte por correo de los cambios en tu cuenta.',
                'Integrar tu expediente laboral y cumplir obligaciones ante el IMSS y el SAT (domicilio, CURP, RFC y NSS).',
                'Comunicarnos con tu contacto de emergencia si te pasa algo durante el trabajo.',
            ],
            'No usamos tus datos para publicidad, mercadotecnia ni ninguna otra finalidad secundaria.',
        ],
        '4. Con quién los compartimos' => [
            'No vendemos tus datos. Solo los conocen el personal autorizado de la empresa y:',
            [
                'Los proveedores de alojamiento web y correo electrónico que trabajan por nuestra cuenta y '
                . 'están obligados a protegerlos.',
                'Las autoridades que la ley indique (por ejemplo IMSS, SAT o autoridades laborales y '
                . 'judiciales), solo cuando estemos obligados.',
            ],
            'Estas transferencias no requieren tu consentimiento porque son necesarias para la relación '
            . 'laboral o las exige la ley.',
        ],
        '5. Cómo los protegemos' => [
            [
                'Tu contraseña se guarda cifrada: nadie, ni tu gerente, puede verla.',
                'Cada persona ve solo lo que necesita: tu gerente ve tus datos de contacto y laborales, '
                . 'pero no tu CURP, RFC, NSS ni domicilio.',
                'El sistema revisa el formato de la CURP, el RFC y el NSS para evitar errores de captura.',
                'Los enlaces que te enviamos son personales, vencen y sirven una sola vez.',
                'Registramos los intentos de inicio de sesión para detectar accesos indebidos.',
            ],
        ],
        '6. Cuánto tiempo los conservamos' => [
            'Mientras dure tu relación laboral. Después de tu baja, solo el tiempo que exijan las '
            . 'obligaciones laborales, fiscales y legales; luego se eliminan. Las ventas que registraste '
            . 'se conservan como parte de la contabilidad de la tienda.',
        ],
        '7. Tus derechos (ARCO) y cómo revocar tu consentimiento' => [
            'Puedes acceder a tus datos, rectificarlos si están mal, cancelarlos u oponerte a su uso, y '
            . "revocar tu consentimiento. Escribe {$escribeA} con tu nombre, el derecho que quieres "
            . 'ejercer y una copia de tu identificación. Te responderemos en un máximo de 20 días hábiles '
            . 'y, si procede, lo haremos efectivo en los 15 días hábiles siguientes.',
            'Si no te respondemos o no estás de acuerdo con la respuesta, puedes acudir a la Secretaría '
            . 'Anticorrupción y Buen Gobierno, la autoridad de protección de datos personales.',
        ],
        '8. Cookies y datos del navegador' => [
            'El sistema usa solo una cookie de sesión, necesaria para mantenerte conectado, y guarda en '
            . 'tu navegador si prefieres el tema claro u oscuro. No usamos cookies de publicidad ni de rastreo.',
        ],
        '9. Cambios a este aviso' => [
            'Si este aviso cambia, te lo enviaremos por correo y te pediremos aceptarlo de nuevo antes de '
            . 'seguir usando el sistema. La fecha de la versión vigente está al inicio del aviso.',
        ],
        '10. Constancia de tu aceptación' => [
            'Al aceptar guardamos la fecha y hora, la versión del aviso, tu dirección IP y tu navegador. '
            . 'Te enviamos por correo una constancia con un folio y el texto completo que aceptaste, y una '
            . 'copia queda en el buzón de la empresa.',
        ],
    ];
}

/**
 * Qué cambió en la versión vigente respecto a la anterior. La página lo muestra
 * a quien ya había aceptado otra versión, para que no tenga que releer todo.
 */
function cambiosAvisoPrivacidad(string $idioma = 'es'): array
{
    if ($idioma === 'en') {
        return cambiosAvisoPrivacidadEn();
    }

    return [
        'Ahora guardamos tu teléfono, domicilio, fecha de nacimiento, CURP, RFC, NSS y un contacto de '
        . 'emergencia, para tu expediente laboral y los trámites ante el IMSS y el SAT.',
        'Cada tipo de dato dice quién lo puede ver. Tu gerente no ve tu CURP, RFC, NSS ni domicilio.',
        'La autoridad a la que puedes acudir ahora es la Secretaría Anticorrupción y Buen Gobierno, '
        . 'que tomó las funciones del INAI con la ley de protección de datos de 2025.',
    ];
}

/** Las secciones como texto plano, para los correos (y la huella), en el idioma de las secciones. */
function textoPlanoAviso(array $secciones, string $idioma = 'es'): string
{
    $loVen = tAviso('lo_ven', $idioma);
    $texto = '';
    foreach ($secciones as $titulo => $bloques) {
        $texto .= mb_strtoupper($titulo) . "\n";
        foreach ($bloques as $bloque) {
            if (is_string($bloque)) {
                $texto .= wordwrap($bloque, 76) . "\n";
            } elseif (array_is_list($bloque)) {
                foreach ($bloque as $item) {
                    $texto .= '  - ' . wordwrap($item, 72, "\n    ") . "\n";
                }
            } else {
                foreach ($bloque as $termino => $descripcion) {
                    [$dato, $quienVe] = is_array($descripcion) ? $descripcion : [$descripcion, ''];
                    $texto .= "  {$termino}:\n    " . wordwrap($dato, 72, "\n    ") . "\n";
                    if ($quienVe !== '') {
                        $texto .= "    {$loVen} " . wordwrap($quienVe, 64, "\n    ") . "\n";
                    }
                }
            }
        }
        $texto .= "\n";
    }
    return $texto;
}

/** Folio de una aceptación: el id del registro en avisos_privacidad ("AP-000042"). */
function folioAviso(int $id): string
{
    return 'AP-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

/**
 * Constancia de aceptación por correo: al empleado (las respuestas llegan a
 * $buzon) y una copia a $buzon, que queda como el registro escrito de la empresa.
 * La huella (SHA-256 del texto aceptado) prueba qué texto exacto se aceptó.
 *
 * El correo sale en el idioma en que se aceptó ($c['idioma']); la copia al
 * buzón lleva el encabezado en español para el expediente.
 *
 * @param array $c ['folio', 'nombre', 'email', 'puesto', 'sucursal', 'fecha', 'ip', 'idioma']
 * @return bool si salió el correo al empleado
 */
function enviarConstanciaAviso(array $c, array $secciones, string $buzon): bool
{
    require_once __DIR__ . '/../login/correo.php';

    $idioma     = $c['idioma'] ?? 'es';
    $tx         = static fn(string $clave, array $vars = []): string => tAviso($clave, $idioma, $vars);
    $textoAviso = textoPlanoAviso($secciones, $idioma);
    $huella     = hash('sha256', $textoAviso);

    // Etiquetas alineadas (con mb_strlen: llevan acentos)
    $campos = [
        $tx('campo.folio')    => $c['folio'],
        $tx('campo.nombre')   => $c['nombre'],
        $tx('campo.correo')   => $c['email'],
        $tx('campo.puesto')   => $c['puesto'],
        $tx('campo.sucursal') => $c['sucursal'],
        $tx('campo.fecha')    => $c['fecha'] . ' ' . $tx('correo.zona'),
        $tx('campo.version')  => fechaVersionAviso($idioma),
        $tx('campo.idioma')   => $tx('idioma.nombre'),
        $tx('campo.ip')       => $c['ip'],
        $tx('campo.huella')   => $huella,
    ];
    $ancho = max(array_map('mb_strlen', array_keys($campos))) + 1;
    $tabla = '';
    foreach ($campos as $etiqueta => $valor) {
        $tabla .= '    ' . $etiqueta . ':' . str_repeat(' ', $ancho - mb_strlen($etiqueta)) . $valor . "\n";
    }
    $linea = str_repeat('-', 70);

    $cuerpo = $tx('correo.intro') . "\n\n" . $tabla . "\n"
        . $tx('correo.confirmar_titulo') . "\n" . $tx('correo.confirmar', ['frase' => $tx('frase')]) . "\n\n"
        . $tx('correo.no_fuiste') . "\n\n"
        . $tx('correo.consulta', ['url' => urlSitio() . 'privacidad/']) . "\n\n"
        . "{$linea}\n" . $tx('correo.texto_titulo') . "\n{$linea}\n\n";

    $asunto = $tx('correo.asunto', ['folio' => $c['folio']]);
    $enviado = enviarCorreo($c['email'], $asunto, $tx('correo.saludo', ['nombre' => $c['nombre']]) . "\n\n{$cuerpo}{$textoAviso}", $buzon);

    if (filter_var($buzon, FILTER_VALIDATE_EMAIL) && strcasecmp($buzon, $c['email']) !== 0) {
        $copia = "Copia para el expediente de {$c['nombre']}. "
            . ($enviado ? 'La constancia se envió a su correo.' : 'ATENCIÓN: no se pudo enviar a su correo.')
            . "\n\n{$cuerpo}{$textoAviso}";
        enviarCorreo($buzon, "[Copia] {$asunto} · {$c['nombre']}", $copia, $c['email']);
    }
    return $enviado;
}

/** Fecha de la versión vigente, para mostrarla ("15/10/2026"; en inglés "10/15/2026"). */
function fechaVersionAviso(string $idioma = 'es'): string
{
    return DateTimeImmutable::createFromFormat('!Y-m-d', AVISO_PRIVACIDAD_VERSION)->format(tAviso('formato_fecha', $idioma));
}
