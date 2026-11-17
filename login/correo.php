<?php
/**
 * Envío de correo del sitio (recuperación de contraseña, avisos al empleado,
 * constancias del aviso de privacidad).
 *
 * Usa SMTP con la cuenta de login/config_correo.php (un buzón de Hostinger del
 * dominio o Gmail con una contraseña de aplicación). Si ese archivo no existe,
 * intenta con mail() del servidor, que en Hostinger solo funciona con un buzón
 * del propio dominio.
 *
 * Es un cliente SMTP mínimo (SSL implícito, AUTH LOGIN, UTF-8) para no
 * depender de librerías externas. Los correos van en texto plano; con $html
 * llevan además una versión HTML (multipart/alternative) y el lector de
 * correo muestra la que pueda.
 */

/** Datos de login/config_correo.php, o null si no existe o está incompleto. */
function configCorreo(): ?array
{
    static $cfg = false;
    if ($cfg === false) {
        $archivo = __DIR__ . '/config_correo.php';
        $leido = is_file($archivo) ? require $archivo : null;
        $cfg = is_array($leido) && !empty($leido['usuario']) && !empty($leido['contrasena']) ? $leido : null;
    }
    return $cfg;
}

/**
 * Cabeceras de contenido y cuerpo del mensaje: solo texto, o texto + HTML.
 * En base64 ninguna línea empieza con "." y no hay que escapar nada para SMTP.
 * @return array{0: string[], 1: string}
 */
