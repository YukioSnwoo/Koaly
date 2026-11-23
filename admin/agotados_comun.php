<?php
/**
 * Alertas de productos agotados para el gerente (sql/019_alertas_agotados.sql).
 * Las crea la caja: al vender la última pieza (cajero/registrar_venta.php) o al
 * intentar vender un producto que ya estaba agotado (cajero/avisar_agotado.php).
 * El gerente las ve en su campanita (perfil/perfil.php) y se cierran solas cuando
 * el producto vuelve a tener existencias (ajuste en gerente/producto.php o una venta).
 *
 * NOW() y CURDATE() salen en hora de México porque quien llama ya la fijó en la
 * conexión (usarHoraLocal() en la caja, guardia.php en el gerente).
 * Sin sql/019 lanzan PDOException: quien llama la atrapa y la venta o el ajuste
 * siguen como siempre.
 */

/**
 * Avisa que el producto está agotado. Para no saturar al gerente hay a lo más una
 * alerta abierta por producto y día: si ya existe, un intento solo suma al contador
 * (varios clics seguidos, a menos de 2 minutos del anterior, cuentan como uno).
 *
 * @param string $origen 'Venta' (se vendió la última pieza) o 'Intento'
 */
function alertarAgotado(PDO $pdo, int $idSucursal, int $idProducto, string $origen, ?int $idCajero, ?int $idVenta = null): void
{
    if ($origen === 'Intento') {
        $sql = "
            INSERT INTO Alertas_Agotados
                (id_sucursal, id_producto, origen, id_cajero, id_venta, intentos, ultimo_intento, creado_en, abierta_el)
            VALUES (?, ?, 'Intento', ?, NULL, 1, NOW(), NOW(), CURDATE())
            ON DUPLICATE KEY UPDATE
                intentos       = intentos + (ultimo_intento IS NULL OR ultimo_intento < NOW() - INTERVAL 2 MINUTE),
                ultimo_intento = NOW()
        ";
        $pdo->prepare($sql)->execute([$idSucursal, $idProducto, $idCajero]);
        return;
    }

    // Venta: si ya había una alerta abierta hoy, se queda como está
    $pdo->prepare("
        INSERT IGNORE INTO Alertas_Agotados
            (id_sucursal, id_producto, origen, id_cajero, id_venta, intentos, creado_en, abierta_el)
        VALUES (?, ?, 'Venta', ?, ?, 0, NOW(), CURDATE())
    ")->execute([$idSucursal, $idProducto, $idCajero, $idVenta]);
}

/** Cierra las alertas abiertas de esos productos: ya tienen existencias otra vez. */
function cerrarAlertasAgotados(PDO $pdo, int $idSucursal, array $idsProductos): void
{
    $ids = array_values(array_unique(array_map('intval', $idsProductos)));
    if ($ids === []) {
        return;
    }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("
        UPDATE Alertas_Agotados
        SET abierta_el = NULL, resuelta_en = NOW()
        WHERE id_sucursal = ? AND id_producto IN ($marcas) AND abierta_el IS NOT NULL
    ")->execute(array_merge([$idSucursal], $ids));
}
