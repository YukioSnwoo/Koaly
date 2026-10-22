<?php
/**
 * Aviso de privacidad para empleados.
 *
 *   privacidad/              el aviso vigente (público, para consultarlo cuando sea)
 *   privacidad/?token=...    enlace personal: muestra los datos del empleado y
 *                            le pide aceptar el aviso. El enlace sirve una sola vez.
 *
 * Al aceptar se guarda la constancia (fecha, versión, IP y navegador) en
 * avisos_privacidad. Un cajero no puede iniciar sesión sin aceptar la versión vigente.
 */
session_start();
header('Cache-Control: no-store');
require_once __DIR__ . '/../admin/database.php';
require_once __DIR__ . '/aviso.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Hora de México, como el panel del gerente (las fechas de la tabla son TIMESTAMP)
date_default_timezone_set('America/Mexico_City');
$pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");

$e = static fn(?string $t): string => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
$fecha = static fn(?string $f, bool $hora = false): string => $f ? date($hora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($f)) : '—';

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
               u.nombre, u.email, u.estado, u.fecha_contratacion, r.nombre_rol,
               s.nombre AS sucursal, s.direccion, s.email_contacto,
               (SELECT GROUP_CONCAT(CONCAT_WS(' · ', c.numero_caja, c.nombre) SEPARATOR ', ')
                FROM Cajas c WHERE c.id_cajero_asignado = u.id_usuario) AS cajas,
               (SELECT g.email FROM Usuarios g
                WHERE g.id_sucursal = u.id_sucursal AND g.id_rol = 2 AND g.estado = 'Activo' LIMIT 1) AS gerente_email
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
$aceptado = $_SESSION['privacidad_aceptado'] ?? null; // PRG: nombre y fecha tras aceptar
unset($_SESSION['privacidad_aceptado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['privacidad_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'El formulario expiró. Vuelve a cargar la página.';
    } elseif (!$enlace) {
        $error = 'El enlace ya no es válido.';
    } elseif (($_POST['acepto'] ?? '') !== '1') {
        $error = 'Marca la casilla para confirmar que leíste y aceptas el aviso.';
    } else {
        // Constancia de la aceptación. El token se borra: el enlace ya no sirve.
        $pdo->prepare("
            UPDATE avisos_privacidad
            SET aceptado_en = NOW(), version = ?, ip = ?, agente = ?, token_hash = NULL
            WHERE id = ? AND aceptado_en IS NULL
        ")->execute([
            AVISO_PRIVACIDAD_VERSION,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $enlace['id'],
        ]);
        $_SESSION['privacidad_aceptado'] = ['nombre' => $enlace['nombre'], 'fecha' => date('d/m/Y H:i')];
        header('Location: ./');
        exit;
    }
}

$contacto = $enlace
    ? (trim((string) $enlace['email_contacto']) ?: ($enlace['gerente_email'] ?: 'el correo de contacto de tu sucursal'))
    : 'el correo de contacto de tu sucursal';
$sucursalAviso = $enlace ? ['nombre' => $enlace['sucursal'], 'direccion' => $enlace['direccion']] : null;
$secciones = seccionesAvisoPrivacidad($enlace && $enlace['sucursal'] ? $sucursalAviso : null, $contacto);
$enlaceInvalido = $token !== '' && !$enlace && !$aceptado;
$verCss = filemtime(__DIR__ . '/privacidad.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Aviso de privacidad | Koaly</title>
    <link rel="stylesheet" href="privacidad.css?v=<?= $verCss ?>">
</head>
<body>
<main class="hoja">
    <header class="hoja-cabecera">
        <span class="marca">Koaly</span>
        <h1>Aviso de privacidad para empleados</h1>
        <p class="version">Versión vigente del <?= $e(fechaVersionAviso()) ?></p>
    </header>

    <?php if ($aceptado): ?>
        <section class="aviso aviso--ok" role="status">
            <h2>¡Gracias, <?= $e($aceptado['nombre']) ?>!</h2>
            <p>Registramos tu aceptación el <?= $e($aceptado['fecha']) ?>. Ya puedes iniciar sesión en el sistema.</p>
            <a class="boton" href="../">Ir a iniciar sesión</a>
        </section>
    <?php elseif ($enlaceInvalido): ?>
        <section class="aviso aviso--error" role="alert">
            <h2>Este enlace ya no es válido</h2>
            <p>Puede que ya lo hayas usado o que haya vencido. Inicia sesión y el sistema te dará uno nuevo,
               o pide a tu gerente que te lo reenvíe. Abajo puedes leer el aviso vigente.</p>
            <a class="boton boton--secundario" href="../">Ir a iniciar sesión</a>
        </section>
    <?php endif; ?>

    <?php if ($enlace): ?>
        <section class="ficha" aria-labelledby="titulo-ficha">
            <h2 id="titulo-ficha">Tus datos en el sistema</h2>
            <p class="ficha-ayuda">Revisa que sean correctos. Si algo está mal, avísale a tu gerente.</p>
            <dl>
                <div><dt>Nombre</dt><dd><?= $e($enlace['nombre']) ?></dd></div>
                <div><dt>Correo de acceso</dt><dd><?= $e($enlace['email']) ?></dd></div>
                <div><dt>Puesto</dt><dd><?= $e($enlace['nombre_rol']) ?></dd></div>
                <div><dt>Sucursal</dt><dd><?= $e($enlace['sucursal'] ?? '—') ?></dd></div>
                <div><dt>Caja asignada</dt><dd><?= $e($enlace['cajas'] ?: 'Sin caja asignada') ?></dd></div>
                <div><dt>Fecha de contratación</dt><dd><?= $e($fecha($enlace['fecha_contratacion'])) ?></dd></div>
                <div><dt>Situación</dt><dd><?= $e($enlace['estado']) ?></dd></div>
            </dl>
        </section>
    <?php endif; ?>

    <article class="texto-aviso">
        <?php foreach ($secciones as $titulo => $parrafos): ?>
            <section>
                <h2><?= $e($titulo) ?></h2>
                <?php foreach ($parrafos as $p): ?>
                    <p><?= $e($p) ?></p>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </article>

    <?php if ($enlace): ?>
        <form method="POST" class="aceptar" id="aceptar">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <input type="hidden" name="csrf" value="<?= $e($_SESSION['privacidad_csrf']) ?>">
            <?php if ($error): ?>
                <p class="aviso aviso--error" role="alert"><?= $e($error) ?></p>
            <?php endif; ?>
            <label class="casilla">
                <input type="checkbox" name="acepto" value="1" required>
                <span>Leí el aviso de privacidad y acepto el tratamiento de mis datos personales en los términos descritos.</span>
            </label>
            <button type="submit" class="boton">Aceptar el aviso</button>
            <p class="nota">Necesitas aceptarlo para usar el sistema de caja. Guardaremos la fecha, la versión del aviso y la dirección IP como constancia.</p>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