function cuerpoCorreo(string $texto, ?string $html): array
{
    $base64 = fn(string $s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
    if ($html === null) {
        return [['Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64'], $base64($texto)];
    }
    $limite = 'koaly-' . bin2hex(random_bytes(12));
    $partes = [];
    foreach (['text/plain' => $texto, 'text/html' => $html] as $tipo => $contenido) {
        $partes[] = "--{$limite}\r\nContent-Type: {$tipo}; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $base64($contenido);
    }
    return [["Content-Type: multipart/alternative; boundary=\"{$limite}\""], implode("\r\n", $partes) . "\r\n--{$limite}--"];
}

/**
 * @param string|null $responderA a dónde llegan las respuestas (Reply-To); sin él, al remitente
 * @param string|null $html       versión HTML del mismo mensaje; $texto queda para quien no la vea
 */
function enviarCorreo(string $para, string $asunto, string $texto, ?string $responderA = null, ?string $html = null): bool
{
    $cfg = configCorreo();
    $responderA = $responderA ?: (($cfg['responder_a'] ?? '') ?: null);
    if ($responderA !== null && (preg_match('/[\r\n]/', $responderA) || !filter_var($responderA, FILTER_VALIDATE_EMAIL))) {
        $responderA = null;
    }

    if ($cfg === null) {
        $responder = $responderA ? "Reply-To: <{$responderA}>\r\n" : '';
        if ($html === null) {
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n" . $responder;
            return @mail($para, mb_encode_mimeheader($asunto, 'UTF-8'), $texto, $headers);
        }
        [$cabeceras, $cuerpo] = cuerpoCorreo($texto, $html);
        $headers = "MIME-Version: 1.0\r\n" . implode("\r\n", $cabeceras) . "\r\n" . $responder;
        return @mail($para, mb_encode_mimeheader($asunto, 'UTF-8'), $cuerpo, $headers);
    }

    try {
        smtpEnviar($cfg, $para, $asunto, $texto, $responderA, $html);
        return true;
    } catch (RuntimeException $e) {
        // Nunca se registra la contraseña: los mensajes solo traen la respuesta del servidor
        error_log('correo.php: ' . $e->getMessage());
        return false;
    }
}

/** @throws RuntimeException si la conexión o algún paso del diálogo SMTP falla */
function smtpEnviar(array $cfg, string $para, string $asunto, string $texto, ?string $responderA = null, ?string $html = null): void
{
    // Evita inyectar cabeceras o comandos SMTP con saltos de línea
    if (preg_match('/[\r\n]/', $para . $asunto) || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('destinatario o asunto inválido');
    }

    $host      = $cfg['host'] ?? 'smtp.gmail.com';
    $puerto    = (int) ($cfg['puerto'] ?? 465);
    $usuario   = $cfg['usuario'];
    $remitente = $cfg['nombre_remitente'] ?? 'Koaly';

    $conexion = @stream_socket_client("ssl://{$host}:{$puerto}", $errno, $error, 10);
    if (!$conexion) {
        throw new RuntimeException("no se pudo conectar a {$host}:{$puerto} ({$error})");
    }
    stream_set_timeout($conexion, 10);

    $comando = function (?string $linea, array $esperados) use ($conexion): void {
        if ($linea !== null) {
            fwrite($conexion, $linea . "\r\n");
        }
        // Respuestas de varias líneas: "250-..." continúa, "250 ..." termina
        $respuesta = '';
        while (($renglon = fgets($conexion, 515)) !== false) {
            $respuesta .= $renglon;
            if (strlen($renglon) < 4 || $renglon[3] === ' ') {
                break;
            }
        }
        if (!in_array((int) substr($respuesta, 0, 3), $esperados, true)) {
            throw new RuntimeException('SMTP respondió: ' . trim($respuesta ?: 'sin respuesta'));
        }
    };

    try {
        $comando(null, [220]);
        $comando('EHLO ' . (gethostname() ?: 'localhost'), [250]);
        $comando('AUTH LOGIN', [334]);
        $comando(base64_encode($usuario), [334]);
        $comando(base64_encode($cfg['contrasena']), [235]);
        $comando("MAIL FROM:<{$usuario}>", [250]);
        $comando("RCPT TO:<{$para}>", [250, 251]);
        $comando('DATA', [354]);

        [$cabeceras, $cuerpo] = cuerpoCorreo($texto, $html);
        $mensaje = implode("\r\n", [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . mb_encode_mimeheader($remitente, 'UTF-8') . " <{$usuario}>",
            "To: <{$para}>",
            ...($responderA ? ["Reply-To: <{$responderA}>"] : []),
            'Subject: ' . mb_encode_mimeheader($asunto, 'UTF-8'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($usuario, '@'), 1) . '>',
            'MIME-Version: 1.0',
            ...$cabeceras,
            '',
            $cuerpo,
        ]);
        $comando($mensaje . "\r\n.", [250]);
        $comando('QUIT', [221]);
    } finally {
        fclose($conexion);
    }
}

/* ------------------------------------------------------------
   Diseño de los correos (versión HTML). Los estilos van en línea:
   los lectores de correo ignoran <style> y las hojas externas.
   Colores fijos del modo claro de estilos/koaly.css.
   ------------------------------------------------------------ */

function correoE(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

/** Enlace con el color de Koaly, para un párrafo o el pie. */
function correoEnlace(string $url, string $texto): string
{
    return '<a href="' . correoE($url) . '" style="color:#6d28d9;">' . correoE($texto) . '</a>';
}

/**
 * Correo con el diseño de Koaly: tarjeta blanca con borde, "Koaly" en morado,
 * los párrafos y, al final, un pie en letra chica separado por una línea.
 * Cada párrafo es texto (aquí se escapa) o ['codigo' => '123456'], que sale
 * en grande y centrado. $pieHtml ya viene escapado: puede llevar un enlace.
 */
function correoHtml(array $parrafos, ?string $pieHtml = null): string
{
    $cuerpo = '';
    foreach ($parrafos as $p) {
        $cuerpo .= is_array($p)
            ? '<p style="margin:4px 0 18px;text-align:center;"><span style="display:inline-block;padding:12px 22px;'
                . 'border:2px solid #3b3156;border-radius:12px;background:#efe9fd;box-shadow:3px 3px 0 #3b3156;'
                . 'font-family:\'Courier New\',Courier,monospace;font-size:30px;font-weight:bold;letter-spacing:8px;color:#221c33;">'
                . correoE($p['codigo']) . '</span></p>'
            : '<p style="margin:0 0 14px;font-size:15px;line-height:1.5;">' . correoE($p) . '</p>';
    }
    $pie = $pieHtml === null ? '' : '<p style="margin:22px 0 0;padding-top:14px;border-top:1px solid #e2dbef;'
        . 'font-size:12px;line-height:1.5;color:#645c7d;">' . $pieHtml . '</p>';

    return '<!doctype html><html><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:24px 12px;background:#f4f1fa;">'
        . '<div style="max-width:480px;margin:0 auto;padding:24px;background:#ffffff;border:2px solid #3b3156;border-radius:14px;'
        . 'font-family:Arial,Helvetica,sans-serif;color:#221c33;">'
        . '<p style="margin:0 0 18px;font-size:20px;font-weight:bold;color:#6d28d9;">Koaly</p>'
        . $cuerpo . $pie
        . '</div></body></html>';
}

/** Los mismos párrafos en texto plano, para quien no vea el HTML (el código va sangrado). */
function correoTexto(array $parrafos, ?string $pie = null): string
{
    $bloques = array_map(fn($p) => is_array($p) ? '    ' . $p['codigo'] : $p, $parrafos);
    if ($pie !== null) {
        $bloques[] = $pie;
    }
    return implode("\n\n", $bloques) . "\n";
}

/**
 * Aviso de que la contraseña cambió, con "Si no fuiste tú, haz clic aquí" en
 * el pie: abre la recuperación con el correo puesto (index.php?recuperar=).
 * Lo usan Mi perfil y la recuperación con código. $textos trae, ya traducidos:
 * asunto, saludo, cuerpo, si_fuiste, no_fuiste (con {enlace}), aqui y no_fuiste_texto.
 */
function enviarAvisoContrasenaCambiada(string $para, string $correoCuenta, array $textos): bool
{
    require_once __DIR__ . '/../privacidad/aviso.php';   // urlSitio()
    $enlace   = urlSitio() . 'index.php?recuperar=' . rawurlencode($correoCuenta);
    $parrafos = [$textos['saludo'], $textos['cuerpo'], $textos['si_fuiste']];
    $pieHtml  = str_replace('{enlace}', correoEnlace($enlace, $textos['aqui']), correoE($textos['no_fuiste']));
    $pieTexto = $textos['no_fuiste_texto'] . "\n" . $enlace;

    return enviarCorreo($para, $textos['asunto'], correoTexto($parrafos, $pieTexto), null, correoHtml($parrafos, $pieHtml));
}
