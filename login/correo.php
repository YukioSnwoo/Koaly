<?php
/**
 * Envío de correo para la recuperación de contraseña.
 *
 * Usa SMTP con la cuenta de login/config_correo.php (por ejemplo Gmail con una
 * contraseña de aplicación). Si ese archivo no existe, intenta con mail() del
 * servidor, que en Hostinger solo funciona con un buzón del propio dominio.
 *
 * Es un cliente SMTP mínimo (SSL implícito, AUTH LOGIN, texto plano en UTF-8)
 * para no depender de librerías externas.
 */

function enviarCorreo(string $para, string $asunto, string $texto): bool
{
    $archivo = __DIR__ . '/config_correo.php';
    $cfg = is_file($archivo) ? require $archivo : null;

    if (!is_array($cfg) || empty($cfg['usuario']) || empty($cfg['contrasena'])) {
        $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
        return @mail($para, mb_encode_mimeheader($asunto, 'UTF-8'), $texto, $headers);
    }

    try {
        smtpEnviar($cfg, $para, $asunto, $texto);
        return true;
    } catch (RuntimeException $e) {
        // Nunca se registra la contraseña: los mensajes solo traen la respuesta del servidor
        error_log('correo.php: ' . $e->getMessage());
        return false;
    }
}

/** @throws RuntimeException si la conexión o algún paso del diálogo SMTP falla */
function smtpEnviar(array $cfg, string $para, string $asunto, string $texto): void
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

        $mensaje = implode("\r\n", [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . mb_encode_mimeheader($remitente, 'UTF-8') . " <{$usuario}>",
            "To: <{$para}>",
            'Subject: ' . mb_encode_mimeheader($asunto, 'UTF-8'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($usuario, '@'), 1) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            // En base64 ninguna línea empieza con "." y no hay que escapar el cuerpo
            rtrim(chunk_split(base64_encode($texto), 76, "\r\n")),
        ]);
        $comando($mensaje . "\r\n.", [250]);
        $comando('QUIT', [221]);
    } finally {
        fclose($conexion);
    }
}
