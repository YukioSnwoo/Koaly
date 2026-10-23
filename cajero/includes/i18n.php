<?php
/**
 * Idiomas de la caja. Archivo independiente: no depende del panel del gerente.
 *
 * El idioma es uno solo en todo el sitio (login, gerente y admin): lo guarda
 * admin/preferencia_idioma.php en una cookie que sobrevive al cerrar sesión.
 * Los textos están en cajero/lang/{es,en}.php (mismas claves en ambos).
 * Todos llegan también al JavaScript con textosCajaParaJs().
 */

require_once __DIR__ . '/../../admin/preferencia_idioma.php';

const IDIOMAS = [
    'es' => 'Español',
    'en' => 'English',
];

$idiomaCaja = idiomaGuardado(array_keys(IDIOMAS));

$GLOBALS['CAJA_IDIOMA']   = $idiomaCaja;
$GLOBALS['CAJA_TEXTOS_ES'] = require __DIR__ . '/../lang/es.php';
$GLOBALS['CAJA_TEXTOS']    = $idiomaCaja === 'es'
    ? $GLOBALS['CAJA_TEXTOS_ES']
    : (require __DIR__ . "/../lang/{$idiomaCaja}.php") + $GLOBALS['CAJA_TEXTOS_ES'];

/** Reemplaza {nombre} por los valores de $vars. */
function i18nReemplazar(string $texto, array $vars): string
{
    foreach ($vars as $k => $v) {
        $texto = str_replace('{' . $k . '}', (string) $v, $texto);
    }
    return $texto;
}

/** Texto traducido. Si falta en el idioma actual usa el español; si falta en ambos, la clave. */
function t(string $clave, array $vars = []): string
{
    $texto = $GLOBALS['CAJA_TEXTOS'][$clave] ?? $clave;
    return i18nReemplazar(is_array($texto) ? $texto[1] : $texto, $vars);
}

function idiomaActual(): string
{
    return $GLOBALS['CAJA_IDIOMA'];
}

/** Importe en pesos con separador de miles: $1,249.90 */
function dinero(float|int|string|null $n): string
{
    return '$' . number_format((float) $n, 2, '.', ',');
}

/** Atajo de htmlspecialchars para la vista. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Todos los textos de la caja para el JavaScript (con respaldo en español). */
function textosCajaParaJs(): string
{
    return json_encode($GLOBALS['CAJA_TEXTOS'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}
