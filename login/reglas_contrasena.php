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
