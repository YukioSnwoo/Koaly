<?php
/**
 * Aviso de privacidad para empleados.
 *
 *   privacidad/              el aviso vigente (público, para consultarlo cuando sea)
 *   privacidad/?token=...    enlace personal: muestra los datos del empleado y
 *                            le pide aceptar el aviso. El enlace sirve una sola vez.
 *
 * Al aceptar se guarda la constancia (fecha, versión, IP y navegador) en
 * avisos_privacidad, se envía por correo al empleado con un folio y el texto
 * aceptado (con copia al buzón de la empresa) y la página la muestra para
 * imprimirla y firmarla. Un cajero no puede iniciar sesión sin aceptar la versión vigente.
 *
 * Idioma: el de todo el sitio (cookie koaly_idioma, admin/preferencia_idioma.php);
 * el botón de la cabecera lo cambia (?idioma=en). Se acepta el texto en el
 * idioma en que se leyó, y en ese idioma salen la constancia y el correo.
 */
session_start();
header('Cache-Control: no-store');
require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/aviso.php';
require_once __DIR__ . '/../login/correo.php';
require_once __DIR__ . '/../admin/preferencia_idioma.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Hora de México, como el panel del gerente (las fechas de la tabla son TIMESTAMP)
date_default_timezone_set('America/Mexico_City');
$pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");

// Cambio de idioma desde la cabecera: se guarda para todo el sitio y se regresa al mismo enlace
if (in_array($_GET['idioma'] ?? '', AVISO_IDIOMAS, true)) {
    guardarIdioma($_GET['idioma']);
    $token = (string) ($_GET['token'] ?? '');
    header('Location: ./' . ($token !== '' ? '?token=' . rawurlencode($token) : ''));
    exit;
}

if (empty($_SESSION['privacidad_csrf'])) {
    $_SESSION['privacidad_csrf'] = bin2hex(random_bytes(16));
}

