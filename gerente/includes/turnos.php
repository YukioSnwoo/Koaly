<?php
/**
 * Turnos de caja (sql/008_turnos_caja.sql) en el panel del gerente.
 * Suma los textos de lang/turnos.php a los del panel sin tocar lang/{es,en}.php.
 * Requiere guardia.php (sesión, $pdo e idioma).
 */

$textosTurnos = require __DIR__ . '/../lang/turnos.php';
$GLOBALS['I18N_ES'] += $textosTurnos['es'];
$GLOBALS['I18N']    += ($textosTurnos[idiomaActual()] ?? []) + $textosTurnos['es'];

/** Monto de un formulario (pesos con hasta 2 decimales, >= 0) en centavos; null si no es válido. */
function montoFormularioEnCentavos(mixed $valor): ?int
{
    $valor = is_string($valor) ? trim($valor) : '';
    if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $valor)) {
        return null;
    }
    return (int) round((float) $valor * 100);
}

/** Diferencia con signo: +$20.00 / −$20.00 / $0.00 */
function dineroConSigno(float|string|null $n): string
{
    $n = (float) $n;
    return ($n > 0 ? '+' : ($n < 0 ? '−' : '')) . dinero(abs($n));
}
