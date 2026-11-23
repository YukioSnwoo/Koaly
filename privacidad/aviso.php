<?php
/**
 * Avisos de privacidad: texto, versión y enlaces de aceptación. Hay tres:
 *   empleado  cajeros (este archivo y aviso_en.php). Se acepta.
 *   gerente   gerentes (aviso_gerente.php). Se acepta.
 *   cliente   compradores (aviso_cliente.php). Solo se consulta: su dirección
 *             viene al final del ticket.
 *
 * Lo usan:
 *   - gerente/includes/correos_empleado.php  (correo de alta con el enlace)
 *   - login/login.php                        (un cajero o gerente sin aceptarlo no entra)
 *   - privacidad/index.php                   (página para leerlo y aceptarlo,
 *                                             y la constancia por correo)
 *
 * Sigue la estructura de la Ley Federal de Protección de Datos Personales en
 * Posesión de los Particulares. Si el texto cambia de fondo, sube la versión
 * de ese aviso: quienes lo aceptaron tendrán que aceptarlo otra vez.
 * Tabla: sql/010_aviso_privacidad.sql (la misma para cajeros y gerentes).
 *
 * Idiomas: el aviso en inglés está en aviso_en.php (traducción; prevalece el
 * español) y los textos de la página y del correo en lang/{es,en}.php.
 */

require_once __DIR__ . '/aviso_en.php';
require_once __DIR__ . '/aviso_gerente.php';
require_once __DIR__ . '/aviso_cliente.php';
require_once __DIR__ . '/formato.php';

// Versión de cada aviso escrito en el código (fecha). Vale mientras el admin no
// publique otra desde su panel (avisos_versiones, sql/018). La del gerente lleva
// "G-" para que la aceptación de un aviso no cuente para el otro: si un cajero
// pasa a gerente, acepta el suyo aunque las fechas coincidan.
const AVISO_PRIVACIDAD_VERSION = '2026-10-26';      // cajeros
const AVISO_GERENTE_VERSION    = 'G-2026-11-17';
const AVISO_CLIENTE_VERSION    = '2026-11-17';
const AVISO_TIPOS              = ['empleado', 'gerente', 'cliente'];
const AVISO_IDIOMAS            = ['es', 'en'];
const AVISO_ENLACE_DIAS        = 7;   // validez del enlace que llega por correo
const AVISO_ENLACE_LOGIN_HORAS = 1;   // validez del enlace que se crea al iniciar sesión

/** Roles que deben aceptar su aviso antes de usar el sistema (2 = gerente, 3 = cajero). */
const AVISO_ROLES_OBLIGADOS = [2, 3];

/** Qué aviso le toca a un rol: el gerente el suyo; el cajero, el de empleados. */
function tipoAvisoRol(int $idRol): string
{
    return $idRol === 2 ? 'gerente' : 'empleado';
}

/**
 * Última versión publicada de cada aviso desde el panel (avisos_versiones), por
 * tipo. Con $pdo la carga (una vez por petición); sin él devuelve lo ya cargado.
 * Sin sql/018, o si nadie ha publicado, está vacía y valen los textos del código.
 */