/** Enlace vigente y sin usar, con los datos del empleado; null si no sirve. */
function buscarEnlace(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT a.id, a.id_usuario,
               u.nombre, u.id_rol, u.email, u.estado, u.fecha_contratacion, u.debe_cambiar, r.nombre_rol,
               s.nombre AS sucursal, s.direccion, s.email_contacto,
               (SELECT GROUP_CONCAT(CONCAT_WS(' · ', c.numero_caja, c.nombre) SEPARATOR ', ')
                FROM Cajas c WHERE c.id_cajero_asignado = u.id_usuario) AS cajas,
               (SELECT g.email FROM Usuarios g
                WHERE g.id_sucursal = u.id_sucursal AND g.id_rol = 2 AND g.estado = 'Activo' LIMIT 1) AS gerente_email,
               (SELECT MAX(p.version) FROM avisos_privacidad p
                WHERE p.id_usuario = u.id_usuario AND p.aceptado_en IS NOT NULL) AS version_anterior
        FROM avisos_privacidad a
        INNER JOIN Usuarios u   ON u.id_usuario = a.id_usuario
        INNER JOIN Roles r      ON r.id_rol = u.id_rol
        LEFT JOIN Sucursales s  ON s.id_sucursal = u.id_sucursal
        WHERE a.token_hash = ? AND a.aceptado_en IS NULL AND a.expira_en > NOW()
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$token   = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$enlace  = $token !== '' ? buscarEnlace($pdo, $token) : null;
$error   = '';
$aceptado = $_SESSION['privacidad_aceptado'] ?? null; // PRG: la constancia tras aceptar
unset($_SESSION['privacidad_aceptado']);

// La constancia, en el idioma en que se aceptó; al aceptar, el del texto que se leyó (va en el formulario)
$idioma = $aceptado['idioma']
    ?? (in_array($_POST['idioma'] ?? '', AVISO_IDIOMAS, true) ? $_POST['idioma'] : idiomaGuardado(AVISO_IDIOMAS));

$e = static fn(?string $t): string => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
$t = static fn(string $clave, array $vars = []): string => tAviso($clave, $idioma, $vars);
$fecha = static fn(?string $f, bool $hora = false): string => $f ? date($t('formato_fecha') . ($hora ? ' H:i' : ''), strtotime($f)) : '—';
/** Valor de la base de datos (puesto, situación) en el idioma de la página; sin traducción, tal cual. */
$deBd = static fn(string $clave, ?string $valor): string => ($texto = $t($clave)) !== $clave ? $texto : (string) $valor;

$contacto = $enlace
    ? (trim((string) $enlace['email_contacto']) ?: ($enlace['gerente_email'] ?: $t('contacto_generico')))
    : $t('contacto_generico');
$sucursalAviso = $enlace && $enlace['sucursal'] ? ['nombre' => $enlace['sucursal'], 'direccion' => $enlace['direccion']] : null;
$secciones = $aceptado['secciones'] ?? seccionesAvisoPrivacidad($sucursalAviso, $contacto, $idioma);
$puesto = $enlace ? $deBd('rol.' . $enlace['id_rol'], $enlace['nombre_rol']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['privacidad_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = $t('error.csrf');
    } elseif (!$enlace) {
        $error = $t('error.enlace');
    } elseif (($_POST['acepto'] ?? '') !== '1') {
        $error = $t('error.casilla');
    } else {
        // Constancia de la aceptación. El token se borra: el enlace ya no sirve.
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $pdo->prepare("
            UPDATE avisos_privacidad
            SET aceptado_en = NOW(), version = ?, ip = ?, agente = ?, token_hash = NULL
            WHERE id = ? AND aceptado_en IS NULL
        ")->execute([
            AVISO_PRIVACIDAD_VERSION,
            $ip,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $enlace['id'],
        ]);

        $constancia = [
            'folio'    => folioAviso((int) $enlace['id']),
            'nombre'   => $enlace['nombre'],
            'email'    => $enlace['email'],
            'puesto'   => $puesto,
            'sucursal' => $enlace['sucursal'] ?? '—',
            'fecha'    => date($t('formato_fecha') . ' H:i'),
            'ip'       => $ip,
            'idioma'   => $idioma,
        ];
        $primerIngreso = (int) $enlace['debe_cambiar'] === 1;
        // Copia al buzón de constancias (config_correo.php) o, si no hay, al contacto de la sucursal
        $buzon = (configCorreo()['buzon_constancias'] ?? '') ?: (str_contains($contacto, '@') ? $contacto : '');
        $constancia['correo_enviado'] = enviarConstanciaAviso($constancia, $secciones, $buzon);
        $constancia['huella']    = hash('sha256', textoPlanoAviso($secciones, $idioma));
        $constancia['secciones'] = $secciones;
        $constancia['primer_ingreso'] = $primerIngreso;

        $_SESSION['privacidad_aceptado'] = $constancia;
        header('Location: ./');
        exit;
    }
}

$enlaceInvalido = $token !== '' && !$enlace && !$aceptado;
// Quien ya aceptó otra versión ve qué cambió; un empleado nuevo no (todo es nuevo para él)
$verCambios = $enlace
    ? $enlace['version_anterior'] !== null && $enlace['version_anterior'] !== AVISO_PRIVACIDAD_VERSION
    : !$aceptado;
$verCss   = filemtime(__DIR__ . '/privacidad.css');
$verKoaly = filemtime(__DIR__ . '/../estilos/koaly.css');
// Ancla de cada sección para el índice
$ancla = static fn(int $i): string => 'seccion-' . ($i + 1);
$otroIdioma = $idioma === 'es' ? 'en' : 'es';
$urlIdioma = '?' . http_build_query(['idioma' => $otroIdioma] + ($token !== '' ? ['token' => $token] : []));
?>
<!DOCTYPE html>
<html lang="<?= $e($idioma) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= $e($t('titulo')) ?> | Koaly</title>
    <script>
    // Tema elegido en el login o en un panel (misma clave); si no hay, claro como el login
    try { var tema = localStorage.getItem('koaly-tema'); } catch (e) {}
    document.documentElement.setAttribute('data-tema', tema === 'oscuro' ? 'oscuro' : 'claro');
    </script>
    <link rel="stylesheet" href="privacidad.css?v=<?= $verCss ?>">
    <!-- Colores, fuentes y modo oscuro comunes de Koaly; va después para que gane -->
    <link rel="stylesheet" href="../estilos/koaly.css?v=<?= $verKoaly ?>">
