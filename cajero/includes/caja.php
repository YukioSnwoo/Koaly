<?php
/**
 * Caja y turno del cajero (sql/008_turnos_caja.sql).
 * Lo usan registrar_venta.php y los endpoints turno_*.php.
 */

/** No se puede vender: el cajero no tiene caja asignada o su caja está cerrada. */
class CajaCerradaException extends DomainException
{
}

/** No alcanza el efectivo de la caja para dar el cambio. */
class SinCambioException extends DomainException
{
}

// Diferencias (en el corte o al abrir) de más de $50 se marcan para el gerente
const UMBRAL_REVISION_CENTAVOS = 5000;

// Piezas que se cuentan en la caja, con los mismos ids que la ventana de Corte
const DENOMINACIONES_CENTAVOS = [
    'b20' => 2000, 'b50' => 5000, 'b100' => 10000, 'b200' => 20000, 'b500' => 50000, 'b1000' => 100000,
    'm050' => 50, 'm1' => 100, 'm2' => 200, 'm5' => 500, 'm10' => 1000, 'm20' => 2000,
];

/**
 * Hora local de las tiendas, igual que el panel del gerente: las columnas
 * DATETIME de Turnos_Caja quedan en hora de México.
 */
function usarHoraLocal(PDO $pdo): void
{
    date_default_timezone_set('America/Mexico_City');
    $pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");
}

/**
 * Cajero con su caja asignada (siempre desde la BD, nunca desde el cliente).
 * Devuelve null si el cajero o su sucursal no están activos; id_caja es null
 * si no tiene caja asignada.
 */
function cajeroConCaja(PDO $pdo, int $idUsuario): ?array
{
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.estado, u.id_sucursal, s.estado AS sucursal_estado,
               c.id_caja, c.numero_caja, c.nombre AS caja_nombre, c.estado AS caja_estado, c.fondo_fijo
        FROM Usuarios u
        LEFT JOIN Sucursales s ON s.id_sucursal = u.id_sucursal
        LEFT JOIN Cajas c ON c.id_cajero_asignado = u.id_usuario AND c.id_sucursal = u.id_sucursal
        WHERE u.id_usuario = ? AND u.id_rol = 3
        ORDER BY c.numero_caja
        LIMIT 1
    ");
    $stmt->execute([$idUsuario]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila || $fila['estado'] !== 'Activo' || $fila['id_sucursal'] === null || $fila['sucursal_estado'] !== 'Activa') {
        return null;
    }
    return $fila;
}

/** Turno abierto de la caja; con $bloquear la fila queda bloqueada hasta el commit. */
function turnoAbierto(PDO $pdo, int $idCaja, bool $bloquear = false): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM Turnos_Caja WHERE id_caja = ? AND estado = 'Abierto' LIMIT 1" . ($bloquear ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$idCaja]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Último turno cerrado de la caja: lo que dejó y quién lo cerró. */
function ultimoTurnoCerrado(PDO $pdo, int $idCaja): ?array
{
    $stmt = $pdo->prepare("
        SELECT t.id_turno, t.efectivo_dejado, t.fecha_cierre, u.nombre AS cajero
        FROM Turnos_Caja t
        LEFT JOIN Usuarios u ON u.id_usuario = t.id_cajero_cierre
        WHERE t.id_caja = ? AND t.estado = 'Cerrado'
        ORDER BY t.fecha_cierre DESC, t.id_turno DESC
        LIMIT 1
    ");
    $stmt->execute([$idCaja]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Piezas por denominación que manda la caja -> [desglose limpio, total en centavos].
 * Solo acepta ids conocidos y enteros >= 0; lanza DomainException si no.
 */
function leerDesglose(mixed $json): array
{
    $piezas = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($piezas)) {
        throw new DomainException(t('servidor.conteo_invalido'));
    }
    $desglose = [];
    $total = 0;
    foreach (DENOMINACIONES_CENTAVOS as $id => $centavos) {
        $n = $piezas[$id] ?? 0;
        if (!is_int($n) && !(is_string($n) && ctype_digit($n))) {
            throw new DomainException(t('servidor.conteo_invalido'));
        }
        $n = (int) $n;
        if ($n < 0 || $n > 100000) {
            throw new DomainException(t('servidor.conteo_invalido'));
        }
        $desglose[$id] = $n;
        $total += $n * $centavos;
    }
    return [$desglose, $total];
}

/** Monto de un formulario (pesos con hasta 2 decimales, >= 0) en centavos; null si no es válido. */
function montoEnCentavos(mixed $valor): ?int
{
    if (!is_string($valor) || !preg_match('/^\d{1,9}(\.\d{1,2})?$/', trim($valor))) {
        return null;
    }
    return (int) round((float) trim($valor) * 100);
}

function centavosABd(int $centavos): string
{
    return number_format($centavos / 100, 2, '.', '');
}

function bdACentavos(mixed $valor): int
{
    return (int) round((float) $valor * 100);
}
