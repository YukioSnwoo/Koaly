<?php
/**
 * Idiomas del inicio de sesión: index.php, login.js y login.php.
 *
 * El idioma es el de todo el sitio (admin/preferencia_idioma.php). En el login
 * se cambia con el botón de la esquina izquierda sin recargar: login.js cambia
 * los textos y escribe la cookie, y los paneles la leen al entrar.
 * Textos en login/lang/{es,en}.php (mismas claves en ambos).
 */

require_once __DIR__ . '/../admin/preferencia_idioma.php';

const IDIOMAS_LOGIN = ['es' => 'Español', 'en' => 'English'];

$GLOBALS['LOGIN_IDIOMA'] = idiomaGuardado(array_keys(IDIOMAS_LOGIN));
$GLOBALS['LOGIN_TEXTOS'] = [];
foreach (array_keys(IDIOMAS_LOGIN) as $codigo) {
    $GLOBALS['LOGIN_TEXTOS'][$codigo] = require __DIR__ . "/lang/{$codigo}.php";
}

/** Texto en el idioma elegido (si falta, en español); {nombre} se reemplaza con $vars. */
function t(string $clave, array $vars = []): string
{
    $textos = $GLOBALS['LOGIN_TEXTOS'];
    $texto = $textos[$GLOBALS['LOGIN_IDIOMA']][$clave] ?? $textos['es'][$clave] ?? $clave;
    foreach ($vars as $k => $v) {
        $texto = str_replace('{' . $k . '}', (string) $v, $texto);
    }
    return $texto;
}

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

/** Todos los idiomas para login.js: así cambia de idioma sin recargar. */
function textosLoginParaJs(): string
{
    return json_encode($GLOBALS['LOGIN_TEXTOS'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}
