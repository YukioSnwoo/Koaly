<?php
/**
 * Idiomas del panel del admin.
 *
 * El idioma es uno solo en todo el sitio (login, gerente y caja): lo guarda
 * preferencia_idioma.php en una cookie que sobrevive al cerrar sesión.
 * Los textos están en admin/lang/{es,en}.php (mismas claves en ambos).
 * Requiere que la sesión ya esté iniciada (header.php lo hace).
 */

require_once __DIR__ . '/preferencia_idioma.php';

const IDIOMAS = [
    'es' => 'Español',
    'en' => 'English',
];

$idiomaAdmin = idiomaGuardado(array_keys(IDIOMAS));

$GLOBALS['I18N_IDIOMA'] = $idiomaAdmin;
$GLOBALS['I18N_ES']     = require __DIR__ . '/lang/es.php';
$GLOBALS['I18N']        = $idiomaAdmin === 'es' ? $GLOBALS['I18N_ES'] : require __DIR__ . "/lang/{$idiomaAdmin}.php";

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
    $texto = $GLOBALS['I18N'][$clave] ?? $GLOBALS['I18N_ES'][$clave] ?? $clave;
    return i18nReemplazar(is_array($texto) ? $texto[1] : $texto, $vars);
}

/** Texto con plural. La traducción es [singular, plural]; {n} es la cantidad. */
function tn(string $clave, int $n, array $vars = []): string
{
    $formas = $GLOBALS['I18N'][$clave] ?? $GLOBALS['I18N_ES'][$clave] ?? [$clave, $clave];
    return i18nReemplazar(abs($n) === 1 ? $formas[0] : $formas[1], ['n' => number_format($n)] + $vars);
}

/**
 * Valor de un catálogo de la base de datos (categoría, método de pago, estado)
 * en el idioma actual. Busca la clave "{grupo}.{valor}"; si no hay traducción
 * (p. ej. una categoría nueva), lo muestra tal cual.
 */
function tValor(string $grupo, ?string $valor, string $siVacio = ''): string
{
    if ($valor === null || $valor === '') {
        return $siVacio;
    }
    $texto = $GLOBALS['I18N'][$grupo . '.' . $valor] ?? $GLOBALS['I18N_ES'][$grupo . '.' . $valor] ?? $valor;
    return is_string($texto) ? $texto : $valor;
}

function idiomaActual(): string
{
    return $GLOBALS['I18N_IDIOMA'];
}

/** Fecha (y hora) de un valor de la base de datos: 21/10/2026 en español, 10/21/2026 en inglés. */
function fecha(string|int $valor, bool $conHora = true): string
{
    $ts = is_int($valor) ? $valor : strtotime($valor);
    $formato = idiomaActual() === 'en' ? 'm/d/Y' : 'd/m/Y';
    return date($formato . ($conHora ? ' H:i' : ''), $ts);
}

/** Atajo de htmlspecialchars para la vista. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Textos que necesita el JavaScript (claves "js.*"), como JSON para header.php. */
function i18nParaJs(): string
{
    $js = [];
    foreach ($GLOBALS['I18N_ES'] as $clave => $_) {
        if (str_starts_with($clave, 'js.')) {
            $js[substr($clave, 3)] = t($clave);
        }
    }
    return json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}