function avisosPublicados(?PDO $pdo = null): array
{
    static $publicados = null;
    if ($publicados === null && $pdo !== null) {
        $publicados = [];
        try {
            $consulta = $pdo->query("
                SELECT v.*, u.nombre AS publicado_por_nombre
                FROM avisos_versiones v
                LEFT JOIN Usuarios u ON u.id_usuario = v.publicado_por
                WHERE v.id = (SELECT MAX(x.id) FROM avisos_versiones x WHERE x.tipo = v.tipo)
            ");
            foreach ($consulta ? $consulta->fetchAll(PDO::FETCH_ASSOC) : [] as $fila) {
                $publicados[$fila['tipo']] = $fila;
            }
        } catch (PDOException) {
            // Sin sql/018: los avisos del código
        }
    }
    return $publicados ?? [];
}

/** Versión del aviso escrito en el código. */
function versionCodigoAviso(string $tipo): string
{
    return match ($tipo) {
        'gerente' => AVISO_GERENTE_VERSION,
        'cliente' => AVISO_CLIENTE_VERSION,
        default   => AVISO_PRIVACIDAD_VERSION,
    };
}

/** Versión vigente: la última publicada desde el panel o, si no hay, la del código. */
function versionAviso(string $tipo): string
{
    return avisosPublicados()[$tipo]['version'] ?? versionCodigoAviso($tipo);
}

/**
 * Versión para publicar hoy: "2026-11-18" (cajeros y clientes) o "G-2026-11-18"
 * (gerentes); si ya hubo una ese día, ".2", ".3"… Nunca repite la del código:
 * si coincidiera, quien aceptó la vieja contaría como si hubiera aceptado la nueva.
 */
function nuevaVersionAviso(PDO $pdo, string $tipo): string
{
    $base = ($tipo === 'gerente' ? 'G-' : '') . date('Y-m-d');
    $stmt = $pdo->prepare("SELECT version FROM avisos_versiones WHERE tipo = ? AND version LIKE ?");
    $stmt->execute([$tipo, $base . '%']);
    $usadas = [...$stmt->fetchAll(PDO::FETCH_COLUMN), versionCodigoAviso($tipo)];
    if (!in_array($base, $usadas, true)) {
        return $base;
    }
    $n = 2;
    while (in_array("{$base}.{$n}", $usadas, true)) {
        $n++;
    }
    return "{$base}.{$n}";
}

/** Rol del usuario, para saber qué aviso le toca cuando quien llama no lo trae. */
function rolParaAviso(PDO $pdo, int $idUsuario): int
{
    $stmt = $pdo->prepare('SELECT id_rol FROM Usuarios WHERE id_usuario = ?');
    $stmt->execute([$idUsuario]);
    return (int) $stmt->fetchColumn();
}

/**
 * Las secciones del aviso vigente de ese tipo: el publicado desde el panel (en
 * inglés, si se escribió la traducción) o el del código, que arma el responsable
 * y el contacto con $sucursal y $contacto.
 */
function seccionesAviso(string $tipo, ?array $sucursal, string $contacto, string $idioma = 'es'): array
{
    if ($publicado = avisosPublicados()[$tipo] ?? null) {
        $traducido = $idioma === 'en' && trim((string) $publicado['texto_en']) !== '';
        return seccionesDesdeTexto($traducido ? $publicado['texto_en'] : $publicado['texto_es']);
    }
    return match ($tipo) {
        'gerente' => seccionesAvisoGerente($sucursal, $contacto, $idioma),
        'cliente' => seccionesAvisoCliente($contacto, $idioma),
        default   => seccionesAvisoPrivacidad($sucursal, $contacto, $idioma),
    };
}

/** Qué cambió en la versión vigente: lo que escribió el admin al publicarla o, si es la del código, el de cajeros. */
function cambiosAviso(string $tipo, string $idioma = 'es'): array
{
    if ($publicado = avisosPublicados()[$tipo] ?? null) {
        $texto = $idioma === 'en' && trim((string) $publicado['cambios_en']) !== '' ? $publicado['cambios_en'] : $publicado['cambios_es'];
        return array_values(array_filter(array_map(
            static fn(string $linea): string => trim(preg_replace('/^[-•*]\s*/u', '', trim($linea))),
            preg_split('/\R/u', (string) $texto)
        ), static fn(string $linea): bool => $linea !== ''));
    }
    return $tipo === 'empleado' ? cambiosAvisoPrivacidad($idioma) : [];
}

/**
 * Contacto que llevan los avisos del código cuando no hay una sucursal de por
 * medio (vista pública y editor del admin): el de cajeros, el genérico de su
 * sucursal; el de gerentes y clientes, el correo de la empresa (config_correo.php).
 */
function contactoAvisoGeneral(string $tipo, string $idioma = 'es'): string
{
    if ($tipo === 'empleado') {
        return tAviso('contacto_generico', $idioma);
    }
    require_once __DIR__ . '/../login/correo.php';
    $cfg = configCorreo() ?? [];
    return ($cfg['responder_a'] ?? '') ?: ($cfg['buzon_constancias'] ?? '');
}

/** El aviso vigente en el formato del editor, para empezar a editar desde él. */
function textoVigenteAviso(string $tipo, string $idioma = 'es'): string
{
    if ($publicado = avisosPublicados()[$tipo] ?? null) {
        return (string) ($idioma === 'en' ? $publicado['texto_en'] : $publicado['texto_es']);
    }
    return textoDesdeSecciones(seccionesAviso($tipo, null, contactoAvisoGeneral($tipo, $idioma), $idioma));
}

/** Las secciones en HTML (página del aviso y vista previa del admin); cada una con id="seccion-N" para el índice. */
function htmlSeccionesAviso(array $secciones, string $loVen): string
{
    $e = static fn(string $texto): string => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $html = '';
    $i = 0;
    foreach ($secciones as $titulo => $bloques) {
        $html .= '<section id="seccion-' . (++$i) . '"><h2>' . $e((string) $titulo) . '</h2>';
        foreach ($bloques as $bloque) {
            if (is_string($bloque)) {
                $html .= '<p>' . $e($bloque) . '</p>';
            } elseif (array_is_list($bloque)) {
                $html .= '<ul>' . implode('', array_map(static fn($item) => '<li>' . $e((string) $item) . '</li>', $bloque)) . '</ul>';
            } else {
                $html .= '<dl class="datos">';
                foreach ($bloque as $termino => $descripcion) {
                    [$dato, $quienVe] = is_array($descripcion) ? $descripcion : [$descripcion, ''];
                    $html .= '<div><dt>' . $e((string) $termino) . '</dt><dd>' . $e((string) $dato)
                        . ($quienVe !== '' ? ' <span class="quien-ve">' . $e($loVen) . ' ' . $e((string) $quienVe) . '</span>' : '')
                        . '</dd></div>';
                }
                $html .= '</dl>';
            }
        }
        $html .= '</section>';
    }
    return $html;
}

/** URL de la raíz del sitio (donde están index.php y privacidad/), sin importar desde qué página se llame. */
function urlSitio(): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
    $raiz  = realpath(dirname(__DIR__));
    $docs  = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: $raiz;
    $ruta  = str_starts_with($raiz, $docs) ? str_replace('\\', '/', substr($raiz, strlen($docs))) : '';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($ruta, '/') . '/';
}

