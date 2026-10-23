<?php
/**
 * Idioma elegido: uno solo en todo el sitio (login, admin, gerente y caja),
 * igual que el modo claro/oscuro. Se guarda en la cookie koaly_idioma, que
 * sobrevive al cerrar sesión, y en $_SESSION['idioma'] por si la cookie está
 * bloqueada. El login la escribe desde el navegador (login/login.js); los
 * paneles, con guardarIdioma().
 */

const COOKIE_IDIOMA = 'koaly_idioma';

/** Idioma guardado si está entre $validos; si no, español. Manda la cookie: es la elección más reciente. */
function idiomaGuardado(array $validos): string
{
    foreach ([$_COOKIE[COOKIE_IDIOMA] ?? null, $_SESSION['idioma'] ?? null] as $idioma) {
        if (in_array($idioma, $validos, true)) {
            return $idioma;
        }
    }
    return 'es';
}

/** Guarda el idioma en la sesión y en la cookie (un año, para todo el sitio). Antes de mandar cualquier salida. */
function guardarIdioma(string $idioma): void
{
    $_SESSION['idioma'] = $idioma;
    $_COOKIE[COOKIE_IDIOMA] = $idioma;
    setcookie(COOKIE_IDIOMA, $idioma, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax']);
}
