<?php
/**
 * Reglas de contraseña: las usan el inicio de sesión (login.php) y el menú de
 * perfil (perfil/guardar.php). login.js aplica las mismas en el navegador.
 */

/** Motivo por el que la contraseña no se acepta, o null si es válida. login.js aplica las mismas reglas. */
function passwordIsTooSimple(string $password, string $email, ?string $nombre = null): ?string
{
    if (strlen($password) < 8) {
        return 'La contraseña debe tener mínimo 8 caracteres';
    }
    if (preg_match('/^[a-zA-Z]+$/', $password)) {
        return 'La contraseña no puede ser solo letras';
    }
    if (preg_match('/^[0-9]+$/', $password)) {
        return 'La contraseña no puede ser solo números';
    }
    if (preg_match('/^[^a-zA-Z0-9]+$/', $password)) {
        return 'La contraseña no puede ser solo símbolos';
    }

    $common = [
        '12345678', 'password', '123456789', 'qwerty', 'abc123',
        'password123', 'admin', 'letmein', 'welcome', '12345',
        '1234567890', 'qwerty123', '1q2w3e4r', 'iloveyou', 'monkey'
    ];
    $passwordLower = mb_strtolower($password);
    if (in_array($passwordLower, $common, true)) {
        return 'La contraseña es demasiado común';
    }

    // Palabras del nombre de 3 letras o más (un nombre vacío o "de" no debe bloquear todo)
    if ($nombre) {
        foreach (preg_split('/\s+/', mb_strtolower(trim($nombre))) as $parte) {
            if (mb_strlen($parte) >= 3 && str_contains($passwordLower, $parte)) {
                return 'La contraseña no puede incluir tu nombre';
            }
        }
    }

    $local = mb_strtolower(explode('@', $email)[0] ?? '');
    if (mb_strlen($local) >= 3 && str_contains($passwordLower, $local)) {
        return 'La contraseña no puede incluir tu correo';
    }

    return null;
}

/* ------------------------------------------------------------
 * Contraseña temporal: la genera el sistema y solo llega por correo
 * (alta y restablecimiento de cajeros); el gerente nunca la ve.
 * Sirve para el primer ingreso y vence (sql/013_contrasena_temporal.sql).
 * ------------------------------------------------------------ */
const PASSWORD_TEMPORAL_DIAS = 7;

/** 10 caracteres sin letras ni números que se confundan (0/O, 1/l/I). */
function generarPasswordTemporal(): string
{
    $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $temporal = '';
    for ($k = 0; $k < 10; $k++) {
        $temporal .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return $temporal;
}

/**
 * Le pone al usuario una contraseña temporal nueva (la anterior deja de servir)
 * y lo obliga a cambiarla al entrar.
 * @return array{password: string, vence: ?string} la contraseña en claro, solo para el
 *         correo, y su vencimiento ('Y-m-d H:i:s', o null si falta la columna)
 */
function ponerPasswordTemporal(PDO $pdo, int $idUsuario): array
{
    $temporal = generarPasswordTemporal();
    $hash     = password_hash($temporal, PASSWORD_DEFAULT);
    try {
        $pdo->prepare("UPDATE Usuarios SET contrasena_hash = ?, debe_cambiar = 1,
                              temporal_expira_en = NOW() + INTERVAL " . PASSWORD_TEMPORAL_DIAS . " DAY
                       WHERE id_usuario = ?")
            ->execute([$hash, $idUsuario]);
        $stmt = $pdo->prepare("SELECT temporal_expira_en FROM Usuarios WHERE id_usuario = ?");
        $stmt->execute([$idUsuario]);
        $vence = $stmt->fetchColumn() ?: null;
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S22') throw $e;
        // Sin la columna (falta sql/013) la temporal no vence, pero sí funciona
        error_log('reglas_contrasena.php: falta Usuarios.temporal_expira_en (sql/013_contrasena_temporal.sql)');
        $pdo->prepare("UPDATE Usuarios SET contrasena_hash = ?, debe_cambiar = 1 WHERE id_usuario = ?")
            ->execute([$hash, $idUsuario]);
        $vence = null;
    }
    return ['password' => $temporal, 'vence' => $vence];
}

/** ¿Venció la contraseña temporal? Sin la columna (falta sql/013) nunca vence. */
function passwordTemporalVencida(PDO $pdo, int $idUsuario): bool
{
    try {
        $stmt = $pdo->prepare("SELECT debe_cambiar = 1 AND temporal_expira_en < NOW() FROM Usuarios WHERE id_usuario = ?");
        $stmt->execute([$idUsuario]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S22') throw $e;
        return false;
    }
}