</head>
<body>
<div class="pagina">
<header class="cabecera no-imprimir">
    <a class="marca" href="../">
        <img src="../Imagenes/koala/koala-normal.png" alt="">
        <span>Koaly</span>
    </a>
    <div class="cabecera-acciones">
        <?php if (!$aceptado): // la constancia se queda en el idioma en que se aceptó ?>
            <a class="volver idioma" href="<?= $e($urlIdioma) ?>" hreflang="<?= $otroIdioma ?>" lang="<?= $otroIdioma ?>"
               title="<?= $e($t('idioma.cambiar_ayuda')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
                <?= $e($t('idioma.cambiar')) ?>
            </a>
        <?php endif; ?>
        <a class="volver" href="../"><?= $e($t('ir_login')) ?></a>
    </div>
</header>

<div class="cuerpo">
<nav class="indice no-imprimir" aria-label="<?= $e($t('indice.aria')) ?>">
    <p class="indice-titulo"><?= $e($t('indice.titulo')) ?></p>
    <ol>
        <?php foreach (array_keys($secciones) as $i => $titulo): ?>
            <li><a href="#<?= $ancla($i) ?>"><?= $e($titulo) ?></a></li>
        <?php endforeach; ?>
        <?php if ($enlace): ?>
            <li class="indice-aceptar"><a href="#aceptar"><?= $e($t('aceptar')) ?></a></li>
        <?php endif; ?>
    </ol>
</nav>

