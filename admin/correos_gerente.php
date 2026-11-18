<?php
/**
 * Correo de acceso del gerente (admin/gerentes.php): al darlo de alta y con
 * "Enviar contraseña temporal". Misma lógica que el de los cajeros
 * (gerente/includes/correos_empleado.php):
 *   - la contraseña temporal la genera el sistema (ponerPasswordTemporal() de
 *     login/reglas_contrasena.php) y solo llega aquí: el admin nunca la ve;
 *   - sirve para el primer ingreso, vence a los PASSWORD_TEMPORAL_DIAS días y el
 *     sistema obliga a cambiarla al entrar;
 *   - si aún no acepta su aviso de privacidad, lleva el enlace personal para hacerlo.
 * Va en español (el sistema no guarda el idioma de cada persona) y con el
 * diseño de Koaly (login/correo.php). Si no sale, la cuenta ya quedó: quien
 * llama avisa al admin para que la reenvíe.
 */
require_once __DIR__ . '/../login/correo.php';
require_once __DIR__ . '/../privacidad/aviso.php';

/**
 * @param array  $gerente ['id_usuario', 'nombre', 'email', 'sucursal' (nombre, o null sin asignar)]
 * @param array  $acceso  de ponerPasswordTemporal(): ['password', 'vence' ('Y-m-d H:i:s' o null)]
 * @param string $motivo  'alta' (bienvenida), 'reenvio' (aún no había entrado) o 'restablecida'
 */
function enviarAccesoGerente(PDO $pdo, array $gerente, array $acceso, string $motivo = 'alta'): bool
{
    // El enlace del aviso, solo si todavía no lo acepta (sin la tabla, sql/010, se omite)
    $enlace = null;
    try {
        if (!avisoAceptado($pdo, (int) $gerente['id_usuario'], 2)) {
            $enlace = crearEnlaceAviso($pdo, (int) $gerente['id_usuario'], AVISO_ENLACE_DIAS * 24, 2);
        }
    } catch (PDOException $e) {
        error_log('correos_gerente.php: sin enlace del aviso: ' . $e->getMessage());
    }

    $sucursal = trim((string) ($gerente['sucursal'] ?? ''));
    $vence    = $acceso['vence'] ? date('d/m/Y', strtotime($acceso['vence'])) : null;

    $parrafos = ["Hola, {$gerente['nombre']}:"];
    $parrafos[] = match ($motivo) {
        'reenvio'      => 'Te reenviamos tu acceso a Koaly. Esta contraseña temporal reemplaza a la que te enviamos antes: esa ya no funciona.',
        'restablecida' => 'La administración restableció tu contraseña de Koaly. La anterior ya no funciona.',
        default        => 'Te damos la bienvenida a Koaly. Ya tienes una cuenta de gerente' . ($sucursal !== '' ? " de {$sucursal}" : '') . '.',
    };
    if ($enlace !== null) {
        $parrafos[] = '1. Lee y acepta tu aviso de privacidad. El enlace es personal y vale ' . AVISO_ENLACE_DIAS . ' días; sin aceptarlo no podrás entrar.';
        $parrafos[] = ['boton' => $enlace, 'texto' => 'Leer y aceptar el aviso'];
        $parrafos[] = '2. Entra al sistema con tu correo y esta contraseña temporal:';
    } else {
        $parrafos[] = 'Entra al sistema con tu correo y esta contraseña temporal:';
    }
    $parrafos[] = ['codigo' => $acceso['password']];
    $parrafos[] = ['lista' => ['Dirección: ' . urlSitio(), 'Usuario: ' . $gerente['email']]];
    $parrafos[] = 'La contraseña temporal solo sirve para tu primer ingreso' . ($vence ? " y vence el {$vence}" : '')
        . '. Al entrar, el sistema te pedirá elegir tu propia contraseña.';
    $pie = 'No compartas esta contraseña: nadie de Koaly te la pedirá. Si no esperabas este correo, avísale a la administración.';

    $asunto = match ($motivo) {
        'reenvio'      => 'Tu acceso a Koaly',
        'restablecida' => 'Se restableció tu contraseña de Koaly',
        default        => 'Bienvenida a Koaly: tu acceso de gerente',
    };
    return enviarCorreo($gerente['email'], $asunto, correoTexto($parrafos, $pie), null, correoHtml($parrafos, correoE($pie)));
}