/** ¿El usuario aceptó la versión vigente del aviso que le toca a su rol? */
function avisoAceptado(PDO $pdo, int $idUsuario, ?int $idRol = null): bool
{
    avisosPublicados($pdo);
    $version = versionAviso(tipoAvisoRol($idRol ?? rolParaAviso($pdo, $idUsuario)));
    $stmt = $pdo->prepare("
        SELECT 1 FROM avisos_privacidad
        WHERE id_usuario = ? AND version = ? AND aceptado_en IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$idUsuario, $version]);
    return $stmt->fetchColumn() !== false;
}

/**
 * Crea un enlace personal para leer y aceptar el aviso. Devuelve la URL completa.
 * Solo se guarda el hash del token: quien lea la tabla no puede usar los enlaces.
 */
function crearEnlaceAviso(PDO $pdo, int $idUsuario, int $horasValidez, ?int $idRol = null): string
{
    avisosPublicados($pdo);
    $version = versionAviso(tipoAvisoRol($idRol ?? rolParaAviso($pdo, $idUsuario)));

    // Los enlaces vencidos que nunca se usaron ya no sirven de constancia
    $pdo->prepare("DELETE FROM avisos_privacidad WHERE id_usuario = ? AND aceptado_en IS NULL AND expira_en < NOW()")
        ->execute([$idUsuario]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("
        INSERT INTO avisos_privacidad (id_usuario, version, token_hash, expira_en)
        VALUES (?, ?, ?, NOW() + INTERVAL ? HOUR)
    ")->execute([$idUsuario, $version, hash('sha256', $token), $horasValidez]);

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
 * @param array $c ['folio', 'nombre', 'email', 'puesto', 'sucursal', 'fecha', 'ip', 'idioma', 'tipo']
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
        $tx('campo.version')  => fechaVersionAviso($idioma, $c['tipo'] ?? 'empleado'),
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
        . $tx('correo.consulta', ['url' => urlAviso($c['tipo'] ?? 'empleado')]) . "\n\n"
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

/* ------------------------------------------------------------
   Quién debe aceptar cada aviso y correo de "actualizamos el aviso"
   (admin/avisos.php; necesita sql/018)
   ------------------------------------------------------------ */

/** Rol que acepta cada aviso; el de clientes solo se consulta. */
const AVISO_ROL_TIPO = ['empleado' => 3, 'gerente' => 2];

/**
 * Cajeros o gerentes que deben aceptar ese aviso (todos menos los dados de baja),
 * con `aceptado` (la versión vigente) y `avisado` (ya se le mandó el correo de esa
 * versión). Sin la tabla de correos (sql/018), $conAvisados = false: avisado = 0.
 */
function personasAviso(PDO $pdo, string $tipo, bool $conAvisados = true): array
{
    avisosPublicados($pdo);
    $version = versionAviso($tipo);
    $avisado = $conAvisados
        ? 'EXISTS (SELECT 1 FROM avisos_notificaciones n WHERE n.id_usuario = u.id_usuario AND n.version = ?)'
        : '0';
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email,
               EXISTS (SELECT 1 FROM avisos_privacidad a
                       WHERE a.id_usuario = u.id_usuario AND a.version = ? AND a.aceptado_en IS NOT NULL) AS aceptado,
               {$avisado} AS avisado
        FROM Usuarios u
        WHERE u.id_rol = ? AND u.estado <> 'Baja'
        ORDER BY u.nombre
    ");
    $stmt->execute($conAvisados ? [$version, $version, AVISO_ROL_TIPO[$tipo]] : [$version, AVISO_ROL_TIPO[$tipo]]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Correo de "actualizamos el aviso" a quien tiene pendiente la versión vigente y
 * aún no lo recibió. No trae enlace para aceptar: al entrar al sistema se le
 * pide (login.php), como promete el propio aviso. Va en español: el idioma de
 * cada empleado no se guarda. Devuelve ['enviados' => n, 'fallidos' => [nombres]].
 */
function avisarAvisoActualizado(PDO $pdo, string $tipo): array
{
    require_once __DIR__ . '/../login/correo.php';
    $tx       = static fn(string $clave, array $vars = []): string => tAviso($clave, 'es', $vars);
    $version  = versionAviso($tipo);
    $cambios  = cambiosAviso($tipo, 'es');
    $vars     = ['quienes' => $tx('quienes.' . $tipo), 'fecha' => fechaVersionAviso('es', $tipo)];
    $url      = urlAviso($tipo);
    $pieHtml  = str_replace('{url}', correoEnlace($url, $url), correoE($tx('actualizado.pie')));
    $pieTexto = $tx('actualizado.pie', ['url' => $url]);
    $registrar = $pdo->prepare('INSERT IGNORE INTO avisos_notificaciones (id_usuario, version) VALUES (?, ?)');
    @set_time_limit(300);   // por SMTP cada correo tarda uno o dos segundos

    $enviados = 0;
    $fallidos = [];
    foreach (personasAviso($pdo, $tipo) as $persona) {
        if ($persona['aceptado'] || $persona['avisado']) {
            continue;
        }
        $parrafos = [
            $tx('correo.saludo', ['nombre' => $persona['nombre']]),
            $tx($cambios ? 'actualizado.intro' : 'actualizado.nuevo', $vars),
        ];
        if ($cambios) {
            $parrafos[] = $tx('actualizado.cambios');
            $parrafos[] = ['lista' => $cambios];
        }
        $parrafos[] = $tx('actualizado.accion');
        $parrafos[] = ['boton' => urlSitio(), 'texto' => $tx('actualizado.boton')];

        if (enviarCorreo($persona['email'], $tx('actualizado.asunto'), correoTexto($parrafos, $pieTexto), null, correoHtml($parrafos, $pieHtml))) {
            $registrar->execute([$persona['id_usuario'], $version]);
            $enviados++;
        } else {
            $fallidos[] = $persona['nombre'];
        }
    }
    return ['enviados' => $enviados, 'fallidos' => $fallidos];
}

/** Fecha de la versión vigente de ese aviso ("15/10/2026"; en inglés "10/15/2026"). */
function fechaVersionAviso(string $idioma = 'es', string $tipo = 'empleado', ?string $version = null): string
{
    // La fecha de "G-2026-11-18.2": sin el prefijo de gerentes ni el número del día
    preg_match('/\d{4}-\d{2}-\d{2}/', $version ?? versionAviso($tipo), $m);
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $m[0] ?? '');
    return $fecha ? $fecha->format(tAviso('formato_fecha', $idioma)) : (string) ($version ?? versionAviso($tipo));
}

/** Dirección pública del aviso de ese tipo (la del cliente va impresa en el ticket). */
function urlAviso(string $tipo = 'empleado'): string
{
    return urlSitio() . 'privacidad/' . ($tipo === 'empleado' ? '' : '?aviso=' . $tipo);
}