<main class="hoja">
    <header class="hoja-cabecera">
        <h1><?= $e($t('h1')) ?></h1>
        <p class="version"><?= $e($t('version', ['fecha' => fechaVersionAviso($idioma)])) ?></p>
    </header>

    <?php if ($aceptado): ?>
        <section class="aviso aviso--ok no-imprimir" role="status">
            <h2><?= $e($t('ok.gracias', ['nombre' => $aceptado['nombre']])) ?></h2>
            <p><?= $e($t('ok.registrado', ['fecha' => $aceptado['fecha']])) ?> <?= $e(!empty($aceptado['primer_ingreso'])
                ? $t('ok.primer_ingreso')
                : $t('ok.puedes_entrar')) ?></p>
            <p><?= $aceptado['correo_enviado']
                ? str_replace('{email}', '<strong>' . $e($aceptado['email']) . '</strong>', $e($t('ok.correo', ['frase' => $t('frase')])))
                : $e($t('ok.sin_correo')) ?></p>
            <a class="boton" href="../?correo=<?= $e(rawurlencode($aceptado['email'])) ?>"><?= $e($t('ir_login')) ?></a>
            <button type="button" class="boton boton--secundario" onclick="window.print()"><?= $e($t('imprimir')) ?></button>
        </section>

        <section class="constancia" aria-labelledby="titulo-constancia">
            <h2 id="titulo-constancia"><?= $e($t('constancia.titulo', ['folio' => $aceptado['folio']])) ?></h2>
            <dl>
                <div><dt><?= $e($t('campo.nombre')) ?></dt><dd><?= $e($aceptado['nombre']) ?></dd></div>
                <div><dt><?= $e($t('campo.correo')) ?></dt><dd><?= $e($aceptado['email']) ?></dd></div>
                <div><dt><?= $e($t('campo.puesto')) ?></dt><dd><?= $e($aceptado['puesto']) ?></dd></div>
                <div><dt><?= $e($t('campo.sucursal')) ?></dt><dd><?= $e($aceptado['sucursal']) ?></dd></div>
                <div><dt><?= $e($t('campo.fecha')) ?></dt><dd><?= $e($aceptado['fecha']) ?></dd></div>
                <div><dt><?= $e($t('campo.ip')) ?></dt><dd><?= $e($aceptado['ip']) ?></dd></div>
                <div><dt><?= $e($t('campo.idioma')) ?></dt><dd><?= $e($t('idioma.nombre')) ?></dd></div>
            </dl>
            <p class="huella"><?= $e($t('huella')) ?> <code><?= $e($aceptado['huella']) ?></code></p>
            <div class="firmas solo-imprimir">
                <p><?= $e($t('firma.empleado')) ?></p>
                <p><?= $e($t('firma.recibe')) ?></p>
            </div>
        </section>
    <?php elseif ($enlaceInvalido): ?>
        <section class="aviso aviso--error" role="alert">
            <h2><?= $e($t('invalido.titulo')) ?></h2>
            <p><?= $e($t('invalido.texto')) ?></p>
            <a class="boton boton--secundario" href="../"><?= $e($t('ir_login')) ?></a>
        </section>
    <?php endif; ?>

    <?php if ($verCambios): ?>
        <section class="cambios no-imprimir" aria-labelledby="titulo-cambios">
            <h2 id="titulo-cambios"><?= $e($t($enlace ? 'cambios.desde' : 'cambios.version')) ?></h2>
            <ul><?php foreach (cambiosAvisoPrivacidad($idioma) as $cambio): ?><li><?= $e($cambio) ?></li><?php endforeach; ?></ul>
        </section>
    <?php endif; ?>

    <?php if ($enlace): ?>
        <section class="ficha" aria-labelledby="titulo-ficha">
            <h2 id="titulo-ficha"><?= $e($t('ficha.titulo')) ?></h2>
            <p class="ficha-ayuda"><?= $e($t('ficha.ayuda')) ?></p>
            <dl>
                <div><dt><?= $e($t('campo.nombre')) ?></dt><dd><?= $e($enlace['nombre']) ?></dd></div>
                <div><dt><?= $e($t('campo.correo_acceso')) ?></dt><dd><?= $e($enlace['email']) ?></dd></div>
                <div><dt><?= $e($t('campo.puesto')) ?></dt><dd><?= $e($puesto) ?></dd></div>
                <div><dt><?= $e($t('campo.sucursal')) ?></dt><dd><?= $e($enlace['sucursal'] ?? '—') ?></dd></div>
                <div><dt><?= $e($t('campo.caja')) ?></dt><dd><?= $e($enlace['cajas'] ?: $t('sin_caja')) ?></dd></div>
                <div><dt><?= $e($t('campo.contratacion')) ?></dt><dd><?= $e($fecha($enlace['fecha_contratacion'])) ?></dd></div>
                <div><dt><?= $e($t('campo.situacion')) ?></dt><dd><?= $e($deBd('estado.' . $enlace['estado'], $enlace['estado'])) ?></dd></div>
            </dl>
        </section>
    <?php endif; ?>

    <article class="texto-aviso">
        <?php $i = 0; foreach ($secciones as $titulo => $parrafos): ?>
            <section id="<?= $ancla($i++) ?>">
                <h2><?= $e($titulo) ?></h2>
                <?php foreach ($parrafos as $p): ?>
                    <?php if (is_string($p)): ?>
                        <p><?= $e($p) ?></p>
                    <?php elseif (array_is_list($p)): ?>
                        <ul><?php foreach ($p as $item): ?><li><?= $e($item) ?></li><?php endforeach; ?></ul>
                    <?php else: ?>
                        <dl class="datos"><?php foreach ($p as $termino => $texto): ?>
                            <?php [$dato, $quienVe] = is_array($texto) ? $texto : [$texto, '']; ?>
                            <div><dt><?= $e($termino) ?></dt><dd><?= $e($dato) ?>
                                <?php if ($quienVe !== ''): ?><span class="quien-ve"><?= $e($t('lo_ven')) ?> <?= $e($quienVe) ?></span><?php endif; ?>
                            </dd></div>
                        <?php endforeach; ?></dl>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </article>

    <?php if ($enlace): ?>
        <form method="POST" class="aceptar" id="aceptar">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <input type="hidden" name="csrf" value="<?= $e($_SESSION['privacidad_csrf']) ?>">
            <input type="hidden" name="idioma" value="<?= $e($idioma) ?>">
            <?php if ($error): ?>
                <p class="aviso aviso--error" role="alert"><?= $e($error) ?></p>
            <?php endif; ?>
            <label class="casilla">
                <input type="checkbox" name="acepto" value="1" required>
                <span><?= $e($t('acepto')) ?></span>
            </label>
            <button type="submit" class="boton"><?= $e($t('aceptar')) ?></button>
            <p class="nota"><?= $e($t('nota')) ?></p>
        </form>
    <?php endif; ?>
</main>
</div>
</div>
</body>
</html>
