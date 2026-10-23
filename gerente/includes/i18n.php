<?php
/**
 * Idiomas del panel del gerente.
 *
 * El idioma es uno solo en todo el sitio (login, admin y caja): lo guarda
 * admin/preferencia_idioma.php en una cookie que sobrevive al cerrar sesión.
 * Los textos están en gerente/lang/{es,en}.php. Para agregar un idioma:
 * crear lang/xx.php con las mismas claves y añadirlo a IDIOMAS.
 *
 * Los textos que se GUARDAN en la base de datos (motivos del kardex) se
 * escriben siempre en español con tEs(): son registros, no interfaz.
 */

require_once __DIR__ . '/../../admin/preferencia_idioma.php';

const IDIOMAS = [
    'es' => 'Español',
    'en' => 'English',
];

$idioma = idiomaGuardado(array_keys(IDIOMAS));

$GLOBALS['I18N_IDIOMA'] = $idioma;
$GLOBALS['I18N_ES']     = require __DIR__ . '/../lang/es.php';
$GLOBALS['I18N']        = $idioma === 'es' ? $GLOBALS['I18N_ES'] : require __DIR__ . "/../lang/{$idioma}.php";

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
    return i18nReemplazar(abs($n) === 1 ? $formas[0] : $formas[1], ['n' => numero($n)] + $vars);
}

/** Texto en español, para lo que se guarda en la base de datos. */
function tEs(string $clave, array $vars = []): string
{
    return i18nReemplazar((string) ($GLOBALS['I18N_ES'][$clave] ?? $clave), $vars);
}

/**
 * Valor de un catálogo de la base de datos (método de pago, categoría) en el
 * idioma actual. Busca la clave "{grupo}.{valor}" (p. ej. metodo_pago.Efectivo);
 * si el admin agregó un valor que aún no tiene traducción, lo muestra tal cual.
 */
function tValor(string $grupo, ?string $valor, string $siVacio = ''): string
{
    if ($valor === null || $valor === '') {
        return $siVacio;
    }
    $clave = $grupo . '.' . $valor;
    $texto = $GLOBALS['I18N'][$clave] ?? $GLOBALS['I18N_ES'][$clave] ?? $valor;
    return is_string($texto) ? $texto : $valor;
}

function idiomaActual(): string
{
    return $GLOBALS['I18N_IDIOMA'];
}

/** Número con separador de miles (1,234.5). */
function numero(float|int $n, int $decimales = 0): string
{
    return number_format($n, $decimales, '.', ',');
}

/** Importe en pesos. */
function dinero(float|int|string|null $n): string
{
    $n = (float) $n;
    return '$' . numero($n, 2);
}

/** Fecha (y hora) de un valor de la base de datos o DateTimeInterface. */
function fecha(string|DateTimeInterface $valor, bool $conHora = true): string
{
    $ts = $valor instanceof DateTimeInterface ? $valor->getTimestamp() : strtotime($valor);
    $formato = idiomaActual() === 'en' ? 'm/d/Y' : 'd/m/Y';
    return date($formato . ($conHora ? ' H:i' : ''), $ts);
}

/** Atajo de htmlspecialchars para la vista. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Textos que necesita el JavaScript, como JSON para footer.php. */
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
