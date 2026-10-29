<?php
/**
 * Turnos de caja (sql/008_turnos_caja.sql) en el panel del gerente.
 * Suma los textos de lang/turnos.php a los del panel sin tocar lang/{es,en}.php.
 * Requiere guardia.php (sesión, $pdo e idioma).
 */

$textosTurnos = require __DIR__ . '/../lang/turnos.php';
$GLOBALS['I18N_ES'] += $textosTurnos['es'];
$GLOBALS['I18N']    += ($textosTurnos[idiomaActual()] ?? []) + $textosTurnos['es'];

/**
 * Número de la caja con turno abierto que bloquea un cambio, o null si no hay.
 * Con $idCaja revisa esa caja; con $idCajero, la caja asignada a ese cajero o
 * la que él abrió. Mientras un turno está abierto hay dinero sin cortar: no se
 * desactiva, reasigna ni borra la caja, ni se aparta al cajero, hasta el corte.
 */
function cajaConTurnoAbierto(PDO $pdo, int $idSucursal, ?int $idCaja = null, ?int $idCajero = null): ?string
{
    $stmt = $pdo->prepare("
        SELECT c.numero_caja
        FROM Turnos_Caja t
        INNER JOIN Cajas c ON c.id_caja = t.id_caja
        WHERE t.estado = 'Abierto' AND t.id_sucursal = ?
          AND (t.id_caja = ? OR c.id_cajero_asignado = ? OR t.id_cajero_apertura = ?)
        LIMIT 1
    ");
    $stmt->execute([$idSucursal, $idCaja ?? 0, $idCajero ?? 0, $idCajero ?? 0]);
    $numero = $stmt->fetchColumn();
    return $numero === false ? null : (string) $numero;
}

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
